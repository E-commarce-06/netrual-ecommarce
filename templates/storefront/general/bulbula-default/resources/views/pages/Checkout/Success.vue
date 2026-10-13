<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useCurrency } from '@/composables/useCurrency';
import ThemeLayout from '../../layouts/ThemeLayout.vue';

interface OrderItem {
  product_name: string;
  product_image?: string;
  quantity: number;
  price: number;
  total: number;
}

interface ShippingAddress {
  first_name: string;
  last_name: string;
  address_line1: string;
  address_line2?: string;
  city: string;
  state: string;
  postal_code: string;
  country: string;
  phone: string;
}

interface Order {
  id: number;
  order_number: string;
  status: string;
  payment_status: string;
  payment_method: string;
  subtotal: number;
  tax: number;
  shipping_cost: number;
  total: number;
  customer_email: string;
  customer_phone?: string;
  created_at: string;
  items: OrderItem[];
  shipping_address: ShippingAddress | null;
  is_guest?: boolean;
}

interface ManualAccount {
  id: string;
  label: string;
  provider: string;
  type: 'bank' | 'wallet';
  account_name: string;
  account_number: string;
  instructions?: string;
}

interface ManualPayment {
  status?: 'pending_review' | 'approved' | 'rejected' | null;
  transfer_reference?: string | null;
  review_note?: string | null;
  submitted_at?: string | null;
  accounts: ManualAccount[];
  can_resubmit: boolean;
}

interface Props {
  order: Order;
  manualPayment?: ManualPayment | null;
}

const props = defineProps<Props>();
const page = usePage();

const { formatPrice } = useCurrency();
const replacementProof = ref<File | null>(null);
const replacementReference = ref('');
const replacementAccountId = ref(props.manualPayment?.accounts?.[0]?.id || '');
const replacementProcessing = ref(false);
const replacementError = ref('');

watch(() => props.manualPayment?.accounts, (accounts) => {
  if (!accounts?.some(account => account.id === replacementAccountId.value)) {
    replacementAccountId.value = accounts?.[0]?.id || '';
  }
});

const selectReplacementProof = (event: Event) => {
  const input = event.target as HTMLInputElement;
  replacementProof.value = input.files?.[0] || null;
  replacementError.value = '';
};

const submitReplacementProof = () => {
  replacementError.value = '';
  if (!replacementAccountId.value) {
    replacementError.value = 'Choose the bank or wallet account you paid.';
    return;
  }
  if (!replacementProof.value) {
    replacementError.value = 'Select a screenshot of the replacement transfer receipt.';
    return;
  }
  if (!['image/jpeg', 'image/png', 'image/webp'].includes(replacementProof.value.type)) {
    replacementError.value = 'Choose a JPEG, PNG, or WebP image.';
    return;
  }
  if (replacementProof.value.size > 5 * 1024 * 1024) {
    replacementError.value = 'The screenshot must be 5 MB or smaller.';
    return;
  }

  replacementProcessing.value = true;
  router.post(`/checkout/success/${props.order.id}/manual-payment`, {
    manual_payment_account_id: replacementAccountId.value,
    manual_payment_reference: replacementReference.value || null,
    payment_proof: replacementProof.value,
  }, {
    forceFormData: true,
    preserveScroll: true,
    onError: (errors) => {
      replacementError.value = errors.payment_proof
        || errors.manual_payment_account_id
        || errors.manual_payment_reference
        || 'The replacement proof could not be submitted.';
    },
    onFinish: () => {
      replacementProcessing.value = false;
    },
  });
};

const isAuthenticated = computed(() => {
  const auth = (page.props as any).auth;
  return !!(auth && auth.user);
});
</script>

