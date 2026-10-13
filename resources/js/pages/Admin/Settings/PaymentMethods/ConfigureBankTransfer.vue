<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { Plus, Trash2, ArrowLeft, ShieldCheck } from 'lucide-vue-next'

interface TransferDestination {
  id: string
  label: string
  provider: string
  type: 'bank' | 'wallet'
  account_name: string
  account_number: string
  instructions: string
  is_active: boolean
}

interface PaymentMethod {
  id: number
  code: string
  name: string
  description: string
  is_active: boolean
  instructions: string
  configuration: Record<string, any>
}

const props = defineProps<{ method: PaymentMethod }>()
const page = usePage()
const errors = computed(() => page.props.errors as Record<string, string>)

const initialDestinations = (): TransferDestination[] => {
  const configured = props.method.configuration?.account_destinations
  if (Array.isArray(configured)) {
    return configured.map((destination: any, index: number) => ({
      id: String(destination.id || `destination-${index + 1}`),
      label: String(destination.label || ''),
      provider: String(destination.provider || ''),
      type: destination.type === 'wallet' ? 'wallet' : 'bank',
      account_name: String(destination.account_name || ''),
      account_number: String(destination.account_number || ''),
      instructions: String(destination.instructions || ''),
      is_active: destination.is_active !== false && destination.is_active !== 0,
    }))
  }

  // Backwards-compatible prefill for older single-bank configurations.
  const legacy = props.method.configuration || {}
  if (legacy.account_number) {
    const bankName = String(legacy.bank_name || 'Bank transfer')
    return [{
      id: 'legacy-transfer-account',
      label: bankName,
      provider: bankName,
      type: 'bank',
      account_name: String(legacy.account_holder_name || legacy.account_name || ''),
      account_number: String(legacy.account_number),
      instructions: '',
      is_active: true,
    }]
  }

  return []
}

const form = useForm({
  name: props.method.name || 'Manual Bank / Wallet Transfer',
  description: props.method.description || '',
  instructions: props.method.instructions || '',
  configuration: {
    account_destinations: initialDestinations(),
    min_order_amount: Number(props.method.configuration?.min_order_amount ?? 0),
    max_order_amount: props.method.configuration?.max_order_amount ?? null,
    verification_days: Number(props.method.configuration?.verification_days ?? 3),
    allowed_countries: props.method.configuration?.allowed_countries || ['*'],
  },
})

const addDestination = () => {
  const id = `transfer-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`
  form.configuration.account_destinations.push({
    id,
    label: '',
    provider: '',
    type: 'wallet',
    account_name: '',
    account_number: '',
    instructions: '',
    is_active: true,
  })
}

const removeDestination = (index: number) => {
  form.configuration.account_destinations.splice(index, 1)
}

const destinationError = (index: number, field: string) => {
  return errors.value[`configuration.account_destinations.${index}.${field}`]
}

const save = () => {
  form.post('/admin/settings/payment-methods/bank_transfer/save', {
    preserveScroll: true,
  })
}
</script>

