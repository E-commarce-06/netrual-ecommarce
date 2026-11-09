<?php

namespace Bulbula\API\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Bulbula\API\Helpers\ApiResponse;
use Bulbula\API\Http\Resources\OrderResource;
use Bulbula\Shop\Models\Order;
use Bulbula\Sales\Services\OrderService;
use Bulbula\Sales\Models\ManualPaymentSubmission;
use Bulbula\Sales\Services\ManualPaymentService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Get customer orders.
     */
    public function index(Request $request)
    {
        $perPage = min($request->get('per_page', 20), config('bulbula-api.pagination.max_per_page'));

        $query = Order::where('user_id', $request->user()->id)
            ->with(['items.product', 'shippingAddress', 'billingAddress', 'latestManualPaymentSubmission']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->latest()->paginate($perPage);

        return ApiResponse::paginated(
            $orders->through(fn($order) => new OrderResource($order)),
            'Orders retrieved successfully'
        );
    }

    /**
     * Get single order details.
     */
    public function show(Request $request, $id)
    {
        $order = Order::with([
            'items.product.images',
            'shippingAddress',
            'billingAddress',
            'shipments',
            'invoices',
            'latestManualPaymentSubmission',
        ])
            ->where('user_id', $request->user()->id)
            ->find($id);

        if (!$order) {
            return ApiResponse::notFound('Order not found', 'ORDER_NOT_FOUND');
        }

        return ApiResponse::success(
            new OrderResource($order),
            'Order details retrieved successfully'
        );
    }

    /**
     * Submit or replace customer evidence for an admin-configured manual transfer.
     * The receipt is evidence only; staff approval remains the only way to mark paid.
     */
    public function submitManualPayment(
        Request $request,
        $id,
        ManualPaymentService $manualPaymentService,
    ) {
        $order = Order::where('user_id', $request->user()->id)->find($id);
        if (!$order) {
            return ApiResponse::notFound('Order not found', 'ORDER_NOT_FOUND');
        }

        if ($order->payment_method !== 'bank_transfer') {
            return ApiResponse::error(
                'This order does not use manual bank or wallet transfer.',
                null,
                422,
                'MANUAL_PAYMENT_NOT_ALLOWED'
            );
        }

        $existing = $order->latestManualPaymentSubmission;
        if ($existing?->status === ManualPaymentSubmission::STATUS_PENDING_REVIEW) {
            return ApiResponse::success([
                'order_id' => $order->id,
                'submission_id' => $existing->id,
                'submission_status' => $existing->status,
                'payment_status' => $order->payment_status,
                'already_submitted' => true,
            ], 'Transfer proof is already awaiting staff review.');
        }

        if ($existing?->status === ManualPaymentSubmission::STATUS_APPROVED
            || $order->payment_status === Order::PAYMENT_PAID) {
            return ApiResponse::error(
                'A manual payment has already been approved for this order.',
                null,
                409,
                'MANUAL_PAYMENT_ALREADY_APPROVED'
            );
        }

        $validated = $request->validate([
            'destination_id' => ['required', 'string', 'max:64'],
            'transfer_reference' => ['nullable', 'string', 'max:150'],
            'payment_proof' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        try {
            $result = DB::transaction(function () use ($order, $validated, $manualPaymentService, $request) {
                $lockedOrder = Order::query()
                    ->whereKey($order->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $latestSubmission = $lockedOrder->manualPaymentSubmissions()
                    ->orderByDesc('id')
                    ->first();

                // Serialize repeated requests so a network retry cannot create
                // duplicate evidence records for the same order.
                if ($latestSubmission?->status === ManualPaymentSubmission::STATUS_PENDING_REVIEW) {
                    return ['submission' => $latestSubmission, 'already_submitted' => true];
                }
                if ($latestSubmission?->status === ManualPaymentSubmission::STATUS_APPROVED
                    || $lockedOrder->payment_status === Order::PAYMENT_PAID) {
                    throw new \RuntimeException('A manual payment has already been approved for this order.');
                }

                $submission = $latestSubmission?->status === ManualPaymentSubmission::STATUS_REJECTED
                    ? $manualPaymentService->submitReplacement(
                        $lockedOrder,
                        $validated['payment_proof'],
                        $validated['destination_id'],
                        $validated['transfer_reference'] ?? null,
                        (int) $request->user()->id,
                    )
                    : $manualPaymentService->createInitialSubmission(
                        $lockedOrder,
                        $validated['payment_proof'],
                        $validated['destination_id'],
                        $validated['transfer_reference'] ?? null,
                        (int) $request->user()->id,
                    );

                return ['submission' => $submission, 'already_submitted' => false];
            });
            $submission = $result['submission'];
        } catch (\Throwable $exception) {
            Log::warning('Mobile manual payment evidence submission failed', [
                'order_id' => $order->id,
                'user_id' => $request->user()->id,
                'error' => $exception->getMessage(),
            ]);

            return ApiResponse::error(
                'Could not submit transfer proof. Please refresh checkout and try again.',
                null,
                422,
                'MANUAL_PAYMENT_SUBMISSION_FAILED'
            );
        }

        return ApiResponse::success([
            'order_id' => $order->id,
            'submission_id' => $submission->id,
            'submission_status' => $submission->status,
            'payment_status' => $order->fresh()->payment_status,
            'already_submitted' => $result['already_submitted'],
        ], 'Transfer proof submitted and awaiting staff review.');
    }

    /**
     * Cancel order.
     */
    public function cancel(Request $request, $id)
    {
        $order = Order::where('user_id', $request->user()->id)->find($id);

        if (!$order) {
            return ApiResponse::notFound('Order not found', 'ORDER_NOT_FOUND');
        }

        if (!in_array($order->status, ['pending', 'processing'])) {
            return ApiResponse::error(
                'Order cannot be cancelled',
                null,
                400,
                'ORDER_CANNOT_BE_CANCELLED'
            );
        }

        if (!app(OrderService::class)->cancelOrder($order, 'Cancelled by customer', true)) {
            return ApiResponse::error(
                'Order cancellation failed',
                null,
                500,
                'ORDER_CANCELLATION_FAILED'
            );
        }

        // Refund processing remains separate from order cancellation.
        return ApiResponse::success(
            new OrderResource($order->refresh()),
            'Order cancelled successfully'
        );
    }

    /**
     * Get order invoice.
     */
    public function invoice(Request $request, $id)
    {
        $order = Order::with(['items.product', 'shippingAddress', 'billingAddress'])
            ->where('user_id', $request->user()->id)
            ->find($id);

        if (!$order) {
            return ApiResponse::notFound('Order not found', 'ORDER_NOT_FOUND');
        }

        // TODO: Generate PDF invoice
        // For now, return invoice data

        return ApiResponse::success([
            'order' => new OrderResource($order),
            'invoice_url' => route('api.orders.invoice.pdf', $order->id),
        ], 'Invoice retrieved successfully');
    }

    /**
     * Track order.
     */
    public function track(Request $request, $id)
    {
        $order = Order::with(['shipments.trackingInfo'])
            ->where('user_id', $request->user()->id)
            ->find($id);

        if (!$order) {
            return ApiResponse::notFound('Order not found', 'ORDER_NOT_FOUND');
        }

        $trackingInfo = [];

        if ($order->shipments->isNotEmpty()) {
            $shipment = $order->shipments->first();
            $trackingInfo = [
                'tracking_number' => $shipment->tracking_number ?? null,
                'carrier' => $shipment->carrier ?? null,
                'status' => $shipment->status ?? null,
                'shipped_at' => $shipment->shipped_at?->toIso8601String(),
                'estimated_delivery' => $shipment->estimated_delivery?->toIso8601String(),
                'tracking_url' => $shipment->tracking_url ?? null,
            ];
        }

        return ApiResponse::success([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'tracking' => $trackingInfo,
        ], 'Order tracking information retrieved');
    }
}
