<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import ThemeLayout from '../../layouts/ThemeLayout.vue';
import { ArrowLeft, CheckCircle2, Mail, MessageCircle, Phone, Send } from 'lucide-vue-next';

const props = defineProps<{
  storeName?: string | null;
  supportEmail?: string | null;
  phone?: string | null;
  whatsapp?: string | null;
}>();

const page = usePage<{ [key: string]: unknown; flash?: { success?: string | null; error?: string | null } }>();
const flash = computed(() => page.props.flash ?? {});
const phoneHref = computed(() => {
  const phone = props.phone?.trim() || '';
  const digits = phone.replace(/\D/g, '');
  return phone.startsWith('+') ? `+${digits}` : digits;
});
const whatsappHref = computed(() => {
  const number = props.whatsapp?.replace(/\D/g, '') || '';
  return number ? `https://wa.me/${number}` : '';
});
const form = useForm({ name: '', email: '', subject: '', message: '' });

function submit() {
  form.post('/contact-us', {
    preserveScroll: true,
    onSuccess: () => form.reset('subject', 'message'),
  });
}
</script>

<template>
  <ThemeLayout>
    <Head title="Contact Us" />
    <main class="min-h-screen bg-slate-50 pb-16">
      <section class="bg-[#071a35] text-white">
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 sm:py-16 lg:px-8">
          <Link href="/help" class="inline-flex items-center gap-2 text-sm font-medium text-slate-300 transition hover:text-white">
            <ArrowLeft class="h-4 w-4" /> Help Center
          </Link>
          <div class="mx-auto mt-7 max-w-3xl text-center">
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-orange-300">{{ props.storeName || 'Customer care' }}</p>
            <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-5xl">Contact us</h1>
            <p class="mx-auto mt-4 max-w-xl text-base leading-7 text-slate-300">Send our team a message. We’ll reply to the email address you provide.</p>
          </div>
        </div>
      </section>

      <div class="mx-auto grid max-w-7xl gap-6 px-4 pt-8 sm:px-6 lg:grid-cols-[minmax(0,1.3fr)_minmax(280px,0.7fr)] lg:px-8">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
          <div v-if="flash.success" role="status" class="mb-6 flex gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
            <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0" />
            <p>{{ flash.success }}</p>
          </div>
          <div v-if="flash.error" role="alert" class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            {{ flash.error }}
          </div>

          <form class="space-y-5" @submit.prevent="submit">
            <div class="grid gap-5 sm:grid-cols-2">
              <div>
                <label for="contact-name" class="mb-2 block text-sm font-semibold text-slate-800">Your name</label>
                <input id="contact-name" v-model="form.name" autocomplete="name" required minlength="2" maxlength="120" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-200" :aria-invalid="!!form.errors.name" />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
              </div>
              <div>
                <label for="contact-email" class="mb-2 block text-sm font-semibold text-slate-800">Email address</label>
                <input id="contact-email" v-model="form.email" type="email" autocomplete="email" required maxlength="255" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-200" :aria-invalid="!!form.errors.email" />
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
              </div>
            </div>

            <div>
              <label for="contact-subject" class="mb-2 block text-sm font-semibold text-slate-800">Subject</label>
              <input id="contact-subject" v-model="form.subject" required minlength="4" maxlength="160" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-900 outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-200" :aria-invalid="!!form.errors.subject" />
              <p v-if="form.errors.subject" class="mt-1 text-sm text-red-600">{{ form.errors.subject }}</p>
            </div>

            <div>
              <label for="contact-message" class="mb-2 block text-sm font-semibold text-slate-800">How can we help?</label>
              <textarea id="contact-message" v-model="form.message" required minlength="10" maxlength="5000" rows="7" placeholder="Tell us a little about what you need help with. Please do not include passwords, one-time codes, or full payment-card details." class="w-full resize-y rounded-lg border border-slate-300 px-3 py-2.5 text-sm leading-6 text-slate-900 outline-none placeholder:text-slate-400 focus:border-orange-500 focus:ring-2 focus:ring-orange-200" :aria-invalid="!!form.errors.message" />
              <div class="mt-1 flex justify-between gap-4">
                <p v-if="form.errors.message" class="text-sm text-red-600">{{ form.errors.message }}</p>
                <span class="ml-auto text-xs text-slate-400">{{ form.message.length }}/5000</span>
              </div>
            </div>

            <p class="text-xs leading-5 text-slate-500">By sending this message, you agree that our team may use the details you provide to respond to your enquiry.</p>
            <button type="submit" :disabled="form.processing" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-orange-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-orange-700 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto">
              <Send class="h-4 w-4" />{{ form.processing ? 'Sending…' : 'Send message' }}
            </button>
          </form>
        </section>

        <aside class="space-y-4">
          <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-lg font-bold text-slate-900">Other ways to reach us</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">Our contact details are managed by the store team.</p>
            <div class="mt-5 space-y-4">
              <a v-if="supportEmail" :href="`mailto:${supportEmail}`" class="flex items-start gap-3 rounded-lg p-2 text-slate-700 transition hover:bg-slate-50">
                <Mail class="mt-0.5 h-5 w-5 shrink-0 text-orange-600" />
                <span class="min-w-0"><span class="block text-xs font-medium text-slate-500">Email</span><span class="break-all text-sm font-semibold">{{ supportEmail }}</span></span>
              </a>
              <a v-if="phoneHref" :href="`tel:${phoneHref}`" class="flex items-start gap-3 rounded-lg p-2 text-slate-700 transition hover:bg-slate-50">
                <Phone class="mt-0.5 h-5 w-5 shrink-0 text-orange-600" />
                <span><span class="block text-xs font-medium text-slate-500">Phone</span><span class="text-sm font-semibold">{{ phone }}</span></span>
              </a>
              <a v-if="whatsappHref" :href="whatsappHref" target="_blank" rel="noopener noreferrer" class="flex items-start gap-3 rounded-lg p-2 text-slate-700 transition hover:bg-slate-50">
                <MessageCircle class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                <span><span class="block text-xs font-medium text-slate-500">WhatsApp</span><span class="text-sm font-semibold">Chat with our team</span></span>
              </a>
              <p v-if="!supportEmail && !phoneHref && !whatsappHref" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">The store has not added public email or phone details yet. You can still use the message form.</p>
            </div>
          </section>

          <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="font-semibold text-slate-900">Need an order update?</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">Browse common answers in our Help Center, or sign in to track a support request.</p>
            <div class="mt-4 flex flex-col gap-2">
              <Link href="/help" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Visit Help Center</Link>
              <Link href="/account/support" class="inline-flex items-center justify-center rounded-lg bg-[#071a35] px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">My support requests</Link>
            </div>
          </section>
        </aside>
      </div>
    </main>
  </ThemeLayout>
</template>
