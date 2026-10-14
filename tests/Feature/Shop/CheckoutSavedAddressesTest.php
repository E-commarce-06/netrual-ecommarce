<?php

use App\Models\User;
use Bulbula\Core\Models\Country;
use Bulbula\Core\Models\PaymentMethod;
use Bulbula\Core\Models\ShippingMethod;
use Bulbula\Customer\Models\Customer;
use Bulbula\Customer\Models\CustomerAddress;
use Bulbula\Product\Models\Product;
use Bulbula\Sales\Models\ManualPaymentSubmission;
use Bulbula\Sales\Services\InvoiceService;
use Bulbula\Shop\Models\Address;
use Bulbula\Shop\Models\Order;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function makeCheckoutSavedAddressProduct(array $overrides = []): Product
{
    $name = 'Checkout address '.Str::random(8);

    return Product::create(array_merge([
        'name' => $name,
        'sku' => 'CHK-'.strtoupper(Str::random(10)),
        'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
        'price' => 100,
        'status' => 'enabled',
        'visibility' => 'both',
        'quantity' => 10,
        'stock_status' => 'in_stock',
        'manage_stock' => false,
        'type' => Product::TYPE_SIMPLE,
    ], $overrides));
}

function makeCheckoutSavedAddressPaymentMethod(string $code = 'cod'): PaymentMethod
{
    $configuration = $code === 'bank_transfer' ? [
        'account_destinations' => [[
            'id' => 'cbe-main',
            'label' => 'CBE Main Account',
            'provider' => 'Commercial Bank of Ethiopia',
            'type' => 'bank',
            'account_name' => 'Example Trading PLC',
            'account_number' => '1000000000',
            'instructions' => 'Use the order number as the transfer note.',
            'is_active' => true,
        ]],
    ] : [];

    return PaymentMethod::updateOrCreate(
        ['code' => $code],
        [
            'name' => $code === 'bank_transfer' ? 'Manual Transfer' : 'Cash on Delivery',
            'type' => $code,
            'is_active' => true,
            'is_default' => true,
            'configuration' => $configuration,
        ],
    );
}

function makeCheckoutSavedAddressShippingMethod(): ShippingMethod
{
    return ShippingMethod::create([
        'name' => 'Checkout standard delivery',
        'slug' => 'checkout-standard-'.Str::lower(Str::random(6)),
        'type' => 'flat-rate',
        'status' => 'active',
        'display_order' => 1,
        'base_cost' => 0,
        'cost_per_kg' => 0,
        'is_default' => true,
    ]);
}

function makeCheckoutSavedAddressFor(User $user, array $overrides = []): Address
{
    return Address::create(array_merge([
        'addressable_type' => User::class,
        'addressable_id' => $user->id,
        'type' => Address::TYPE_SHIPPING,
        'first_name' => 'Alemu',
        'last_name' => 'Kebede',
        'company' => null,
        'address_line1' => '12 Bole Road',
        'address_line2' => 'Woreda 03, near the post office',
        'city' => 'Addis Ababa',
        'state' => 'Addis Ababa',
        'postal_code' => '1000',
        'country' => 'ET',
        'phone' => '+251911234567',
        'email' => null,
        'is_default' => false,
    ], $overrides));
}

function putCheckoutSavedAddressCart(Product $product, bool $requiresShipping = true): void
{
    Session::put('cart', [[
        'product_id' => $product->id,
        'quantity' => 1,
        'price' => (float) $product->price,
        'type' => $product->type,
        'requires_shipping' => $requiresShipping,
    ]]);
}

function makeCheckoutSavedAddressCountry(): Country
{
    return Country::create([
        'name' => 'Ethiopia',
        'code' => 'ET',
        'code3' => 'ETH',
        'phone_code' => '+251',
        'currency_code' => 'ETB',
        'currency_symbol' => 'Br',
        'is_active' => true,
        'sort_order' => 1,
    ]);
}

