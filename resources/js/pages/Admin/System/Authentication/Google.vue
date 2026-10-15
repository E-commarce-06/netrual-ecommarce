<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import { CheckCircle2, KeyRound, Save, ShieldCheck, TriangleAlert } from 'lucide-vue-next'

interface Settings {
  enabled: boolean
  active_environment: 'test' | 'live'
  web_enabled: boolean
  api_enabled: boolean
  registration_enabled: boolean
  max_id_token_age_minutes: number
  test_client_id: string
  test_client_secret_configured: boolean
  test_api_audiences_text: string
  live_client_id: string
  live_client_secret_configured: boolean
  live_api_audiences_text: string
  active_web_ready: boolean
  active_api_ready: boolean
}

const props = defineProps<{
  settings: Settings
  callbackUrl: string
}>()

const formDataFromSettings = (settings: Settings) => ({
  enabled: settings.enabled,
  active_environment: settings.active_environment,
  web_enabled: settings.web_enabled,
  api_enabled: settings.api_enabled,
  registration_enabled: settings.registration_enabled,
  max_id_token_age_minutes: settings.max_id_token_age_minutes,
  test_client_id: settings.test_client_id,
  test_client_secret: '',
  clear_test_client_secret: false,
  test_api_audiences_text: settings.test_api_audiences_text,
  live_client_id: settings.live_client_id,
  live_client_secret: '',
  clear_live_client_secret: false,
  live_api_audiences_text: settings.live_api_audiences_text,
})

const form = useForm(formDataFromSettings(props.settings))

const testWebReady = computed(() => {
  const savedSecretRemains = props.settings.test_client_secret_configured
    && !form.clear_test_client_secret
    && form.test_client_id.trim() === props.settings.test_client_id.trim()

  return Boolean(form.test_client_id.trim() && (form.test_client_secret.trim() || savedSecretRemains))
})

const liveWebReady = computed(() => {
  const savedSecretRemains = props.settings.live_client_secret_configured
    && !form.clear_live_client_secret
    && form.live_client_id.trim() === props.settings.live_client_id.trim()

  return Boolean(form.live_client_id.trim() && (form.live_client_secret.trim() || savedSecretRemains))
})
const testApiReady = computed(() => form.test_api_audiences_text.trim() !== '')
const liveApiReady = computed(() => form.live_api_audiences_text.trim() !== '')

const activeProfileReady = computed(() => {
  if (!form.enabled) return true
  if (!form.web_enabled && !form.api_enabled) return false
  if (form.web_enabled && !(form.active_environment === 'test' ? testWebReady.value : liveWebReady.value)) return false
  if (form.api_enabled && !(form.active_environment === 'test' ? testApiReady.value : liveApiReady.value)) return false
  return true
})

const activeStatusLabel = computed(() => {
  if (!form.enabled) return 'Feature disabled'
  if (!form.web_enabled && !form.api_enabled) return 'No sign-in channel enabled'
  return activeProfileReady.value ? 'Active profile ready' : 'Active profile incomplete'
})

const save = () => {
  form.put('/admin/system/authentication/google', {
    preserveScroll: true,
    onSuccess: (page) => {
      const flash = page.props.flash as { success?: string | null; error?: string | null } | undefined

      // A redirect with a flash error is still an HTTP/Inertia success. Do not clear
      // secrets or reset the form unless the server confirms the database write.
      if (!flash?.success || flash.error) return

      form.test_client_secret = ''
      form.live_client_secret = ''
      form.clear_test_client_secret = false
      form.clear_live_client_secret = false

      const savedSettings = page.props.settings as Settings | undefined
      if (!savedSettings) return

      form.defaults(formDataFromSettings(savedSettings))
      form.reset()
    },
  })
}
</script>

