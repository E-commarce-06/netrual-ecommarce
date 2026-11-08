<?php

declare(strict_types=1);

namespace Bulbula\Support\Http\Controllers;

use App\Http\Controllers\Controller;
use Bulbula\Core\Services\ThemeViewResolver;
use Bulbula\Shop\Models\Order;
use Bulbula\Support\Models\SupportTicket;
use Bulbula\Support\Models\SupportTicketMessage;
use Bulbula\Support\Services\SupportTicketNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Response;

class SupportTicketController extends Controller
{
    private const CATEGORIES = [
        'orders' => 'Orders & tracking',
        'shipping' => 'Shipping & delivery',
        'returns' => 'Returns & refunds',
        'payments' => 'Payments & billing',
        'account' => 'Account & security',
        'products' => 'Product question',
        'other' => 'Something else',
    ];

    public function __construct(
        protected ThemeViewResolver $themeResolver,
        protected SupportTicketNotificationService $notifications,
    ) {}

    public function index(Request $request): Response
    {
        $tickets = SupportTicket::query()
            ->where('user_id', $request->user()->id)
            ->with('order:id,order_number')
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (SupportTicket $ticket): array => $this->ticketSummary($ticket));

        return $this->themeResolver->inertia('Account/Support/Index', ['tickets' => $tickets]);
    }

    public function create(Request $request): Response
    {
        return $this->themeResolver->inertia('Account/Support/Create', [
            'categories' => self::CATEGORIES,
            'orders' => $this->customerOrders($request)->get(['id', 'order_number', 'status']),
            'selectedOrder' => $request->integer('order') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', 'in:' . implode(',', array_keys(self::CATEGORIES))],
            'subject' => ['required', 'string', 'min:4', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'order_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $order = null;
        if (! empty($data['order_id'])) {
            $order = $this->customerOrders($request)->whereKey($data['order_id'])->first();
            if (! $order) {
                throw ValidationException::withMessages(['order_id' => 'Choose one of your own orders, or leave this blank.']);
            }
        }

        $ticket = DB::transaction(function () use ($data, $user, $order): SupportTicket {
            $ticket = SupportTicket::create([
                'ticket_number' => 'HC-' . now()->format('ymd') . '-' . Str::upper(Str::random(7)),
                'user_id' => $user->id,
                'customer_name' => $user->name,
                'customer_email' => $user->email,
                'order_id' => $order?->id,
                'category' => $data['category'],
                'subject' => trim($data['subject']),
                'status' => 'open',
                'priority' => 'normal',
                'last_message_at' => now(),
            ]);

            $ticket->messages()->create([
                'author_type' => 'customer',
                'author_id' => $user->id,
                'author_name' => $user->name,
                'body' => trim($data['message']),
            ]);

            return $ticket;
        });

        $this->notifications->customerCreated($ticket);
        $this->notifications->staffNewTicket($ticket);

        return redirect()->route('shop.account.support.show', $ticket->id)
            ->with('success', 'Your request has been sent. Keep the ticket number for reference.');
    }

    public function show(Request $request, SupportTicket $ticket): Response
    {
        $ticket = $this->ownedTicket($request, $ticket->id)
            ->with(['messages' => fn ($query) => $query->where('is_internal', false), 'order:id,order_number,status'])
            ->firstOrFail();

        return $this->themeResolver->inertia('Account/Support/Show', [
            'ticket' => $this->ticketDetails($ticket),
            'canReply' => $ticket->status !== 'closed',
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:5000'],
        ]);

        $ticket = $this->ownedTicket($request, $ticket->id)->firstOrFail();
        if ($ticket->status === 'closed') {
            throw ValidationException::withMessages(['message' => 'This request is closed. Please open a new support request if you still need help.']);
        }
        $user = $request->user();

        DB::transaction(function () use ($ticket, $user, $data): void {
            $ticket->messages()->create([
                'author_type' => 'customer',
                'author_id' => $user->id,
                'author_name' => $user->name,
                'body' => trim($data['message']),
            ]);
            $ticket->forceFill([
                'status' => 'open',
                'resolved_at' => null,
                'closed_at' => null,
                'last_message_at' => now(),
            ])->save();
        });

        $this->notifications->customerReplied($ticket->fresh());

        return back()->with('success', 'Your reply has been added to the conversation.');
    }

    private function customerOrders(Request $request): Builder
    {
        return Order::query()
            ->where(function (Builder $query) use ($request): void {
                $query->where('user_id', $request->user()->id)
                    ->orWhere('customer_email', $request->user()->email);
            })
            ->latest('created_at')
            ->limit(50);
    }

    private function ownedTicket(Request $request, int $id): Builder
    {
        return SupportTicket::query()
            ->whereKey($id)
            ->where('user_id', $request->user()->id);
    }

    private function ticketSummary(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'category' => self::CATEGORIES[$ticket->category] ?? 'Other',
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'order_number' => $ticket->order?->order_number,
            'messages_count' => $ticket->messages_count ?? 0,
            'updated_at' => $ticket->last_message_at?->format('M j, Y · g:i A'),
        ];
    }

    private function ticketDetails(SupportTicket $ticket): array
    {
        return array_merge($this->ticketSummary($ticket), [
            'order_status' => $ticket->order?->status,
            'created_at' => $ticket->created_at?->format('M j, Y · g:i A'),
            'messages' => $ticket->messages->map(fn (SupportTicketMessage $message): array => [
                'id' => $message->id,
                'author_type' => $message->author_type,
                'author_name' => $message->author_name,
                'body' => $message->body,
                'created_at' => $message->created_at?->format('M j, Y · g:i A'),
            ])->values(),
        ]);
    }
}
