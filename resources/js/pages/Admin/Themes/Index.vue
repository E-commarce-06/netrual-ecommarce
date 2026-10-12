<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import {
  Settings, CheckCircle, Eye, AlertTriangle, Monitor, Package, Wand2, ChevronRight,
} from 'lucide-vue-next';

interface Theme {
  id: number;
  name: string;
  slug: string;
  description: string;
  version: string;
  author: string;
  screenshot: string | null;
  is_active: boolean;
  is_default: boolean;
  exists: boolean;
  supports: string[];
  homepageEditor: HomepageEditorMeta;
}

interface HomepageEditorMeta {
  mode: 'native' | 'layout';
  showPublishedBanner: boolean;
  showImportLayout: boolean;
  editorUrl: string;
  editorLabel: string;
  bannerTitle: string;
  bannerDescription: string;
}

interface Props {
  themes: Theme[];
}

const props = defineProps<Props>();
const page = usePage();
const flash = computed(() => (page.props as any).flash || {});

const activeTheme = computed(() => props.themes.find(t => t.is_active));

const goToSettings = (slug: string) => {
  router.get('/admin/appearance');
};

</script>

<template>
  <AdminLayout title="Design Templates">
    <Head title="Design Templates" />

    <div class="py-8">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
          <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Design Templates</h1>
            <p class="mt-1 text-sm text-gray-500">
              Settings for the storefront theme in use.
            </p>
          </div>
        </div>

        <!-- Alerts -->
        <div v-if="flash.success" class="mb-6 rounded-lg bg-green-50 p-4 border border-green-200">
          <div class="flex">
            <CheckCircle class="h-5 w-5 text-green-500" />
            <p class="ml-3 text-sm font-medium text-green-800">
              {{ flash.success }}
            </p>
          </div>
        </div>

        <div v-if="flash.error" class="mb-6 rounded-lg bg-red-50 p-4 border border-red-200">
          <div class="flex">
            <AlertTriangle class="h-5 w-5 text-red-500" />
            <p class="ml-3 text-sm font-medium text-red-800">
              {{ flash.error }}
            </p>
          </div>
        </div>

        <!-- Active Theme -->
        <div v-if="activeTheme" class="mb-12">
          <h2 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-4">Current Theme</h2>
          <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden flex flex-col md:flex-row">
            <!-- Screenshot -->
            <div class="md:w-1/2 lg:w-2/5 aspect-video bg-gray-100 relative group overflow-hidden">
                <img
                  v-if="activeTheme.screenshot"
                  :src="activeTheme.screenshot"
                  :alt="activeTheme.name"
                  class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-105"
                />
                <div v-else class="w-full h-full flex flex-col items-center justify-center bg-gradient-to-br from-blue-600 to-indigo-700 gap-3">
                  <div class="w-16 h-16 rounded-2xl bg-white/20 flex items-center justify-center">
                    <Monitor class="w-9 h-9 text-white/80" />
                  </div>
                  <span class="text-white font-bold text-lg tracking-wide">{{ activeTheme.name }}</span>
                </div>
                <div class="absolute top-4 left-4">
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-500 text-white shadow-sm ring-2 ring-white">
                    <CheckCircle class="w-3.5 h-3.5 mr-1" />
                    Active
                  </span>
                </div>
            </div>
            
            <!-- Details -->
            <div class="p-6 md:p-8 md:w-1/2 lg:w-3/5 flex flex-col justify-center">
              <div class="flex items-start justify-between">
                <div>
                  <h3 class="text-2xl font-bold text-gray-900 mb-2">{{ activeTheme.name }}</h3>
                  <p class="text-gray-500 mb-4 text-base leading-relaxed">{{ activeTheme.description }}</p>
                  <div class="flex items-center gap-4 text-sm text-gray-500">
                    <div class="flex items-center">
                      <span class="font-medium text-gray-900 mr-1">Version:</span> {{ activeTheme.version }}
                    </div>
                    <div class="w-1 h-1 bg-gray-300 rounded-full"></div>
                    <div class="flex items-center">
                      <span class="font-medium text-gray-900 mr-1">Author:</span> {{ activeTheme.author }}
                    </div>
                  </div>
                </div>
              </div>

              <div class="mt-8 flex flex-wrap gap-3">
                <a
                  href="/"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-flex items-center px-5 py-2.5 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-300 transition-colors"
                  title="Preview active theme"
                >
                  <Eye class="w-4 h-4 mr-2" />
                  Preview
                </a>
                <button
                  @click="goToSettings(activeTheme.slug)"
                  class="inline-flex items-center px-5 py-2.5 border border-transparent shadow-sm text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                >
                  <Settings class="w-4 h-4 mr-2" />
                  Customize
                </button>
                <!-- Layout is live → link to page builder -->
                <a
                  v-if="activeTheme.homepageEditor?.showPublishedBanner"
                  :href="activeTheme.homepageEditor.editorUrl"
                  class="inline-flex items-center gap-2 px-5 py-2.5 border border-green-300 text-sm font-medium rounded-lg text-green-700 bg-green-50 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-400 transition-colors"
                >
                  <Wand2 class="w-4 h-4" />
                  {{ activeTheme.homepageEditor.editorLabel }}
                  <ChevronRight class="w-3.5 h-3.5 opacity-60" />
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State (Only if no active theme AND no other themes) -->
        <div
          v-if="props.themes.length === 0"
          class="text-center py-20 bg-white rounded-xl border border-dashed border-gray-300 cursor-pointer hover:bg-gray-50 transition-colors"
        >
          <Package class="mx-auto h-12 w-12 text-gray-400" />
          <h3 class="mt-4 text-lg font-medium text-gray-900">No themes installed</h3>
          <p class="mt-2 text-sm text-gray-500">No theme is set up yet.</p>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
