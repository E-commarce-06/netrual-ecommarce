<?php

declare(strict_types=1);

namespace Bulbula\Support\Http\Controllers;

use App\Http\Controllers\Controller;
use Bulbula\Core\Services\ThemeViewResolver;
use Bulbula\Core\Services\SettingService;
use Inertia\Response;

class HelpCenterController extends Controller
{
    public function __construct(
        protected ThemeViewResolver $themeResolver,
        protected SettingService $settings,
    ) {}

    public function index(): Response
    {
        return $this->themeResolver->inertia('Help/Index', [
            'supportEmail' => $this->settings->get('support_email') ?: $this->settings->get('admin_email'),
            'categories' => [
                [
                    'id' => 'orders',
                    'title' => 'Orders & tracking',
                    'description' => 'Order status, changes, cancellations and delivery progress.',
                    'icon' => 'package',
                    'articles' => [
                        ['question' => 'Where can I see my order status?', 'answer' => 'Sign in and open My Orders to see the latest status and order details. If you checked out as a guest, use Track an Order and the email address used at checkout.', 'keywords' => 'status order track order tracking placed processing shipped delivered'],
                        ['question' => 'How do I track a shipment?', 'answer' => 'Open the order details in My Orders. When tracking information is available, it will appear with the shipment details. Guest customers can use the Track an Order link.', 'keywords' => 'tracking shipment carrier delivery'],
                        ['question' => 'Can I change or cancel an order?', 'answer' => 'Order changes depend on how far the order has progressed. Open the order and contact support as soon as possible; include the order so the team can check what is still possible.', 'keywords' => 'change edit cancel order address'],
                    ],
                ],
                [
                    'id' => 'shipping',
                    'title' => 'Shipping & delivery',
                    'description' => 'Delivery updates, missing parcels and address questions.',
                    'icon' => 'truck',
                    'articles' => [
                        ['question' => 'Why has my tracking not updated?', 'answer' => 'Tracking can take time to update after a parcel is handed to the carrier. Check your order again later; if the expected delivery window has passed, contact support with your order number.', 'keywords' => 'tracking update carrier parcel delay'],
                        ['question' => 'My order has not arrived. What should I do?', 'answer' => 'Check the latest order and carrier updates first. If the parcel is marked delivered but you cannot find it, or the delivery estimate has passed, open a support request and include the order.', 'keywords' => 'late missing delivered parcel lost'],
                        ['question' => 'Can I update my delivery address?', 'answer' => 'Address changes may only be possible before dispatch. Contact support immediately with the correct address and order number. Never send payment-card details in a support message.', 'keywords' => 'address change delivery shipping'],
                    ],
                ],
                [
                    'id' => 'returns',
                    'title' => 'Returns & refunds',
                    'description' => 'Ask about return eligibility, refunds and order problems.',
                    'icon' => 'refresh-cw',
                    'articles' => [
                        ['question' => 'How do I request a return?', 'answer' => 'Return eligibility and timelines are governed by the store’s published return policy and local consumer rules. Open a support request, choose Returns & refunds, and include the order and a short description of the issue.', 'keywords' => 'return refund exchange eligibility damaged'],
                        ['question' => 'When will my refund arrive?', 'answer' => 'After a refund is approved and processed, the time for funds to appear depends on your payment provider. Contact support with your order number if you need an update.', 'keywords' => 'refund status money payment'],
                        ['question' => 'My item arrived damaged or incorrect.', 'answer' => 'Please open a support request as soon as possible, select Returns & refunds, and include the order number and a clear description. The team will advise on the next steps.', 'keywords' => 'damaged wrong item defective return'],
                    ],
                ],
                [
                    'id' => 'payments',
                    'title' => 'Payments & billing',
                    'description' => 'Payment methods, failed payments and receipts.',
                    'icon' => 'credit-card',
                    'articles' => [
                        ['question' => 'Which payment methods can I use?', 'answer' => 'Available payment methods are shown securely during checkout and can vary by store and location. Do not send card numbers, passwords or one-time codes to support.', 'keywords' => 'payment methods card bank wallet checkout'],
                        ['question' => 'My payment failed, but I see a charge.', 'answer' => 'Some banks show a temporary authorization while a payment is being confirmed. Check your order and payment status before trying again. If the status is unclear, contact support with the order number and the time of the attempt—never include full card details.', 'keywords' => 'failed charged pending payment duplicate'],
                        ['question' => 'Where can I get an invoice or receipt?', 'answer' => 'Open your order details in My Orders to view available billing documents. If you cannot access a document, send support the order number.', 'keywords' => 'invoice receipt billing tax'],
                    ],
                ],
                [
                    'id' => 'account',
                    'title' => 'Account & security',
                    'description' => 'Sign-in help, profile details and keeping your account safe.',
                    'icon' => 'user',
                    'articles' => [
                        ['question' => 'I cannot sign in to my account.', 'answer' => 'Use the password reset option on the sign-in page and check that you are using the email address registered to the account. If you still need help, contact support without sharing your password or verification code.', 'keywords' => 'login sign in password reset account'],
                        ['question' => 'How do I update my profile or addresses?', 'answer' => 'Sign in and open your account profile or saved addresses. If you cannot access the account, contact support from the email address associated with it.', 'keywords' => 'profile address email account update'],
                        ['question' => 'How do I keep my account secure?', 'answer' => 'Use a unique password, sign out on shared devices and never share a password, one-time code or payment details with anyone claiming to be support.', 'keywords' => 'security password privacy account'],
                    ],
                ],
            ],
        ]);
    }
}
