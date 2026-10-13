<?php

namespace Bulbula\Sales\Services;

use Bulbula\Core\Models\Currency;
use Bulbula\Core\Models\PaymentMethod;
use Bulbula\Sales\Models\ManualPaymentSubmission;
use Bulbula\Shop\Models\Order;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Handles customer-submitted transfer evidence and staff verification.
 * A receipt is evidence only: the order is paid only after a staff member
 * reconciles the transfer and approves the submission.
 */
class ManualPaymentService
{
    public function __construct(
        protected OrderService $orderService,
        protected InvoiceService $invoiceService,
        protected TransactionService $transactionService,
    ) {}

    /**
     * Return only customer-safe, active destinations from a payment method's
     * configuration. Gateway credentials and unrelated configuration are never
     * exposed to the storefront.
     */
    public function publicDestinations(?PaymentMethod $method): array
    {
        if (! $method) {
            return [];
        }

        $configuration = $method->configuration ?? [];
        if (is_string($configuration)) {
            $configuration = json_decode($configuration, true) ?: [];
        }
        if (! is_array($configuration)) {
            return [];
        }

        if (array_key_exists('account_destinations', $configuration)) {
            $destinations = is_array($configuration['account_destinations'])
                ? $configuration['account_destinations']
                : [];
        } else {
            // Read older single-bank configurations until an administrator
            // replaces them with the new multi-destination form.
            $legacyAccountNumber = trim((string) ($configuration['account_number'] ?? ''));
            $legacyBankName = trim((string) ($configuration['bank_name'] ?? ''));
            $legacyAccountName = trim((string) (
                $configuration['account_holder_name']
                ?? $configuration['account_name']
                ?? ''
            ));

            $destinations = $legacyAccountNumber === '' ? [] : [[
                'id' => 'legacy-transfer-account',
                'label' => $legacyBankName !== '' ? $legacyBankName : 'Bank transfer',
                'provider' => $legacyBankName !== '' ? $legacyBankName : 'Bank',
                'type' => 'bank',
                'account_name' => $legacyAccountName,
                'account_number' => $legacyAccountNumber,
                'instructions' => '',
                'is_active' => true,
            ]];
        }

        return collect($destinations)
            ->filter(fn ($destination) => is_array($destination))
            ->values()
            ->map(function (array $destination, int $index): array {
                return [
                    'id' => trim((string) ($destination['id'] ?? 'destination-' . ($index + 1))),
                    'label' => trim((string) ($destination['label'] ?? $destination['provider'] ?? 'Transfer account')),
                    'provider' => trim((string) ($destination['provider'] ?? '')),
                    'type' => in_array(($destination['type'] ?? 'bank'), ['bank', 'wallet'], true)
                        ? $destination['type']
                        : 'bank',
                    'account_name' => trim((string) ($destination['account_name'] ?? '')),
                    'account_number' => trim((string) ($destination['account_number'] ?? '')),
                    'instructions' => trim((string) ($destination['instructions'] ?? '')),
                    'is_active' => filter_var($destination['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                ];
            })
            ->filter(fn (array $destination) => $destination['is_active']
                && $destination['id'] !== ''
                && $destination['account_number'] !== '')
            ->values()
            ->map(fn (array $destination) => collect($destination)->except('is_active')->all())
            ->all();
    }

    /**
     * Create the initial proof record as part of the storefront order
     * transaction. The caller removes the stored file if the order transaction
     * later rolls back.
     */
    public function createInitialSubmission(
        Order $order,
        UploadedFile $proof,
        string $destinationId,
        ?string $transferReference,
        ?int $submittedByUserId = null,
    ): ManualPaymentSubmission {
        if ($order->payment_method !== 'bank_transfer'
            || $order->status !== Order::STATUS_PENDING
            || $order->payment_status !== Order::PAYMENT_PENDING) {
            throw new RuntimeException('This order cannot accept a manual payment submission.');
        }

        if ($order->manualPaymentSubmissions()->exists()) {
            throw new RuntimeException('A manual payment submission already exists for this order.');
        }

        $method = $this->activeBankTransferMethod();
        $destination = $this->findDestination($method, $destinationId);

        return $this->storeSubmission(
            $order,
            $method,
            $destination,
            $proof,
            $transferReference,
            $submittedByUserId,
        );
    }

    /**
     * A rejected submission may be replaced, while preserving the rejected
     * attempt for audit. The shared payment-status service reopens the order as
     * pending before the new evidence is recorded.
     */
    public function submitReplacement(
        Order $order,
        UploadedFile $proof,
        string $destinationId,
        ?string $transferReference,
        ?int $submittedByUserId = null,
    ): ManualPaymentSubmission {
        $storedPath = null;

        try {
            return DB::transaction(function () use (
                $order,
                $proof,
                $destinationId,
                $transferReference,
                $submittedByUserId,
                &$storedPath,
            ): ManualPaymentSubmission {
                $lockedOrder = Order::query()
                    ->whereKey($order->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedOrder->payment_method !== 'bank_transfer'
                    || $lockedOrder->status !== Order::STATUS_PENDING
                    || $lockedOrder->payment_status !== Order::PAYMENT_FAILED) {
                    throw new RuntimeException('Only a rejected, unpaid transfer order can receive replacement proof.');
                }

                $latestSubmission = ManualPaymentSubmission::query()
                    ->where('order_id', $lockedOrder->id)
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                if (! $latestSubmission || $latestSubmission->status !== ManualPaymentSubmission::STATUS_REJECTED) {
                    throw new RuntimeException('A new proof can be submitted only after the previous proof was rejected.');
                }

                $method = $this->activeBankTransferMethod();
                $destination = $this->findDestination($method, $destinationId);

                if (! $this->orderService->updatePaymentStatus(
                    $lockedOrder,
                    Order::PAYMENT_PENDING,
                    'Customer submitted replacement manual payment evidence.',
                ) || $lockedOrder->fresh()->payment_status !== Order::PAYMENT_PENDING) {
                    throw new RuntimeException('The order could not be reopened for payment review.');
                }

                return $this->storeSubmission(
                    $lockedOrder,
                    $method,
                    $destination,
                    $proof,
                    $transferReference,
                    $submittedByUserId,
                    $storedPath,
                );
            });
        } catch (Throwable $exception) {
            if ($storedPath) {
                $this->removeStoredProof($storedPath);
            }

            throw $exception;
        }
    }

    /** Approve only a pending receipt for a still-pending, uncancelled order. */
    public function approve(
        ManualPaymentSubmission $submission,
        ?string $reviewNote,
        ?int $reviewerId,
    ): void {
        DB::transaction(function () use ($submission, $reviewNote, $reviewerId): void {
            $lockedSubmission = ManualPaymentSubmission::query()
                ->whereKey($submission->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSubmission->status !== ManualPaymentSubmission::STATUS_PENDING_REVIEW) {
                throw new RuntimeException('This submission has already been reviewed.');
            }

            $order = Order::query()
                ->whereKey($lockedSubmission->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->payment_method !== 'bank_transfer'
                || $order->status === Order::STATUS_CANCELLED
                || $order->payment_status !== Order::PAYMENT_PENDING) {
                throw new RuntimeException('This order is no longer eligible for manual payment approval.');
            }

            $alreadyApproved = ManualPaymentSubmission::query()
                ->where('order_id', $order->id)
                ->where('status', ManualPaymentSubmission::STATUS_APPROVED)
                ->exists();

            if ($alreadyApproved) {
                throw new RuntimeException('A manual payment has already been approved for this order.');
            }

            $comment = 'Manual transfer verified against the receiving account and approved.';
            if ($reviewNote) {
                $comment .= ' Staff note: ' . $reviewNote;
            }

            if (! $this->orderService->updatePaymentStatus($order, Order::PAYMENT_PAID, $comment)
                || $order->fresh()->payment_status !== Order::PAYMENT_PAID) {
                throw new RuntimeException('The order payment could not be advanced.');
            }

            $invoice = $this->invoiceService->createFromOrderIfMissing($order);
            if ($invoice && ! $this->invoiceService->markAsPaid((int) $invoice->id)) {
                throw new RuntimeException('The invoice could not be marked as paid.');
            }

            $this->transactionService->createPaymentIfMissing($order, [
                'payment_method' => 'bank_transfer',
                'gateway' => 'manual_transfer',
                'gateway_transaction_id' => 'manual-payment-submission-' . $lockedSubmission->id,
                'amount' => (float) $order->total,
                'status' => 'completed',
                'notes' => $lockedSubmission->transfer_reference
                    ? 'Manual transfer approved. Customer reference: ' . $lockedSubmission->transfer_reference
                    : 'Manual transfer approved after staff verification.',
                'invoice_id' => $invoice?->id,
                'response_data' => [
                    'manual_payment_submission_id' => $lockedSubmission->id,
                    'transfer_reference' => $lockedSubmission->transfer_reference,
                    'destination' => $lockedSubmission->destination_snapshot,
                ],
            ]);

            $lockedSubmission->update([
                'status' => ManualPaymentSubmission::STATUS_APPROVED,
                'reviewed_by_user_id' => $reviewerId,
                'review_note' => $reviewNote,
                'reviewed_at' => now(),
            ]);
        });
    }

    /** Reject a receipt and notify the order lifecycle of a failed attempt. */
    public function reject(
        ManualPaymentSubmission $submission,
        string $reason,
        ?int $reviewerId,
    ): void {
        $reason = trim($reason);
        if (mb_strlen($reason) < 3) {
            throw new RuntimeException('Please provide a clear rejection reason.');
        }

        DB::transaction(function () use ($submission, $reason, $reviewerId): void {
            $lockedSubmission = ManualPaymentSubmission::query()
                ->whereKey($submission->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSubmission->status !== ManualPaymentSubmission::STATUS_PENDING_REVIEW) {
                throw new RuntimeException('This submission has already been reviewed.');
            }

            $order = Order::query()
                ->whereKey($lockedSubmission->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->payment_method !== 'bank_transfer'
                || $order->status === Order::STATUS_CANCELLED
                || $order->payment_status !== Order::PAYMENT_PENDING) {
                throw new RuntimeException('This order is no longer eligible for manual payment rejection.');
            }

            if (! $this->orderService->updatePaymentStatus(
                $order,
                Order::PAYMENT_FAILED,
                'Manual transfer rejected: ' . $reason,
            ) || $order->fresh()->payment_status !== Order::PAYMENT_FAILED) {
                throw new RuntimeException('The failed payment state could not be recorded.');
            }

            $lockedSubmission->update([
                'status' => ManualPaymentSubmission::STATUS_REJECTED,
                'reviewed_by_user_id' => $reviewerId,
                'review_note' => $reason,
                'reviewed_at' => now(),
            ]);
        });
    }

    private function activeBankTransferMethod(): PaymentMethod
    {
        $method = PaymentMethod::query()
            ->where('code', 'bank_transfer')
            ->where('is_active', true)
            ->first();

        if (! $method) {
            throw new RuntimeException('Manual bank or wallet transfer is not currently available.');
        }

        return $method;
    }

    private function findDestination(PaymentMethod $method, string $destinationId): array
    {
        $destination = collect($this->publicDestinations($method))
            ->first(fn (array $candidate) => hash_equals($candidate['id'], $destinationId));

        if (! $destination) {
            throw new RuntimeException('The selected transfer account is no longer available. Refresh checkout and try again.');
        }

        return $destination;
    }

    /**
     * Store a generated-name proof file on the private local disk and create an
     * immutable attempt record. The original filename is deliberately ignored.
     */
    private function storeSubmission(
        Order $order,
        PaymentMethod $method,
        array $destination,
        UploadedFile $proof,
        ?string $transferReference,
        ?int $submittedByUserId,
        ?string &$storedPath = null,
    ): ManualPaymentSubmission {
        $path = $proof->store('manual-payment-proofs', 'local');
        if (! is_string($path) || $path === '') {
            throw new RuntimeException('The payment proof could not be stored securely. Please try again.');
        }

        $storedPath = $path;

        try {
            return ManualPaymentSubmission::create([
                'order_id' => $order->id,
                'payment_method_id' => $method->id,
                'payment_method_code' => $method->code,
                'destination_id' => $destination['id'],
                'destination_snapshot' => collect($destination)->except('is_active')->all(),
                'transfer_reference' => $transferReference ? trim($transferReference) : null,
                'amount' => (float) $order->total,
                'currency' => Currency::getDefault()?->code ?? 'USD',
                'proof_disk' => 'local',
                'proof_path' => $path,
                'proof_mime_type' => $proof->getMimeType() ?: 'application/octet-stream',
                'proof_size' => $proof->getSize(),
                'submitted_by_user_id' => $submittedByUserId,
                'status' => ManualPaymentSubmission::STATUS_PENDING_REVIEW,
            ]);
        } catch (Throwable $exception) {
            $this->removeStoredProof($path);
            throw $exception;
        }
    }

    private function removeStoredProof(string $path): void
    {
        try {
            Storage::disk('local')->delete($path);
        } catch (Throwable $cleanupException) {
            Log::warning('Unable to remove an orphaned manual payment proof', [
                'path' => $path,
                'error' => $cleanupException->getMessage(),
            ]);
        }
    }
}
