<?php

namespace Bulbula\Sales\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Bulbula\Sales\Models\ManualPaymentSubmission;
use Bulbula\Sales\Services\ManualPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Throwable;

class ManualPaymentReviewController extends Controller
{
    public function __construct(protected ManualPaymentService $manualPaymentService) {}

    public function index(Request $request): InertiaResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:pending_review,approved,rejected,all'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);
        $status = $filters['status'] ?? ManualPaymentSubmission::STATUS_PENDING_REVIEW;
        $search = trim((string) ($filters['search'] ?? ''));

        $query = ManualPaymentSubmission::query()
            ->with([
                'order' => fn ($query) => $query->withTrashed()->with('user'),
                'reviewer',
            ]);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('transfer_reference', 'like', '%' . $search . '%')
                    ->orWhereHas('order', function ($orderQuery) use ($search): void {
                        $orderQuery->where('order_number', 'like', '%' . $search . '%')
                            ->orWhere('customer_email', 'like', '%' . $search . '%');
                    });
            });
        }

        $submissions = $query->orderByDesc('id')->paginate(20)->withQueryString();
        $submissions->setCollection(
            $submissions->getCollection()->map(function (ManualPaymentSubmission $submission): array {
                $order = $submission->order;

                return [
                    'id' => $submission->id,
                    'status' => $submission->status,
                    'amount' => (float) $submission->amount,
                    'currency' => $submission->currency,
                    'transfer_reference' => $submission->transfer_reference,
                    'destination_snapshot' => $submission->destination_snapshot,
                    'proof_url' => route('admin.sales.manual-payments.proof', $submission),
                    'submitted_at' => $submission->created_at?->format('Y-m-d H:i:s'),
                    'review_note' => $submission->review_note,
                    'reviewed_at' => $submission->reviewed_at?->format('Y-m-d H:i:s'),
                    'reviewer_name' => $submission->reviewer?->name,
                    'order' => $order ? [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'status' => $order->status,
                        'payment_status' => $order->payment_status,
                        'total' => (float) $order->total,
                        'customer_email' => $order->customer_email,
                        'customer_name' => $order->user?->name,
                    ] : null,
                ];
            })
        );

        return Inertia::render('Admin/Sales/ManualPayments/Index', [
            'submissions' => $submissions,
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
            'pendingCount' => ManualPaymentSubmission::where(
                'status',
                ManualPaymentSubmission::STATUS_PENDING_REVIEW
            )->count(),
        ]);
    }

    /** Serve proof only through this authenticated admin route. */
    public function proof(ManualPaymentSubmission $submission)
    {
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (! in_array($submission->proof_mime_type, $allowedMimeTypes, true)) {
            abort(404);
        }

        $disk = Storage::disk($submission->proof_disk ?: 'local');
        if (! $disk->exists($submission->proof_path)) {
            abort(404);
        }

        $extension = strtolower(pathinfo($submission->proof_path, PATHINFO_EXTENSION));
        $filename = 'manual-payment-proof-' . $submission->id . '.' . ($extension ?: 'img');

        return $disk->response($submission->proof_path, $filename, [
            'Content-Type' => $submission->proof_mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Content-Security-Policy' => 'sandbox',
        ], 'inline');
    }

    public function approve(Request $request, ManualPaymentSubmission $submission): RedirectResponse
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $adminId = Auth::guard('admin')->id() ?? Auth::id();
            $this->manualPaymentService->approve(
                $submission,
                isset($validated['note']) ? trim($validated['note']) : null,
                $adminId ? (int) $adminId : null,
            );

            return back()->with('success', 'Manual transfer approved; the order payment has been recorded.');
        } catch (Throwable $exception) {
            Log::warning('Manual payment approval failed', [
                'submission_id' => $submission->id,
                'error' => $exception->getMessage(),
            ]);

            return back()->with('error', $exception->getMessage());
        }
    }

    public function reject(Request $request, ManualPaymentSubmission $submission): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        try {
            $adminId = Auth::guard('admin')->id() ?? Auth::id();
            $this->manualPaymentService->reject(
                $submission,
                trim($validated['reason']),
                $adminId ? (int) $adminId : null,
            );

            return back()->with('success', 'Manual transfer rejected. The reason is saved for the customer.');
        } catch (Throwable $exception) {
            Log::warning('Manual payment rejection failed', [
                'submission_id' => $submission->id,
                'error' => $exception->getMessage(),
            ]);

            return back()->with('error', $exception->getMessage());
        }
    }
}
