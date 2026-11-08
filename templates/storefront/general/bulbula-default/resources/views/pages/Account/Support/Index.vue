<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ThemeLayout from '../../../layouts/ThemeLayout.vue';
import Pagination from '@/components/Admin/Pagination.vue';
import { LifeBuoy, Plus, MessageSquareText, ArrowRight, Search } from 'lucide-vue-next';

interface Ticket { id: number; ticket_number: string; subject: string; category: string; status: string; priority: string; order_number?: string | null; messages_count: number; updated_at: string }
defineProps<{ tickets: { data: Ticket[]; current_page: number; last_page: number; from: number | null; to: number | null; total: number; per_page: number; links?: Array<{ url: string | null; label: string; active: boolean }> } }>();
const statusClasses: Record<string, string> = { open: 'bg-blue-50 text-blue-700 ring-blue-600/20', in_progress: 'bg-amber-50 text-amber-700 ring-amber-600/20', awaiting_customer: 'bg-violet-50 text-violet-700 ring-violet-600/20', resolved: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', closed: 'bg-slate-100 text-slate-600 ring-slate-500/20' };
const statusLabel = (status: string) => ({ open: 'Open', in_progress: 'In progress', awaiting_customer: 'Reply received', resolved: 'Resolved', closed: 'Closed' } as Record<string, string>)[status] || status;
</script>

<template>
  <ThemeLayout>
    <Head title="My Support Requests" />
    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:py-12">
      <div class="mx-auto max-w-5xl">
        <Link href="/help" class="text-sm font-medium text-slate-600 hover:text-slate-900">← Help Center</Link>
        <div class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <div><div class="flex items-center gap-3"><span class="flex h-11 w-11 items-center justify-center rounded-xl bg-orange-100 text-orange-700"><LifeBuoy class="h-5 w-5" /></span><h1 class="text-3xl font-bold tracking-tight text-slate-900">My support requests</h1></div><p class="mt-3 text-slate-600">Review your conversations and follow up with our team.</p></div>
          <Link href="/account/support/create" class="inline-flex items-center justify-center gap-2 rounded-lg bg-orange-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-orange-700"><Plus class="h-4 w-4" /> New request</Link>
        </div>

        <section v-if="tickets.data.length" class="mt-8 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
          <div class="hidden grid-cols-[minmax(0,1fr)_150px_170px] gap-4 border-b border-slate-200 bg-slate-50 px-6 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500 sm:grid"><span>Request</span><span>Status</span><span>Last update</span></div>
          <Link v-for="ticket in tickets.data" :key="ticket.id" :href="`/account/support/${ticket.id}`" class="grid gap-3 border-b border-slate-100 px-5 py-5 transition hover:bg-slate-50 last:border-b-0 sm:grid-cols-[minmax(0,1fr)_150px_170px] sm:items-center sm:gap-4 sm:px-6">
            <div class="min-w-0"><div class="flex flex-wrap items-center gap-x-2 gap-y-1"><span class="text-xs font-semibold text-slate-500">{{ ticket.ticket_number }}</span><span v-if="ticket.order_number" class="text-xs text-slate-400">Order {{ ticket.order_number }}</span></div><h2 class="mt-1 truncate font-semibold text-slate-900">{{ ticket.subject }}</h2><p class="mt-1 text-sm text-slate-500">{{ ticket.category }} <span aria-hidden="true">·</span> {{ ticket.messages_count }} {{ ticket.messages_count === 1 ? 'message' : 'messages' }}</p></div>
            <div><span :class="statusClasses[ticket.status] || statusClasses.closed" class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset">{{ statusLabel(ticket.status) }}</span></div>
            <div class="flex items-center justify-between text-sm text-slate-500"><span>{{ ticket.updated_at }}</span><ArrowRight class="h-4 w-4 text-slate-400" /></div>
          </Link>
          <Pagination :data="tickets" resource-name="requests" />
        </section>

        <section v-else class="mt-8 rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
          <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-500"><MessageSquareText class="h-7 w-7" /></span><h2 class="mt-4 text-lg font-semibold text-slate-900">No support requests yet</h2><p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-600">If you need help with an order, payment or account, send us a message and we’ll keep the conversation here.</p><Link href="/account/support/create" class="mt-5 inline-flex items-center gap-2 rounded-lg bg-[#071a35] px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800"><Plus class="h-4 w-4" /> Contact support</Link><Link href="/help" class="mt-4 flex items-center justify-center gap-1 text-sm font-medium text-slate-600 hover:text-slate-900"><Search class="h-4 w-4" /> Browse help articles</Link>
        </section>
      </div>
    </main>
  </ThemeLayout>
</template>
