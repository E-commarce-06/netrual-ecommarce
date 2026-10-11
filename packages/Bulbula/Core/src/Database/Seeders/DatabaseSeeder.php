<?php

declare(strict_types=1);

namespace Bulbula\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Bulbula\Admin\Database\Seeders\AdminUserSeeder;
use Bulbula\Admin\Database\Seeders\AdminMenuSeeder;
use Bulbula\Shop\Database\Seeders\ThemeSeeder;
use Bulbula\Core\Database\Seeders\PaymentMethodsTableSeeder;
use Bulbula\Core\Database\Seeders\ShippingMethodSeeder;
use Bulbula\Core\Database\Seeders\TaxSeeder;
use Bulbula\Core\Database\Seeders\EmailSeeder;
use Bulbula\Customer\Database\Seeders\CustomerMenuSeeder;
use Bulbula\Customer\Database\Seeders\CustomerGroupSeeder;
use Bulbula\CMS\Database\Seeders\BlockSeeder;
use Bulbula\CMS\Database\Seeders\PageSeeder;
use Bulbula\CMS\Database\Seeders\StorefrontMenuSeeder;
use Bulbula\Marketing\Database\Seeders\MarketingMenuSeeder;
use Bulbula\Marketing\Database\Seeders\SampleMarketingSeeder;
use Bulbula\Reports\Database\Seeders\ReportsMenuSeeder;
use Bulbula\Sales\Database\Seeders\CreditMemoEmailTemplatesSeeder;
use Bulbula\Sales\Database\Seeders\ShipmentEmailTemplatesSeeder;
use Bulbula\Settings\Database\Seeders\AiSettingsSeeder;
use Bulbula\System\Database\Seeders\SystemSettingsSeeder;
use Bulbula\UIEditor\Database\Seeders\GlobalRegionSeeder;
use Bulbula\Blog\Database\Seeders\BlogSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // Admin Package Seeders
            AdminUserSeeder::class,
            AdminMenuSeeder::class,

            // Core Package Seeders
            CountrySeeder::class,
            CurrencySeeder::class,
            PaymentMethodsTableSeeder::class,
            ShippingMethodSeeder::class,
            TaxSeeder::class,
            EmailSeeder::class,
            AiSettingsSeeder::class,
            SystemSettingsSeeder::class,

            // Customer Package Seeders
            CustomerMenuSeeder::class,
            CustomerGroupSeeder::class,

            // Product Package Seeders

            // CMS Package Seeders
            BlockSeeder::class,
            PageSeeder::class,
            StorefrontMenuSeeder::class,

            // Marketing Package Seeders
            MarketingMenuSeeder::class,
            SampleMarketingSeeder::class,

            // Sales Package Seeders
            CreditMemoEmailTemplatesSeeder::class,
            ShipmentEmailTemplatesSeeder::class,

            // Reports Package Seeders
            ReportsMenuSeeder::class,

            // Shop Package Seeders
            ThemeSeeder::class,

            // UIEditor Package Seeders
            GlobalRegionSeeder::class,
            // Note: UIEditorPageSeeder is called by ThemeSeeder (theme-specific CMS page layouts)

            // Blog Package Seeders
            BlogSeeder::class,
        ]);
    }
}
