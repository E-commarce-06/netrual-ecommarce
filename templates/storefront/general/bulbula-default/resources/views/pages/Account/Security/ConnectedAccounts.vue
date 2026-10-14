<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import ThemeLayout from '../../../layouts/ThemeLayout.vue';
import { computed } from 'vue';

interface Props {
  google: {
    linked: boolean;
    available: boolean;
  };
}

defineProps<Props>();
const page = usePage<{ csrf_token?: string; flash?: { error?: string | null; success?: string | null; info?: string | null }; errors?: Record<string, string> }>();
const csrfToken = computed(() => page.props.csrf_token ?? '');
const currentPasswordError = computed(() => page.props.errors?.current_password ?? '');
const flashMessage = computed(() => page.props.flash?.error || page.props.flash?.success || page.props.flash?.info || '');
</script>

<template>
  <ThemeLayout>
    <Head title="Connected accounts" />

    <main class="container mx-auto px-4 py-8">
      <div class="mx-auto max-w-3xl">
        <a href="/account/profile" class="mb-5 inline-flex items-center text-sm text-blue-700 hover:underline">
          <span aria-hidden="true" class="mr-2">←</span> Back to profile
        </a>

        <header class="mb-8">
          <h1 class="text-3xl font-bold text-gray-900">Connected accounts</h1>
          <p class="mt-2 text-gray-600">Manage the sign-in methods connected to your customer account.</p>
        </header>

        <div
          v-if="flashMessage"
          role="status"
          class="mb-6 rounded-lg border px-4 py-3 text-sm"
          :class="page.props.flash?.error ? 'border-red-200 bg-red-50 text-red-700' : 'border-green-200 bg-green-50 text-green-700'"
        >
          {{ flashMessage }}
        </div>

        <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-4">
              <div class="flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-white" aria-hidden="true">
                <svg class="h-6 w-6" viewBox="0 0 48 48">
                  <path fill="#4285F4" d="M43.6 24.5c0-1.4-.1-2.7-.4-4H24v7.5h11a9.4 9.4 0 0 1-4.1 6.2v5h6.6c3.9-3.6 6.1-8.7 6.1-14.7Z" />
                  <path fill="#34A853" d="M24 44c5.5 0 10.1-1.8 13.5-4.8l-6.6-5c-1.8 1.2-4.1 2-6.9 2-5.3 0-9.8-3.6-11.4-8.4H5.8v5.2A20 20 0 0 0 24 44Z" />
                  <path fill="#FBBC05" d="M12.6 27.8a12 12 0 0 1 0-7.6V15H5.8a20 20 0 0 0 0 18Z" />
                  <path fill="#EA4335" d="M24 11.8c3 0 5.7 1 7.8 3.1l5.8-5.8C34.1 5.8 29.5 4 24 4A20 20 0 0 0 5.8 15l6.8 5.2c1.6-4.8 6.1-8.4 11.4-8.4Z" />
                </svg>
              </div>
              <div>
                <h2 class="text-lg font-semibold text-gray-900">Google</h2>
                <p class="mt-1 text-sm text-gray-600">
                  {{ google.linked ? 'A Google account is connected to this customer account.' : 'Connect a Google account to use it as an additional sign-in method.' }}
                </p>
              </div>
            </div>

            <span
              class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
              :class="google.linked ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'"
            >
              {{ google.linked ? 'Connected' : 'Not connected' }}
            </span>
          </div>

          <div class="mt-6 rounded-lg bg-blue-50 p-4 text-sm text-blue-900">
            For your protection, connecting Google requires your current password and a fresh Google authorization. An existing account is never linked merely because its email matches.
          </div>

          <form
            v-if="!google.linked && google.available"
            method="POST"
            action="/account/security/google/connect"
            class="mt-6 space-y-4"
          >
            <input type="hidden" name="_token" :value="csrfToken" />
            <div>
              <label for="connect_current_password" class="mb-1 block text-sm font-medium text-gray-800">
                Confirm your current password
              </label>
              <input
                id="connect_current_password"
                name="current_password"
                type="password"
                required
                autocomplete="current-password"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
              />
              <p v-if="currentPasswordError" class="mt-1 text-sm text-red-600">{{ currentPasswordError }}</p>
            </div>
            <button type="submit" class="rounded-md bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-gray-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">
              Connect Google
            </button>
          </form>

          <p v-else-if="!google.linked" class="mt-6 text-sm text-gray-600">
            Google sign-in is currently disabled by the store administrator.
          </p>

          <form
            v-else
            method="POST"
            action="/account/security/google"
            class="mt-6 space-y-4"
          >
            <input type="hidden" name="_token" :value="csrfToken" />
            <input type="hidden" name="_method" value="DELETE" />
            <div>
              <label for="disconnect_current_password" class="mb-1 block text-sm font-medium text-gray-800">
                Confirm your current password to disconnect
              </label>
              <input
                id="disconnect_current_password"
                name="current_password"
                type="password"
                required
                autocomplete="current-password"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
              />
              <p v-if="currentPasswordError" class="mt-1 text-sm text-red-600">{{ currentPasswordError }}</p>
              <p class="mt-2 text-xs text-gray-500">
                If you originally created this account with Google and do not know a local password, set one using
                <a href="/forgot-password" class="underline">Forgot password</a> before disconnecting, so you do not lose access.
              </p>
            </div>
            <button type="submit" class="rounded-md border border-red-300 px-5 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-400 focus-visible:ring-offset-2">
              Disconnect Google
            </button>
          </form>
        </section>
      </div>
    </main>
  </ThemeLayout>
</template>
