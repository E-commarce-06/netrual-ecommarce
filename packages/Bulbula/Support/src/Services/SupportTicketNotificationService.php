<?php

declare(strict_types=1);

namespace Bulbula\Support\Services;

use Bulbula\Core\Services\SettingService;
use Bulbula\Support\Models\SupportTicket;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SupportTicketNotificationService
{
    public function customerCreated(SupportTicket $ticket): void
    {
        $url = route('shop.account.support.show', $ticket->id);
        $this->send(
            $ticket->customer_email,
            "We received your request {$ticket->ticket_number}",
            "Hello {$ticket->customer_name},\n\nYour support request has been received. Our team will review it and reply as soon as possible.\n\nTicket: {$ticket->ticket_number}\nSubject: {$ticket->subject}\nView your request: {$url}\n\nPlease reply from your support inbox so your conversation stays together."
        );
    }

    public function staffNewTicket(SupportTicket $ticket): void
    {
        $email = app(SettingService::class)->get('support_email')
            ?: app(SettingService::class)->get('admin_email');

        if (! is_string($email) || trim($email) === '') {
            return;
        }

        $url = route('admin.support.show', $ticket->id);
        $this->send(
            $email,
            "New support request {$ticket->ticket_number}",
            "A customer has opened a support request.\n\nTicket: {$ticket->ticket_number}\nCustomer: {$ticket->customer_name} <{$ticket->customer_email}>\nSubject: {$ticket->subject}\nCategory: {$ticket->category}\n\nOpen the support inbox: {$url}"
        );
    }

    public function customerReplied(SupportTicket $ticket): void
    {
        $email = app(SettingService::class)->get('support_email')
            ?: app(SettingService::class)->get('admin_email');

        if (! is_string($email) || trim($email) === '') {
            return;
        }

        $this->send(
            $email,
            "Customer replied to {$ticket->ticket_number}",
            "{$ticket->customer_name} replied to support request {$ticket->ticket_number}.\n\nSubject: {$ticket->subject}\nOpen the conversation: " . route('admin.support.show', $ticket->id)
        );
    }

    public function staffReplied(SupportTicket $ticket): void
    {
        $this->send(
            $ticket->customer_email,
            "An update to support request {$ticket->ticket_number}",
            "Hello {$ticket->customer_name},\n\nOur team has replied to your support request.\n\nTicket: {$ticket->ticket_number}\nSubject: {$ticket->subject}\nRead and reply: " . route('shop.account.support.show', $ticket->id)
        );
    }

    private function send(string $to, string $subject, string $body): void
    {
        try {
            Mail::raw($body, static function ($message) use ($to, $subject): void {
                $message->to($to)->subject($subject);
            });
        } catch (Throwable $exception) {
            // A mail transport outage must not prevent a customer from saving a ticket or reply.
            Log::warning('Support ticket email could not be sent.', [
                'recipient' => $to,
                'subject' => $subject,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
