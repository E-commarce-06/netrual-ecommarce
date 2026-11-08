<?php

declare(strict_types=1);

namespace Bulbula\Support\Http\Controllers;

use App\Http\Controllers\Controller;
use Bulbula\Core\Services\SettingService;
use Bulbula\Core\Services\ThemeViewResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Response;
use Throwable;

class ContactController extends Controller
{
    public function __construct(
        protected ThemeViewResolver $themeResolver,
        protected SettingService $settings,
    ) {}

    public function index(): Response
    {
        return $this->themeResolver->inertia('Contact/Index', [
            'storeName' => $this->settings->get('store_name')
                ?: $this->settings->get('site_name')
                ?: config('brand.name', config('app.name')),
            'supportEmail' => $this->supportEmail(),
            'phone' => $this->settings->get('store_phone')
                ?: $this->settings->get('contact_phone'),
            'whatsapp' => $this->settings->get('store_whatsapp'),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'subject' => ['required', 'string', 'min:4', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $recipient = $this->supportEmail();

        if (! $recipient || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            Log::error('Contact form has no valid support recipient configured.');

            return back()->with('error', 'We cannot receive messages right now. Please try again later.');
        }

        $name = trim($data['name']);
        $email = trim($data['email']);
        $subject = trim(preg_replace('/[\r\n]+/', ' ', $data['subject']) ?? $data['subject']);
        $messageBody = trim($data['message']);
        $body = "A customer sent a message through the Contact Us form.\n\n"
            . "Name: {$name}\n"
            . "Email: {$email}\n"
            . "Subject: {$subject}\n\n"
            . "Message:\n{$messageBody}\n";

        try {
            Mail::raw($body, static function ($mail) use ($recipient, $email, $name, $subject): void {
                $mail->to($recipient)
                    ->replyTo($email, $name)
                    ->subject('[Contact form] ' . $subject);
            });
        } catch (Throwable $exception) {
            Log::warning('Contact form email could not be sent.', [
                'error' => $exception->getMessage(),
            ]);

            return back()->with('error', 'Your message could not be sent right now. Please try again shortly.');
        }

        return back()->with('success', 'Thanks for contacting us. Your message has been sent to our support team.');
    }

    private function supportEmail(): ?string
    {
        $email = $this->settings->get('support_email')
            ?: $this->settings->get('store_email')
            ?: $this->settings->get('admin_email');

        if (! is_string($email) || trim($email) === '') {
            return null;
        }

        return trim($email);
    }
}