<template>
  <ThemeLayout>
    <div class="container mx-auto px-4 py-8">
      <div class="max-w-3xl mx-auto">
        <div v-if="$page.props.flash?.error" class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
          {{ $page.props.flash.error }}
        </div>
        <!-- Success Header -->
        <div class="text-center mb-8">
          <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
          </div>
          <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ order.payment_method === 'bank_transfer' && order.payment_status !== 'paid' ? 'Order received' : 'Order Confirmed!' }}</h1>
          <p class="text-gray-600">{{ order.payment_method === 'bank_transfer' && order.payment_status !== 'paid' ? 'Your order is waiting for manual payment verification.' : 'Thank you for your purchase' }}</p>
        </div>

        <!-- Order Details Card -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
          <div class="border-b pb-6 mb-6">
            <div class="flex justify-between items-start mb-4">
              <div>
                <p class="text-sm text-gray-600">Order Number</p>
                <p class="text-xl font-bold text-gray-900">{{ order.order_number }}</p>
              </div>
              <div class="text-right">
                <p class="text-sm text-gray-600">Order Date</p>
                <p class="font-medium">{{ order.created_at }}</p>
              </div>
            </div>
            
            <div v-if="order.payment_method === 'bank_transfer' && order.payment_status !== 'paid'" class="bg-amber-50 border border-amber-200 rounded-lg p-4">
              <p class="text-sm text-amber-900"><strong>Payment is not confirmed yet</strong></p>
              <p class="text-sm text-amber-800 mt-1">We received your screenshot. Staff will compare it with the actual incoming transaction before confirming payment.</p>
            </div>
            <div v-else class="bg-blue-50 border border-blue-200 rounded-lg p-4">
              <p class="text-sm text-blue-800"><strong>Confirmation Email Sent</strong></p>
              <p class="text-sm text-blue-700 mt-1">We've sent a confirmation email to <strong>{{ order.customer_email }}</strong> with your order details.</p>
            </div>
          </div>

          <section v-if="order.payment_method === 'bank_transfer'" class="mb-6 rounded-xl border p-5" :class="manualPayment?.status === 'approved' ? 'border-green-200 bg-green-50' : manualPayment?.status === 'rejected' ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50'">
            <div v-if="manualPayment?.status === 'approved'">
              <h2 class="font-bold text-green-900">Transfer verified</h2>
              <p class="mt-1 text-sm text-green-800">Staff approved the transfer. Your order has moved to the normal processing workflow.</p>
            </div>
            <div v-else-if="manualPayment?.status === 'rejected'">
              <h2 class="font-bold text-red-900">More information is needed</h2>
              <p class="mt-1 text-sm text-red-800">The submitted proof was not approved. Review the staff note below, make or confirm the correct transfer, and submit a new receipt.</p>
              <div v-if="manualPayment.review_note" class="mt-3 rounded-lg bg-white/70 p-3 text-sm text-red-900">
                <strong>Staff reason:</strong> {{ manualPayment.review_note }}
              </div>
            </div>
            <div v-else>
              <h2 class="font-bold text-amber-900">Transfer proof under review</h2>
              <p class="mt-1 text-sm text-amber-800">Your order remains unpaid and will not be processed until staff verify the transfer. Do not send passwords, PINs, or one-time codes to staff.</p>
            </div>
            <p v-if="manualPayment?.transfer_reference" class="mt-2 text-sm text-gray-700">Your transfer reference: <strong>{{ manualPayment.transfer_reference }}</strong></p>
            <p v-if="manualPayment?.submitted_at" class="mt-1 text-xs text-gray-600">Proof submitted {{ manualPayment.submitted_at }}</p>

            <form v-if="manualPayment?.can_resubmit" @submit.prevent="submitReplacementProof" class="mt-5 space-y-4 border-t border-red-200 pt-4">
              <div>
                <label class="mb-2 block text-sm font-medium text-gray-800">Pay-to account for the replacement transfer</label>
                <label v-for="account in manualPayment.accounts" :key="account.id" class="mb-2 flex cursor-pointer gap-3 rounded-lg border bg-white p-3" :class="replacementAccountId === account.id ? 'border-blue-500 ring-1 ring-blue-500' : 'border-gray-200'">
                  <input v-model="replacementAccountId" type="radio" :value="account.id" class="mt-1" />
                  <span class="min-w-0 text-sm">
                    <strong class="block">{{ account.label }} — {{ account.provider }}</strong>
                    <span>{{ account.account_name }} · <span class="font-mono">{{ account.account_number }}</span></span>
                    <span v-if="account.instructions" class="block text-gray-600">{{ account.instructions }}</span>
                  </span>
                </label>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-gray-800">New transfer reference (if available)</label>
                <input v-model="replacementReference" type="text" maxlength="150" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-gray-800">Replacement receipt screenshot *</label>
                <input type="file" accept="image/jpeg,image/png,image/webp" @change="selectReplacementProof" class="block w-full text-sm" />
                <p class="mt-1 text-xs text-gray-600">JPEG, PNG, or WebP, maximum 5 MB.</p>
              </div>
              <p v-if="replacementError" class="text-sm text-red-700">{{ replacementError }}</p>
              <button type="submit" :disabled="replacementProcessing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                {{ replacementProcessing ? 'Submitting…' : 'Submit replacement proof' }}
              </button>
            </form>
            <p v-else-if="manualPayment?.status === 'rejected'" class="mt-4 text-sm text-red-800">No active transfer destination is available for a replacement. Please contact the store before paying again.</p>
          </section>

          <!-- Order Items -->
          <div class="mb-6">
            <h2 class="text-lg font-bold mb-4">Order Items</h2>
            <div class="space-y-4">
              <div
                v-for="(item, index) in order.items"
                :key="index"
                class="flex items-center gap-4 pb-4 border-b last:border-b-0"
              >
                <img
                  v-if="item.product_image"
                  :src="item.product_image"
                  :alt="item.product_name"
                  class="w-20 h-20 object-cover rounded"
                />
                <div class="flex-1">
                  <p class="font-medium">{{ item.product_name }}</p>
                  <p class="text-sm text-gray-600">Quantity: {{ item.quantity }}</p>
                  <p class="text-sm text-gray-600">{{ formatPrice(item.price) }} each</p>
                </div>
                <div class="text-right">
                  <p class="font-bold">{{ formatPrice(item.total) }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Shipping Address -->
          <div v-if="order.shipping_address" class="mb-6">
            <h2 class="text-lg font-bold mb-3">Shipping Address</h2>
            <div class="bg-gray-50 p-4 rounded-lg">
              <p class="font-medium">
                {{ order.shipping_address.first_name }} {{ order.shipping_address.last_name }}
              </p>
              <p class="text-sm text-gray-600">{{ order.shipping_address.address_line1 }}</p>
              <p v-if="order.shipping_address.address_line2" class="text-sm text-gray-600">
                {{ order.shipping_address.address_line2 }}
              </p>
              <p class="text-sm text-gray-600">
                {{ order.shipping_address.city }}, {{ order.shipping_address.state }} {{ order.shipping_address.postal_code }}
              </p>
              <p class="text-sm text-gray-600">{{ order.shipping_address.country }}</p>
              <p class="text-sm text-gray-600 mt-2">Phone: {{ order.shipping_address.phone }}</p>
            </div>
          </div>

          <!-- Order Summary -->
          <div class="border-t pt-6">
            <h2 class="text-lg font-bold mb-4">Order Summary</h2>
            <div class="space-y-2">
              <div class="flex justify-between text-sm">
                <span>Subtotal</span>
                <span>{{ formatPrice(order.subtotal) }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span>Shipping</span>
                <span>{{ order.shipping_cost === 0 ? 'FREE' : formatPrice(order.shipping_cost) }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span>Tax</span>
                <span>{{ formatPrice(order.tax) }}</span>
              </div>
              <div class="border-t pt-2 flex justify-between text-lg font-bold">
                <span>Total</span>
                <span>{{ formatPrice(order.total) }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex gap-4 justify-center">
          <a
            href="/products"
            class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium"
          >
            Continue Shopping
          </a>
          <a
            v-if="order.is_guest"
            :href="`/checkout/track-order?order_number=${encodeURIComponent(order.order_number)}`"
            class="px-6 py-3 border border-blue-300 text-blue-700 rounded-lg hover:bg-blue-50 font-medium"
          >
            Track Guest Order
          </a>
          <a
            v-if="isAuthenticated"
            href="/account/orders"
            class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-medium"
          >
            View Orders
          </a>
        </div>

        <!-- What's Next -->
        <div class="mt-8 bg-gray-50 rounded-lg p-6">
          <h3 class="font-bold mb-3">What's Next?</h3>
          <ul class="space-y-2 text-sm text-gray-700">
            <li class="flex items-start">
              <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
              </svg>
              <span v-if="order.payment_method === 'bank_transfer' && order.payment_status !== 'paid'">Staff will verify your transfer before the order is processed</span>
              <span v-else>You'll receive a confirmation email shortly</span>
            </li>
            <li class="flex items-start">
              <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
              </svg>
              <span>We'll send you tracking information once your order ships</span>
            </li>
            <li class="flex items-start">
              <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
              </svg>
              <span>You can track your order status in your account</span>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </ThemeLayout>
</template>
