<?php

use App\Models\User;
use Bulbula\Core\Models\EmailTemplate;
use Bulbula\Sales\Models\Invoice;
use Bulbula\Sales\Models\ManualPaymentReasonTemplate;
use Bulbula\Sales\Models\ManualPaymentSubmission;
use Bulbula\Sales\Models\Transaction;
use Bulbula\Sales\Services\InvoiceService;
use Bulbula\Shop\Models\Order;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function makeManualReviewOrder(array $overrides = []): Order
{
    static $sequence = 0;
    $sequence++;

    return Order::create(array_merge([
        'order_number' => 'ORD-MANUAL-REVIEW-' . $sequence,
        'status' => Order::STATUS_PENDING,
        'payment_status' => Order::PAYMENT_PENDING,
        'subtotal' => 1250,
        'tax' => 0,
        'shipping_cost' => 0,
        'discount' => 0,
        'total' => 1250,
        'payment_method' => 'bank_transfer',
        'shipping_method' => 'standard',
        'customer_email' => 'customer@example.test',
    ], $overrides));
}

function makeManualReviewSubmission(Order $order, array $overrides = []): ManualPaymentSubmission
{
    return ManualPaymentSubmission::create(array_merge([
        'order_id' => $order->id,
        'payment_method_id' => null,
        'payment_method_code' => 'bank_transfer',
        'destination_id' => 'cbe-main',
        'destination_snapshot' => [
            'label' => 'CBE Main Account',
            'provider' => 'Commercial Bank of Ethiopia',
            'type' => 'bank',
            'account_name' => 'Example Trading PLC',
            'account_number' => '1000000000',
        ],
        'transfer_reference' => 'FT-123456789',
        'amount' => 1250,
        'currency' => 'ETB',
        'proof_disk' => 'local',
        'proof_path' => 'manual-payment-proofs/proof.png',
        'proof_mime_type' => 'image/png',
        'proof_size' => 1024,
        'status' => ManualPaymentSubmission::STATUS_PENDING_REVIEW,
    ], $overrides));
}

test('admin order details show manual transfer evidence through the protected proof route only', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'is_active' => true,
    ]);
    $customer = User::factory()->create(['role' => 'customer']);
    $order = makeManualReviewOrder([
        'user_id' => $customer->id,
        'customer_email' => $customer->email,
    ]);

    Storage::fake('local');
    Storage::disk('local')->put('manual-payment-proofs/proof.png', 'private proof bytes');
    $submission = makeManualReviewSubmission($order);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.sales.orders.show', $order->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Sales/Orders/Show')
            ->where('manualPaymentSubmissions.0.transfer_reference', 'FT-123456789')
            ->where('manualPaymentSubmissions.0.destination_snapshot.account_number', '1000000000')
            ->where('manualPaymentSubmissions.0.proof_url', route('admin.sales.manual-payments.proof', $submission))
            ->missing('manualPaymentSubmissions.0.proof_path')
            ->missing('manualPaymentSubmissions.0.proof_disk')
            ->missing('order.manual_payment_submissions')
            ->has('reasonTemplates')
        );

    $proofResponse = $this->get(route('admin.sales.manual-payments.proof', $submission))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Content-Security-Policy', 'sandbox');

    $cacheControlDirectives = array_map(
        static fn (string $directive): string => strtolower(trim($directive)),
        explode(',', (string) $proofResponse->headers->get('Cache-Control')),
    );
    expect($cacheControlDirectives)->toContain('private', 'no-store', 'max-age=0');
});

