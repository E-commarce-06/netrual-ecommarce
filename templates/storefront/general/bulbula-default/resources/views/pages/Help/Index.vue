<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import ThemeLayout from '../../layouts/ThemeLayout.vue';
import { Search, Package, Truck, RefreshCw, CreditCard, UserRound, ArrowRight, ChevronDown, LifeBuoy, ShieldCheck } from 'lucide-vue-next';

interface Article { question: string; answer: string; keywords: string }
interface Category { id: string; title: string; description: string; icon: string; articles: Article[] }
const props = defineProps<{ categories: Category[]; supportEmail?: string | null }>();
const query = ref('');
const expanded = ref('');
const activeCategory = ref('all');
const iconMap: Record<string, any> = { package: Package, truck: Truck, 'refresh-cw': RefreshCw, 'credit-card': CreditCard, user: UserRound };
const normalizedQuery = computed(() => query.value.trim().toLowerCase());
const visibleCategories = computed(() => props.categories
  .filter(category => activeCategory.value === 'all' || category.id === activeCategory.value)
  .map(category => ({
    ...category,
    articles: category.articles.filter(article => !normalizedQuery.value || `${article.question} ${article.answer} ${article.keywords} ${category.title}`.toLowerCase().includes(normalizedQuery.value)),
  }))
  .filter(category => category.articles.length > 0));
const totalResults = computed(() => visibleCategories.value.reduce((total, category) => total + category.articles.length, 0));
function toggleArticle(key: string) { expanded.value = expanded.value === key ? '' : key; }
</script>

