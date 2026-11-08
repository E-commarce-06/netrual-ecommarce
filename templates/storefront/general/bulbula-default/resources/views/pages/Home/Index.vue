<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import {
  ArrowRight,
  ArrowUpRight,
  Box,
  ChevronRight,
  Headphones,
  PackageCheck,
  ShieldCheck,
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

const page = usePage()
const brand = computed(() => (page.props.brand ?? {}) as Record<string, string>)
const showQuickView = ref(false)
const selectedProductSlug = ref<string | null>(null)

const categories = computed(() => props.featuredCategories.length ? props.featuredCategories : props.categories)
const featuredProducts = computed(() => props.featuredProducts.slice(0, Math.max(2, Number(props.homepageSettings.featured_count) || 4)))
const newProducts = computed(() => {
  const count = Math.max(2, Number(props.homepageSettings.new_arrivals_count) || 4)
  const featuredIds = new Set(featuredProducts.value.map((product) => product.id))

  return props.newProducts.filter((product) => !featuredIds.has(product.id)).slice(0, count)
})
const defaultHeroImage = '/images/bneo-ethiopian-family-shopping.jpg'
const heroImage = computed(() => props.homepageSettings.hero_image || defaultHeroImage)
const heroImageAlt = computed(() => heroImage.value === defaultHeroImage ? 'An Ethiopian family shopping online together at home' : props.homepageSettings.hero_title)
const promoProduct = computed(() => {
  if (props.homepageSettings.promo_image) return null

  return [props.bannerProduct, ...props.offerProducts].find((product) => product?.image && product.image !== heroImage.value) || null
})
const promoImage = computed(() => props.homepageSettings.promo_image || promoProduct.value?.image || '')
const heroSecondaryUrl = computed(() => {
  const target = props.homepageSettings.hero_secondary_url
  const fallback = target || '#categories'
  const hasCategories = props.homepageSettings.categories_enabled && categories.value.length > 0

  return fallback === '#categories' && !hasCategories ? '/products' : fallback
})
const siteName = computed(() => props.siteConfig?.name || brand.value.name || 'Store')

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
          <div class="relative isolate grid overflow-hidden rounded-[2.25rem] border border-[#e9dfd0] bg-[#e8dfd0] shadow-[0_28px_80px_-28px_rgba(11,32,64,0.28)] lg:min-h-[620px]">
            <div class="relative order-2 min-h-[320px] overflow-hidden sm:min-h-[420px] lg:absolute lg:inset-0 lg:z-0 lg:min-h-0">
              <img :src="heroImage" :alt="heroImageAlt" class="absolute inset-0 h-full w-full object-cover object-[60%_center] lg:object-center" fetchpriority="high" />
              <div class="absolute inset-0 bg-gradient-to-t from-[#081a33]/60 via-[#081a33]/10 to-transparent lg:bg-gradient-to-r lg:from-[#081a33]/35 lg:via-[#081a33]/5 lg:to-transparent" />
              <a v-if="heroProducts[0]" :href="`/product/${heroProducts[0].slug}`" class="absolute bottom-4 left-4 right-4 flex items-center justify-between gap-4 rounded-2xl border border-white/30 bg-[#0b2040]/90 p-4 text-white shadow-xl backdrop-blur-xl transition hover:bg-[#0b2040] sm:bottom-6 sm:left-6 sm:right-6 sm:p-5 lg:bottom-8 lg:left-auto lg:right-8 lg:max-w-[20rem]">
                <span class="min-w-0 block line-clamp-2 text-sm font-semibold leading-5 sm:text-base">{{ heroProducts[0].name }}</span>
                <ArrowUpRight class="h-5 w-5 shrink-0 text-orange-200" />
              </a>
            </div>

            <div class="relative z-20 order-1 flex items-center px-4 py-7 sm:px-8 sm:py-10 lg:col-start-1 lg:row-start-1 lg:px-10 lg:py-12 xl:px-14">
              <div class="relative w-full max-w-[560px] rounded-[1.75rem] border border-white/85 bg-[#fffdf8]/95 p-6 shadow-[0_22px_70px_-32px_rgba(11,32,64,0.34)] backdrop-blur sm:p-9 lg:translate-x-12 lg:p-11">
                <div class="mb-4 flex h-1.5 w-16 overflow-hidden rounded-full" aria-hidden="true">
                  <span class="w-1/3 bg-[#078930]" />
                  <span class="w-1/3 bg-[#FCDD09]" />
                  <span class="w-1/3 bg-[#DA121A]" />
                </div>
                <p class="inline-flex items-center gap-2 rounded-full border border-[#e8c8a6] bg-white px-3.5 py-2 text-[10px] font-bold uppercase tracking-[0.2em] text-[#8a4b16] sm:text-xs">
                  {{ homepageSettings.hero_eyebrow }}
                </p>
                <h1 class="mt-5 max-w-[12ch] whitespace-pre-line text-[2.65rem] font-semibold leading-[1.02] tracking-[-0.045em] text-[#10213d] sm:mt-6 sm:text-5xl md:text-6xl lg:text-[3.9rem] xl:text-[4.35rem]">
                  {{ homepageSettings.hero_title }}
                </h1>
                <p class="mt-4 max-w-lg text-base leading-7 text-[#44546a] sm:text-lg sm:leading-8">
                  {{ homepageSettings.hero_description }}
                </p>
                <div class="mt-7 flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:flex-wrap">
                  <a :href="homepageSettings.hero_primary_url || '/products'" class="group inline-flex min-h-12 items-center justify-center gap-3 rounded-xl bg-[#f39a3c] px-5 py-3 text-sm font-bold text-[#10213d] shadow-lg shadow-orange-950/20 transition hover:-translate-y-0.5 hover:bg-[#ffad55]">
                    {{ homepageSettings.hero_primary_text || 'Shop all products' }}
                    <ArrowRight class="h-4 w-4 transition-transform group-hover:translate-x-1" />
                  </a>
                  <a v-if="homepageSettings.hero_secondary_text" :href="heroSecondaryUrl" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border border-[#cbd3dc] bg-white px-5 py-3 text-sm font-semibold text-[#10213d] transition hover:border-[#10213d] hover:bg-[#10213d] hover:text-white">
                    {{ homepageSettings.hero_secondary_text }}
                    <ChevronRight class="h-4 w-4" />
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Service highlights -->
      <section v-if="homepageSettings.show_trust_strip" class="px-4 pb-10 sm:px-6 lg:px-10 lg:pb-14">
        <div class="mx-auto grid max-w-[1240px] grid-cols-2 gap-3 rounded-2xl border border-[#e9e5dd] bg-white px-4 py-5 shadow-sm sm:grid-cols-4 sm:gap-5 sm:px-7">
          <div class="flex items-center gap-3 px-1 py-1">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-orange-50 text-orange-700 dark:bg-orange-500/10 dark:text-orange-300"><Truck class="h-5 w-5" /></span>
            <div><p class="text-sm font-bold text-[#12213a] dark:text-white">Reliable delivery</p><p class="mt-0.5 text-xs text-gray-500">To your doorstep</p></div>
          </div>
          <div class="flex items-center gap-3 px-1 py-1">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-800 dark:bg-orange-500/10 dark:text-orange-300"><ShieldCheck class="h-5 w-5" /></span>
            <div><p class="text-sm font-bold text-[#12213a] dark:text-white">Secure checkout</p><p class="mt-0.5 text-xs text-gray-500">Shop with confidence</p></div>
          </div>
          <div class="flex items-center gap-3 px-1 py-1">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 dark:bg-orange-500/10 dark:text-orange-300"><PackageCheck class="h-5 w-5" /></span>
            <div><p class="text-sm font-bold text-[#12213a] dark:text-white">Easy returns</p><p class="mt-0.5 text-xs text-gray-500">Simple, stress-free</p></div>
          </div>
          <div class="flex items-center gap-3 px-1 py-1">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-700 dark:bg-orange-500/10 dark:text-orange-300"><Headphones class="h-5 w-5" /></span>
            <div><p class="text-sm font-bold text-[#12213a] dark:text-white">Customer support</p><p class="mt-0.5 text-xs text-gray-500">Friendly support</p></div>
          </div>
        </div>
      </section>

      <!-- Categories -->
      <section v-if="homepageSettings.categories_enabled && categories.length" id="categories" class="scroll-mt-24 px-4 pb-12 sm:px-6 lg:px-10 lg:pb-16">
        <div class="mx-auto max-w-[1320px]">
          <div class="mb-5 flex items-end justify-between gap-4 sm:mb-7">
            <h2 class="text-2xl font-semibold tracking-tight text-[#12213a] sm:text-3xl lg:text-4xl">{{ homepageSettings.categories_title }}</h2>
            <a href="/products" class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-[#d9d4ca] px-3 py-2 text-xs font-semibold text-[#12213a] transition hover:border-orange-300 hover:bg-orange-50 sm:px-4 sm:text-sm">View all <ArrowUpRight class="h-4 w-4" /></a>
          </div>
          <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-5">
            <a v-for="(category, index) in categories.slice(0, 8)" :key="category.id" :href="`/category/${category.slug}`" class="group relative isolate flex min-h-[170px] flex-col justify-between overflow-hidden rounded-2xl border border-black/5 bg-gradient-to-br p-4 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-lg sm:min-h-[200px] sm:rounded-3xl sm:p-5" :class="categoryTint(index)">
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
        </div>
      </section>

      <!-- Featured products -->
      <section v-if="homepageSettings.featured_enabled && featuredProducts.length" class="px-4 py-8 sm:px-6 sm:py-10 lg:px-10 lg:py-12">
        <div class="mx-auto max-w-[1320px] rounded-[2rem] border border-[#e9e5dd] bg-white px-4 py-6 shadow-sm sm:px-6 sm:py-8 lg:px-8 lg:py-9 dark:border-white/10">
          <div class="mb-5 flex flex-col gap-3 sm:mb-7 sm:flex-row sm:items-end sm:justify-between">
            <h2 class="text-2xl font-semibold tracking-tight text-[#12213a] dark:text-white sm:text-3xl lg:text-4xl">{{ homepageSettings.featured_title }}</h2>
            <a href="/products" class="inline-flex self-start items-center gap-2 rounded-full border border-[#d9d4ca] px-4 py-2.5 text-sm font-semibold text-[#12213a] transition hover:border-orange-300 hover:bg-orange-50 dark:border-white/20 dark:text-white dark:hover:bg-white/5">View all <ArrowRight class="h-4 w-4" /></a>
          </div>
          <div class="grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4">
            <ProductCard v-for="product in featuredProducts" :key="product.id" :product="product" @quick-view="openQuickView" />
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
              <p class="mt-4 max-w-lg text-sm leading-7 text-[#482f1e] sm:text-base">{{ homepageSettings.promo_description }}</p>
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
                <span class="absolute bottom-8 right-8 text-[2rem] font-black tracking-[-0.08em] text-white/70 sm:text-5xl">{{ siteName }}</span>
              </div>
              <div class="absolute inset-0 bg-gradient-to-t from-[#7e421b]/25 to-transparent md:bg-gradient-to-l md:from-transparent md:to-[#e88b36]/15" />
              <div v-if="promoProduct" class="absolute bottom-5 left-5 right-5 rounded-2xl border border-white/25 bg-[#10213d]/85 p-3 text-white shadow-lg backdrop-blur-xl sm:bottom-7 sm:left-7 sm:right-auto sm:max-w-[250px] sm:p-4">
                <p class="line-clamp-1 text-sm font-semibold">{{ promoProduct.name }}</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- New arrivals -->
      <section v-if="homepageSettings.new_arrivals_enabled && newProducts.length" class="px-4 pb-10 sm:px-6 sm:pb-12 lg:px-10 lg:pb-14">
        <div class="mx-auto max-w-[1320px] rounded-[2rem] border border-[#e9e5dd] bg-white px-4 py-6 shadow-sm sm:px-6 sm:py-8 lg:px-8 lg:py-9 dark:border-white/10">
          <div class="mb-5 flex flex-col gap-3 sm:mb-7 sm:flex-row sm:items-end sm:justify-between">
            <h2 class="text-2xl font-semibold tracking-tight text-[#12213a] dark:text-white sm:text-3xl lg:text-4xl">{{ homepageSettings.new_arrivals_title }}</h2>
            <a href="/products?sort=newest" class="inline-flex self-start items-center gap-2 rounded-full border border-[#d9d4ca] px-4 py-2.5 text-sm font-semibold text-[#12213a] transition hover:border-orange-300 hover:bg-orange-50 dark:border-white/20 dark:text-white dark:hover:bg-white/5">Shop new arrivals <ArrowRight class="h-4 w-4" /></a>
          </div>
          <div class="grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4">
            <ProductCard v-for="product in newProducts" :key="product.id" :product="product" @quick-view="openQuickView" />
          </div>
        </div>
      </section>
    </div>

    <QuickViewModal :is-open="showQuickView" :product-slug="selectedProductSlug" @close="showQuickView = false" />
  </ThemeLayout>
</template>
