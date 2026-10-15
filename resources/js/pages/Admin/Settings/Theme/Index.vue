<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Palette, Save } from 'lucide-vue-next';

interface ThemeField {
    id: string;
    type: string;
    label: string;
    description?: string;
    default?: unknown;
    options?: Record<string, string>;
    min?: number;
    max?: number;
    step?: number;
}

interface ThemeSection {
    id: string;
    title: string;
    description?: string;
    fields: ThemeField[];
}

const props = defineProps<{
    theme: { name: string; slug: string } | null;
    sections: ThemeSection[];
    values: Record<string, unknown>;
}>();

// Build the nested settings object (settings[section][field]) from the
// flattened "section.field" values sent by the server.
const initial: Record<string, Record<string, unknown>> = {};
for (const section of props.sections) {
    initial[section.id] = {};
    for (const field of section.fields) {
        const key = `${section.id}.${field.id}`;
        initial[section.id][field.id] = props.values[key] ?? field.default ?? '';
    }
}

const form = useForm({ settings: initial });

function save() {
    form.post('/admin/settings/theme', { preserveScroll: true });
}
</script>

<template>
    <AdminLayout title="Theme Settings">
        <Head title="Theme Settings" />

        <div class="p-6 space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Theme Settings</h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        <template v-if="theme">
                            Customize the active theme “{{ theme.name }}” — colors, fonts, layout and features apply store-wide, including the homepage hero.
                        </template>
                        <template v-else>No active theme is configured.</template>
                    </p>
                </div>
                <button
                    v-if="theme"
                    type="button"
                    :disabled="form.processing"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition disabled:opacity-60"
                    @click="save"
                >
                    <Save class="w-4 h-4 mr-2" />
                    {{ form.processing ? 'Saving...' : 'Save Changes' }}
                </button>
            </div>

            <div
                v-if="!theme"
                class="rounded-xl border border-yellow-200 bg-yellow-50 px-5 py-4 text-sm text-yellow-800 dark:border-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-200"
            >
                There is no active storefront theme, so there are no theme settings to edit. Activate a theme first.
            </div>

            <form v-else @submit.prevent="save" class="space-y-6">
                <div
                    v-for="section in sections"
                    :key="section.id"
                    class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden"
                >
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800">
                        <h2 class="flex items-center text-base font-semibold text-gray-900 dark:text-white">
                            <Palette class="w-4 h-4 mr-2 text-blue-500" />
                            {{ section.title }}
                        </h2>
                        <p v-if="section.description" class="mt-0.5 text-sm text-gray-600 dark:text-gray-400">{{ section.description }}</p>
                    </div>

                    <div class="px-6 py-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div v-for="field in section.fields" :key="field.id">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                {{ field.label }}
                            </label>

                            <!-- Color -->
                            <div v-if="field.type === 'color'" class="flex items-center gap-2">
                                <input
                                    type="color"
                                    :value="form.settings[section.id][field.id]"
                                    class="h-10 w-12 shrink-0 cursor-pointer rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 p-1"
                                    @input="form.settings[section.id][field.id] = ($event.target as HTMLInputElement).value"
                                />
                                <input
                                    type="text"
                                    :value="form.settings[section.id][field.id]"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    @input="form.settings[section.id][field.id] = ($event.target as HTMLInputElement).value"
                                />
                            </div>

                            <!-- Select -->
                            <select
                                v-else-if="field.type === 'select'"
                                v-model="form.settings[section.id][field.id]"
                                class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                                <option v-for="(label, value) in field.options" :key="value" :value="value">{{ label }}</option>
                            </select>

                            <!-- Number -->
                            <input
                                v-else-if="field.type === 'number'"
                                type="number"
                                :min="field.min"
                                :max="field.max"
                                :step="field.step ?? 1"
                                :value="form.settings[section.id][field.id]"
                                class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                @input="form.settings[section.id][field.id] = Number(($event.target as HTMLInputElement).value)"
                            />

                            <!-- Boolean -->
                            <button
                                v-else-if="field.type === 'boolean'"
                                type="button"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                :class="form.settings[section.id][field.id] ? 'bg-blue-600' : 'bg-gray-300 dark:bg-gray-600'"
                                @click="form.settings[section.id][field.id] = !form.settings[section.id][field.id]"
                            >
                                <span
                                    class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform"
                                    :class="form.settings[section.id][field.id] ? 'translate-x-5' : 'translate-x-1'"
                                />
                            </button>

                            <!-- Text (default) -->
                            <input
                                v-else
                                type="text"
                                :value="form.settings[section.id][field.id]"
                                class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                @input="form.settings[section.id][field.id] = ($event.target as HTMLInputElement).value"
                            />

                            <p v-if="field.description" class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ field.description }}</p>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
