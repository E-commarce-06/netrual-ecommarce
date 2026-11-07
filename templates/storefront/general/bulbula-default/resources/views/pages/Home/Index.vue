<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import {
  ArrowRight,
  ArrowUpRight,
  BadgeCheck,
  Box,
  ChevronRight,
  Headphones,
  PackageCheck,
  ShieldCheck,
  Sparkles,
  Truck,
} from 'lucide-vue-next'
import ThemeLayout from '../../layouts/ThemeLayout.vue'
import ProductCard from '../../components/ProductCard.vue'
import QuickViewModal from '../../components/QuickViewModal.vue'

interface HomepageSettings {
  announcement_enabled: boolean
  announcement_text: string
  hero_eyebrow: string
  hero_title: string
  hero_description: string
  hero_primary_text: string
  hero_primary_url: string
  hero_secondary_text: string
  hero_secondary_url: string
  hero_image: string
  show_trust_strip: boolean
  categories_enabled: boolean
  categories_title: string
  featured_enabled: boolean
  featured_title: string
  featured_count: number
  new_arrivals_enabled: boolean
  new_arrivals_title: string
  new_arrivals_count: number
  promo_enabled: boolean
  promo_eyebrow: string
  promo_title: string
  promo_description: string
  promo_cta_text: string
  promo_cta_url: string
  promo_image: string
}

interface Product {
  id: number
  name: string
  slug: string
  sku: string
  type?: string
  price: number
  special_price: number | null
  image: string | null
  rating: number
  reviews_count: number
  stock_quantity: number
  in_stock: boolean
  has_configurable_attributes: boolean
  badges?: Array<{ text: string; class?: string }>
  brand?: { id: number; name: string; slug: string }
}

interface Category {
  id: number
  name: string
  slug: string
  description?: string | null
  image?: string | null
  products_count?: number
}

interface Props {
  homepageSettings: HomepageSettings
  featuredProducts?: Product[]
  heroProducts?: Product[]
  offerProducts?: Product[]
  bannerProduct?: Product | null
  featuredCategories?: Category[]
  newProducts?: Product[]
  categories?: Category[]
  siteConfig: { name: string; url: string; description: string }
  seo?: { title?: string; description?: string; keywords?: string }
}

const props = withDefaults(defineProps<Props>(), {
  featuredProducts: () => [],
  heroProducts: () => [],
  offerProducts: () => [],
  bannerProduct: null,
  featuredCategories: () => [],
  newProducts: () => [],
  categories: () => [],
})

const showQuickView = ref(false)
const selectedProductSlug = ref<string | null>(null)

const categories = computed(() => props.featuredCategories.length ? props.featuredCategories : props.categories)
const featuredProducts = computed(() => props.featuredProducts.slice(0, Math.max(2, Number(props.homepageSettings.featured_count) || 4)))
const newProducts = computed(() => props.newProducts.slice(0, Math.max(2, Number(props.homepageSettings.new_arrivals_count) || 4)))
const heroImage = computed(() => props.homepageSettings.hero_image || props.heroProducts.find((product) => product.image)?.image || props.featuredProducts.find((product) => product.image)?.image || '')
const promoImage = computed(() => props.homepageSettings.promo_image || props.bannerProduct?.image || props.offerProducts.find((product) => product.image)?.image || '')
const heroSecondaryUrl = computed(() => {
  const target = props.homepageSettings.hero_secondary_url
  return !props.homepageSettings.categories_enabled && target === '#categories' ? '/products' : target || '#categories'
})
const siteName = computed(() => props.siteConfig?.name || 'BNEO')

const categoryTints = [
  'from-orange-200 via-orange-100 to-amber-50',
  'from-sky-200 via-cyan-100 to-blue-50',
  'from-violet-200 via-fuchsia-100 to-pink-50',
  'from-lime-200 via-emerald-100 to-teal-50',
]

function categoryTint(index: number) {
  return categoryTints[index % categoryTints.length]
}

function openQuickView(slug: string) {
  selectedProductSlug.value = slug
  showQuickView.value = true
}
</script>

