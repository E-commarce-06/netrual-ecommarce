<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ThemeLayout from '../../../layouts/ThemeLayout.vue';
import { LifeBuoy, ArrowLeft, Send, PackageCheck, MessageSquareText } from 'lucide-vue-next';

interface Message { id: number; author_type: string; author_name: string; body: string; created_at: string }
interface Ticket { id: number; ticket_number: string; subject: string; category: string; status: string; priority: string; order_number?: string | null; order_status?: string | null; created_at: string; messages: Message[] }
const props = defineProps<{ ticket: Ticket; canReply: boolean }>();
const form = useForm({ message: '' });
const page = usePage();
const flash = computed(() => (page.props.flash as { success?: string; error?: string } | undefined) || {});
function submit() { form.post(`/account/support/${props.ticket.id}/messages`, { preserveScroll: true, onSuccess: () => form.reset() }); }
const statusLabel = (status: string) => ({ open: 'Open', in_progress: 'In progress', awaiting_customer: 'Reply received', resolved: 'Resolved', closed: 'Closed' } as Record<string, string>)[status] || status;
</script>

<template>
  <ThemeLayout>
    <Head :title="`${ticket.ticket_number} · Support`" />
    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:py-12">
      <div class="mx-auto max-w-4xl">
        <Link href="/account/support" class="inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-slate-900"><ArrowLeft class="h-4 w-4" /> My support requests</Link>
        <div class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"><div><div class="flex flex-wrap items-center gap-2"><span class="text-sm font-semibold text-orange-700">{{ ticket.ticket_number }}</span><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ statusLabel(ticket.status) }}</span></div><h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ ticket.subject }}</h1><p class="mt-2 text-sm text-slate-500">{{ ticket.category }} <span v-if="ticket.order_number">· Order {{ ticket.order_number }}<span v-if="ticket.order_status"> ({{ ticket.order_status }})</span></span> · Opened {{ ticket.created_at }}</p></div><Link href="/help" class="inline-flex items-center gap-2 self-start rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"><LifeBuoy class="h-4 w-4" /> Help Center</Link></div>

        <div v-if="flash.success || flash.error" :class="flash.error ? 'border-red-200 bg-red-50 text-red-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'" class="mt-5 rounded-xl border px-4 py-3 text-sm font-medium" role="status">{{ flash.error || flash.success }}</div>

        <div class="mt-7 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
          <div class="mb-5 flex items-center gap-2 border-b border-slate-100 pb-4"><MessageSquareText class="h-5 w-5 text-slate-500" /><h2 class="font-semibold text-slate-900">Conversation</h2><span class="ml-auto text-xs text-slate-500">{{ ticket.messages.length }} {{ ticket.messages.length === 1 ? 'message' : 'messages' }}</span></div>
          <div class="space-y-5">
            <article v-for="message in ticket.messages" :key="message.id" class="rounded-xl border p-4 sm:p-5" :class="message.author_type === 'customer' ? 'ml-4 sm:ml-14 border-orange-100 bg-orange-50/60' : 'mr-4 sm:mr-14 border-slate-200 bg-slate-50'">
              <header class="flex flex-wrap items-center gap-x-2 gap-y-1"><span class="text-sm font-semibold text-slate-900">{{ message.author_type === 'admin' ? 'Support team' : 'You' }}</span><span v-if="message.author_type === 'admin'" class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-800">Official reply</span><time class="ml-auto text-xs text-slate-500">{{ message.created_at }}</time></header>
              <p class="mt-3 whitespace-pre-wrap break-words text-sm leading-6 text-slate-700">{{ message.body }}</p>
            </article>
          </div>
        </div>

        <form v-if="canReply" @submit.prevent="submit" class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6"><label for="reply" class="block text-sm font-semibold text-slate-900">Add a reply</label><textarea id="reply" v-model="form.message" rows="5" maxlength="5000" required placeholder="Write your reply…" class="mt-3 w-full resize-y rounded-lg border border-slate-300 px-3 py-2.5 text-sm leading-6 focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-200" /><p v-if="form.errors.message" class="mt-1 text-sm text-red-600">{{ form.errors.message }}</p><div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><p class="text-xs text-slate-500">Never include your password, one-time code, or full card number.</p><button type="submit" :disabled="form.processing || !form.message.trim()" class="inline-flex items-center justify-center gap-2 rounded-lg bg-orange-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-orange-700 disabled:opacity-60"><Send class="h-4 w-4" />{{ form.processing ? 'Sending…' : 'Send reply' }}</button></div></form>
        <div v-else class="mt-5 flex gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900"><PackageCheck class="h-5 w-5 shrink-0" /><p>This request is closed. If you still need help, <Link href="/account/support/create" class="font-semibold underline underline-offset-2">open a new request</Link>.</p></div>
      </div>
    </main>
  </ThemeLayout>
</template>