<template>
  <AdminLayout title="Manual Bank / Wallet Transfer">
    <Head title="Configure Manual Transfer" />

    <div class="p-6 max-w-5xl mx-auto space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Manual transfer configuration</h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Add the bank and mobile-wallet accounts customers can pay at checkout.
          </p>
        </div>
        <Link href="/admin/settings/payment-methods" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
          <ArrowLeft class="w-4 h-4" /> Back to payment methods
        </Link>
      </div>

      <form @submit.prevent="save" class="space-y-6">
        <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6 space-y-5">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Checkout display</h2>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment method name</label>
            <input v-model="form.name" type="text" maxlength="255" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-gray-900 dark:text-white" />
            <p v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
            <textarea v-model="form.description" rows="2" maxlength="1000" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-gray-900 dark:text-white" placeholder="Pay to a bank or mobile wallet and upload your receipt." />
            <p v-if="errors.description" class="mt-1 text-sm text-red-600">{{ errors.description }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Instructions shown at checkout</label>
            <textarea v-model="form.instructions" rows="3" maxlength="2000" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-gray-900 dark:text-white" placeholder="Transfer the exact order total, then upload a screenshot of the receipt. We will verify it before confirming payment." />
            <p v-if="errors.instructions" class="mt-1 text-sm text-red-600">{{ errors.instructions }}</p>
          </div>
        </section>

        <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6 space-y-5">
          <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
            <div>
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Receiving accounts</h2>
              <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Examples: CBE, Telebirr, or another account your business controls. Only active destinations are shown at checkout.</p>
            </div>
            <button type="button" @click="addDestination" class="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700">
              <Plus class="w-4 h-4" /> Add account
            </button>
          </div>
          <p v-if="errors['configuration.account_destinations']" class="text-sm text-red-600">{{ errors['configuration.account_destinations'] }}</p>

          <div v-if="form.configuration.account_destinations.length" class="space-y-4">
            <article v-for="(destination, index) in form.configuration.account_destinations" :key="destination.id" class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 space-y-4">
              <div class="flex items-center justify-between gap-3">
                <h3 class="font-medium text-gray-900 dark:text-white">Destination {{ index + 1 }}</h3>
                <div class="flex items-center gap-4">
                  <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input v-model="destination.is_active" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                    Active
                  </label>
                  <button type="button" @click="removeDestination(index)" class="inline-flex items-center gap-1 text-sm text-red-600 hover:text-red-700">
                    <Trash2 class="w-4 h-4" /> Remove
                  </button>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Customer-facing label</label>
                  <input v-model="destination.label" type="text" maxlength="100" placeholder="e.g. CBE — Main account" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-gray-900 dark:text-white" />
                  <p v-if="destinationError(index, 'label')" class="mt-1 text-sm text-red-600">{{ destinationError(index, 'label') }}</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Bank or wallet provider</label>
                  <input v-model="destination.provider" type="text" maxlength="100" placeholder="e.g. CBE or Telebirr" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-gray-900 dark:text-white" />
                  <p v-if="destinationError(index, 'provider')" class="mt-1 text-sm text-red-600">{{ destinationError(index, 'provider') }}</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Destination type</label>
                  <select v-model="destination.type" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-gray-900 dark:text-white">
                    <option value="bank">Bank account</option>
                    <option value="wallet">Mobile wallet</option>
                  </select>
                  <p v-if="destinationError(index, 'type')" class="mt-1 text-sm text-red-600">{{ destinationError(index, 'type') }}</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Account holder</label>
                  <input v-model="destination.account_name" type="text" maxlength="150" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-gray-900 dark:text-white" />
                  <p v-if="destinationError(index, 'account_name')" class="mt-1 text-sm text-red-600">{{ destinationError(index, 'account_name') }}</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Account / wallet number</label>
                  <input v-model="destination.account_number" type="text" maxlength="100" autocomplete="off" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-gray-900 dark:text-white" />
                  <p v-if="destinationError(index, 'account_number')" class="mt-1 text-sm text-red-600">{{ destinationError(index, 'account_number') }}</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Extra customer instructions (optional)</label>
                  <textarea v-model="destination.instructions" rows="2" maxlength="500" placeholder="Branch, wallet name, or payment note" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-gray-900 dark:text-white" />
                  <p v-if="destinationError(index, 'instructions')" class="mt-1 text-sm text-red-600">{{ destinationError(index, 'instructions') }}</p>
                </div>
              </div>
            </article>
          </div>
          <div v-else class="rounded-lg border border-dashed border-gray-300 dark:border-gray-600 p-8 text-center text-sm text-gray-500 dark:text-gray-400">
            No receiving accounts yet. Add at least one account before saving.
          </div>
        </section>

        <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6 space-y-5">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Availability</h2>
          <p class="text-sm text-gray-600 dark:text-gray-400">Customers transfer the order total shown at checkout; this manual method does not add a processing fee.</p>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Minimum order amount</label>
              <input v-model.number="form.configuration.min_order_amount" type="number" min="0" step="0.01" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-gray-900 dark:text-white" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Maximum order amount (optional)</label>
              <input v-model.number="form.configuration.max_order_amount" type="number" min="0" step="0.01" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-gray-900 dark:text-white" />
            </div>
          </div>
          <p v-if="errors['configuration.max_order_amount']" class="text-sm text-red-600">{{ errors['configuration.max_order_amount'] }}</p>
        </section>

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 rounded-xl border border-amber-200 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-800 p-4">
          <div class="flex items-start gap-3">
            <ShieldCheck class="w-5 h-5 text-amber-700 dark:text-amber-300 mt-0.5" />
            <p class="text-sm text-amber-900 dark:text-amber-200">Screenshots are treated as evidence only. Staff must verify the actual incoming transfer before approving payment. Enable or disable the whole method from the Payment Methods list.</p>
          </div>
          <button type="submit" :disabled="form.processing" class="shrink-0 px-5 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 disabled:opacity-50">
            {{ form.processing ? 'Saving…' : 'Save configuration' }}
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