<template>
  <ThemeLayout>
    <Head title="Help Center" />
    <main class="bg-slate-50 min-h-screen pb-16">
      <section class="bg-[#071a35] text-white">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
          <div class="mx-auto max-w-3xl text-center">
            <div class="mx-auto mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-white/10 text-orange-300"><LifeBuoy class="h-6 w-6" /></div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-orange-300">Customer care</p>
            <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-5xl">How can we help?</h1>
            <p class="mx-auto mt-4 max-w-xl text-base leading-7 text-slate-300">Find a quick answer, check an order, or get help from our support team.</p>
            <label class="relative mx-auto mt-8 block max-w-2xl text-left">
              <Search class="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
              <input v-model="query" type="search" placeholder="Search orders, delivery, returns, payments…" aria-label="Search help articles" class="h-14 w-full rounded-xl border border-white/15 bg-white pl-12 pr-4 text-base text-slate-900 shadow-xl outline-none placeholder:text-slate-400 focus:ring-4 focus:ring-orange-300/40" />
            </label>
            <div class="mt-5 flex flex-wrap justify-center gap-x-5 gap-y-2 text-sm text-slate-300">
              <span>Popular:</span>
              <button type="button" class="underline decoration-slate-500 underline-offset-4 hover:text-white" @click="query = 'track order'">Track my order</button>
              <button type="button" class="underline decoration-slate-500 underline-offset-4 hover:text-white" @click="query = 'refund'">Refunds</button>
              <button type="button" class="underline decoration-slate-500 underline-offset-4 hover:text-white" @click="query = 'payment'">Payments</button>
            </div>
          </div>
        </div>
      </section>

      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <section class="-mt-6 grid gap-3 sm:grid-cols-3">
          <Link href="/account/orders" class="group flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><Package class="h-5 w-5" /></span>
            <span class="min-w-0 flex-1"><strong class="block text-sm font-semibold text-slate-900">My orders</strong><span class="mt-1 block text-xs text-slate-500">View details and order status</span></span><ArrowRight class="h-4 w-4 text-slate-400 transition group-hover:translate-x-1" />
          </Link>
          <Link href="/checkout/track-order" class="group flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-700"><Truck class="h-5 w-5" /></span>
            <span class="min-w-0 flex-1"><strong class="block text-sm font-semibold text-slate-900">Track an order</strong><span class="mt-1 block text-xs text-slate-500">Look up a guest order, too</span></span><ArrowRight class="h-4 w-4 text-slate-400 transition group-hover:translate-x-1" />
          </Link>
          <Link href="/account/support/create" class="group flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-orange-50 text-orange-700"><LifeBuoy class="h-5 w-5" /></span>
            <span class="min-w-0 flex-1"><strong class="block text-sm font-semibold text-slate-900">Contact support</strong><span class="mt-1 block text-xs text-slate-500">Send us a message and track it</span></span><ArrowRight class="h-4 w-4 text-slate-400 transition group-hover:translate-x-1" />
          </Link>
        </section>

        <section class="mt-12">
          <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="text-sm font-semibold uppercase tracking-wider text-orange-700">Help topics</p><h2 class="mt-1 text-2xl font-bold text-slate-900">Browse answers</h2><p class="mt-2 text-sm text-slate-600">Choose a topic or search across all help articles.</p></div>
            <p class="text-sm text-slate-500" aria-live="polite">{{ totalResults }} {{ totalResults === 1 ? 'answer' : 'answers' }}{{ normalizedQuery ? ' found' : '' }}</p>
          </div>
          <div class="mt-5 flex gap-2 overflow-x-auto pb-2" aria-label="Filter help topics">
            <button type="button" @click="activeCategory = 'all'" :aria-pressed="activeCategory === 'all'" :class="activeCategory === 'all' ? 'bg-[#071a35] text-white' : 'bg-white text-slate-700 hover:bg-slate-100'" class="shrink-0 rounded-full border border-slate-200 px-4 py-2 text-sm font-medium">All topics</button>
            <button v-for="category in categories" :key="category.id" type="button" @click="activeCategory = category.id" :aria-pressed="activeCategory === category.id" :class="activeCategory === category.id ? 'bg-[#071a35] text-white' : 'bg-white text-slate-700 hover:bg-slate-100'" class="shrink-0 rounded-full border border-slate-200 px-4 py-2 text-sm font-medium">{{ category.title }}</button>
          </div>

          <div v-if="visibleCategories.length" class="mt-6 grid gap-6 lg:grid-cols-2">
            <section v-for="category in visibleCategories" :key="category.id" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
              <header class="flex items-start gap-3 border-b border-slate-100 px-5 py-5 sm:px-6">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700"><component :is="iconMap[category.icon] || Package" class="h-5 w-5" /></span>
                <div><h3 class="font-semibold text-slate-900">{{ category.title }}</h3><p class="mt-1 text-sm text-slate-500">{{ category.description }}</p></div>
              </header>
              <div class="divide-y divide-slate-100">
                <article v-for="(article, index) in category.articles" :key="article.question" class="px-5 sm:px-6">
                  <h4>
                    <button type="button" :aria-expanded="expanded === `${category.id}-${index}`" class="flex w-full items-center justify-between gap-4 py-4 text-left text-sm font-medium text-slate-800 hover:text-orange-700" @click="toggleArticle(`${category.id}-${index}`)">
                      <span>{{ article.question }}</span><ChevronDown class="h-4 w-4 shrink-0 text-slate-400 transition-transform" :class="expanded === `${category.id}-${index}` ? 'rotate-180' : ''" />
                    </button>
                  </h4>
                  <p v-if="expanded === `${category.id}-${index}`" class="pb-5 pr-5 text-sm leading-6 text-slate-600">{{ article.answer }}</p>
                </article>
              </div>
            </section>
          </div>
          <div v-else class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
            <Search class="mx-auto h-8 w-8 text-slate-400" /><h3 class="mt-3 font-semibold text-slate-900">No answers found</h3><p class="mt-1 text-sm text-slate-500">Try a different search, or send our team a message.</p><Link href="/account/support/create" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-[#071a35] px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Contact support <ArrowRight class="h-4 w-4" /></Link>
          </div>
        </section>

        <section class="mt-12 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
          <div class="grid gap-6 p-6 sm:p-8 md:grid-cols-[1fr_auto] md:items-center">
            <div class="flex items-start gap-4"><span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700"><ShieldCheck class="h-5 w-5" /></span><div><h2 class="text-lg font-bold text-slate-900">Still need a hand?</h2><p class="mt-1 max-w-2xl text-sm leading-6 text-slate-600">Open a support request to keep the conversation and any order details together. You can follow replies from your account.</p><a v-if="supportEmail" :href="`mailto:${supportEmail}`" class="mt-2 inline-block text-sm font-medium text-slate-700 underline underline-offset-4">{{ supportEmail }}</a></div></div>
            <div class="flex flex-col gap-2 sm:flex-row"><Link href="/account/support" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">My requests</Link><Link href="/account/support/create" class="inline-flex items-center justify-center gap-2 rounded-lg bg-orange-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-orange-700">Contact support <ArrowRight class="h-4 w-4" /></Link></div>
          </div>
        </section>
      </div>
    </main>
  </ThemeLayout>
</template>
