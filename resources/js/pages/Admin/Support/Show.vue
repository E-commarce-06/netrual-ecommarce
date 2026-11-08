<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { computed, ref } from 'vue';
import { ArrowLeft, Send, Package, UserRound, Clock3, LifeBuoy, CheckCircle2 } from 'lucide-vue-next';

interface Message { id: number; author_type: string; author_name: string; body: string; created_at: string }
interface Ticket { id: number; ticket_number: string; subject: string; customer_name: string; customer_email: string; category: string; status: string; priority: string; order_number?: string | null; last_message_at: string; created_at: string; assigned_admin?: string | null; order?: { id: number; order_number: string; status: string; total: number; customer_email: string } | null; messages: Message[] }
const props = defineProps<{ ticket: Ticket; statuses: string[]; priorities: string[] }>();
const replyForm = useForm({ message: '' });
const status = ref(props.ticket.status);
const priority = ref(props.ticket.priority);
const statusLabels: Record<string, string> = { open: 'Open', in_progress: 'In progress', awaiting_customer: 'Waiting customer', resolved: 'Resolved', closed: 'Closed' };
const categoryLabels: Record<string, string> = { orders: 'Orders & tracking', shipping: 'Shipping & delivery', returns: 'Returns & refunds', payments: 'Payments & billing', account: 'Account & security', products: 'Product question', other: 'Something else' };
const categoryLabel = computed(() => categoryLabels[props.ticket.category] || props.ticket.category);
function saveStatus() { router.put(`/admin/support/${props.ticket.id}/status`, { status: status.value, priority: priority.value }, { preserveScroll: true }); }
function sendReply() { replyForm.post(`/admin/support/${props.ticket.id}/reply`, { preserveScroll: true, onSuccess: () => replyForm.reset() }); }
</script>

<template>
  <Head :title="`${ticket.ticket_number} · Support`" />
  <AdminLayout>
    <div class="min-h-screen bg-slate-50 px-4 py-6 sm:px-6 lg:px-8">
      <div class="mx-auto max-w-7xl">
        <Link href="/admin/support" class="inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-slate-900"><ArrowLeft class="h-4 w-4" /> Support inbox</Link>
        <div class="mt-5 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><span class="text-sm font-bold text-orange-700">{{ ticket.ticket_number }}</span><span class="rounded-full bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ statusLabels[ticket.status] || ticket.status }}</span><span class="text-xs capitalize text-slate-500">{{ ticket.priority }} priority</span></div><h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ ticket.subject }}</h1><p class="mt-2 text-sm text-slate-500">{{ categoryLabel }} · Opened {{ ticket.created_at }} · Last activity {{ ticket.last_message_at }}</p></div><div class="flex flex-wrap gap-2"><a :href="`mailto:${ticket.customer_email}`" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Email customer</a><Link v-if="ticket.order" :href="`/admin/sales/orders/${ticket.order.id}`" class="inline-flex items-center gap-2 rounded-lg bg-[#071a35] px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800"><Package class="h-4 w-4" /> View order</Link></div></div>

        <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
          <div class="space-y-5">
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
              <div class="flex items-center justify-between border-b border-slate-100 pb-4"><h2 class="font-semibold text-slate-900">Customer conversation</h2><span class="text-xs text-slate-500">{{ ticket.messages.length }} {{ ticket.messages.length === 1 ? 'message' : 'messages' }}</span></div>
              <div class="mt-5 space-y-5">
                <article v-for="message in ticket.messages" :key="message.id" class="rounded-xl border p-4 sm:p-5" :class="message.author_type === 'admin' ? 'border-blue-100 bg-blue-50/60 ml-0 sm:ml-12' : 'border-slate-200 bg-slate-50 mr-0 sm:mr-12'">
                  <header class="flex flex-wrap items-center gap-x-2 gap-y-1"><span class="text-sm font-semibold text-slate-900">{{ message.author_type === 'admin' ? message.author_name : ticket.customer_name }}</span><span :class="message.author_type === 'admin' ? 'bg-blue-100 text-blue-800' : 'bg-slate-200 text-slate-700'" class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide">{{ message.author_type === 'admin' ? 'Support' : 'Customer' }}</span><time class="ml-auto text-xs text-slate-500">{{ message.created_at }}</time></header><p class="mt-3 whitespace-pre-wrap break-words text-sm leading-6 text-slate-700">{{ message.body }}</p>
                </article>
              </div>
            </section>

            <form @submit.prevent="sendReply" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6"><label for="reply" class="block text-sm font-semibold text-slate-900">Reply to customer</label><textarea id="reply" v-model="replyForm.message" rows="6" maxlength="5000" required placeholder="Write a clear, helpful response. The customer will receive an email notification." class="mt-3 w-full resize-y rounded-lg border border-slate-300 px-3 py-2.5 text-sm leading-6 focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-100" /><p v-if="replyForm.errors.message" class="mt-1 text-sm text-red-600">{{ replyForm.errors.message }}</p><div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><p class="text-xs text-slate-500">Replying moves this request to “Waiting customer”.</p><button type="submit" :disabled="replyForm.processing || !replyForm.message.trim()" class="inline-flex items-center justify-center gap-2 rounded-lg bg-orange-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-60"><Send class="h-4 w-4" />{{ replyForm.processing ? 'Sending…' : 'Send reply' }}</button></div></form>
          </div>

          <aside class="space-y-5">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="flex items-center gap-2 font-semibold text-slate-900"><UserRound class="h-4 w-4 text-slate-500" /> Customer</h2><p class="mt-4 font-semibold text-slate-900">{{ ticket.customer_name }}</p><a :href="`mailto:${ticket.customer_email}`" class="mt-1 block break-all text-sm text-blue-700 hover:underline">{{ ticket.customer_email }}</a><p class="mt-4 text-xs text-slate-500">Ticket category</p><p class="mt-1 text-sm font-medium text-slate-800">{{ categoryLabel }}</p></section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="flex items-center gap-2 font-semibold text-slate-900"><Clock3 class="h-4 w-4 text-slate-500" /> Manage request</h2><form @submit.prevent="saveStatus" class="mt-4 space-y-4"><div><label for="status" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label><select id="status" v-model="status" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm"><option v-for="item in statuses" :key="item" :value="item">{{ statusLabels[item] || item }}</option></select></div><div><label for="priority" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Priority</label><select id="priority" v-model="priority" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm"><option v-for="item in priorities" :key="item" :value="item" class="capitalize">{{ item.charAt(0).toUpperCase() + item.slice(1) }}</option></select></div><button type="submit" class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Save changes</button></form><p v-if="ticket.assigned_admin" class="mt-4 border-t border-slate-100 pt-3 text-xs text-slate-500">Assigned to <span class="font-semibold text-slate-700">{{ ticket.assigned_admin }}</span></p></section>
            <section v-if="ticket.order" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="flex items-center gap-2 font-semibold text-slate-900"><Package class="h-4 w-4 text-slate-500" /> Related order</h2><Link :href="`/admin/sales/orders/${ticket.order.id}`" class="mt-4 block font-semibold text-blue-700 hover:underline">#{{ ticket.order.order_number }}</Link><p class="mt-1 text-sm capitalize text-slate-600">{{ ticket.order.status }}</p><p class="mt-2 text-xs text-slate-500">{{ ticket.order.customer_email }}</p></section>
            <div class="flex gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900"><CheckCircle2 class="h-5 w-5 shrink-0" /><p>Keep replies clear, respectful and focused on the customer’s next step.</p></div>
          </aside>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
