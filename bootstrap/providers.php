<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
    Bulbula\Core\Providers\ThemeServiceProvider::class,
    Bulbula\Core\Providers\CoreServiceProvider::class,
    Bulbula\Core\Providers\MailConfigServiceProvider::class,
    Bulbula\Admin\Providers\AdminServiceProvider::class,
    Bulbula\Product\Providers\ProductServiceProvider::class,
    Bulbula\Cart\Providers\CartServiceProvider::class,
    Bulbula\Shop\Providers\ShopServiceProvider::class,
    Bulbula\Settings\Providers\SettingsServiceProvider::class,
    Bulbula\Stripe\Providers\StripeServiceProvider::class,
    Bulbula\Razorpay\Providers\RazorpayServiceProvider::class,
    Bulbula\PhonePe\Providers\PhonePeServiceProvider::class,
    Bulbula\Sales\Providers\SalesServiceProvider::class,
    Bulbula\Customer\Providers\CustomerServiceProvider::class,
    Bulbula\Setup\Providers\SetupServiceProvider::class,  // Must be before CMS to prevent catch-all route conflict
    Bulbula\Blog\Providers\BlogServiceProvider::class,
    Bulbula\CMS\Providers\CMSServiceProvider::class,
    Bulbula\UIEditor\Providers\UIEditorServiceProvider::class,
    Bulbula\System\Providers\SystemServiceProvider::class,
    Bulbula\Reports\ReportsServiceProvider::class,
    Bulbula\Marketing\MarketingServiceProvider::class,
    Bulbula\API\Providers\APIServiceProvider::class,
];
