<script setup lang="ts">
import { computed, onUnmounted, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import {
  ArrowUpRight,
  Check,
  ImagePlus,
  MonitorSmartphone,
  Save,
  Sparkles,
  Store,
  X,
} from 'lucide-vue-next'

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

interface HomepageForm extends HomepageSettings {
  hero_image_upload: File | null
  promo_image_upload: File | null
}

const props = defineProps<{ settings: HomepageSettings }>()

const form = useForm<HomepageForm>({
  ...props.settings,
  hero_image_upload: null,
  promo_image_upload: null,
})

const heroUploadPreview = ref('')
const promoUploadPreview = ref('')

const heroPreview = computed(() => heroUploadPreview.value || form.hero_image)
const promoPreview = computed(() => promoUploadPreview.value || form.promo_image)

function replacePreview(target: 'hero' | 'promo', event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0] ?? null
  const preview = target === 'hero' ? heroUploadPreview : promoUploadPreview
  const field = target === 'hero' ? 'hero_image_upload' : 'promo_image_upload'

  if (preview.value) URL.revokeObjectURL(preview.value)
  preview.value = file ? URL.createObjectURL(file) : ''
  form[field] = file
}

function removeImage(target: 'hero' | 'promo') {
  const preview = target === 'hero' ? heroUploadPreview : promoUploadPreview
  const field = target === 'hero' ? 'hero_image' : 'promo_image'
  const uploadField = target === 'hero' ? 'hero_image_upload' : 'promo_image_upload'

  if (preview.value) URL.revokeObjectURL(preview.value)
  preview.value = ''
  form[field] = ''
  form[uploadField] = null
}

function save() {
  form.post('/admin/content/homepage', {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: (page) => {
      const savedSettings = page.props.settings as HomepageSettings
      form.defaults({
        ...savedSettings,
        hero_image_upload: null,
        promo_image_upload: null,
      })
      form.reset()
      if (heroUploadPreview.value) URL.revokeObjectURL(heroUploadPreview.value)
      if (promoUploadPreview.value) URL.revokeObjectURL(promoUploadPreview.value)
      heroUploadPreview.value = ''
      promoUploadPreview.value = ''
    },
  })
}

onUnmounted(() => {
  if (heroUploadPreview.value) URL.revokeObjectURL(heroUploadPreview.value)
  if (promoUploadPreview.value) URL.revokeObjectURL(promoUploadPreview.value)
})
</script>

