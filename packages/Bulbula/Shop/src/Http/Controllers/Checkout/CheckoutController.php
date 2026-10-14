<?php

namespace Bulbula\Shop\Http\Controllers\Checkout;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Bulbula\Cart\Services\CartTaxCalculator;
use Bulbula\Cart\Services\CartShippingCalculator;
use Bulbula\Cart\Support\CartTypeHelper;
use Bulbula\Shop\Services\CheckoutService;
use Bulbula\Shop\Models\Address;
use Bulbula\Shop\Models\ShippingMethod;
use Bulbula\Shop\Models\Order;
use Bulbula\Product\Models\Product;
use Bulbula\Core\Models\PaymentMethod;
use Bulbula\Core\Models\Currency;
use Bulbula\Core\Models\Country;
use Bulbula\Core\Models\EmailTemplate;
use Bulbula\Core\Services\ThemeViewResolver;
use Bulbula\Core\Services\SettingService;
use Bulbula\Core\Services\PaymentGatewayManager;
use Bulbula\Sales\Services\InvoiceService;
use Bulbula\Sales\Services\TransactionService;
use Bulbula\Sales\Services\ManualPaymentService;
use Bulbula\Sales\Models\ManualPaymentSubmission;
use Bulbula\UIEditor\Services\LayoutService;
use Bulbula\CMS\Models\Page;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    protected CartTaxCalculator $taxCalculator;
    protected CartShippingCalculator $shippingCalculator;
    protected CheckoutService $checkoutService;
    protected ThemeViewResolver $themeResolver;
    protected SettingService $settingService;
    protected PaymentGatewayManager $gatewayManager;
    protected InvoiceService $invoiceService;
    protected TransactionService $transactionService;
    protected ManualPaymentService $manualPaymentService;
    protected LayoutService $layoutService;

    public function __construct(
        CartTaxCalculator $taxCalculator,
        CartShippingCalculator $shippingCalculator,
        CheckoutService $checkoutService,
        ThemeViewResolver $themeResolver,
        SettingService $settingService,
        PaymentGatewayManager $gatewayManager,
        InvoiceService $invoiceService,
        TransactionService $transactionService,
        ManualPaymentService $manualPaymentService,
        LayoutService $layoutService
    ) {
        $this->taxCalculator = $taxCalculator;
        $this->shippingCalculator = $shippingCalculator;
        $this->checkoutService = $checkoutService;
        $this->themeResolver = $themeResolver;
        $this->settingService = $settingService;
        $this->gatewayManager = $gatewayManager;
        $this->invoiceService = $invoiceService;
        $this->transactionService = $transactionService;
        $this->manualPaymentService = $manualPaymentService;
        $this->layoutService = $layoutService;
    }

    /**
     * Display checkout page
     */
    public function index(Request $request): Response
    {
        // Get cart items from session
        $items = $this->getCartItems();

        if (empty($items)) {
            return Inertia::render($this->themeResolver->resolve('Checkout/Index'), [
                'error' => 'Your cart is empty',
                'cartEmpty' => true,
                'cartItems' => [],
                'cartSummary' => [
                    'subtotal' => 0,
                    'taxes' => [
                        'breakdown' => [],
                        'total' => 0,
                    ],
                    'shipping' => [
                        'options' => [],
                        'selected' => null,
                        'cost' => 0,
                    ],
                    'total' => 0,
                ],
                'checkoutConfig' => [
                    'allow_guest_checkout' => (bool) $this->settingService->get('checkout_allow_guest', true),
                    'checkout_require_account' => (bool) $this->settingService->get('checkout_require_account', false),
                    'require_terms_acceptance' => (bool) config('shop.checkout.require_terms_acceptance', true),
                    'enable_newsletter_signup' => (bool) config('shop.checkout.enable_newsletter_signup', true),
                    'enable_order_notes' => (bool) config('shop.checkout.enable_order_notes', true),
                ],
                'userAddresses' => [],
                'paymentMethods' => [],
                'countries' => Country::active()->ordered()
                    ->select('id', 'name', 'code', 'phone_code')
                    ->get()
                    ->toArray(),
            ]);
        }

        // Calculate subtotal
        $subtotal = collect($items)->sum(function ($item) {
            return $item['quantity'] * $item['price'];
        });

        // Calculate taxes
        $taxResult = $this->taxCalculator->calculate($items, []);

        $requiresShipping = CartTypeHelper::cartRequiresShipping($items);
        $shippingResult = ['options' => [], 'default' => null];
        $selectedShipping = null;
        $shippingCost = 0;

        if ($requiresShipping) {
            $shippingResult = $this->shippingCalculator->calculate($items, []);

            $selectedShippingId = Session::get('checkout.shipping_method_id');

            if ($selectedShippingId) {
                $selectedShipping = collect($shippingResult['options'])->firstWhere('id', $selectedShippingId);
            }

            if (! $selectedShipping) {
                $selectedShipping = $shippingResult['default'] ?? null;
            }

            $shippingCost = $selectedShipping['cost'] ?? 0;
        }

        $couponData = Session::get('cart_coupon');
        if (($couponData['type'] ?? null) === 'free_shipping') {
            $shippingCost = 0;
            if ($selectedShipping) {
                $selectedShipping['cost'] = 0;
            }
        }
        $discountAmount = $couponData['discount_amount'] ?? 0;
        $grandTotal = $subtotal + $taxResult['total'] + $shippingCost - $discountAmount;

        // Get payment methods
        $paymentMethods = PaymentMethod::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function ($method) use ($grandTotal) {
                $manualAccounts = $method->code === 'bank_transfer'
                    ? $this->manualPaymentService->publicDestinations($method)
                    : [];

                return [
                    'id' => $method->code,
                    'code' => $method->code,
                    'name' => $method->name,
                    'description' => $method->description,
                    'instructions' => $method->instructions,
                    'is_default' => $method->is_default,
                    'fee' => $method->code === 'bank_transfer' ? 0 : $method->calculateFee($grandTotal),
                    'is_available' => $method->isAvailableForAmount($grandTotal),
                    'manual_accounts' => $manualAccounts,
                ];
            })
            ->filter(fn ($method) => $method['is_available']
                && ($method['code'] !== 'bank_transfer' || count($method['manual_accounts']) > 0))
            ->values()
            ->toArray();

        // Get checkout configuration
        $checkoutConfig = [
            'allow_guest_checkout' => (bool) $this->settingService->get('checkout_allow_guest', true),
            'checkout_require_account' => (bool) $this->settingService->get('checkout_require_account', false),
            'require_terms_acceptance' => (bool) config('shop.checkout.require_terms_acceptance', true),
            'enable_newsletter_signup' => (bool) config('shop.checkout.enable_newsletter_signup', true),
            'enable_order_notes' => (bool) config('shop.checkout.enable_order_notes', true),
        ];

        // The storefront address book uses Shop\Models\Address, distinct from
        // the Customer\Models\CustomerAddress API address book. Only expose
        // this user's shipping addresses for carts that actually need delivery.
        $userAddresses = [];
        if (Auth::check() && $requiresShipping) {
            $userAddresses = Address::query()
                ->where('addressable_type', \App\Models\User::class)
                ->where('addressable_id', Auth::id())
                ->where('type', Address::TYPE_SHIPPING)
                ->orderByDesc('is_default')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (Address $address) => [
                    'id' => (int) $address->id,
                    'first_name' => $address->first_name,
                    'last_name' => $address->last_name,
                    'company' => $address->company,
                    'address_line1' => $address->address_line1,
                    'address_line2' => $address->address_line2,
                    'city' => $address->city,
                    'state' => $address->state,
                    'postal_code' => $address->postal_code,
                    'country' => $address->country,
                    'phone' => $address->phone,
                    'is_default' => (bool) $address->is_default,
                ])
                ->all();
        }

        return Inertia::render($this->themeResolver->resolve('Checkout/Index'), [
            'cartItems' => collect($items)->map(function ($item) {
                $product = Product::find($item['product_id']);
                return [
                    'id' => $item['id'] ?? $item['product_id'],
                    'product_id' => $item['product_id'],
                    'product' => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'slug' => $product->slug,
                        'image' => $product->mainImage?->url,
                    ],
                    'quantity' => $item['quantity'],
                    'price' => round($item['price'], 2),
                    'total' => round($item['quantity'] * $item['price'], 2),
                ];
            })->toArray(),
            'cartSummary' => [
                'subtotal' => round($subtotal, 2),
                'discount' => round($discountAmount, 2),
                'coupon' => $couponData,
                'requires_shipping' => $requiresShipping,
                'taxes' => [
                    'breakdown' => $taxResult['breakdown'],
                    'total' => round($taxResult['total'], 2),
                ],
                'shipping' => [
                    'options' => $shippingResult['options'],
                    'selected' => $selectedShipping,
                    'cost' => round($shippingCost, 2),
                ],
                'total' => round(max(0, $grandTotal), 2),
            ],
            'checkoutConfig' => $checkoutConfig,
            'userAddresses' => $userAddresses,
            'paymentMethods' => $paymentMethods,
            'countries' => Country::active()->ordered()
                ->select('id', 'name', 'code', 'phone_code')
                ->get()
                ->toArray(),
            'cartEmpty' => false,
        ]);
    }

    /**
     * Process checkout and create order
     */
    public function store(Request $request)
    {
        $items = $this->getCartItems();

        if (empty($items)) {
            return back()->with('error', 'Your cart is empty');
        }

        $requiresShipping = CartTypeHelper::cartRequiresShipping($items);
        $useSavedShippingAddress = $requiresShipping
            && Auth::check()
            && $request->filled('shipping_address_id');
        $requestedPaymentMethod = PaymentMethod::query()
            ->where('code', trim((string) $request->input('payment_method', '')))
            ->first();

        if (! $requestedPaymentMethod || ! $requestedPaymentMethod->is_active) {
            return back()->with('error', 'The selected payment method is not currently available.');
        }

        if ($requestedPaymentMethod->code === 'bank_transfer'
            && count($this->manualPaymentService->publicDestinations($requestedPaymentMethod)) === 0) {
            return back()->with('error', 'Manual transfer is not available until a bank or wallet account is configured.');
        }

        $rules = [
            'email' => 'required|email',
            'phone' => 'nullable|string',
            'payment_method' => 'required|string',
            'billing_same_as_shipping' => 'boolean',
            'terms_accepted' => 'required|accepted',
            'newsletter_signup' => 'boolean',
            'order_notes' => 'nullable|string',
            'create_account' => 'boolean',
            'password' => 'required_if:create_account,true|nullable|string|min:8|confirmed',
            'password_confirmation' => 'required_with:password|nullable|string',
        ];

        if ($requestedPaymentMethod->code === 'bank_transfer') {
            $rules = array_merge($rules, [
                'manual_payment_account_id' => 'required|string|max:64',
                'manual_payment_reference' => 'nullable|string|max:150',
                'payment_proof' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            ]);
        }

        if ($requiresShipping) {
            if ($useSavedShippingAddress) {
                $rules['shipping_address_id'] = 'required|integer';
            } else {
                $rules = array_merge($rules, [
                    'shipping_address' => 'required|array',
                    'shipping_address.first_name' => 'required|string',
                    'shipping_address.last_name' => 'required|string',
                    'shipping_address.address_line1' => 'required|string',
                    'shipping_address.city' => 'required|string',
                    'shipping_address.state' => 'required|string',
                    'shipping_address.postal_code' => 'required|string',
                    'shipping_address.country' => 'required|string',
                    'shipping_address.phone' => 'required|string',
                ]);
            }

            $rules = array_merge($rules, [
                'shipping_method_id' => 'required|exists:shipping_methods,id',
                'billing_address' => 'required_if:billing_same_as_shipping,false|array',
            ]);
        } else {
            $rules = array_merge($rules, [
                'billing_address' => 'required|array',
                'billing_address.first_name' => 'required|string',
                'billing_address.last_name' => 'required|string',
                'billing_address.address_line1' => 'nullable|string',
                'billing_address.city' => 'nullable|string',
                'billing_address.state' => 'nullable|string',
                'billing_address.postal_code' => 'nullable|string',
                'billing_address.country' => 'nullable|string',
                'billing_address.phone' => 'nullable|string',
            ]);
        }

        $validated = $request->validate($rules);

        if (! $requiresShipping) {
            // Digital checkout asks only for an order contact. The shared order
            // address snapshot has NOT NULL location columns, so store absent
            // optional values as empty strings rather than making them required.
            foreach (['address_line1', 'city', 'state', 'postal_code', 'country'] as $locationField) {
                $validated['billing_address'][$locationField] = $validated['billing_address'][$locationField] ?? '';
            }
        }

        if ($useSavedShippingAddress) {
            $savedShippingAddress = Address::query()
                ->whereKey($validated['shipping_address_id'])
                ->where('addressable_type', \App\Models\User::class)
                ->where('addressable_id', Auth::id())
                ->where('type', Address::TYPE_SHIPPING)
                ->first();

            if (! $savedShippingAddress) {
                return back()->withErrors([
                    'shipping_address_id' => 'That saved shipping address is no longer available. Please choose another address.',
                ]);
            }

            foreach ([
                'first_name',
                'last_name',
                'address_line1',
                'city',
                'state',
                'postal_code',
                'country',
                'phone',
            ] as $requiredField) {
                if (blank($savedShippingAddress->{$requiredField})) {
                    return back()->withErrors([
                        'shipping_address_id' => 'This saved address is missing required delivery details. Update it in your address book or enter another address.',
                    ]);
                }
            }

            $validated['shipping_address'] = $savedShippingAddress->only([
                'first_name',
                'last_name',
                'company',
                'address_line1',
                'address_line2',
                'city',
                'state',
                'postal_code',
                'country',
                'phone',
                'email',
            ]);
        }

        $requireAccount = (bool) $this->settingService->get('checkout_require_account', false);
        $allowGuest = (bool) $this->settingService->get('checkout_allow_guest', true);

        if ($requireAccount && ! $allowGuest && ! Auth::check()) {
            $request->validate([
                'create_account' => 'accepted',
                'password' => 'required|string|min:8|confirmed',
            ], [
                'create_account.accepted' => 'Account creation is required to complete checkout.',
            ]);
        }

        // Calculate totals
        $subtotal = collect($items)->sum(function ($item) {
            return $item['quantity'] * $item['price'];
        });

        $taxResult = $this->taxCalculator->calculate($items, []);
        $shippingCost = 0;
        $selectedShipping = null;

        if ($requiresShipping) {
            $shippingResult = $this->shippingCalculator->calculate($items, []);
            $selectedShipping = collect($shippingResult['options'])
                ->firstWhere('id', $validated['shipping_method_id']);

            if (! $selectedShipping) {
                return back()->with('error', 'Invalid shipping method');
            }

            $shippingCost = $selectedShipping['cost'];
        }

        // Carry only the coupon code; the checkout service revalidates it and
        // recalculates the discount under a coupon-row lock.
        $couponData = Session::get('cart_coupon');
        $couponCode = $couponData['code'] ?? null;
        $grandTotal = $subtotal + $taxResult['total'] + $shippingCost;

        $billingAddress = $requiresShipping
            ? (($validated['billing_same_as_shipping'] ?? true)
                ? $validated['shipping_address']
                : ($validated['billing_address'] ?? $validated['shipping_address']))
            : $validated['billing_address'];

        // Prepare order data
        $orderData = [
            'user_id' => Auth::id(),
            'customer_email' => $validated['email'],
            'customer_phone' => $validated['phone'] ?? ($billingAddress['phone'] ?? null),
            'payment_method' => $validated['payment_method'],
            'shipping_method' => $selectedShipping['name'] ?? null,
            'shipping_address' => $requiresShipping ? $validated['shipping_address'] : null,
            'billing_address' => $billingAddress,
            'same_as_shipping' => $requiresShipping ? ($validated['billing_same_as_shipping'] ?? true) : false,
            'notes' => $validated['order_notes'] ?? null,
            'subtotal' => $subtotal,
            'tax' => $taxResult['total'],
            'shipping_cost' => $shippingCost,
            'discount' => 0,
            'total' => round(max(0, $grandTotal), 2),
            'coupon_code' => $couponCode,
            'create_account' => $validated['create_account'] ?? false,
            'password' => $validated['password'] ?? null,
            'newsletter_subscribed' => $validated['newsletter_signup'] ?? false,
            'requires_shipping' => $requiresShipping,
        ];

        if ($requestedPaymentMethod->code === 'bank_transfer') {
            $orderData['manual_payment'] = [
                'destination_id' => $validated['manual_payment_account_id'],
                'transfer_reference' => $validated['manual_payment_reference'] ?? null,
                'proof' => $validated['payment_proof'],
            ];
        }

        // Create order
        $result = $this->checkoutService->createOrder($orderData);

        if (!$result['success']) {
            return back()->with('error', $result['message'] ?? 'Failed to create order');
        }

        $order = $result['data']['order'];

        // Manual transfer proof is not a payment confirmation. Keep the order
        // pending for staff verification and do not call a gateway or the
        // immediate-payment fallback below.
        if ($requestedPaymentMethod->code === 'bank_transfer') {
            Session::put('checkout.last_order_id', $order->id);
            Session::forget('cart');
            Session::forget('cart_coupon');
            $this->sendManualOrderPlacedEmail($order, $validated);

            return redirect()->route('shop.checkout.success', [
                'order' => $order->id,
            ])->with('success', 'Order received. Your transfer proof is awaiting staff verification.');
        }

        // Handle payment via gateway manager
        $paymentMethod = $validated['payment_method'];
        
        // Check if a payment gateway handles this payment method
        $gateway = $this->gatewayManager->getByPaymentMethod($paymentMethod);
        
        if ($gateway) {
            // Gateway exists - check if it's properly configured
            $isConfigured = $gateway->isConfigured();
            
            if (!$isConfigured) {
                Log::warning('Checkout: Gateway not configured', [
                    'gateway_code' => $gateway->getCode(),
                ]);
                return back()->with('error', "Payment gateway {$paymentMethod} is not properly configured. Please contact support or try another payment method.");
            }
            
            try {
                // Process payment (will redirect to gateway or return success)
                $response = $this->gatewayManager->processPayment($order, $validated);
                
                // Handle different response types from payment gateways
                if ($response instanceof \Illuminate\Http\RedirectResponse) {
                    // External redirect (Stripe Checkout, etc.)
                    // Cart will be cleared when user returns successfully
                    $redirectUrl = $response->getTargetUrl();

                    Session::put('checkout.last_order_id', $order->id);
                    
                    return back()->with([
                        'redirect_url' => $redirectUrl,
                    ]);
                }
                
                // Handle array response (Razorpay, etc. - payment data for frontend)
                if (is_array($response)) {
                    // Don't clear cart yet - will be cleared after payment verification in callback
                    // Store payment data in session for redundancy
                    Session::put('razorpay_payment_data', $response);
                    Session::put('checkout.last_order_id', $order->id);
                    
                    // Return back to checkout with payment_response as flash data
                    // Flash data will be automatically included in Inertia props
                    return back()->with([
                        'payment_response' => $response,
                        'redirect_url' => $response['redirect_url'] ?? null,
                    ]);
                }
                
                // For immediate payment methods (COD, etc.), clear cart now
                Session::forget('cart');
                Session::forget('cart_coupon');
                Session::put('checkout.last_order_id', $order->id);
                
                // For any other response type, return as-is
                return $response;
                
            } catch (\Exception $e) {
                Log::error('Payment gateway error', [
                    'order_id' => $order->id,
                    'payment_method' => $paymentMethod,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                return back()->with('error', 'Payment processing failed: ' . $e->getMessage());
            }
        }
        
        // No gateway found - treat as COD or immediate payment method
        // This handles COD and any custom payment methods without gateways
        if (!app(\Bulbula\Sales\Services\OrderService::class)->updatePaymentStatus(
            $order,
            Order::PAYMENT_PAID,
            'Payment completed via manual/instant method'
        )) {
            return back()->with('error', 'Payment was received, but the order could not be advanced. Please contact support.');
        }

        $invoice = $this->invoiceService->createFromOrderIfMissing($order);
        $this->transactionService->createPaymentIfMissing($order, [
            'payment_method' => $paymentMethod,
            'gateway' => $paymentMethod,
            'amount' => $order->total,
            'status' => 'completed',
            'notes' => 'Payment completed via manual/instant method',
            'invoice_id' => $invoice?->id,
        ]);

        // Send order confirmation email
        try {
            $template = EmailTemplate::findByCode('order_placed');
            
            if ($template) {
                $customerName = trim(
                    ($validated['shipping_address']['first_name'] ?? $validated['billing_address']['first_name'] ?? '')
                    .' '
                    .($validated['shipping_address']['last_name'] ?? $validated['billing_address']['last_name'] ?? '')
                ) ?: 'Customer';
                
                $template->send($order->customer_email, [
                    'customer_name' => $customerName,
                    'order_number' => $order->order_number,
                    'order_date' => $order->created_at->format('F j, Y'),
                    'order_total' => '₹' . number_format($order->total, 2),
                    'store_name' => config('app.name', config('brand.name')),
                    'store_url' => url('/'),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Order confirmation email failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }

        // Keep last order marker for guest success page authorization
        Session::put('checkout.last_order_id', $order->id);

        // Clear cart and checkout session
        Session::forget('cart');
        Session::forget('cart_coupon');

        // Redirect to success page
        return redirect()->route('shop.checkout.success', [
            'order' => $order->id
        ])->with('success', 'Order placed successfully!');
    }

    /**
     * Replace a rejected manual transfer screenshot for the same order.
     */
    public function resubmitManualPayment(Request $request, int $orderId)
    {
        $order = Order::findOrFail($orderId);
        $lastOrderId = (int) Session::get('checkout.last_order_id');
        $isOrderOwner = Auth::check() && (int) $order->user_id === (int) Auth::id();
        $isGuestOrderInSession = $lastOrderId === (int) $order->id;

        if (! $isOrderOwner && ! $isGuestOrderInSession) {
            abort(403);
        }

        if ($order->payment_method !== 'bank_transfer') {
            abort(404);
        }

        $validated = $request->validate([
            'manual_payment_account_id' => 'required|string|max:64',
            'manual_payment_reference' => 'nullable|string|max:150',
            'payment_proof' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        try {
            $this->manualPaymentService->submitReplacement(
                $order,
                $validated['payment_proof'],
                $validated['manual_payment_account_id'],
                $validated['manual_payment_reference'] ?? null,
                Auth::id() ? (int) Auth::id() : null,
            );
        } catch (\Throwable $exception) {
            Log::warning('Manual payment proof replacement failed', [
                'order_id' => $order->id,
                'error' => $exception->getMessage(),
            ]);

            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('shop.checkout.success', ['order' => $order->id])
            ->with('success', 'Your replacement proof was submitted and is awaiting verification.');
    }

    /**
     * Display order success page
     */
    public function success(Request $request, $order)
    {
        $order = Order::with(['items', 'addresses', 'latestManualPaymentSubmission'])->findOrFail($order);
        $lastOrderId = (int) Session::get('checkout.last_order_id');

        if (Auth::check()) {
            $isOrderOwner = (int) $order->user_id === (int) Auth::id();
            $isLastGuestOrderInSession = $lastOrderId === (int) $order->id;

            if (! $isOrderOwner && ! $isLastGuestOrderInSession) {
                abort(403);
            }
        } else {
            if ($lastOrderId !== (int) $order->id) {
                abort(403);
            }
        }

        $isLastOrderInSession = $lastOrderId === (int) $order->id;
        $canGuestContinueManualReview = $order->payment_method === 'bank_transfer'
            && $order->status === Order::STATUS_PENDING
            && in_array($order->payment_status, [Order::PAYMENT_PENDING, Order::PAYMENT_FAILED], true);

        if ($isLastOrderInSession) {
            Session::forget('cart');
            Session::forget('cart_coupon');
            Session::forget('checkout');

            if ($canGuestContinueManualReview) {
                Session::put('checkout.last_order_id', $order->id);
            } else {
                Session::forget('checkout.last_order_id');
            }
        }

        $shippingAddress = $order->shippingAddress;
        $manualSubmission = $order->latestManualPaymentSubmission;
        $manualPaymentAccounts = [];

        if ($order->payment_method === 'bank_transfer'
            && $manualSubmission?->status === ManualPaymentSubmission::STATUS_REJECTED) {
            $activeManualMethod = PaymentMethod::query()
                ->where('code', 'bank_transfer')
                ->where('is_active', true)
                ->first();
            $manualPaymentAccounts = $this->manualPaymentService->publicDestinations($activeManualMethod);
        }

        return Inertia::render($this->themeResolver->resolve('Checkout/Success'), [
            'manualPayment' => $order->payment_method === 'bank_transfer' ? [
                'status' => $manualSubmission?->status,
                'transfer_reference' => $manualSubmission?->transfer_reference,
                'review_note' => $manualSubmission?->review_note,
                'submitted_at' => $manualSubmission?->created_at?->format('F j, Y g:i A'),
                'accounts' => $manualPaymentAccounts,
                'can_resubmit' => $manualSubmission?->status === ManualPaymentSubmission::STATUS_REJECTED
                    && $canGuestContinueManualReview
                    && count($manualPaymentAccounts) > 0,
            ] : null,
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
                'subtotal' => round($order->subtotal, 2),
                'tax' => round($order->tax, 2),
                'shipping_cost' => round($order->shipping_cost, 2),
                'total' => round($order->total, 2),
                'customer_email' => $order->customer_email,
                'customer_phone' => $order->customer_phone,
                'is_guest' => $order->user_id === null,
                'created_at' => $order->created_at->format('F j, Y g:i A'),
                'items' => $order->items->map(function ($item) {
                    return [
                        'product_name' => $item->product_name,
                        'product_image' => $item->product_image,
                        'quantity' => $item->quantity,
                        'price' => round($item->price, 2),
                        'total' => round($item->total, 2),
                    ];
                })->toArray(),
                'shipping_address' => $shippingAddress ? [
                    'first_name' => $shippingAddress->first_name,
                    'last_name' => $shippingAddress->last_name,
                    'address_line1' => $shippingAddress->address_line1,
                    'address_line2' => $shippingAddress->address_line2,
                    'city' => $shippingAddress->city,
                    'state' => $shippingAddress->state,
                    'postal_code' => $shippingAddress->postal_code,
                    'country' => $shippingAddress->country,
                    'phone' => $shippingAddress->phone,
                ] : null,
            ],
        ]);
    }

    private function sendManualOrderPlacedEmail(Order $order, array $validated): void
    {
        try {
            $template = EmailTemplate::findByCode('order_placed');
            if (! $template) {
                return;
            }

            $customerName = trim(
                ($validated['shipping_address']['first_name'] ?? $validated['billing_address']['first_name'] ?? '')
                . ' '
                . ($validated['shipping_address']['last_name'] ?? $validated['billing_address']['last_name'] ?? '')
            ) ?: 'Customer';
            $currency = Currency::getDefault();

            $template->send($order->customer_email, [
                'customer_name' => $customerName,
                'order_number' => $order->order_number,
                'order_date' => $order->created_at->format('F j, Y'),
                'order_total' => $currency?->format((float) $order->total)
                    ?? ('USD ' . number_format((float) $order->total, 2)),
                'payment_status' => 'Awaiting manual transfer verification',
                'store_name' => config('app.name', config('brand.name')),
                'store_url' => url('/'),
            ]);
        } catch (\Throwable $exception) {
            Log::error('Manual payment order email failed', [
                'order_id' => $order->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Guest order tracking form page.
     */
    public function guestTrack(Request $request): Response
    {
        $prefilledOrderNumber = trim((string) $request->query('order_number', ''));

        $trackPage = Page::where('url_key', 'track-order')->where('status', 'published')->first();
        $layoutData = $trackPage ? $this->layoutService->getPublishedForPage($trackPage)?->layout_data : null;

        return Inertia::render($this->themeResolver->resolve('Checkout/GuestTrack'), [
            'lookup' => [
                'order_number' => $prefilledOrderNumber,
            ],
            'trackedOrder' => null,
            'layoutData'   => $layoutData,
        ]);
    }

    /**
     * Guest order tracking lookup by order number.
     */
    public function guestTrackLookup(Request $request): Response
    {
        $validated = $request->validate([
            'order_number' => 'required|string|max:120',
        ]);

        $orderNumber = ltrim(trim((string) $validated['order_number']), '#');

        $trackPage   = Page::where('url_key', 'track-order')->where('status', 'published')->first();
        $layoutData  = $trackPage ? $this->layoutService->getPublishedForPage($trackPage)?->layout_data : null;

        $order = Order::with(['items', 'shippingAddress', 'latestManualPaymentSubmission'])
            ->where('order_number', $orderNumber)
            ->whereNull('user_id')
            ->first();

        if (!$order) {
            return Inertia::render($this->themeResolver->resolve('Checkout/GuestTrack'), [
                'lookup' => [
                    'order_number' => $validated['order_number'],
                ],
                'trackedOrder' => null,
                'layoutData'   => $layoutData,
                'error' => 'Guest order not found. Please check your order ID.',
            ]);
        }

        return Inertia::render($this->themeResolver->resolve('Checkout/GuestTrack'), [
            'lookup' => [
                'order_number' => $validated['order_number'],
            ],
            'layoutData'   => $layoutData,
            'trackedOrder' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'manual_payment_review' => $order->payment_method === 'bank_transfer' && $order->latestManualPaymentSubmission ? [
                    'status' => $order->latestManualPaymentSubmission->status,
                    'reason' => $order->latestManualPaymentSubmission->status === ManualPaymentSubmission::STATUS_REJECTED
                        ? $order->latestManualPaymentSubmission->review_note
                        : null,
                    'submitted_at' => $order->latestManualPaymentSubmission->created_at?->format('F j, Y g:i A'),
                    'reviewed_at' => $order->latestManualPaymentSubmission->reviewed_at?->format('F j, Y g:i A'),
                ] : null,
                'total' => round((float) $order->total, 2),
                'created_at' => $order->created_at->format('F j, Y g:i A'),
                'items_count' => $order->items->sum('quantity'),
                'shipping_address' => $order->shippingAddress ? [
                    'first_name' => $order->shippingAddress->first_name,
                    'last_name' => $order->shippingAddress->last_name,
                    'city' => $order->shippingAddress->city,
                    'state' => $order->shippingAddress->state,
                    'postal_code' => $order->shippingAddress->postal_code,
                    'country' => $order->shippingAddress->country,
                ] : null,
            ],
        ]);
    }

    /**
     * Update selected shipping method in session
     */
    public function updateShipping(Request $request)
    {
        $validated = $request->validate([
            'shipping_method_id' => 'required|exists:shipping_methods,id',
        ]);

        Session::put('checkout.shipping_method_id', $validated['shipping_method_id']);

        return back();
    }
    
    /**
     * Get cart items from session
     */
    private function getCartItems(): array
    {
        $items = Session::get('cart', []);

        foreach ($items as &$item) {
            $product = Product::find($item['product_id']);
            if ($product) {
                // Use the same current price in the displayed totals, tax/shipping
                // calculations, and the order-item snapshot created at checkout.
                $item['price'] = (float) ($product->getFinalPrice() ?? $item['price'] ?? 0);
                $item['tax_class_id'] = $item['tax_class_id'] ?? $product->tax_class_id;
                $item['weight'] = $item['weight'] ?? $product->weight;
                $snapshot = CartTypeHelper::snapshotFromProduct($product);
                $item['type'] = $item['type'] ?? $snapshot['type'];
                $item['requires_shipping'] = $item['requires_shipping'] ?? $snapshot['requires_shipping'];
            }
        }

        return $items;
    }
}
