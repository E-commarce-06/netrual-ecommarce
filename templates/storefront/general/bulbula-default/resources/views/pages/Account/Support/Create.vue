<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import ThemeLayout from '../../../layouts/ThemeLayout.vue';
import { LifeBuoy, ArrowLeft, ShieldCheck, Send } from 'lucide-vue-next';

interface OrderOption { id: number; order_number: string; status: string }
const props = defineProps<{ categories: Record<string, string>; orders: OrderOption[]; selectedOrder?: number | null }>();
const form = useForm({ category: 'orders', subject: '', order_id: props.selectedOrder ? String(props.selectedOrder) : '', message: '' });
function submit() { form.post('/account/support', { preserveScroll: true }); }
</script>

<template>
  <ThemeLayout>
    <Head title="Contact Support" />
    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:py-12">
      <div class="mx-auto max-w-3xl">
        <Link href="/help" class="text-sm font-medium text-slate-600 hover:text-slate-900">← Help Center</Link>
        <div class="mt-5 flex items-center gap-3"><span class="flex h-11 w-11 items-center justify-center rounded-xl bg-orange-100 text-orange-700"><LifeBuoy class="h-5 w-5" /></span><div><h1 class="text-3xl font-bold tracking-tight text-slate-900">Contact support</h1><p class="mt-1 text-sm text-slate-600">Tell us what happened. Your request and replies will stay together in your account.</p></div></div>
        <form @submit.prevent="submit" class="mt-7 space-y-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
          <div class="grid gap-5 sm:grid-cols-2">
            <div><label for="category" class="mb-2 block text-sm font-semibold text-slate-800">What do you need help with?</label><select id="category" v-model="form.category" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-200"><option v-for="(label, value) in categories" :key="value" :value="value">{{ label }}</option></select><p v-if="form.errors.category" class="mt-1 text-sm text-red-600">{{ form.errors.category }}</p></div>
            <div><label for="order_id" class="mb-2 block text-sm font-semibold text-slate-800">Related order <span class="font-normal text-slate-500">(optional)</span></label><select id="order_id" v-model="form.order_id" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-200"><option value="">No order selected</option><option v-for="order in orders" :key="order.id" :value="String(order.id)">#{{ order.order_number }} · {{ order.status }}</option></select><p v-if="form.errors.order_id" class="mt-1 text-sm text-red-600">{{ form.errors.order_id }}</p><p v-if="!orders.length" class="mt-1 text-xs text-slate-500">No recent orders found. You can still send a request.</p></div>
          </div>
          <div><label for="subject" class="mb-2 block text-sm font-semibold text-slate-800">Subject</label><input id="subject" v-model="form.subject" required minlength="4" maxlength="160" autocomplete="off" placeholder="A short summary of the issue" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-200" /><div class="mt-1 flex justify-between"><p v-if="form.errors.subject" class="text-sm text-red-600">{{ form.errors.subject }}</p><span class="ml-auto text-xs text-slate-400">{{ form.subject.length }}/160</span></div></div>
          <div><label for="message" class="mb-2 block text-sm font-semibold text-slate-800">How can we help?</label><textarea id="message" v-model="form.message" required minlength="10" maxlength="5000" rows="7" placeholder="Share the details that will help us resolve your request. Please do not include passwords, one-time codes, or full payment-card details." class="w-full resize-y rounded-lg border border-slate-300 px-3 py-2.5 text-sm leading-6 text-slate-900 placeholder:text-slate-400 focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-200" /><div class="mt-1 flex justify-between"><p v-if="form.errors.message" class="text-sm text-red-600">{{ form.errors.message }}</p><span class="ml-auto text-xs text-slate-400">{{ form.message.length }}/5000</span></div></div>
          <div class="flex items-start gap-3 rounded-xl bg-slate-50 p-4 text-sm text-slate-600"><ShieldCheck class="mt-0.5 h-5 w-5 shrink-0 text-emerald-700" /><p>Your request is private to you and our support team. We’ll email you when there’s a reply, and you can continue the conversation here.</p></div>
          <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-between"><Link href="/account/support" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"><ArrowLeft class="h-4 w-4" /> My requests</Link><button type="submit" :disabled="form.processing" class="inline-flex items-center justify-center gap-2 rounded-lg bg-orange-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-orange-700 disabled:cursor-not-allowed disabled:opacity-60"><Send class="h-4 w-4" />{{ form.processing ? 'Sending…' : 'Send request' }}</button></div>
        </form>
      </div>
    </main>
  </ThemeLayout>
</template>