<template>
  <AdminLayout>
    <Head title="Google authentication settings" />

    <div class="mx-auto max-w-6xl space-y-6 p-4 sm:p-6 lg:p-8">
      <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p class="text-sm font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-500">System</p>
          <h1 class="mt-1 text-2xl font-bold text-gray-900 sm:text-3xl dark:text-gray-400">Google authentication</h1>
          <p class="mt-2 max-w-3xl text-sm text-gray-600 dark:text-gray-400">
            Configure customer Google sign-in for the storefront and API/mobile clients. Admin sign-in is not affected.
          </p>
        </div>
        <div class="flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-semibold"
             :class="!form.enabled ? 'bg-gray-100 text-gray-700' : activeProfileReady ? 'bg-green-50 text-green-800' : 'bg-amber-50 text-amber-800'">
          <CheckCircle2 v-if="form.enabled && activeProfileReady" class="h-4 w-4" />
          <TriangleAlert v-else-if="form.enabled" class="h-4 w-4" />
          {{ activeStatusLabel }}
        </div>
      </header>

      <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-950">
        <div class="flex gap-3">
          <ShieldCheck class="mt-0.5 h-5 w-5 shrink-0 text-blue-700" />
          <div class="space-y-1">
            <p class="font-semibold">Security policies enforced by the server</p>
            <p>Existing accounts are never linked by matching email. Linking requires a signed-in customer, their current password, and fresh Google authorization. Admin and inactive accounts cannot use Google sign-in.</p>
            <p>Google's verified-email claim updates the local verification timestamp when present; it is not a sign-in requirement. This feature adds no terms-acceptance requirement.</p>
          </div>
        </div>
      </div>

      <div v-if="!form.enabled" role="status" class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
        <p class="font-semibold">Google sign-in is currently off.</p>
        <p class="mt-1">
          Saving credentials does not activate sign-in. To enable the selected {{ form.active_environment }} profile, turn on “Enable Google authentication” below and save again.
        </p>
        <p v-if="form.active_environment === 'test'" class="mt-1">
          The test profile may be limited to users listed as test users in Google Cloud. Use the live profile for public customer sign-in after its web client is configured.
        </p>
      </div>

      <form @submit.prevent="save" class="space-y-6">
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
          <div class="mb-5 flex items-center gap-3">
            <div class="rounded-lg bg-blue-50 p-2 text-blue-700"><ShieldCheck class="h-5 w-5" /></div>
            <div>
              <h2 class="text-lg font-semibold text-gray-900">Feature and policy</h2>
              <p class="text-sm text-gray-600">Changes apply to new sign-in attempts after saving.</p>
            </div>
          </div>

          <div class="grid gap-5 md:grid-cols-2">
            <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-4">
              <input v-model="form.enabled" type="checkbox" class="mt-1 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
              <span>
                <span class="block font-medium text-gray-900">Enable Google authentication</span>
                <span class="mt-1 block text-sm text-gray-600">Master switch for the storefront and API/mobile Google flows.</span>
              </span>
            </label>

            <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-4">
              <input v-model="form.registration_enabled" type="checkbox" class="mt-1 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
              <span>
                <span class="block font-medium text-gray-900">Allow new customer accounts</span>
                <span class="mt-1 block text-sm text-gray-600">Existing linked customers may still sign in when this is off.</span>
              </span>
            </label>

            <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-4">
              <input v-model="form.web_enabled" type="checkbox" class="mt-1 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
              <span>
                <span class="block font-medium text-gray-900">Storefront web sign-in</span>
                <span class="mt-1 block text-sm text-gray-600">Uses Google's authorization-code flow and the existing web session.</span>
              </span>
            </label>

            <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-4">
              <input v-model="form.api_enabled" type="checkbox" class="mt-1 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
              <span>
                <span class="block font-medium text-gray-900">API/mobile sign-in</span>
                <span class="mt-1 block text-sm text-gray-600">Verifies Google ID tokens server-side, then issues a short-lived Sanctum token.</span>
              </span>
            </label>
          </div>

          <div class="mt-5 grid gap-5 md:grid-cols-2">
            <div>
              <label for="active_environment" class="mb-1 block text-sm font-medium text-gray-800">Active configuration</label>
              <select id="active_environment" v-model="form.active_environment" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                <option value="test">Test</option>
                <option value="live">Live</option>
              </select>
              <p v-if="form.errors.active_environment" class="mt-1 text-sm text-red-600">{{ form.errors.active_environment }}</p>
              <p class="mt-1 text-xs text-gray-500">This selects both the web OAuth credentials and API token audiences.</p>
            </div>

            <div>
              <label for="max_id_token_age_minutes" class="mb-1 block text-sm font-medium text-gray-800">Maximum API ID-token age</label>
              <div class="flex items-center gap-2">
                <input id="max_id_token_age_minutes" v-model.number="form.max_id_token_age_minutes" type="number" min="1" max="60" required class="w-28 rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" />
                <span class="text-sm text-gray-600">minutes</span>
              </div>
              <p v-if="form.errors.max_id_token_age_minutes" class="mt-1 text-sm text-red-600">{{ form.errors.max_id_token_age_minutes }}</p>
              <p class="mt-1 text-xs text-gray-500">The server also checks the signature, issuer, exact audience, nonce, and token expiry.</p>
            </div>
          </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
          <div class="mb-5 flex items-center gap-3">
            <div class="rounded-lg bg-gray-100 p-2 text-gray-700"><KeyRound class="h-5 w-5" /></div>
            <div>
              <h2 class="text-lg font-semibold text-gray-900">Test and live Google clients</h2>
              <p class="text-sm text-gray-600">Use separate OAuth clients and API audiences for each profile.</p>
            </div>
          </div>

          <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
            “Test” is a separate Google OAuth configuration, not an offline sandbox. Set the Google consent screen to Testing and add test users when appropriate. The callback URI must be registered in both web clients.
          </div>

          <div class="grid gap-6 xl:grid-cols-2">
            <div class="space-y-4 rounded-lg border border-gray-200 p-4">
              <div class="flex items-center justify-between">
                <h3 class="font-semibold text-gray-900">Test profile</h3>
                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700">Non-production client</span>
              </div>

              <div>
                <label for="test_client_id" class="mb-1 block text-sm font-medium text-gray-800">Web OAuth client ID</label>
                <input id="test_client_id" v-model="form.test_client_id" type="text" autocomplete="off" spellcheck="false" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" />
                <p v-if="form.errors.test_client_id" class="mt-1 text-sm text-red-600">{{ form.errors.test_client_id }}</p>
              </div>

              <div>
                <label for="test_client_secret" class="mb-1 block text-sm font-medium text-gray-800">Web OAuth client secret</label>
                <input id="test_client_secret" v-model="form.test_client_secret" type="password" autocomplete="new-password" :placeholder="settings.test_client_secret_configured ? 'Saved; leave blank to keep it' : 'Enter the Google client secret'" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" />
                <p v-if="form.errors.test_client_secret" class="mt-1 text-sm text-red-600">{{ form.errors.test_client_secret }}</p>
                <p v-if="settings.test_client_secret_configured && form.test_client_id.trim() === settings.test_client_id.trim() && !form.test_client_secret.trim()" class="mt-1 text-xs font-medium text-green-700">
                  Secret saved securely. This password field is blank by design; leave it blank to keep the saved secret.
                </p>
                <label v-if="settings.test_client_secret_configured" class="mt-2 inline-flex items-center gap-2 text-xs text-gray-600">
                  <input v-model="form.clear_test_client_secret" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-red-600 focus:ring-red-500" />
                  Remove saved test secret
                </label>
              </div>

              <div>
                <label for="test_api_audiences_text" class="mb-1 block text-sm font-medium text-gray-800">API/mobile client ID audiences</label>
                <textarea id="test_api_audiences_text" v-model="form.test_api_audiences_text" rows="3" spellcheck="false" placeholder="One Google OAuth client ID per line" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"></textarea>
                <p v-if="form.errors.test_api_audiences_text" class="mt-1 text-sm text-red-600">{{ form.errors.test_api_audiences_text }}</p>
                <p class="mt-1 text-xs text-gray-500">Add every client ID that may request an ID token for the API, including Android/iOS clients.</p>
              </div>
            </div>

            <div class="space-y-4 rounded-lg border border-gray-200 p-4">
              <div class="flex items-center justify-between">
                <h3 class="font-semibold text-gray-900">Live profile</h3>
                <span class="rounded-full bg-green-50 px-2.5 py-1 text-xs font-medium text-green-800">Production client</span>
              </div>

              <div>
                <label for="live_client_id" class="mb-1 block text-sm font-medium text-gray-800">Web OAuth client ID</label>
                <input id="live_client_id" v-model="form.live_client_id" type="text" autocomplete="off" spellcheck="false" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" />
                <p v-if="form.errors.live_client_id" class="mt-1 text-sm text-red-600">{{ form.errors.live_client_id }}</p>
              </div>

              <div>
                <label for="live_client_secret" class="mb-1 block text-sm font-medium text-gray-800">Web OAuth client secret</label>
                <input id="live_client_secret" v-model="form.live_client_secret" type="password" autocomplete="new-password" :placeholder="settings.live_client_secret_configured ? 'Saved; leave blank to keep it' : 'Enter the Google client secret'" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" />
                <p v-if="form.errors.live_client_secret" class="mt-1 text-sm text-red-600">{{ form.errors.live_client_secret }}</p>
                <p v-if="settings.live_client_secret_configured && form.live_client_id.trim() === settings.live_client_id.trim() && !form.live_client_secret.trim()" class="mt-1 text-xs font-medium text-green-700">
                  Secret saved securely. This password field is blank by design; leave it blank to keep the saved secret.
                </p>
                <label v-if="settings.live_client_secret_configured" class="mt-2 inline-flex items-center gap-2 text-xs text-gray-600">
                  <input v-model="form.clear_live_client_secret" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-red-600 focus:ring-red-500" />
                  Remove saved live secret
                </label>
              </div>

              <div>
                <label for="live_api_audiences_text" class="mb-1 block text-sm font-medium text-gray-800">API/mobile client ID audiences</label>
                <textarea id="live_api_audiences_text" v-model="form.live_api_audiences_text" rows="3" spellcheck="false" placeholder="One Google OAuth client ID per line" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"></textarea>
                <p v-if="form.errors.live_api_audiences_text" class="mt-1 text-sm text-red-600">{{ form.errors.live_api_audiences_text }}</p>
                <p class="mt-1 text-xs text-gray-500">Use production Android/iOS client IDs; these must match the token audience exactly.</p>
              </div>
            </div>
          </div>

          <div class="mt-5 rounded-lg bg-gray-50 p-4">
            <label for="callback_url" class="mb-1 block text-sm font-medium text-gray-800">Registered web callback URI</label>
            <input id="callback_url" :value="callbackUrl" readonly class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 font-mono text-sm text-gray-700" />
            <p class="mt-1 text-xs text-gray-500">Copy this exact URI into the Authorized redirect URIs for both web OAuth clients. It is derived from the configured application URL.</p>
          </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
          <h2 class="text-lg font-semibold text-gray-900">Secret handling</h2>
          <ul class="mt-3 list-inside list-disc space-y-2 text-sm text-gray-600">
            <li>Client secrets are encrypted in the database and are never returned to this page; leave the password field blank to keep a saved value.</li>
            <li>Encryption depends on the Laravel application key. After key rotation, configure <code>APP_PREVIOUS_KEYS</code> or re-enter the secrets.</li>
            <li>Do not save OAuth access tokens or refresh tokens here. The sign-in flow requests only identity scopes.</li>
          </ul>
        </section>

        <div class="flex flex-wrap items-center justify-end gap-3">
          <p v-if="form.hasErrors" role="alert" class="mr-auto text-sm text-red-700 dark:text-red-500">Please correct the highlighted settings.</p>
          <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-md bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">
            <Save class="h-4 w-4" />
            {{ form.processing ? 'Saving…' : 'Save configuration' }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