<template>
  <AdminLayout title="Homepage">
    <Head title="Homepage Settings" />

    <div class="min-h-screen bg-gray-50 px-4 py-6 dark:bg-brand-navy sm:px-6 lg:px-8">
      <form class="mx-auto max-w-7xl space-y-6" @submit.prevent="save">
        <header class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/[0.04] sm:flex-row sm:items-center sm:justify-between sm:p-6">
          <div class="flex items-start gap-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300">
              <Store class="h-6 w-6" />
            </div>
            <div>
              <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">Homepage</h1>
                <span class="rounded-full bg-orange-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-orange-800 dark:bg-orange-500/15 dark:text-orange-300">Custom storefront</span>
              </div>
              <p class="mt-1 max-w-2xl text-sm leading-6 text-gray-600 dark:text-gray-300">
                Manage the homepage with simple content fields and section switches. Product and category cards stay connected to your live catalog.
              </p>
            </div>
          </div>
          <div class="flex flex-wrap items-center gap-2 sm:justify-end">
            <a href="/" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-white/15 dark:text-gray-200 dark:hover:bg-white/5">
              <ArrowUpRight class="h-4 w-4" />
              Preview storefront
            </a>
            <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-xl bg-brand-orange px-5 py-2.5 text-sm font-bold text-brand-navy shadow-sm transition hover:bg-orange-500 disabled:cursor-not-allowed disabled:opacity-60">
              <Save class="h-4 w-4" />
              {{ form.processing ? 'Saving…' : 'Save homepage' }}
            </button>
          </div>
        </header>

        <div v-if="form.recentlySuccessful" class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
          <Check class="h-4 w-4" /> Changes saved and live on your storefront.
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_350px]">
          <div class="space-y-6">
            <!-- Announcement -->
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.04]">
              <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-5 py-4 dark:border-white/10 sm:px-6">
                <div>
                  <h2 class="font-semibold text-gray-900 dark:text-white">Announcement bar</h2>
                  <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">A short message above the storefront header.</p>
                </div>
                <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                  <input v-model="form.announcement_enabled" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-orange focus:ring-brand-orange" />
                  Show
                </label>
              </div>
              <div class="p-5 sm:px-6">
                <label for="announcement_text" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Message</label>
                <input id="announcement_text" v-model="form.announcement_text" type="text" maxlength="180" placeholder="Good finds. Thoughtfully chosen." class="w-full rounded-xl border border-gray-300 bg-white px-3.5 py-3 text-sm text-gray-900 outline-none transition focus:border-orange-400 focus:ring-2 focus:ring-orange-200 dark:border-white/15 dark:bg-white/5 dark:text-white dark:focus:border-orange-400 dark:focus:ring-orange-400/20" />
                <p v-if="form.errors.announcement_text" class="mt-1 text-xs text-red-600">{{ form.errors.announcement_text }}</p>
              </div>
            </section>

            <!-- Hero -->
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.04]">
              <div class="border-b border-gray-100 px-5 py-4 dark:border-white/10 sm:px-6">
                <div class="flex items-center gap-2">
                  <Sparkles class="h-4 w-4 text-orange-500" />
                  <h2 class="font-semibold text-gray-900 dark:text-white">Hero section</h2>
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Set the first message shoppers see, plus its main image and links.</p>
              </div>
              <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
                <div>
                  <label for="hero_eyebrow" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Small label</label>
                  <input id="hero_eyebrow" v-model="form.hero_eyebrow" type="text" maxlength="80" class="w-full rounded-xl border border-gray-300 bg-white px-3.5 py-3 text-sm text-gray-900 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-200 dark:border-white/15 dark:bg-white/5 dark:text-white dark:focus:ring-orange-400/20" />
                </div>
                <div>
                  <label for="hero_title" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Headline</label>
                  <input id="hero_title" v-model="form.hero_title" type="text" maxlength="140" class="w-full rounded-xl border border-gray-300 bg-white px-3.5 py-3 text-sm text-gray-900 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-200 dark:border-white/15 dark:bg-white/5 dark:text-white dark:focus:ring-orange-400/20" />
                  <p v-if="form.errors.hero_title" class="mt-1 text-xs text-red-600">{{ form.errors.hero_title }}</p>
                </div>
                <div class="sm:col-span-2">
                  <label for="hero_description" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Supporting text</label>
                  <textarea id="hero_description" v-model="form.hero_description" rows="3" maxlength="500" class="w-full resize-y rounded-xl border border-gray-300 bg-white px-3.5 py-3 text-sm text-gray-900 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-200 dark:border-white/15 dark:bg-white/5 dark:text-white dark:focus:ring-orange-400/20" />
                </div>

                <div class="sm:col-span-2">
                  <div class="mb-1.5 flex items-center justify-between gap-3">
                    <label for="hero_image" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Hero image</label>
                    <button v-if="heroPreview" type="button" class="inline-flex items-center gap-1 text-xs font-medium text-gray-500 hover:text-red-600 dark:text-gray-400" @click="removeImage('hero')">
                      <X class="h-3.5 w-3.5" /> Remove image
                    </button>
                  </div>
                  <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_220px]">
                    <div>
                      <input id="hero_image" v-model="form.hero_image" type="text" placeholder="Paste an image URL or /storage/... path" class="w-full rounded-xl border border-gray-300 bg-white px-3.5 py-3 text-sm text-gray-900 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-200 dark:border-white/15 dark:bg-white/5 dark:text-white dark:focus:ring-orange-400/20" />
                      <label class="mt-3 flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-gray-300 px-4 py-3 text-sm font-medium text-gray-600 transition hover:border-orange-400 hover:bg-orange-50 dark:border-white/20 dark:text-gray-300 dark:hover:bg-orange-500/5">
                        <ImagePlus class="h-4 w-4" /> Upload an image
                        <input class="sr-only" type="file" accept="image/jpeg,image/png,image/webp" @change="replacePreview('hero', $event)" />
                      </label>
                      <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Optional. Recommended: landscape image, at least 1400 × 1000 px. Uploads are limited to 5 MB.</p>
                      <p v-if="form.errors.hero_image_upload" class="mt-1 text-xs text-red-600">{{ form.errors.hero_image_upload }}</p>
                    </div>
                    <div class="relative aspect-[4/3] overflow-hidden rounded-xl bg-gray-100 dark:bg-white/5">
                      <img v-if="heroPreview" :src="heroPreview" alt="Hero preview" class="h-full w-full object-cover" />
                      <div v-else class="flex h-full flex-col items-center justify-center gap-2 p-4 text-center text-gray-400 dark:text-gray-500">
                        <ImagePlus class="h-7 w-7" />
                        <span class="text-xs">Catalog product image will be used if no hero image is set.</span>
                      </div>
                    </div>
                  </div>
                </div>

                <div>
                  <label for="hero_primary_text" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Primary button</label>
                  <input id="hero_primary_text" v-model="form.hero_primary_text" type="text" maxlength="60" class="w-full rounded-xl border border-gray-300 bg-white px-3.5 py-3 text-sm text-gray-900 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-200 dark:border-white/15 dark:bg-white/5 dark:text-white dark:focus:ring-orange-400/20" />
                </div>
                <div>
                  <label for="hero_primary_url" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Primary button link</label>
                  <input id="hero_primary_url" v-model="form.hero_primary_url" type="text" maxlength="255" placeholder="/products" class="w-full rounded-xl border border-gray-300 bg-white px-3.5 py-3 text-sm text-gray-900 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-200 dark:border-white/15 dark:bg-white/5 dark:text-white dark:focus:ring-orange-400/20" />
                  <p v-if="form.errors.hero_primary_url" class="mt-1 text-xs text-red-600">{{ form.errors.hero_primary_url }}</p>
                </div>
                <div>
                  <label for="hero_secondary_text" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Secondary button</label>
                  <input id="hero_secondary_text" v-model="form.hero_secondary_text" type="text" maxlength="60" class="w-full rounded-xl border border-gray-300 bg-white px-3.5 py-3 text-sm text-gray-900 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-200 dark:border-white/15 dark:bg-white/5 dark:text-white dark:focus:ring-orange-400/20" />
                </div>
                <div>
                  <label for="hero_secondary_url" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Secondary button link</label>
                  <input id="hero_secondary_url" v-model="form.hero_secondary_url" type="text" maxlength="255" placeholder="#categories" class="w-full rounded-xl border border-gray-300 bg-white px-3.5 py-3 text-sm text-gray-900 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-200 dark:border-white/15 dark:bg-white/5 dark:text-white dark:focus:ring-orange-400/20" />
                  <p v-if="form.errors.hero_secondary_url" class="mt-1 text-xs text-red-600">{{ form.errors.hero_secondary_url }}</p>
                </div>
              </div>
            </section>

            <!-- Catalog sections -->
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.04]">
              <div class="border-b border-gray-100 px-5 py-4 dark:border-white/10 sm:px-6">
                <div class="flex items-center gap-2">
                  <MonitorSmartphone class="h-4 w-4 text-orange-500" />
                  <h2 class="font-semibold text-gray-900 dark:text-white">Catalog sections</h2>
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Choose which live catalog collections to show and how many items they contain.</p>
              </div>
              <div class="divide-y divide-gray-100 dark:divide-white/10">
                <div class="grid gap-4 p-5 sm:grid-cols-[1fr_auto] sm:items-center sm:px-6">
                  <div class="flex items-start gap-3">
                    <input v-model="form.categories_enabled" type="checkbox" class="mt-1 h-4 w-4 rounded border-gray-300 text-brand-orange focus:ring-brand-orange" />
                    <div class="min-w-0 flex-1">
                      <label class="block text-sm font-semibold text-gray-900 dark:text-white">Categories</label>
                      <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Browse the active top-level categories in your catalog.</p>
                    </div>
                  </div>
                  <input v-model="form.categories_title" type="text" aria-label="Categories section title" maxlength="80" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-200 dark:border-white/15 dark:bg-white/5 dark:text-white sm:max-w-xs" />
                </div>
                <div class="grid gap-4 p-5 sm:grid-cols-[1fr_auto] sm:items-center sm:px-6">
                  <div class="flex items-start gap-3">
                    <input v-model="form.featured_enabled" type="checkbox" class="mt-1 h-4 w-4 rounded border-gray-300 text-brand-orange focus:ring-brand-orange" />
                    <div class="min-w-0 flex-1">
                      <label class="block text-sm font-semibold text-gray-900 dark:text-white">Featured products</label>
                      <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Shows the products currently marked as featured.</p>
                    </div>
                  </div>
                  <div class="grid grid-cols-[minmax(0,1fr)_100px] gap-2">
                    <input v-model="form.featured_title" type="text" aria-label="Featured products title" maxlength="80" class="min-w-0 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-200 dark:border-white/15 dark:bg-white/5 dark:text-white" />
                    <select v-model.number="form.featured_count" aria-label="Featured products count" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-white/15 dark:bg-white/5 dark:text-white">
                      <option :value="2">2 items</option><option :value="4">4 items</option><option :value="6">6 items</option><option :value="8">8 items</option>
                    </select>
                  </div>
                </div>
                <div class="grid gap-4 p-5 sm:grid-cols-[1fr_auto] sm:items-center sm:px-6">
                  <div class="flex items-start gap-3">
                    <input v-model="form.new_arrivals_enabled" type="checkbox" class="mt-1 h-4 w-4 rounded border-gray-300 text-brand-orange focus:ring-brand-orange" />
                    <div class="min-w-0 flex-1">
                      <label class="block text-sm font-semibold text-gray-900 dark:text-white">New arrivals</label>
                      <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Automatically displays your newest products.</p>
                    </div>
                  </div>
                  <div class="grid grid-cols-[minmax(0,1fr)_100px] gap-2">
                    <input v-model="form.new_arrivals_title" type="text" aria-label="New arrivals title" maxlength="80" class="min-w-0 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-200 dark:border-white/15 dark:bg-white/5 dark:text-white" />
                    <select v-model.number="form.new_arrivals_count" aria-label="New arrivals count" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-white/15 dark:bg-white/5 dark:text-white">
                      <option :value="2">2 items</option><option :value="4">4 items</option><option :value="6">6 items</option><option :value="8">8 items</option>
                    </select>
                  </div>
                </div>
              </div>
            </section>

            <!-- Promotional feature -->
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.04]">
              <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-5 py-4 dark:border-white/10 sm:px-6">
                <div>
                  <h2 class="font-semibold text-gray-900 dark:text-white">Feature banner</h2>
                  <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">A large mid-page campaign block. Turn it off to keep the page shorter.</p>
                </div>
                <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                  <input v-model="form.promo_enabled" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-orange focus:ring-brand-orange" />
                  Show
                </label>
              </div>
              <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
                <div>
                  <label for="promo_eyebrow" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Small label</label>
                  <input id="promo_eyebrow" v-model="form.promo_eyebrow" type="text" maxlength="80" class="w-full rounded-xl border border-gray-300 bg-white px-3.5 py-3 text-sm text-gray-900 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-200 dark:border-white/15 dark:bg-white/5 dark:text-white" />
                </div>
                <div>
                  <label for="promo_title" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Headline</label>
                  <input id="promo_title" v-model="form.promo_title" type="text" maxlength="120" class="w-full rounded-xl border border-gray-300 bg-white px-3.5 py-3 text-sm text-gray-900 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-200 dark:border-white/15 dark:bg-white/5 dark:text-white" />
                </div>
                <div class="sm:col-span-2">
                  <label for="promo_description" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Supporting text</label>
                  <textarea id="promo_description" v-model="form.promo_description" rows="2" maxlength="400" class="w-full resize-y rounded-xl border border-gray-300 bg-white px-3.5 py-3 text-sm text-gray-900 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-200 dark:border-white/15 dark:bg-white/5 dark:text-white" />
                </div>
                <div>
                  <label for="promo_cta_text" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Button label</label>
                  <input id="promo_cta_text" v-model="form.promo_cta_text" type="text" maxlength="60" class="w-full rounded-xl border border-gray-300 bg-white px-3.5 py-3 text-sm text-gray-900 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-200 dark:border-white/15 dark:bg-white/5 dark:text-white" />
                </div>
                <div>
                  <label for="promo_cta_url" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Button link</label>
                  <input id="promo_cta_url" v-model="form.promo_cta_url" type="text" maxlength="255" placeholder="/products" class="w-full rounded-xl border border-gray-300 bg-white px-3.5 py-3 text-sm text-gray-900 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-200 dark:border-white/15 dark:bg-white/5 dark:text-white" />
                  <p v-if="form.errors.promo_cta_url" class="mt-1 text-xs text-red-600">{{ form.errors.promo_cta_url }}</p>
                </div>
                <div class="sm:col-span-2">
                  <div class="mb-1.5 flex items-center justify-between gap-3">
                    <label for="promo_image" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Banner image</label>
                    <button v-if="promoPreview" type="button" class="inline-flex items-center gap-1 text-xs font-medium text-gray-500 hover:text-red-600 dark:text-gray-400" @click="removeImage('promo')">
                      <X class="h-3.5 w-3.5" /> Remove image
                    </button>
                  </div>
                  <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_220px]">
                    <div>
                      <input id="promo_image" v-model="form.promo_image" type="text" placeholder="Paste an image URL or /storage/... path" class="w-full rounded-xl border border-gray-300 bg-white px-3.5 py-3 text-sm text-gray-900 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-200 dark:border-white/15 dark:bg-white/5 dark:text-white" />
                      <label class="mt-3 flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-gray-300 px-4 py-3 text-sm font-medium text-gray-600 transition hover:border-orange-400 hover:bg-orange-50 dark:border-white/20 dark:text-gray-300 dark:hover:bg-orange-500/5">
                        <ImagePlus class="h-4 w-4" /> Upload an image
                        <input class="sr-only" type="file" accept="image/jpeg,image/png,image/webp" @change="replacePreview('promo', $event)" />
                      </label>
                      <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Optional. A landscape image works best; uploads are limited to 5 MB.</p>
                      <p v-if="form.errors.promo_image_upload" class="mt-1 text-xs text-red-600">{{ form.errors.promo_image_upload }}</p>
                    </div>
                    <div class="relative aspect-[4/3] overflow-hidden rounded-xl bg-gray-100 dark:bg-white/5">
                      <img v-if="promoPreview" :src="promoPreview" alt="Feature banner preview" class="h-full w-full object-cover" />
                      <div v-else class="flex h-full flex-col items-center justify-center gap-2 p-4 text-center text-gray-400 dark:text-gray-500">
                        <ImagePlus class="h-7 w-7" />
                        <span class="text-xs">The brand artwork is used when no image is set.</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </section>

            <!-- Confidence bar -->
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/[0.04] sm:p-6">
              <label class="flex cursor-pointer items-start gap-3">
                <input v-model="form.show_trust_strip" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-gray-300 text-brand-orange focus:ring-brand-orange" />
                <span>
                  <span class="block text-sm font-semibold text-gray-900 dark:text-white">Show service highlights</span>
                  <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Displays the secure checkout, easy returns and customer support strip beneath the hero.</span>
                </span>
              </label>
            </section>

            <div class="flex justify-end pb-4">
              <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-xl bg-brand-orange px-5 py-3 text-sm font-bold text-brand-navy shadow-sm transition hover:bg-orange-500 disabled:cursor-not-allowed disabled:opacity-60">
                <Save class="h-4 w-4" />
                {{ form.processing ? 'Saving…' : 'Save homepage' }}
              </button>
            </div>
          </div>

          <!-- Live sample preview -->
          <aside class="space-y-4 xl:sticky xl:top-24 xl:self-start">
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-[#0b2040]">
              <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-white/10">
                <div>
                  <p class="text-sm font-semibold text-gray-900 dark:text-white">Quick preview</p>
                  <p class="text-xs text-gray-500 dark:text-gray-400">Hero content preview</p>
                </div>
                <MonitorSmartphone class="h-4 w-4 text-gray-400" />
              </div>
              <div class="p-3">
                <div class="relative min-h-[410px] overflow-hidden rounded-[1.25rem] bg-brand-navy p-5 text-white">
                  <img v-if="heroPreview" :src="heroPreview" alt="" class="absolute inset-0 h-full w-full object-cover opacity-35" />
                  <div class="absolute inset-0 bg-gradient-to-br from-brand-navy/95 via-brand-navy/85 to-brand-navy/50" />
                  <div class="relative z-10 flex min-h-[370px] flex-col justify-between">
                    <div>
                      <span class="inline-flex rounded-full border border-white/15 bg-white/10 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-orange-200">{{ form.hero_eyebrow || 'YOUR STORE' }}</span>
                      <h3 class="mt-5 whitespace-pre-line text-3xl font-bold leading-tight tracking-tight">{{ form.hero_title || 'Your next favourite starts here.' }}</h3>
                      <p class="mt-3 line-clamp-4 text-sm leading-6 text-white/75">{{ form.hero_description }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                      <span class="inline-flex items-center gap-2 rounded-lg bg-brand-orange px-3 py-2 text-xs font-bold text-brand-navy">{{ form.hero_primary_text || 'Shop now' }} <ArrowUpRight class="h-3.5 w-3.5" /></span>
                      <span class="inline-flex rounded-lg border border-white/20 px-3 py-2 text-xs font-semibold text-white">{{ form.hero_secondary_text || 'Explore' }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-white/[0.04]">
              <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300"><Sparkles class="h-5 w-5" /></div>
                <div>
                  <p class="text-sm font-semibold text-gray-900 dark:text-white">Live catalog sections</p>
                  <p class="text-xs text-gray-500 dark:text-gray-400">No product copying required.</p>
                </div>
              </div>
              <ul class="mt-4 space-y-2 text-xs text-gray-600 dark:text-gray-300">
                <li class="flex items-center gap-2"><Check class="h-3.5 w-3.5 text-emerald-500" /> Featured products update from your catalog</li>
                <li class="flex items-center gap-2"><Check class="h-3.5 w-3.5 text-emerald-500" /> New arrivals sort automatically</li>
                <li class="flex items-center gap-2"><Check class="h-3.5 w-3.5 text-emerald-500" /> Categories link to the right storefront pages</li>
              </ul>
              <Link href="/admin/content/media" class="mt-4 inline-flex items-center gap-1 text-xs font-semibold text-orange-700 hover:text-orange-800 dark:text-orange-300">
                Open Media Library <ArrowUpRight class="h-3.5 w-3.5" />
              </Link>
            </div>
          </aside>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
