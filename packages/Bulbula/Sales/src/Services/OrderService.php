<?php

namespace Bulbula\Sales\Services;

use Bulbula\Shop\Models\Order;
use Bulbula\Sales\Models\OrderHistory;
use Bulbula\Sales\Repositories\OrderRepository;
use Bulbula\Core\Models\EmailTemplate;
use Bulbula\Core\Models\EmailConfiguration;
use Bulbula\Core\Models\Currency;
use Bulbula\Admin\Services\AdminNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class OrderService
{
    public function __construct(
        protected OrderRepository $orderRepository,
        protected AdminNotificationService $adminNotificationService
    ) {}

    /**
     * Update order status and create history entry.
     *
     * @param Order $order
     * @param string $newStatus
     * @param string|null $comment
     * @param bool $notifyCustomer
     * @return bool
     */
    public function updateStatus(
        Order $order,
        string $newStatus,
        ?string $comment = null,
        bool $notifyCustomer = false
    ): bool {
        if (!array_key_exists($newStatus, Order::getStatuses())) {
            Log::warning('Rejected unknown order status', [
                'order_id' => $order->id,
                'status' => $newStatus,
            ]);
            return false;
        }

        try {
            DB::beginTransaction();

            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->first();

            if (!$lockedOrder) {
                throw new \RuntimeException('Order not found.');
            }

            $oldStatus = $lockedOrder->status;
            if ($oldStatus === $newStatus) {
                DB::commit();
                $order->refresh();
                return true;
            }

            $lockedOrder->status = $newStatus;
            $lockedOrder->save();

            $this->createHistoryEntry($lockedOrder, [
                'status_from' => $oldStatus,
                'status_to' => $newStatus,
                'comment' => $comment,
                'customer_notified' => $notifyCustomer,
                'visible_to_customer' => true,
            ]);

            $this->handleStatusChangeActions($lockedOrder, $oldStatus, $newStatus);
            $this->sendStatusChangeEmail($lockedOrder, $oldStatus, $newStatus);

            $this->dispatchOrderEvent(
                action: 'order.status.updated',
                title: "Order #{$lockedOrder->order_number} status updated",
                message: "Status changed from {$oldStatus} to {$newStatus}.",
                order: $lockedOrder,
                severity: $newStatus === Order::STATUS_CANCELLED ? 'warning' : 'info',
                context: [
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'comment' => $comment,
                    'notify_customer' => $notifyCustomer,
                ]
            );

            DB::commit();
            $order->refresh();
            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to update order status', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Update order payment status and create history entry.
     *
     * @param Order $order
     * @param string $newPaymentStatus
     * @return bool
     */
    public function updatePaymentStatus(
        Order $order,
        string $newPaymentStatus,
        ?string $comment = null
    ): bool {
        if (!array_key_exists($newPaymentStatus, Order::getPaymentStatuses())) {
            Log::warning('Rejected unknown order payment status', [
                'order_id' => $order->id,
                'payment_status' => $newPaymentStatus,
            ]);
            return false;
        }

        try {
            DB::beginTransaction();

            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->first();

            if (!$lockedOrder) {
                throw new \RuntimeException('Order not found.');
            }

            $oldPaymentStatus = $lockedOrder->payment_status;
            $oldOrderStatus = $lockedOrder->status;

            // Late failure/pending callbacks must not undo an accepted payment.
            if ($oldPaymentStatus === Order::PAYMENT_PAID
                && in_array($newPaymentStatus, [Order::PAYMENT_PENDING, Order::PAYMENT_FAILED], true)) {
                DB::commit();
                $order->refresh();
                return true;
            }

            // A refund is terminal; stale payment callbacks cannot reopen it.
            if ($oldPaymentStatus === Order::PAYMENT_REFUNDED
                && $newPaymentStatus !== Order::PAYMENT_REFUNDED) {
                DB::commit();
                $order->refresh();
                return true;
            }

            $paymentStatusChanged = $oldPaymentStatus !== $newPaymentStatus;
            $newOrderStatus = $oldOrderStatus;

            if ($paymentStatusChanged && $newPaymentStatus === Order::PAYMENT_PAID
                && $oldOrderStatus === Order::STATUS_PENDING) {
                $newOrderStatus = Order::STATUS_PROCESSING;
            } elseif ($newPaymentStatus === Order::PAYMENT_REFUNDED
                && $oldOrderStatus !== Order::STATUS_REFUNDED) {
                $newOrderStatus = Order::STATUS_REFUNDED;
            }

            $orderStatusChanged = $oldOrderStatus !== $newOrderStatus;

            if (!$paymentStatusChanged && !$orderStatusChanged) {
                DB::commit();
                $order->refresh();
                return true;
            }

            if ($paymentStatusChanged) {
                $lockedOrder->payment_status = $newPaymentStatus;
            }
            if ($orderStatusChanged) {
                $lockedOrder->status = $newOrderStatus;
            }
            $lockedOrder->save();

            if ($paymentStatusChanged) {
                $this->createHistoryEntry($lockedOrder, [
                    'payment_status_from' => $oldPaymentStatus,
                    'payment_status_to' => $newPaymentStatus,
                    'comment' => $comment,
                    'customer_notified' => false,
                    'visible_to_customer' => true,
                ]);
            }

            if ($orderStatusChanged) {
                $this->createHistoryEntry($lockedOrder, [
                    'status_from' => $oldOrderStatus,
                    'status_to' => $newOrderStatus,
                    'comment' => $comment,
                    'customer_notified' => false,
                    'visible_to_customer' => true,
                ]);

                $this->handleStatusChangeActions($lockedOrder, $oldOrderStatus, $newOrderStatus);
                $this->sendStatusChangeEmail($lockedOrder, $oldOrderStatus, $newOrderStatus);
                $this->dispatchOrderEvent(
                    action: 'order.status.updated',
                    title: "Order #{$lockedOrder->order_number} status updated",
                    message: "Status changed from {$oldOrderStatus} to {$newOrderStatus}.",
                    order: $lockedOrder,
                    severity: $newOrderStatus === Order::STATUS_CANCELLED ? 'warning' : 'info',
                    context: [
                        'old_status' => $oldOrderStatus,
                        'new_status' => $newOrderStatus,
                        'comment' => $comment,
                        'source' => 'payment_status_transition',
                    ]
                );
            }

            if ($paymentStatusChanged) {
                $this->sendPaymentStatusEmail($lockedOrder, $oldPaymentStatus, $newPaymentStatus);
                $this->dispatchOrderEvent(
                    action: 'order.payment.updated',
                    title: "Order #{$lockedOrder->order_number} payment updated",
                    message: "Payment status changed from {$oldPaymentStatus} to {$newPaymentStatus}.",
                    order: $lockedOrder,
                    severity: $newPaymentStatus === Order::PAYMENT_FAILED ? 'warning' : 'info',
                    context: [
                        'old_payment_status' => $oldPaymentStatus,
                        'new_payment_status' => $newPaymentStatus,
                        'comment' => $comment,
                    ]
                );
            }

            DB::commit();
            $order->refresh();
            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to update payment status', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Cancel an order with optional stock restoration.
     *
     * @param Order $order
     * @param string $reason
     * @param bool $restoreStock
     * @return bool
     */
    public function cancelOrder(
        Order $order,
        string $reason,
        bool $restoreStock = true
    ): bool {
        try {
            DB::beginTransaction();

            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->first();

            if (!$lockedOrder) {
                throw new \RuntimeException('Order not found.');
            }

            $oldStatus = $lockedOrder->status;
            if ($oldStatus === Order::STATUS_CANCELLED) {
                if ($restoreStock) {
                    $this->restoreStockOnce($lockedOrder);
                }
                DB::commit();
                $order->refresh();
                return true;
            }

            $lockedOrder->status = Order::STATUS_CANCELLED;
            $lockedOrder->save();

            $this->createHistoryEntry($lockedOrder, [
                'status_from' => $oldStatus,
                'status_to' => Order::STATUS_CANCELLED,
                'comment' => "Order cancelled. Reason: {$reason}",
                'customer_notified' => true,
                'visible_to_customer' => true,
            ]);

            if ($restoreStock) {
                $this->restoreStockOnce($lockedOrder);
            }

            $this->sendCancellationEmail($lockedOrder, $reason);

            $this->dispatchOrderEvent(
                action: 'order.cancelled',
                title: "Order #{$lockedOrder->order_number} cancelled",
                message: "Order was cancelled. Reason: {$reason}",
                order: $lockedOrder,
                severity: 'warning',
                context: [
                    'reason' => $reason,
                    'restore_stock' => $restoreStock,
                    'old_status' => $oldStatus,
                ]
            );

            DB::commit();
            $order->refresh();
            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to cancel order', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Create a new order (admin-initiated).
     *
     * @param array $data
     * @return Order
     */
    public function createOrder(array $data): Order
    {
        DB::beginTransaction();

        try {
            // Generate unique order number
            $data['order_number'] = $this->generateOrderNumber();
            $data['status'] = Order::STATUS_PENDING;
            $data['payment_status'] = Order::PAYMENT_PENDING;

            // Create order
            $order = Order::create($data);

            // Create order items
            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    $order->items()->create($itemData);
                }
            }

            // Create addresses
            if (isset($data['shipping_address'])) {
                $order->addresses()->create(array_merge(
                    $data['shipping_address'],
                    ['type' => 'shipping']
                ));
            }

            if (isset($data['billing_address'])) {
                $order->addresses()->create(array_merge(
                    $data['billing_address'],
                    ['type' => 'billing']
                ));
            }

            // Create history entry
            $this->createHistoryEntry($order, [
                'status_to' => Order::STATUS_PENDING,
                'comment' => 'Order created by admin',
                'customer_notified' => false,
                'visible_to_customer' => true,
            ]);

            $this->dispatchOrderEvent(
                action: 'order.created',
                title: "New order #{$order->order_number}",
                message: 'A new order was created from admin panel.',
                order: $order,
                severity: 'success',
                context: [
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'total' => $order->total,
                ]
            );

            DB::commit();
            return $order->fresh(['items', 'addresses']);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Send status change email notification.
     *
     * @param Order $order
     * @param string $oldStatus
     * @param string $newStatus
     * @return void
     */
    public function sendStatusChangeEmail(
        Order $order,
        string $oldStatus,
        string $newStatus
    ): void {
        try {
            // Determine which template to use based on new status
            $templateCode = null;
            switch ($newStatus) {
                case 'cancelled':
                    $templateCode = 'order_cancelled';
                    break;
                case 'completed':
                    $templateCode = 'order_completed';
                    break;
            }

            // If no specific template for this status, skip email
            if (!$templateCode) {
                return;
            }

            // Skip if order has no customer email
            if (empty($order->customer_email)) {
                return;
            }

            // Skip if email settings are not configured in Settings -> Email
            $emailConfig = EmailConfiguration::where('is_active', true)->first();
            if (!$emailConfig || empty($emailConfig->mail_from_address)) {
                return;
            }

            $template = EmailTemplate::findByCode($templateCode);
            
            if (!$template || !$template->is_active) {
                return;
            }

            // Prepare template data
            $data = [
                'order_number' => $order->order_number,
                'customer_name' => $order->user->name ?? 'Customer',
                'customer_email' => $order->customer_email,
                'payment_amount' => $this->formatCurrency($order->total),
                'order_total' => '₹' . number_format($order->total, 2),
                'old_status' => ucfirst($oldStatus),
                'new_status' => ucfirst($newStatus),
                'store_name' => config('app.name', config('brand.name')),
                'store_url' => config('app.url'),
            ];

            // Use the template's send method
            $template->send($order->customer_email, $data);

        } catch (\Exception $e) {
            Log::error('Failed to send status change email', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send payment status change email notification.
     *
     * @param Order $order
     * @param string $oldPaymentStatus
     * @param string $newPaymentStatus
     * @return void
     */
    protected function sendPaymentStatusEmail(
        Order $order,
        string $oldPaymentStatus,
        string $newPaymentStatus
    ): void {
        try {
            // Determine which template to use based on new payment status
            $templateCode = null;
            switch ($newPaymentStatus) {
                case 'paid':
                    $templateCode = 'payment_received';
                    break;
                case 'failed':
                    $templateCode = 'payment_failed';
                    break;
            }

            // If no specific template for this status, skip email
            if (!$templateCode) {
                return;
            }

            // Skip if order has no customer email
            if (empty($order->customer_email)) {
                return;
            }

            // Skip if email settings are not configured in Settings -> Email
            $emailConfig = EmailConfiguration::where('is_active', true)->first();
            if (!$emailConfig || empty($emailConfig->mail_from_address)) {
                return;
            }

            $template = EmailTemplate::findByCode($templateCode);
            
            if (!$template || !$template->is_active) {
                return;
            }

            // Prepare template data
            $data = [
                'order_number' => $order->order_number,
                'customer_name' => $order->user->name ?? 'Customer',
                'customer_email' => $order->customer_email,
                'payment_amount' => '₹' . number_format($order->total, 2),
                'payment_date' => now()->format('F d, Y'),
                'transaction_id' => $order->payment_transaction_id ?? 'N/A',
                'store_name' => config('app.name', config('brand.name')),
                'store_url' => config('app.url'),
            ];

            // Use the template's send method
            $template->send($order->customer_email, $data);

        } catch (\Exception $e) {
            Log::error('Failed to send payment status email', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Format a monetary amount using the default store currency.
     *
     * @param float $amount
     * @return string
     */
    protected function formatCurrency(float $amount): string
    {
        $currency = Currency::getDefault();

        return $currency
            ? $currency->format($amount)
            : '$' . number_format($amount, 2);
    }

    /**
     * Create order history entry.
     *
     * @param Order $order
     * @param array $data
     * @return OrderHistory
     */
    protected function createHistoryEntry(Order $order, array $data): OrderHistory
    {
        $data['order_id'] = $order->id;
        $data['admin_user_id'] = Auth::id();

        return OrderHistory::create($data);
    }

    /**
     * Handle automatic actions when status changes.
     *
     * @param Order $order
     * @param string $oldStatus
     * @param string $newStatus
     * @return void
     */
    protected function handleStatusChangeActions(Order $order, string $oldStatus, string $newStatus): void
    {
        // Auto-generate invoice if configured
        if ($newStatus === Order::STATUS_COMPLETED) {
            $autoGenerate = config('sales.invoices.auto_generate', true);
            if ($autoGenerate) {
                // TODO: Generate invoice (will be implemented in Invoice module)
            }
        }

        if ($newStatus === Order::STATUS_PROCESSING && $oldStatus !== Order::STATUS_PROCESSING) {
            $this->reduceStockOnce($order);
        } elseif ($newStatus === Order::STATUS_CANCELLED && $oldStatus !== Order::STATUS_CANCELLED) {
            $this->restoreStockOnce($order);
        }
    }

    /**
     * Deduct stock only once for the current processing cycle.
     */
    protected function reduceStockOnce(Order $order): void
    {
        if ($order->stock_deducted_at && !$order->stock_restored_at) {
            return;
        }

        $this->reduceStock($order);
        $order->stock_deducted_at = now();
        $order->stock_restored_at = null;
        $order->save();
    }

    /**
     * Restore stock only when this order's deduction is recorded and has not
     * already been restored. Legacy orders without the forward-only marker are
     * deliberately left untouched.
     */
    protected function restoreStockOnce(Order $order): void
    {
        if (!$order->stock_deducted_at || $order->stock_restored_at) {
            return;
        }

        $this->restoreStock($order);
        $order->stock_restored_at = now();
        $order->save();
    }

    /**
     * Reduce product stock for order items.
     *
     * @param Order $order
     * @return void
     */
    protected function reduceStock(Order $order): void
    {
        foreach ($order->items as $item) {
            if ($item->product && $item->product->manage_stock) {
                $item->product->decrement('quantity', $item->quantity);
            }
        }
    }

    /**
     * Restore product stock for cancelled order.
     *
     * @param Order $order
     * @return void
     */
    protected function restoreStock(Order $order): void
    {
        foreach ($order->items as $item) {
            if ($item->product && $item->product->manage_stock) {
                $item->product->increment('quantity', $item->quantity);
            }
        }
    }

    /**
     * Send order cancellation email.
     *
     * @param Order $order
     * @param string $reason
     * @return void
     */
    protected function sendCancellationEmail(Order $order, string $reason): void
    {
        try {
            $template = EmailTemplate::where('key', 'order_cancelled')->first();
            
            if (!$template || !$template->enabled) {
                return;
            }

            $data = [
                'order' => $order,
                'reason' => $reason,
                'customer_name' => $order->user->name ?? 'Customer',
            ];

            Mail::send([], [], function ($message) use ($order, $template, $data) {
                $message->to($order->customer_email)
                    ->subject($template->renderSubject($data))
                    ->html($template->renderBody($data));
            });

        } catch (\Exception $e) {
            Log::error('Failed to send cancellation email', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Generate unique order number.
     *
     * @return string
     */
    protected function generateOrderNumber(): string
    {
        $prefix = config('sales.orders.number_prefix', 'ORD-');
        $length = config('sales.orders.number_length', 8);
        
        do {
            $number = $prefix . strtoupper(substr(uniqid(), -$length));
            $exists = Order::where('order_number', $number)->exists();
        } while ($exists);

        return $number;
    }

    protected function dispatchOrderEvent(
        string $action,
        string $title,
        ?string $message,
        Order $order,
        string $severity = 'info',
        array $context = []
    ): void {
        try {
            $actorUserId = Auth::id();
            $actionUrl = "/admin/sales/orders/{$order->id}";

            $meta = array_merge($context, [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'order_status' => $order->status,
                'payment_status' => $order->payment_status,
            ]);

            $this->adminNotificationService->notifyAllAdmins(
                type: $action,
                title: $title,
                message: $message,
                actionUrl: $actionUrl,
                actorUserId: $actorUserId,
                entityType: 'order',
                entityId: (int) $order->id,
                meta: $meta,
                severity: $severity
            );

            $this->adminNotificationService->log(
                action: $action,
                description: $message,
                actorUserId: $actorUserId,
                entityType: 'order',
                entityId: (int) $order->id,
                context: $meta,
                level: $severity === 'success' ? 'info' : $severity
            );
        } catch (\Exception $e) {
            Log::error('Failed to dispatch admin order event', [
                'order_id' => $order->id,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