<template>
  <ThemeLayout>
    <Head :title="seo?.title || `${siteName} — Shop online`">
      <meta name="description" :content="seo?.description || homepageSettings.hero_description || siteConfig.description" />
      <meta name="keywords" :content="seo?.keywords || 'online store, products, shopping'" />
    </Head>

    <div class="overflow-hidden bg-[#f7f6f2] text-[#12213a]">
      <!-- Hero -->
      <section class="px-4 pb-8 pt-5 sm:px-6 sm:pb-10 sm:pt-7 lg:px-10 lg:pb-12 lg:pt-9">
        <div class="mx-auto max-w-[1440px]">
          <div class="relative isolate grid min-h-[570px] overflow-hidden rounded-[2rem] bg-[#0b2040] shadow-[0_28px_80px_-28px_rgba(11,32,64,0.48)] lg:min-h-[600px] lg:grid-cols-[0.95fr_1.05fr]">
            <div class="pointer-events-none absolute -left-32 -top-40 h-[28rem] w-[28rem] rounded-full bg-[#ef8a24]/20 blur-3xl" />
            <div class="pointer-events-none absolute -bottom-48 left-[35%] h-[28rem] w-[28rem] rounded-full bg-[#4580a7]/20 blur-3xl" />

            <div class="relative z-10 flex flex-col items-start justify-center px-6 py-12 sm:px-10 sm:py-14 lg:px-14 lg:py-16 xl:px-20">
              <p class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/[0.08] px-3.5 py-2 text-[10px] font-bold uppercase tracking-[0.2em] text-orange-200 sm:text-xs">
                <span class="h-1.5 w-1.5 rounded-full bg-[#f4a04a] shadow-[0_0_12px_rgba(244,160,74,0.9)]" />
                {{ homepageSettings.hero_eyebrow }}
              </p>
              <h1 class="mt-6 max-w-2xl whitespace-pre-line text-[2.65rem] font-semibold leading-[1.04] tracking-[-0.045em] text-white sm:mt-7 sm:text-5xl md:text-6xl lg:text-[3.6rem] xl:text-[4.25rem]">
                {{ homepageSettings.hero_title }}
              </h1>
              <p class="mt-5 max-w-xl text-base leading-7 text-blue-100/75 sm:text-lg sm:leading-8">
                {{ homepageSettings.hero_description }}
              </p>
              <div class="mt-8 flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:flex-wrap">
                <a :href="homepageSettings.hero_primary_url || '/products'" class="group inline-flex min-h-12 items-center justify-center gap-3 rounded-xl bg-[#f39a3c] px-5 py-3 text-sm font-bold text-[#10213d] shadow-lg shadow-orange-950/20 transition hover:-translate-y-0.5 hover:bg-[#ffad55]">
                  {{ homepageSettings.hero_primary_text || 'Shop all products' }}
                  <ArrowRight class="h-4 w-4 transition-transform group-hover:translate-x-1" />
                </a>
                <a v-if="homepageSettings.hero_secondary_text" :href="heroSecondaryUrl" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border border-white/25 bg-white/[0.06] px-5 py-3 text-sm font-semibold text-white transition hover:border-white/50 hover:bg-white/10">
                  {{ homepageSettings.hero_secondary_text }}
                  <ChevronRight class="h-4 w-4" />
                </a>
              </div>
              <div class="mt-8 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs font-medium text-blue-100/65 sm:mt-10">
                <span class="inline-flex items-center gap-2"><BadgeCheck class="h-4 w-4 text-orange-300" /> Thoughtfully selected</span>
                <span class="hidden h-4 w-px bg-white/20 sm:block" />
                <span class="inline-flex items-center gap-2"><Sparkles class="h-4 w-4 text-orange-300" /> New discoveries, often</span>
              </div>
            </div>

            <div class="relative min-h-[330px] overflow-hidden sm:min-h-[410px] lg:min-h-0">
              <img v-if="heroImage" :src="heroImage" :alt="homepageSettings.hero_title" class="absolute inset-0 h-full w-full object-cover object-center" fetchpriority="high" />
              <div v-else class="absolute inset-0 bg-[radial-gradient(circle_at_70%_35%,rgba(243,154,60,0.48),transparent_24%),radial-gradient(circle_at_38%_72%,rgba(74,130,169,0.45),transparent_35%),linear-gradient(135deg,#102c50,#07172e)]">
                <div class="absolute right-[8%] top-[13%] h-56 w-56 rounded-full border border-white/10 bg-white/[0.04] sm:h-72 sm:w-72" />
                <div class="absolute right-[18%] top-[24%] h-40 w-40 rounded-full border border-orange-200/25 bg-orange-300/10 shadow-[0_0_90px_rgba(243,154,60,0.17)] sm:h-52 sm:w-52" />
                <div class="absolute bottom-[12%] left-[10%] h-32 w-32 rounded-[2rem] border border-white/10 bg-white/[0.05] rotate-[-12deg] sm:h-44 sm:w-44" />
              </div>
              <div class="absolute inset-0 bg-gradient-to-t from-[#081a33]/80 via-[#081a33]/15 to-transparent lg:bg-gradient-to-r lg:from-[#0b2040]/35 lg:via-transparent lg:to-transparent" />
              <div class="absolute inset-x-5 bottom-5 flex items-end justify-between gap-3 sm:inset-x-8 sm:bottom-8">
                <div class="max-w-[17rem] rounded-2xl border border-white/20 bg-[#0b2040]/55 p-4 text-white shadow-xl backdrop-blur-xl sm:p-5">
                  <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-orange-200">A little more considered</p>
                  <p class="mt-2 text-sm font-semibold leading-5 sm:text-base">{{ heroProducts[0]?.name || 'Good things for everyday living' }}</p>
                  <p class="mt-1 text-xs text-white/65">Discover pieces worth keeping close.</p>
                </div>
                <div class="hidden h-14 w-14 items-center justify-center rounded-2xl border border-white/20 bg-white/10 text-white backdrop-blur-lg sm:flex">
                  <Box class="h-6 w-6" />
                </div>
              </div>
              <div class="pointer-events-none absolute right-6 top-6 hidden rounded-full border border-white/20 bg-white/10 px-4 py-2 text-[10px] font-bold uppercase tracking-[0.15em] text-white backdrop-blur-lg sm:block lg:right-10 lg:top-10">
                Curated for real life
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Service highlights -->
      <section v-if="homepageSettings.show_trust_strip" class="px-4 pb-10 sm:px-6 lg:px-10 lg:pb-14">
        <div class="mx-auto grid max-w-[1240px] grid-cols-2 gap-3 rounded-2xl border border-[#e9e5dd] bg-white px-4 py-5 shadow-sm sm:grid-cols-4 sm:gap-5 sm:px-7">
          <div class="flex items-center gap-3 px-1 py-1">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-orange-50 text-orange-700"><Truck class="h-5 w-5" /></span>
            <div><p class="text-xs font-bold text-[#12213a] sm:text-sm">Reliable delivery</p><p class="mt-0.5 text-[10px] text-gray-500 sm:text-xs">To your doorstep</p></div>
          </div>
          <div class="flex items-center gap-3 px-1 py-1">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-800"><ShieldCheck class="h-5 w-5" /></span>
            <div><p class="text-xs font-bold text-[#12213a] sm:text-sm">Secure checkout</p><p class="mt-0.5 text-[10px] text-gray-500 sm:text-xs">Shop with confidence</p></div>
          </div>
          <div class="flex items-center gap-3 px-1 py-1">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700"><PackageCheck class="h-5 w-5" /></span>
            <div><p class="text-xs font-bold text-[#12213a] sm:text-sm">Easy returns</p><p class="mt-0.5 text-[10px] text-gray-500 sm:text-xs">Simple, stress-free</p></div>
          </div>
          <div class="flex items-center gap-3 px-1 py-1">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-700"><Headphones class="h-5 w-5" /></span>
            <div><p class="text-xs font-bold text-[#12213a] sm:text-sm">Here to help</p><p class="mt-0.5 text-[10px] text-gray-500 sm:text-xs">Friendly support</p></div>
          </div>
        </div>
      </section>

      <!-- Categories -->
      <section v-if="homepageSettings.categories_enabled" id="categories" class="scroll-mt-24 px-4 pb-14 sm:px-6 lg:px-10 lg:pb-20">
        <div class="mx-auto max-w-[1320px]">
          <div class="mb-6 flex items-end justify-between gap-4 sm:mb-8">
            <div>
              <p class="mb-2 text-[10px] font-bold uppercase tracking-[0.2em] text-orange-700">Find your corner</p>
              <h2 class="text-2xl font-semibold tracking-tight text-[#12213a] sm:text-3xl lg:text-4xl">{{ homepageSettings.categories_title }}</h2>
            </div>
            <a href="/products" class="hidden items-center gap-2 text-sm font-semibold text-[#12213a] transition hover:text-orange-700 sm:inline-flex">View all <ArrowUpRight class="h-4 w-4" /></a>
          </div>
          <div v-if="categories.length" class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">
            <a v-for="(category, index) in categories.slice(0, 8)" :key="category.id" :href="`/category/${category.slug}`" class="group relative isolate flex min-h-[190px] flex-col justify-between overflow-hidden rounded-2xl border border-black/5 bg-gradient-to-br p-4 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-lg sm:min-h-[235px] sm:rounded-3xl sm:p-6" :class="categoryTint(index)">
              <img v-if="category.image" :src="category.image" :alt="category.name" class="absolute inset-0 -z-10 h-full w-full object-cover transition duration-500 group-hover:scale-105" loading="lazy" />
              <div v-if="category.image" class="absolute inset-0 -z-10 bg-gradient-to-t from-[#0b2040]/85 via-[#0b2040]/10 to-black/5" />
              <div v-else class="absolute -right-7 -top-8 -z-10 h-36 w-36 rounded-full border border-white/60 bg-white/30 transition duration-500 group-hover:scale-110 sm:h-48 sm:w-48" />
              <span class="flex h-10 w-10 items-center justify-center rounded-xl border border-white/60 bg-white/65 text-[#12213a] shadow-sm backdrop-blur-sm transition group-hover:bg-white sm:h-12 sm:w-12 sm:rounded-2xl">
                <Box class="h-5 w-5 sm:h-6 sm:w-6" />
              </span>
              <div>
                <h3 class="text-base font-bold leading-tight text-[#12213a] group-hover:underline sm:text-xl" :class="{ 'text-white': category.image }">{{ category.name }}</h3>
                <p class="mt-1 text-[11px] font-medium text-[#30405a]/80 sm:mt-2 sm:text-xs" :class="{ 'text-white/75': category.image }">{{ category.products_count ?? 0 }} {{ (category.products_count ?? 0) === 1 ? 'item' : 'items' }}</p>
              </div>
              <span class="absolute right-4 top-4 flex h-8 w-8 items-center justify-center rounded-full bg-white/70 text-[#12213a] transition group-hover:translate-x-0.5 group-hover:bg-white sm:right-5 sm:top-5"><ArrowUpRight class="h-4 w-4" /></span>
            </a>
          </div>
          <div v-else class="rounded-2xl border border-dashed border-[#d9d4ca] bg-white/70 px-6 py-10 text-center">
            <Box class="mx-auto h-7 w-7 text-gray-400" />
            <p class="mt-3 text-sm font-semibold text-[#12213a]">Categories will appear here</p>
            <p class="mt-1 text-xs text-gray-500">Add active categories to your catalog to help shoppers browse.</p>
          </div>
        </div>
      </section>

      <!-- Featured products -->
      <section v-if="homepageSettings.featured_enabled" class="bg-white px-4 py-14 sm:px-6 sm:py-16 lg:px-10 lg:py-20">
        <div class="mx-auto max-w-[1320px]">
          <div class="mb-7 flex items-end justify-between gap-4 sm:mb-9">
            <div>
              <p class="mb-2 text-[10px] font-bold uppercase tracking-[0.2em] text-orange-700">Chosen with care</p>
              <h2 class="text-2xl font-semibold tracking-tight text-[#12213a] sm:text-3xl lg:text-4xl">{{ homepageSettings.featured_title }}</h2>
              <p class="mt-2 max-w-xl text-sm leading-6 text-gray-500">A few standout finds from across the store.</p>
            </div>
            <a href="/products" class="hidden items-center gap-2 rounded-full border border-[#d9d4ca] px-4 py-2.5 text-sm font-semibold text-[#12213a] transition hover:border-orange-300 hover:bg-orange-50 sm:inline-flex">Explore all <ArrowRight class="h-4 w-4" /></a>
          </div>
          <div v-if="featuredProducts.length" class="grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4">
            <ProductCard v-for="product in featuredProducts" :key="product.id" :product="product" @quick-view="openQuickView" />
          </div>
          <div v-else class="rounded-2xl border border-dashed border-[#d9d4ca] bg-[#fbfaf7] px-6 py-10 text-center">
            <Sparkles class="mx-auto h-7 w-7 text-orange-500" />
            <p class="mt-3 text-sm font-semibold text-[#12213a]">Featured products will show here</p>
            <p class="mt-1 text-xs text-gray-500">Mark a few products as featured in your catalog to fill this section.</p>
          </div>
        </div>
      </section>

      <!-- Feature banner -->
      <section v-if="homepageSettings.promo_enabled" class="px-4 py-14 sm:px-6 sm:py-16 lg:px-10 lg:py-20">
        <div class="mx-auto max-w-[1320px]">
          <div class="relative isolate grid min-h-[390px] overflow-hidden rounded-[2rem] bg-[#e88b36] shadow-[0_24px_70px_-30px_rgba(128,77,25,0.55)] md:grid-cols-[1fr_0.9fr]">
            <div class="pointer-events-none absolute -left-24 -top-28 h-80 w-80 rounded-full border border-white/25 bg-white/10" />
            <div class="relative z-10 flex flex-col items-start justify-center px-6 py-10 sm:px-10 sm:py-14 lg:px-16">
              <span class="rounded-full border border-[#562d0e]/15 bg-white/20 px-3.5 py-2 text-[10px] font-bold uppercase tracking-[0.2em] text-[#562d0e]">{{ homepageSettings.promo_eyebrow }}</span>
              <h2 class="mt-5 max-w-xl text-3xl font-semibold leading-tight tracking-[-0.035em] text-[#17243b] sm:text-4xl lg:text-5xl">{{ homepageSettings.promo_title }}</h2>
              <p class="mt-4 max-w-lg text-sm leading-7 text-[#482f1e]/80 sm:text-base">{{ homepageSettings.promo_description }}</p>
              <a :href="homepageSettings.promo_cta_url || '/products'" class="mt-7 inline-flex items-center gap-3 rounded-xl bg-[#10213d] px-5 py-3.5 text-sm font-bold text-white shadow-md transition hover:-translate-y-0.5 hover:bg-[#18345c]">
                {{ homepageSettings.promo_cta_text }} <ArrowRight class="h-4 w-4" />
              </a>
            </div>
            <div class="relative min-h-[250px] overflow-hidden md:min-h-[390px]">
              <img v-if="promoImage" :src="promoImage" :alt="homepageSettings.promo_title" class="absolute inset-0 h-full w-full object-cover" loading="lazy" />
              <div v-else class="absolute inset-0 bg-[radial-gradient(circle_at_50%_45%,rgba(255,255,255,0.5),transparent_28%),linear-gradient(145deg,#f5ba75,#da702c)]">
                <div class="absolute left-[23%] top-[19%] h-36 w-36 rounded-[2rem] border border-white/35 bg-white/20 rotate-[-12deg] shadow-xl sm:h-48 sm:w-48" />
                <div class="absolute left-[42%] top-[34%] h-36 w-36 rounded-[2rem] border border-white/40 bg-[#fff9ef]/65 rotate-[9deg] shadow-2xl sm:h-48 sm:w-48" />
                <div class="absolute left-[31%] top-[48%] h-28 w-28 rounded-full bg-[#12213a]/90 shadow-xl sm:h-36 sm:w-36" />
                <span class="absolute bottom-8 right-8 text-[2rem] font-black tracking-[-0.08em] text-white/70 sm:text-5xl">BNEO</span>
              </div>
              <div class="absolute inset-0 bg-gradient-to-t from-[#7e421b]/25 to-transparent md:bg-gradient-to-l md:from-transparent md:to-[#e88b36]/15" />
              <div v-if="bannerProduct" class="absolute bottom-5 left-5 right-5 rounded-2xl border border-white/25 bg-[#10213d]/70 p-3 text-white shadow-lg backdrop-blur-xl sm:bottom-7 sm:left-7 sm:right-auto sm:max-w-[250px] sm:p-4">
                <p class="text-[9px] font-bold uppercase tracking-[0.16em] text-orange-200">Featured find</p>
                <p class="mt-1 line-clamp-1 text-sm font-semibold">{{ bannerProduct.name }}</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- New arrivals -->
      <section v-if="homepageSettings.new_arrivals_enabled" class="bg-white px-4 pb-14 sm:px-6 sm:pb-16 lg:px-10 lg:pb-20">
        <div class="mx-auto max-w-[1320px]">
          <div class="mb-7 flex items-end justify-between gap-4 sm:mb-9">
            <div>
              <p class="mb-2 text-[10px] font-bold uppercase tracking-[0.2em] text-orange-700">Just landed</p>
              <h2 class="text-2xl font-semibold tracking-tight text-[#12213a] sm:text-3xl lg:text-4xl">{{ homepageSettings.new_arrivals_title }}</h2>
              <p class="mt-2 max-w-xl text-sm leading-6 text-gray-500">The latest additions, automatically refreshed from your catalog.</p>
            </div>
            <a href="/products?sort=newest" class="hidden items-center gap-2 rounded-full border border-[#d9d4ca] px-4 py-2.5 text-sm font-semibold text-[#12213a] transition hover:border-orange-300 hover:bg-orange-50 sm:inline-flex">See what's new <ArrowRight class="h-4 w-4" /></a>
          </div>
          <div v-if="newProducts.length" class="grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4">
            <ProductCard v-for="product in newProducts" :key="product.id" :product="product" @quick-view="openQuickView" />
          </div>
          <div v-else class="rounded-2xl border border-dashed border-[#d9d4ca] bg-[#fbfaf7] px-6 py-10 text-center">
            <PackageCheck class="mx-auto h-7 w-7 text-orange-500" />
            <p class="mt-3 text-sm font-semibold text-[#12213a]">New arrivals will show here</p>
            <p class="mt-1 text-xs text-gray-500">Add products to your catalog and this collection will update automatically.</p>
          </div>
        </div>
      </section>

      <!-- Closing CTA -->
      <section class="px-4 py-14 sm:px-6 lg:px-10 lg:py-20">
        <div class="mx-auto flex max-w-[1320px] flex-col items-start justify-between gap-6 rounded-[2rem] border border-[#dce4eb] bg-[#eef3f6] px-6 py-9 sm:flex-row sm:items-center sm:px-10 sm:py-11 lg:px-14">
          <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-orange-700">A good place to begin</p>
            <h2 class="mt-2 text-2xl font-semibold tracking-tight text-[#12213a] sm:text-3xl">Make room for something lovely.</h2>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-600">Browse useful, beautiful finds selected to make the everyday a little better.</p>
          </div>
          <a href="/products" class="inline-flex shrink-0 items-center gap-3 rounded-xl bg-[#10213d] px-5 py-3.5 text-sm font-bold text-white transition hover:bg-[#18345c]">
            Browse the store <ArrowRight class="h-4 w-4" />
          </a>
        </div>
      </section>
    </div>

    <QuickViewModal :is-open="showQuickView" :product-slug="selectedProductSlug" @close="showQuickView = false" />
  </ThemeLayout>
</template>
