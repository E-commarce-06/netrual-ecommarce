<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        $now = now();
        DB::table('email_templates')->insertOrIgnore([
            [
                'code' => 'manual_transfer_approved',
                'name' => 'Manual Transfer Approved',
                'description' => 'Sent when staff verify a manual bank or wallet transfer.',
                'category' => 'payment',
                'subject' => 'Transfer verified for order #{order_number}',
                'from_name' => '{store_name}',
                'from_email' => null,
                'reply_to' => null,
                'cc' => null,
                'bcc' => null,
                'html_content' => <<<'HTML'
<!doctype html>
<html><body style="font-family:Arial,sans-serif;line-height:1.6;color:#222">
  <div style="max-width:600px;margin:0 auto;padding:24px">
    <h2 style="color:#15803d">Your transfer is verified</h2>
    <p>Hi {customer_name},</p>
    <p>Our team verified the transfer for order <strong>#{order_number}</strong>. Your payment is confirmed and the order has moved into processing.</p>
    <p>Amount: <strong>{payment_amount}</strong></p>
    <p>We will send another update when your order ships.</p>
    <p>{store_name}</p>
  </div>
</body></html>
HTML,
                'plain_text_content' => <<<'TEXT'
Your transfer is verified

Hi {customer_name},

Our team verified the transfer for order #{order_number}. Your payment is confirmed and the order has moved into processing.

Amount: {payment_amount}

We will send another update when your order ships.

{store_name}
TEXT,
                'variables' => json_encode([
                    'customer_name',
                    'order_number',
                    'payment_amount',
                    'store_name',
                ], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'is_system' => true,
                'locale' => 'en',
                'times_sent' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'manual_transfer_rejected',
                'name' => 'Manual Transfer Needs Attention',
                'description' => 'Sent when staff reject a manual transfer proof and explain what needs to be corrected.',
                'category' => 'payment',
                'subject' => 'Action needed for payment on order #{order_number}',
                'from_name' => '{store_name}',
                'from_email' => null,
                'reply_to' => null,
                'cc' => null,
                'bcc' => null,
                'html_content' => <<<'HTML'
<!doctype html>
<html><body style="font-family:Arial,sans-serif;line-height:1.6;color:#222">
  <div style="max-width:600px;margin:0 auto;padding:24px">
    <h2 style="color:#b91c1c">We could not approve this transfer proof</h2>
    <p>Hi {customer_name},</p>
    <p>Our team reviewed the proof for order <strong>#{order_number}</strong>, but could not confirm the transfer. The order is still unpaid.</p>
    <p><strong>Review reason:</strong></p>
    <div style="white-space:pre-wrap;border-left:3px solid #ef4444;padding:8px 12px;background:#fef2f2">{payment_reason_html}</div>
    <p>Please review the reason and contact us if you need help. Do not make another transfer until you have confirmed what needs to be corrected.</p>
    <p>Order total: <strong>{payment_amount}</strong></p>
    <p>{store_name}</p>
  </div>
</body></html>
HTML,
                'plain_text_content' => <<<'TEXT'
We could not approve this transfer proof

Hi {customer_name},

Our team reviewed the proof for order #{order_number}, but could not confirm the transfer. The order is still unpaid.

Review reason: {payment_reason}

Please review the reason and contact us if you need help. Do not make another transfer until you have confirmed what needs to be corrected.

Order total: {payment_amount}

{store_name}
TEXT,
                'variables' => json_encode([
                    'customer_name',
                    'order_number',
                    'payment_amount',
                    'payment_reason_html',
                    'payment_reason',
                    'store_name',
                ], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'is_system' => true,
                'locale' => 'en',
                'times_sent' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        // Keep these merchant-editable email templates on rollback rather than
        // deleting any edits an administrator may have made.
    }
};
