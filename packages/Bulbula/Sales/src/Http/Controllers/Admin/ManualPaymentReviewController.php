<?php

namespace Bulbula\Sales\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Bulbula\Sales\Models\ManualPaymentReasonTemplate;
use Bulbula\Sales\Models\ManualPaymentSubmission;
use Bulbula\Sales\Services\ManualPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
            'reasonTemplates' => ManualPaymentReasonTemplate::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get(['id', 'label', 'reason']),
            'managedReasonTemplates' => ManualPaymentReasonTemplate::query()
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get(['id', 'label', 'reason', 'is_active', 'sort_order']),
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
            'verified_settled' => ['required', 'accepted'],
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
            'save_as_template' => ['nullable', 'boolean'],
            'template_label' => ['nullable', 'string', 'min:2', 'max:120'],
        ]);

        $reason = trim($validated['reason']);
        $saveAsTemplate = $request->boolean('save_as_template');
        $templateLabel = trim((string) ($validated['template_label'] ?? ''));
        if ($saveAsTemplate && $templateLabel === '') {
            $templateLabel = Str::limit($reason, 120, '');
        }

        try {
            $adminId = Auth::guard('admin')->id() ?? Auth::id();
            $this->manualPaymentService->reject(
                $submission,
                $reason,
                $adminId ? (int) $adminId : null,
            );
        } catch (Throwable $exception) {
            Log::warning('Manual payment rejection failed', [
                'submission_id' => $submission->id,
                'error' => $exception->getMessage(),
            ]);

            return back()->with('error', $exception->getMessage());
        }

        if ($saveAsTemplate) {
            try {
                $this->saveReasonTemplate($templateLabel, $reason);
            } catch (Throwable $exception) {
                Log::warning('Manual payment reason template could not be saved after rejection', [
                    'submission_id' => $submission->id,
                    'error' => $exception->getMessage(),
                ]);

                return back()->with(
                    'success',
                    'Manual transfer rejection was recorded, but the reusable reason could not be saved. You can add it from Manage rejection reasons.'
                );
            }
        }

        $message = $saveAsTemplate
            ? 'Manual transfer rejected and the reason saved for future use.'
            : 'Manual transfer rejected. The reason is saved for the customer.';

        return back()->with('success', $message);
    }

    public function storeReasonTemplate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'min:2', 'max:120'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        try {
            $this->saveReasonTemplate(trim($validated['label']), trim($validated['reason']));

            return back()->with('success', 'Rejection reason saved.');
        } catch (Throwable $exception) {
            Log::warning('Manual payment reason template creation failed', [
                'error' => $exception->getMessage(),
            ]);

            return back()->with('error', 'Could not save that rejection reason.');
        }
    }

    public function updateReasonTemplate(Request $request, ManualPaymentReasonTemplate $template): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'min:2', 'max:120'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $template->update([
            'label' => trim($validated['label']),
            'reason' => trim($validated['reason']),
            'is_active' => $validated['is_active'],
            'sort_order' => $validated['sort_order'] ?? $template->sort_order,
        ]);

        return back()->with('success', 'Rejection reason updated.');
    }

    public function deleteReasonTemplate(ManualPaymentReasonTemplate $template): RedirectResponse
    {
        $template->delete();

        return back()->with('success', 'Rejection reason deleted.');
    }

    private function saveReasonTemplate(string $label, string $reason): ManualPaymentReasonTemplate
    {
        $existing = ManualPaymentReasonTemplate::query()
            ->where('reason', $reason)
            ->first();

        if ($existing) {
            if (! $existing->is_active) {
                $existing->update(['is_active' => true]);
            }

            return $existing;
        }

        $baseCode = Str::slug($label);
        if ($baseCode === '') {
            $baseCode = 'manual-payment-reason';
        }
        $baseCode = substr($baseCode, 0, 48);
        $code = $baseCode;
        $suffix = 2;

        while (ManualPaymentReasonTemplate::query()->where('code', $code)->exists()) {
            $ending = '-' . $suffix++;
            $code = substr($baseCode, 0, 64 - strlen($ending)) . $ending;
        }

        return ManualPaymentReasonTemplate::create([
            'code' => $code,
            'label' => $label,
            'reason' => $reason,
            'is_active' => true,
            'sort_order' => (int) (ManualPaymentReasonTemplate::max('sort_order') ?? 0) + 10,
        ]);
    }
}
