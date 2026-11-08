<?php

use App\Models\User;
use Bulbula\Shop\Models\Order;
use Bulbula\Support\Models\SupportTicket;
use Bulbula\Support\Models\SupportTicketMessage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    config(['mail.default' => 'array']);
});

test('the public help center provides searchable customer topics', function () {
    $this->get('/help')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('templates/bulbula-default/pages/Help/Index')
            ->has('categories', 5)
            ->where('categories.0.id', 'orders')
            ->has('categories.0.articles', 3)
        );
});

test('a signed-in customer can create and view a support conversation', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    $response = $this->actingAs($customer)->post('/account/support', [
        'category' => 'orders',
        'subject' => 'Please check my order',
        'message' => 'Could you please confirm the latest progress on this order?',
        'order_id' => null,
    ]);

    $ticket = SupportTicket::query()->firstOrFail();
    $response->assertRedirect(route('shop.account.support.show', $ticket->id));
    expect($ticket->user_id)->toBe($customer->id)
        ->and($ticket->status)->toBe('open')
        ->and($ticket->ticket_number)->toStartWith('HC-');
    $this->assertDatabaseHas('support_ticket_messages', [
        'ticket_id' => $ticket->id,
        'author_type' => 'customer',
        'body' => 'Could you please confirm the latest progress on this order?',
    ]);

    $this->actingAs($customer)
        ->get(route('shop.account.support.show', $ticket->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('templates/bulbula-default/pages/Account/Support/Show')
            ->where('ticket.ticket_number', $ticket->ticket_number)
            ->where('canReply', true)
            ->has('ticket.messages', 1)
        );
});

test('customers can link only orders that belong to their account', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $other = User::factory()->create(['role' => 'customer']);
    $ownOrder = Order::create([
        'order_number' => 'ORD-SUPPORT-OWN-' . $customer->id,
        'user_id' => $customer->id,
        'customer_email' => $customer->email,
    ]);
    $otherOrder = Order::create([
        'order_number' => 'ORD-SUPPORT-OTHER-' . $other->id,
        'user_id' => $other->id,
        'customer_email' => $other->email,
    ]);

    $this->actingAs($customer)->post('/account/support', [
        'category' => 'orders',
        'subject' => 'Question about my order',
        'message' => 'Please check this order for me when you have a moment.',
        'order_id' => $ownOrder->id,
    ])->assertRedirect();

    expect(SupportTicket::query()->firstOrFail()->order_id)->toBe($ownOrder->id);

    $this->actingAs($customer)->from('/account/support/create')->post('/account/support', [
        'category' => 'orders',
        'subject' => 'Question about another order',
        'message' => 'I should not be allowed to attach another customer’s order.',
        'order_id' => $otherOrder->id,
    ])->assertSessionHasErrors('order_id');

    expect(SupportTicket::count())->toBe(1);
});

test('customers cannot view another account’s ticket', function () {
    $owner = User::factory()->create(['role' => 'customer']);
    $other = User::factory()->create(['role' => 'customer']);
    $ticket = SupportTicket::create([
        'ticket_number' => 'HC-TEST-OWNERSHIP',
        'user_id' => $owner->id,
        'customer_name' => $owner->name,
        'customer_email' => $owner->email,
        'category' => 'orders',
        'subject' => 'Private support request',
        'status' => 'open',
        'priority' => 'normal',
        'last_message_at' => now(),
    ]);

    $this->actingAs($other)
        ->get(route('shop.account.support.show', $ticket->id))
        ->assertNotFound();
});

test('admins can reply to tickets and change status and priority', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $ticket = SupportTicket::create([
        'ticket_number' => 'HC-TEST-ADMIN',
        'user_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'category' => 'returns',
        'subject' => 'Question about a return',
        'status' => 'open',
        'priority' => 'normal',
        'last_message_at' => now(),
    ]);
    SupportTicketMessage::create([
        'ticket_id' => $ticket->id,
        'author_type' => 'customer',
        'author_id' => $customer->id,
        'author_name' => $customer->name,
        'body' => 'I would like to ask about the return process.',
    ]);

    $this->actingAs($admin, 'admin')
        ->post(route('admin.support.reply', $ticket->id), ['message' => 'Please share your order number and our team will review it.'])
        ->assertRedirect();

    expect($ticket->fresh()->status)->toBe('awaiting_customer');
    $this->assertDatabaseHas('support_ticket_messages', [
        'ticket_id' => $ticket->id,
        'author_type' => 'admin',
        'author_id' => $admin->id,
    ]);

    $this->actingAs($admin, 'admin')
        ->put(route('admin.support.update', $ticket->id), ['status' => 'resolved', 'priority' => 'high'])
        ->assertRedirect();

    expect($ticket->fresh()->status)->toBe('resolved')
        ->and($ticket->fresh()->priority)->toBe('high')
        ->and($ticket->fresh()->resolved_at)->not->toBeNull();
});