test('staff can reject a transfer with a saved reason and the customer sees the decision and reason', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'is_active' => true,
    ]);
    $customer = User::factory()->create(['role' => 'customer']);
    $order = makeManualReviewOrder([
        'user_id' => $customer->id,
        'customer_email' => $customer->email,
    ]);
    $submission = makeManualReviewSubmission($order);
    $reason = 'We could not find this transfer in the receiving account yet. Please confirm it has settled.';

    $this->actingAs($admin, 'admin')
        ->post(route('admin.sales.manual-payments.reject', $submission), [
            'reason' => $reason,
            'save_as_template' => true,
            'template_label' => 'Transfer not visible yet',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($submission->fresh()->status)->toBe(ManualPaymentSubmission::STATUS_REJECTED)
        ->and($submission->fresh()->review_note)->toBe($reason)
        ->and($order->fresh()->payment_status)->toBe(Order::PAYMENT_FAILED)
        ->and(ManualPaymentReasonTemplate::query()->where('label', 'Transfer not visible yet')->value('reason'))
            ->toBe($reason);

    $template = EmailTemplate::findByCode('manual_transfer_rejected');
    expect($template)->not->toBeNull()
        ->and($template->html_content)->toContain('{payment_reason_html}')
        ->and($template->plain_text_content)->toContain('{payment_reason}');

    $renderedEmail = $template->render([
        'payment_reason_html' => nl2br(e($reason)),
        'payment_reason' => $reason,
    ]);
    expect($renderedEmail['html'])->toContain(nl2br(e($reason)))
        ->and($renderedEmail['text'])->toContain($reason);

    $this->actingAs($customer, 'web')
        ->get(route('shop.account.orders.show', $order->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('order.manual_payment_review.status', ManualPaymentSubmission::STATUS_REJECTED)
            ->where('order.manual_payment_review.reason', $reason)
            ->where('order.manual_payment_review.can_resubmit', true)
            ->missing('order.manual_payment_review.proof_url')
            ->missing('order.manual_payment_review.transfer_reference')
        );
});

test('staff can create, edit, deactivate, and delete reusable rejection reasons', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'is_active' => true,
    ]);
    $this->actingAs($admin, 'admin');

    $this->post(route('admin.sales.manual-payments.reason-templates.store'), [
        'label' => 'Custom reference issue',
        'reason' => 'The transfer reference is missing. Please provide an updated receipt.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $template = ManualPaymentReasonTemplate::query()->where('label', 'Custom reference issue')->firstOrFail();

    $this->put(route('admin.sales.manual-payments.reason-templates.update', $template), [
        'label' => 'Reference needs correction',
        'reason' => 'Please send a receipt with the transfer reference clearly visible.',
        'is_active' => false,
        'sort_order' => 77,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $template->refresh();
    expect($template->label)->toBe('Reference needs correction')
        ->and($template->is_active)->toBeFalse()
        ->and($template->sort_order)->toBe(77);

    $this->delete(route('admin.sales.manual-payments.reason-templates.delete', $template))
        ->assertRedirect();

    expect(ManualPaymentReasonTemplate::query()->whereKey($template->id)->exists())->toBeFalse();
});

test('manual transfer approval requires the review workflow and records verified payment', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'is_active' => true,
    ]);
    $order = makeManualReviewOrder();
    $submission = makeManualReviewSubmission($order);
    $invoice = app(InvoiceService::class)->createFromOrder($order);

    expect(fn () => app(InvoiceService::class)->markAsPaid((int) $invoice->id))
        ->toThrow(\Exception::class);

    $this->actingAs($admin, 'admin')
        ->post(route('admin.sales.orders.update-payment-status', $order->id), [
            'payment_status' => Order::PAYMENT_PAID,
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($order->fresh()->payment_status)->toBe(Order::PAYMENT_PENDING);

    $this->post(route('admin.sales.manual-payments.approve', $submission), [])
        ->assertRedirect()
        ->assertSessionHasErrors('verified_settled');

    expect($order->fresh()->payment_status)->toBe(Order::PAYMENT_PENDING)
        ->and($submission->fresh()->status)->toBe(ManualPaymentSubmission::STATUS_PENDING_REVIEW);

    $this->post(route('admin.sales.manual-payments.approve', $submission), [
        'verified_settled' => true,
    ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($order->fresh()->payment_status)->toBe(Order::PAYMENT_PAID)
        ->and($order->fresh()->status)->toBe(Order::STATUS_PROCESSING)
        ->and($submission->fresh()->status)->toBe(ManualPaymentSubmission::STATUS_APPROVED)
        ->and(Invoice::query()->where('order_id', $order->id)->value('status'))->toBe('paid')
        ->and(Transaction::query()
            ->where('order_id', $order->id)
            ->where('gateway', 'manual_transfer')
            ->where('status', 'completed')
            ->count())->toBe(1);
});

test('guest tracking shows only the manual payment decision and customer-facing reason', function () {
    $order = makeManualReviewOrder([
        'order_number' => 'ORD-GUEST-REVIEW-1',
        'payment_status' => Order::PAYMENT_FAILED,
        'user_id' => null,
    ]);
    $reason = 'The amount shown on this receipt does not match the order total.';
    makeManualReviewSubmission($order, [
        'status' => ManualPaymentSubmission::STATUS_REJECTED,
        'review_note' => $reason,
        'reviewed_at' => now(),
    ]);

    $this->post(route('shop.checkout.track.lookup'), [
        'order_number' => $order->order_number,
    ])->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('trackedOrder.manual_payment_review.status', ManualPaymentSubmission::STATUS_REJECTED)
            ->where('trackedOrder.manual_payment_review.reason', $reason)
            ->missing('trackedOrder.manual_payment_review.proof_url')
            ->missing('trackedOrder.manual_payment_review.transfer_reference')
        );
});

test('manual review email templates are seeded without replacing merchant edits', function () {
    $template = EmailTemplate::query()
        ->where('code', 'manual_transfer_rejected')
        ->where('locale', 'en')
        ->firstOrFail();
    $template->update([
        'subject' => 'Merchant-customized rejection notice',
        'html_content' => '<p>Merchant-owned content</p>',
    ]);

    $migration = require base_path(
        'packages/Bulbula/Sales/src/Database/Migrations/2026_10_13_000003_seed_manual_transfer_review_email_templates.php'
    );
    $migration->up();

    expect($template->fresh()->subject)->toBe('Merchant-customized rejection notice')
        ->and($template->fresh()->html_content)->toBe('<p>Merchant-owned content</p>');
});
