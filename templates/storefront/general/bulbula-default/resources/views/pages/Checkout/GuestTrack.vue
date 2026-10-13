<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import ThemeLayout from '../../layouts/ThemeLayout.vue'
import UIBlockRenderer from '@/components/UIEditor/UIBlockRenderer.vue'
import { useCurrency } from '@/composables/useCurrency'

interface TrackedOrder {
  id: number
  order_number: string
  status: string
  payment_status: string
  manual_payment_review?: {
    status: 'pending_review' | 'approved' | 'rejected'
    reason?: string | null
    submitted_at?: string | null
    reviewed_at?: string | null
  } | null
  total: number
  created_at: string
  items_count: number
  shipping_address: {
    first_name: string
    last_name: string
    city: string
    state: string
    postal_code: string
    country: string
  } | null
}

const props = defineProps<{
  lookup: { order_number: string }
  trackedOrder: TrackedOrder | null
  error?: string
  layoutData?: Record<string, unknown> | null
}>()

const orderNumber = ref(props.lookup?.order_number ?? '')
const hasLayout = computed(() => !!(props.layoutData?.sections && (props.layoutData.sections as unknown[]).length))

const { formatPrice } = useCurrency()

const submit = () => {
  router.post('/checkout/track-order', {
    order_number: orderNumber.value,
  })
}
</script>

<template>
  <ThemeLayout>
    <Head title="Track Your Order" />

    <!-- UIEditor hero/banner renders above the tracking form when published -->
    <UIBlockRenderer v-if="hasLayout" :layout="layoutData" :editor-mode="false" />

    <div class="container mx-auto px-4 py-8 max-w-2xl">
      <h1 class="text-2xl font-bold mb-2">Track Guest Order</h1>
      <p class="text-gray-600 mb-6">Enter your order ID to check order status.</p>

      <div class="bg-white rounded-lg shadow p-5">
        <label class="block text-sm font-medium mb-2">Order ID</label>
        <div class="flex gap-2">
          <input v-model="orderNumber" type="text" class="flex-1 border rounded px-3 py-2" placeholder="ORD-..." />
          <button @click="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Track</button>
        </div>
        <p v-if="error" class="mt-3 text-sm text-red-600">{{ error }}</p>
      </div>

      <div v-if="trackedOrder" class="bg-white rounded-lg shadow p-5 mt-6">
        <h2 class="text-lg font-semibold mb-3">Order #{{ trackedOrder.order_number }}</h2>
        <div class="space-y-2 text-sm">
          <div class="flex justify-between"><span class="text-gray-600">Status</span><span class="font-medium">{{ trackedOrder.status }}</span></div>
          <div class="flex justify-between"><span class="text-gray-600">Payment Status</span><span>{{ trackedOrder.payment_status }}</span></div>
          <div class="flex justify-between"><span class="text-gray-600">Placed On</span><span>{{ trackedOrder.created_at }}</span></div>
          <div class="flex justify-between"><span class="text-gray-600">Items</span><span>{{ trackedOrder.items_count }}</span></div>
          <div class="flex justify-between"><span class="text-gray-600">Total</span><span class="font-semibold">{{ formatPrice(trackedOrder.total) }}</span></div>
        </div>
        <section v-if="trackedOrder.manual_payment_review" :class="[
          'mt-5 rounded-lg border p-4',
          trackedOrder.manual_payment_review.status === 'approved' ? 'border-green-200 bg-green-50' :
          trackedOrder.manual_payment_review.status === 'rejected' ? 'border-red-200 bg-red-50' :
          'border-amber-200 bg-amber-50'
        ]">
          <h3 class="font-semibold" :class="trackedOrder.manual_payment_review.status === 'approved' ? 'text-green-900' : trackedOrder.manual_payment_review.status === 'rejected' ? 'text-red-900' : 'text-amber-900'">
            {{ trackedOrder.manual_payment_review.status === 'pending_review' ? 'Transfer proof pending staff review' : trackedOrder.manual_payment_review.status === 'approved' ? 'Transfer approved' : 'Transfer proof rejected' }}
          </h3>
          <p v-if="trackedOrder.manual_payment_review.status === 'pending_review'" class="mt-1 text-sm text-amber-800">Your order remains unpaid until staff verify that the funds have settled in the receiving account.</p>
          <p v-else-if="trackedOrder.manual_payment_review.status === 'approved'" class="mt-1 text-sm text-green-800">Staff verified the transfer and confirmed your payment.</p>
          <p v-else class="mt-1 text-sm text-red-800">The submitted proof was not approved. Review the reason below and contact the store if you need help.</p>
          <div v-if="trackedOrder.manual_payment_review.status === 'rejected' && trackedOrder.manual_payment_review.reason" class="mt-3 rounded bg-white/80 p-3 text-sm text-red-900">
            <strong>Staff reason:</strong>
            <p class="mt-1 whitespace-pre-line">{{ trackedOrder.manual_payment_review.reason }}</p>
          </div>
          <p v-if="trackedOrder.manual_payment_review.submitted_at" class="mt-2 text-xs text-gray-600">Proof submitted {{ trackedOrder.manual_payment_review.submitted_at }}</p>
          <p v-if="trackedOrder.manual_payment_review.reviewed_at" class="mt-1 text-xs text-gray-600">Reviewed {{ trackedOrder.manual_payment_review.reviewed_at }}</p>
        </section>
      </div>
    </div>
  </ThemeLayout>
</template>
