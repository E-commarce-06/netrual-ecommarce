<?php

declare(strict_types=1);

namespace Bulbula\Support\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Bulbula\Support\Models\SupportTicket;
use Bulbula\Support\Models\SupportTicketMessage;
use Bulbula\Support\Services\SupportTicketNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SupportTicketController extends Controller
{
    public function __construct(protected SupportTicketNotificationService $notifications) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:all,open,in_progress,awaiting_customer,resolved,closed'],
            'priority' => ['nullable', 'in:all,low,normal,high,urgent'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);
        $status = $filters['status'] ?? 'open';
        $priority = $filters['priority'] ?? 'all';
        $search = trim((string) ($filters['search'] ?? ''));

        $query = SupportTicket::query()->with(['order:id,order_number', 'assignedAdmin:id,name']);
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        if ($priority !== 'all') {
            $query->where('priority', $priority);
        }
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('ticket_number', 'like', '%' . $search . '%')
                    ->orWhere('subject', 'like', '%' . $search . '%')
                    ->orWhere('customer_name', 'like', '%' . $search . '%')
                    ->orWhere('customer_email', 'like', '%' . $search . '%')
                    ->orWhereHas('order', fn ($order) => $order->where('order_number', 'like', '%' . $search . '%'));
            });
        }

        $tickets = $query->orderByRaw("CASE WHEN status = 'open' THEN 0 WHEN status = 'in_progress' THEN 1 WHEN status = 'awaiting_customer' THEN 2 ELSE 3 END")
            ->orderByDesc('last_message_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (SupportTicket $ticket): array => $this->summary($ticket));

        return Inertia::render('Admin/Support/Index', [
            'tickets' => $tickets,
            'filters' => compact('status', 'priority', 'search'),
            'counts' => [
                'open' => SupportTicket::where('status', 'open')->count(),
                'in_progress' => SupportTicket::where('status', 'in_progress')->count(),
                'awaiting_customer' => SupportTicket::where('status', 'awaiting_customer')->count(),
                'resolved' => SupportTicket::where('status', 'resolved')->count(),
            ],
        ]);
    }

    public function show(SupportTicket $ticket): Response
    {
        $ticket->load([
            'messages' => fn ($query) => $query->where('is_internal', false),
            'order' => fn ($query) => $query->withTrashed()->with('user:id,name,email'),
            'assignedAdmin:id,name',
        ]);

        return Inertia::render('Admin/Support/Show', [
            'ticket' => array_merge($this->summary($ticket), [
                'customer_name' => $ticket->customer_name,
                'customer_email' => $ticket->customer_email,
                'category' => $ticket->category,
                'created_at' => $ticket->created_at?->format('M j, Y · g:i A'),
                'assigned_admin' => $ticket->assignedAdmin?->name,
                'order' => $ticket->order ? [
                    'id' => $ticket->order->id,
                    'order_number' => $ticket->order->order_number,
                    'status' => $ticket->order->status,
                    'total' => $ticket->order->total,
                    'customer_email' => $ticket->order->customer_email,
                ] : null,
                'messages' => $ticket->messages->map(fn (SupportTicketMessage $message): array => [
                    'id' => $message->id,
                    'author_type' => $message->author_type,
                    'author_name' => $message->author_name,
                    'body' => $message->body,
                    'created_at' => $message->created_at?->format('M j, Y · g:i A'),
                ])->values(),
            ]),
            'statuses' => SupportTicket::STATUSES,
            'priorities' => SupportTicket::PRIORITIES,
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'min:2', 'max:5000']]);
        $admin = Auth::guard('admin')->user();

        DB::transaction(function () use ($ticket, $admin, $data): void {
            $ticket->messages()->create([
                'author_type' => 'admin',
                'author_id' => $admin?->id,
                'author_name' => $admin?->name ?: 'Support team',
                'body' => trim($data['message']),
            ]);

            $ticket->forceFill([
                'assigned_admin_id' => $admin?->id,
                'status' => 'awaiting_customer',
                'resolved_at' => null,
                'closed_at' => null,
                'last_message_at' => now(),
            ])->save();
        });

        $this->notifications->staffReplied($ticket->fresh());

        return back()->with('success', 'Your reply has been sent to the customer.');
    }

    public function update(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:' . implode(',', SupportTicket::STATUSES)],
            'priority' => ['required', 'in:' . implode(',', SupportTicket::PRIORITIES)],
        ]);

        $ticket->forceFill([
            'status' => $data['status'],
            'priority' => $data['priority'],
            'resolved_at' => $data['status'] === 'resolved' ? ($ticket->resolved_at ?: now()) : null,
            'closed_at' => $data['status'] === 'closed' ? ($ticket->closed_at ?: now()) : null,
        ])->save();

        return back()->with('success', 'Ticket status and priority updated.');
    }

    private function summary(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'customer_name' => $ticket->customer_name,
            'customer_email' => $ticket->customer_email,
            'category' => $ticket->category,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'order_number' => $ticket->order?->order_number,
            'assigned_admin' => $ticket->assignedAdmin?->name,
            'last_message_at' => $ticket->last_message_at?->format('M j, Y · g:i A'),
            'created_at' => $ticket->created_at?->format('M j, Y · g:i A'),
        ];
    }
}
