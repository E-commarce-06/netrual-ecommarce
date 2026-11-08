<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import Pagination from '@/components/Admin/Pagination.vue';
import { ref } from 'vue';
import { LifeBuoy, Search, ArrowRight, MessageSquareText, Clock3, CheckCircle2, CircleDot } from 'lucide-vue-next';

interface Ticket { id: number; ticket_number: string; subject: string; customer_name: string; customer_email: string; category: string; status: string; priority: string; order_number?: string | null; assigned_admin?: string | null; last_message_at: string }
interface PageData { data: Ticket[]; current_page: number; last_page: number; from: number | null; to: number | null; total: number; per_page: number; links?: Array<{ url: string | null; label: string; active: boolean }> }
const props = defineProps<{ tickets: PageData; filters: { status: string; priority: string; search: string }; counts: { open: number; in_progress: number; awaiting_customer: number; resolved: number } }>();
const search = ref(props.filters.search || '');
const status = ref(props.filters.status || 'open');
const priority = ref(props.filters.priority || 'all');
function applyFilters() { router.get('/admin/support', { status: status.value, priority: priority.value, search: search.value || undefined }, { preserveState: true, replace: true }); }
function statusName(value: string) { return ({ open: 'Open', in_progress: 'In progress', awaiting_customer: 'Waiting customer', resolved: 'Resolved', closed: 'Closed' } as Record<string, string>)[value] || value; }
const statusClasses: Record<string, string> = { open: 'bg-blue-50 text-blue-700', in_progress: 'bg-amber-50 text-amber-800', awaiting_customer: 'bg-violet-50 text-violet-800', resolved: 'bg-emerald-50 text-emerald-800', closed: 'bg-slate-100 text-slate-600' };
const priorityClasses: Record<string, string> = { low: 'text-slate-500', normal: 'text-slate-600', high: 'text-orange-700', urgent: 'text-red-700 font-bold' };
</script>

<template>
  <Head title="Support Inbox" />
  <AdminLayout>
    <div class="min-h-screen bg-slate-50 px-4 py-6 sm:px-6 lg:px-8">
      <div class="mx-auto max-w-7xl">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><div class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wider text-orange-700"><LifeBuoy class="h-4 w-4" /> Customer care</div><h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900">Support inbox</h1><p class="mt-2 text-sm text-slate-600">Review customer requests, respond, and keep every resolution in one place.</p></div><div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 shadow-sm"><span class="font-semibold text-slate-900">{{ counts.open + counts.in_progress }}</span> need attention</div></div>

        <section class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
          <button type="button" @click="status = 'open'; applyFilters()" class="rounded-xl border border-slate-200 bg-white p-4 text-left shadow-sm hover:border-blue-300"><span class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500"><CircleDot class="h-4 w-4 text-blue-600" /> Open</span><span class="mt-2 block text-2xl font-bold text-slate-900">{{ counts.open }}</span></button>
          <button type="button" @click="status = 'in_progress'; applyFilters()" class="rounded-xl border border-slate-200 bg-white p-4 text-left shadow-sm hover:border-amber-300"><span class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500"><Clock3 class="h-4 w-4 text-amber-600" /> In progress</span><span class="mt-2 block text-2xl font-bold text-slate-900">{{ counts.in_progress }}</span></button>
          <button type="button" @click="status = 'awaiting_customer'; applyFilters()" class="rounded-xl border border-slate-200 bg-white p-4 text-left shadow-sm hover:border-violet-300"><span class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500"><MessageSquareText class="h-4 w-4 text-violet-600" /> Waiting customer</span><span class="mt-2 block text-2xl font-bold text-slate-900">{{ counts.awaiting_customer }}</span></button>
          <button type="button" @click="status = 'resolved'; applyFilters()" class="rounded-xl border border-slate-200 bg-white p-4 text-left shadow-sm hover:border-emerald-300"><span class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500"><CheckCircle2 class="h-4 w-4 text-emerald-600" /> Resolved</span><span class="mt-2 block text-2xl font-bold text-slate-900">{{ counts.resolved }}</span></button>
        </section>

        <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
          <form @submit.prevent="applyFilters" class="grid gap-3 border-b border-slate-200 bg-slate-50 p-4 sm:grid-cols-[minmax(0,1fr)_190px_160px_auto] sm:items-center">
            <label class="relative"><Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" /><input v-model="search" type="search" placeholder="Search ticket, customer or order" aria-label="Search support tickets" class="w-full rounded-lg border border-slate-300 bg-white py-2.5 pl-9 pr-3 text-sm focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-100" /></label>
            <select v-model="status" aria-label="Filter by status" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="all">All statuses</option><option value="open">Open</option><option value="in_progress">In progress</option><option value="awaiting_customer">Waiting customer</option><option value="resolved">Resolved</option><option value="closed">Closed</option></select>
            <select v-model="priority" aria-label="Filter by priority" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="all">All priorities</option><option value="urgent">Urgent</option><option value="high">High</option><option value="normal">Normal</option><option value="low">Low</option></select>
            <button type="submit" class="rounded-lg bg-[#071a35] px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Apply</button>
          </form>

          <div v-if="tickets.data.length" class="divide-y divide-slate-100">
            <Link v-for="ticket in tickets.data" :key="ticket.id" :href="`/admin/support/${ticket.id}`" class="grid gap-3 px-4 py-5 transition hover:bg-slate-50 sm:grid-cols-[minmax(0,1fr)_150px_115px_165px] sm:items-center sm:gap-5 sm:px-6">
              <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><span class="text-xs font-bold text-slate-500">{{ ticket.ticket_number }}</span><span v-if="ticket.order_number" class="text-xs text-slate-400">Order {{ ticket.order_number }}</span></div><h2 class="mt-1 truncate font-semibold text-slate-900">{{ ticket.subject }}</h2><p class="mt-1 truncate text-sm text-slate-600">{{ ticket.customer_name }} · {{ ticket.customer_email }}</p><p class="mt-1 text-xs capitalize text-slate-500">{{ ticket.category.replace('_', ' ') }}<span v-if="ticket.assigned_admin"> · Assigned to {{ ticket.assigned_admin }}</span></p></div>
              <div><span :class="statusClasses[ticket.status] || statusClasses.closed" class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold">{{ statusName(ticket.status) }}</span></div>
              <div :class="priorityClasses[ticket.priority] || 'text-slate-600'" class="text-sm capitalize">{{ ticket.priority }} priority</div>
              <div class="flex items-center justify-between text-sm text-slate-500"><span>{{ ticket.last_message_at }}</span><ArrowRight class="h-4 w-4" /></div>
            </Link>
          </div>
          <div v-else class="px-6 py-16 text-center"><span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-500"><MessageSquareText class="h-6 w-6" /></span><h2 class="mt-4 font-semibold text-slate-900">No requests match these filters</h2><p class="mt-1 text-sm text-slate-500">Try another status or search term.</p></div>
          <Pagination :data="tickets" resource-name="tickets" />
        </section>
      </div>
    </div>
  </AdminLayout>
</template>