test('checkout loads only this user’s Shop shipping addresses and the account default action works', function () {
    $user = User::factory()->create(['role' => 'customer']);
    $otherUser = User::factory()->create(['role' => 'customer']);
    $customer = Customer::create([
        'user_id' => $user->id,
        'first_name' => 'Alemu',
        'last_name' => 'Kebede',
        'email' => $user->email,
        'is_guest' => false,
    ]);
    CustomerAddress::create([
        'customer_id' => $customer->id,
        'type' => 'shipping',
        'first_name' => 'Customer Book',
        'last_name' => 'Only',
        'address_line_1' => 'Customer API address',
        'city' => 'Addis Ababa',
        'state' => 'Addis Ababa',
        'postal_code' => '1000',
        'country' => 'ET',
        'phone' => '+251911111111',
        'is_default_shipping' => true,
        'is_default_billing' => false,
    ]);
    makeCheckoutSavedAddressCountry();
    makeCheckoutSavedAddressPaymentMethod();
    makeCheckoutSavedAddressShippingMethod();

    $olderAddress = makeCheckoutSavedAddressFor($user);
    $defaultAddress = makeCheckoutSavedAddressFor($user, ['is_default' => true]);
    makeCheckoutSavedAddressFor($user, [
        'type' => Address::TYPE_BILLING,
        'is_default' => true,
        'address_line1' => 'Billing only street',
    ]);
    makeCheckoutSavedAddressFor($otherUser, ['is_default' => true]);

    $product = makeCheckoutSavedAddressProduct();
    putCheckoutSavedAddressCart($product);

    $this->actingAs($user, 'web')
        ->get(route('shop.checkout.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('userAddresses', 2)
            ->where('userAddresses.0.id', $defaultAddress->id)
            ->where('userAddresses.1.id', $olderAddress->id)
            ->where('countries.0.code', 'ET')
        );

    $this->get(route('shop.account.addresses.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('addresses')
            ->where('countries.0.code', 'ET')
        );

    $this->put(route('shop.account.addresses.default', $olderAddress))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($olderAddress->fresh()->is_default)->toBeTrue()
        ->and($defaultAddress->fresh()->is_default)->toBeFalse();
});

test('manual checkout accepts a saved address ID and keeps billing data in the invoice snapshot', function () {
    $user = User::factory()->create(['role' => 'customer']);
    $customer = Customer::create([
        'user_id' => $user->id,
        'first_name' => 'Alemu',
        'last_name' => 'Kebede',
        'email' => $user->email,
        'is_guest' => false,
    ]);
    makeCheckoutSavedAddressPaymentMethod('bank_transfer');
    $shippingMethod = makeCheckoutSavedAddressShippingMethod();
    $savedAddress = makeCheckoutSavedAddressFor($user, ['is_default' => true]);
    $product = makeCheckoutSavedAddressProduct();
    putCheckoutSavedAddressCart($product);
    Storage::fake('local');

    $response = $this->actingAs($user, 'web')->post(route('shop.checkout.store'), [
        'email' => $user->email,
        'payment_method' => 'bank_transfer',
        'terms_accepted' => true,
        'billing_same_as_shipping' => true,
        'shipping_method_id' => $shippingMethod->id,
        'shipping_address_id' => $savedAddress->id,
        'manual_payment_account_id' => 'cbe-main',
        'manual_payment_reference' => 'FT-CHECKOUT-123',
        'payment_proof' => UploadedFile::fake()->image('receipt.png'),
    ]);

    $order = Order::query()->latest('id')->firstOrFail();
    $response->assertRedirect(route('shop.checkout.success', ['order' => $order->id]))
        ->assertSessionHasNoErrors();

    $shipping = $order->addresses()->where('type', Address::TYPE_SHIPPING)->firstOrFail();
    $billing = $order->addresses()->where('type', Address::TYPE_BILLING)->firstOrFail();

    expect($order->payment_status)->toBe(Order::PAYMENT_PENDING)
        ->and($order->status)->toBe(Order::STATUS_PENDING)
        ->and($shipping->address_line1)->toBe($savedAddress->address_line1)
        ->and($billing->address_line1)->toBe($savedAddress->address_line1)
        ->and($billing->country)->toBe('ET')
        ->and(ManualPaymentSubmission::query()->where('order_id', $order->id)->count())->toBe(1);

    $customerAddress = CustomerAddress::query()
        ->where('customer_id', $customer->id)
        ->where('type', 'shipping')
        ->firstOrFail();
    expect($customerAddress->address_line_1)->toBe($savedAddress->address_line1)
        ->and($customerAddress->country)->toBe('ET');

    $invoice = app(InvoiceService::class)->createFromOrder($order);
    $invoiceData = app(InvoiceService::class)->generateInvoiceData($invoice);

    expect($invoiceData['billing_address']['full_name'])->toBe('Alemu Kebede')
        ->and($invoiceData['billing_address']['address_line1'])->toBe($savedAddress->address_line1)
        ->and($invoiceData['billing_address']['country'])->toBe('ET');
});

test('checkout rejects foreign, billing-only, deleted, and incomplete saved address IDs', function () {
    $user = User::factory()->create(['role' => 'customer']);
    $foreignUser = User::factory()->create(['role' => 'customer']);
    makeCheckoutSavedAddressPaymentMethod();
    $shippingMethod = makeCheckoutSavedAddressShippingMethod();
    $foreignAddress = makeCheckoutSavedAddressFor($foreignUser);
    $billingAddress = makeCheckoutSavedAddressFor($user, ['type' => Address::TYPE_BILLING]);
    $deletedAddress = makeCheckoutSavedAddressFor($user);
    $deletedAddress->delete();
    $incompleteAddress = makeCheckoutSavedAddressFor($user, ['phone' => null]);
    $product = makeCheckoutSavedAddressProduct();
    putCheckoutSavedAddressCart($product);

    foreach ([$foreignAddress, $billingAddress, $deletedAddress, $incompleteAddress] as $address) {
        $this->actingAs($user, 'web')
            ->post(route('shop.checkout.store'), [
                'email' => $user->email,
                'payment_method' => 'cod',
                'terms_accepted' => true,
                'shipping_method_id' => $shippingMethod->id,
                'shipping_address_id' => $address->id,
            ])
            ->assertSessionHasErrors('shipping_address_id');
    }

    expect(Order::query()->count())->toBe(0);
});

test('new shipping addresses still require all delivery fields', function () {
    $user = User::factory()->create(['role' => 'customer']);
    makeCheckoutSavedAddressPaymentMethod();
    $shippingMethod = makeCheckoutSavedAddressShippingMethod();
    $product = makeCheckoutSavedAddressProduct();
    putCheckoutSavedAddressCart($product);

    $this->actingAs($user, 'web')
        ->post(route('shop.checkout.store'), [
            'email' => $user->email,
            'payment_method' => 'cod',
            'terms_accepted' => true,
            'shipping_method_id' => $shippingMethod->id,
            'shipping_address' => [
                'first_name' => 'Alemu',
                'last_name' => 'Kebede',
                'address_line1' => '12 Bole Road',
                'city' => 'Addis Ababa',
                'state' => 'Addis Ababa',
                'country' => 'ET',
            ],
        ])
        ->assertSessionHasErrors([
            'shipping_address.postal_code',
            'shipping_address.phone',
        ]);

    expect(Order::query()->count())->toBe(0);
});

test('digital manual orders keep an invoice contact without saving a postal address', function () {
    $user = User::factory()->create(['role' => 'customer']);
    $customer = Customer::create([
        'user_id' => $user->id,
        'first_name' => 'Alemu',
        'last_name' => 'Kebede',
        'email' => $user->email,
        'is_guest' => false,
    ]);
    makeCheckoutSavedAddressPaymentMethod('bank_transfer');
    $product = makeCheckoutSavedAddressProduct([
        'type' => Product::TYPE_VIRTUAL,
        'manage_stock' => false,
    ]);
    putCheckoutSavedAddressCart($product, false);
    Storage::fake('local');

    $response = $this->actingAs($user, 'web')->post(route('shop.checkout.store'), [
        'email' => $user->email,
        'payment_method' => 'bank_transfer',
        'terms_accepted' => true,
        'billing_address' => [
            'first_name' => 'Alemu',
            'last_name' => 'Kebede',
            'address_line1' => null,
            'address_line2' => null,
            'city' => null,
            'state' => null,
            'postal_code' => null,
            'country' => 'ET',
            'phone' => null,
        ],
        'manual_payment_account_id' => 'cbe-main',
        'manual_payment_reference' => 'FT-DIGITAL-123',
        'payment_proof' => UploadedFile::fake()->image('receipt.png'),
    ]);

    $order = Order::query()->latest('id')->firstOrFail();
    $response->assertRedirect(route('shop.checkout.success', ['order' => $order->id]))
        ->assertSessionHasNoErrors();

    expect($order->addresses()->where('type', Address::TYPE_SHIPPING)->exists())->toBeFalse()
        ->and($order->addresses()->where('type', Address::TYPE_BILLING)->value('first_name'))->toBe('Alemu')
        ->and($order->addresses()->where('type', Address::TYPE_BILLING)->value('address_line1'))->toBe('')
        ->and($order->addresses()->where('type', Address::TYPE_BILLING)->value('country'))->toBe('ET')
        ->and(CustomerAddress::query()->where('customer_id', $customer->id)->exists())->toBeFalse();

    $invoice = app(InvoiceService::class)->createFromOrder($order);
    $invoiceData = app(InvoiceService::class)->generateInvoiceData($invoice);

    expect($invoiceData['billing_address']['full_name'])->toBe('Alemu Kebede')
        ->and($invoiceData['billing_address']['address_line1'])->toBe('');
});

test('digital-only checkout does not offer saved shipping addresses as an order contact', function () {
    $user = User::factory()->create(['role' => 'customer']);
    makeCheckoutSavedAddressCountry();
    makeCheckoutSavedAddressPaymentMethod();
    makeCheckoutSavedAddressFor($user, ['is_default' => true]);
    $product = makeCheckoutSavedAddressProduct([
        'type' => Product::TYPE_VIRTUAL,
        'manage_stock' => false,
    ]);
    putCheckoutSavedAddressCart($product, false);

    $this->actingAs($user, 'web')
        ->get(route('shop.checkout.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('cartSummary.requires_shipping', false)
            ->has('userAddresses', 0)
            ->where('countries.0.code', 'ET')
        );
});
