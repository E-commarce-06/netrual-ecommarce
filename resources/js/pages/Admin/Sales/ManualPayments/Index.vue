<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/layouts/AdminLayout.vue'
import Pagination from '@/components/Admin/Pagination.vue'
import { Check, ExternalLink, Search, X, CircleAlert } from 'lucide-vue-next'

interface Submission {
  id: number
  status: 'pending_review' | 'approved' | 'rejected'
  amount: number
  currency: string
  transfer_reference?: string | null
  destination_snapshot: Record<string, string>
  proof_url: string
  submitted_at: string
  review_note?: string | null
  reviewed_at?: string | null
  reviewer_name?: string | null
  order: {
    id: number
    order_number: string
    status: string
    payment_status: string
    total: number
    customer_email: string
    customer_name?: string | null
  } | null
}

interface PaginationData {
  data: Submission[]
  current_page: number
  last_page: number
  from: number | null
  to: number | null
  total: number
  per_page: number
  links?: Array<{ url: string | null; label: string; active: boolean }>
}

const props = defineProps<{
  submissions: PaginationData
  filters: { status: string; search: string }
  pendingCount: number
}>()

const page = usePage()
const search = ref(props.filters.search || '')
const status = ref(props.filters.status || 'pending_review')
const rejectingSubmission = ref<Submission | null>(null)
const rejectionReason = ref('')
const submittingRejection = ref(false)
const serverErrors = computed(() => page.props.errors as Record<string, string>)

const applyFilters = () => {
  router.get('/admin/sales/manual-payments', {
    status: status.value,
    search: search.value || undefined,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

const clearFilters = () => {
  search.value = ''
  status.value = 'pending_review'
  applyFilters()
}

const approve = (submission: Submission) => {
  const orderLabel = submission.order?.order_number || `submission ${submission.id}`
  if (!window.confirm(`Approve the verified transfer for ${orderLabel}? This will mark the order as paid and advance its normal order/inventory workflow.`)) return

  router.post(`/admin/sales/manual-payments/${submission.id}/approve`, {}, {
    preserveScroll: true,
  })
}

const openReject = (submission: Submission) => {
  rejectingSubmission.value = submission
  rejectionReason.value = ''
}

const closeReject = () => {
  if (submittingRejection.value) return
  rejectingSubmission.value = null
  rejectionReason.value = ''
}

const submitReject = () => {
  if (!rejectingSubmission.value || !rejectionReason.value.trim()) return
  submittingRejection.value = true
  router.post(`/admin/sales/manual-payments/${rejectingSubmission.value.id}/reject`, {
    reason: rejectionReason.value.trim(),
  }, {
    preserveScroll: true,
    onSuccess: () => {
      rejectingSubmission.value = null
      rejectionReason.value = ''
    },
    onFinish: () => {
      submittingRejection.value = false
    },
  })
}

const statusLabels: Record<string, string> = {
  pending_review: 'Pending review',
  approved: 'Approved',
  rejected: 'Rejected',
}

const statusClasses: Record<string, string> = {
  pending_review: 'bg-amber-50 text-amber-800 border-amber-200 dark:bg-amber-900/20 dark:text-amber-300 dark:border-amber-800',
  approved: 'bg-green-50 text-green-800 border-green-200 dark:bg-green-900/20 dark:text-green-300 dark:border-green-800',
  rejected: 'bg-red-50 text-red-800 border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800',
}

const statusLabel = (value: string) => statusLabels[value] || value
const statusClass = (value: string) => statusClasses[value] || 'bg-gray-100 text-gray-700 border-gray-200'

const formatDate = (value?: string | null) => value
  ? new Date(value.replace(' ', 'T') + (value.endsWith('Z') ? '' : 'Z')).toLocaleString()
  : '—'
</script>

<template>
  <AdminLayout title="Manual Payment Review">
    <Head title="Manual Payment Review" />

    <div class="p-6 space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
          <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Manual payment review</h1>
            <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
              {{ pendingCount }} pending
            </span>
          </div>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Compare each receipt with the actual incoming bank or wallet transaction before approving.</p>
        </div>
        <Link href="/admin/sales/orders" class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
          Back to orders
        </Link>
      </div>

      <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900 dark:border-blue-900/50 dark:bg-blue-900/20 dark:text-blue-200">
        A screenshot is customer-provided evidence, not confirmation by itself. Verify recipient, amount, reference, and settled funds in the receiving account before using Approve.
      </div>

      <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
        <div class="grid grid-cols-1 md:grid-cols-[1fr_220px_auto_auto] gap-3">
          <div class="relative">
            <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
            <input v-model="search" @keyup.enter="applyFilters" type="search" placeholder="Order number, email, or transfer reference" class="w-full pl-9 pr-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-white" />
          </div>
          <select v-model="status" @change="applyFilters" class="w-full px-3 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-900 dark:text-white">
            <option value="pending_review">Pending review</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
            <option value="all">All submissions</option>
          </select>
          <button @click="applyFilters" class="px-4 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700">Search</button>
          <button @click="clearFilters" class="px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Reset</button>
        </div>
      </section>

      <section class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900/40">
              <tr>
                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Order / customer</th>
                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Transfer</th>
                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Destination paid to</th>
                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Evidence</th>
                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Review</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
              <tr v-for="submission in submissions.data" :key="submission.id" class="align-top">
                <td class="px-5 py-4 min-w-[210px]">
                  <Link v-if="submission.order" :href="`/admin/sales/orders/${submission.order.id}`" class="font-semibold text-blue-600 hover:underline dark:text-blue-400">
                    {{ submission.order.order_number }}
                  </Link>
                  <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ submission.order?.customer_name || submission.order?.customer_email || 'Order unavailable' }}</p>
                  <p v-if="submission.order?.customer_name" class="text-xs text-gray-500 dark:text-gray-400">{{ submission.order.customer_email }}</p>
                  <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Submitted {{ formatDate(submission.submitted_at) }}</p>
                </td>
                <td class="px-5 py-4 min-w-[150px]">
                  <p class="font-semibold text-gray-900 dark:text-white">{{ submission.currency }} {{ Number(submission.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</p>
                  <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Order total: {{ submission.currency }} {{ Number(submission.order?.total || submission.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</p>
                  <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Reference</p>
                  <p class="break-all text-sm text-gray-800 dark:text-gray-200">{{ submission.transfer_reference || 'Not provided' }}</p>
                </td>
                <td class="px-5 py-4 min-w-[190px]">
                  <p class="font-medium text-gray-900 dark:text-white">{{ submission.destination_snapshot?.label || submission.destination_snapshot?.provider || 'Transfer account' }}</p>
                  <p class="text-xs text-gray-500 dark:text-gray-400">{{ submission.destination_snapshot?.provider }} · {{ submission.destination_snapshot?.type === 'wallet' ? 'Wallet' : 'Bank' }}</p>
                  <p class="mt-1 text-sm text-gray-800 dark:text-gray-200">{{ submission.destination_snapshot?.account_name }}</p>
                  <p class="font-mono text-sm text-gray-700 dark:text-gray-300">{{ submission.destination_snapshot?.account_number }}</p>
                </td>
                <td class="px-5 py-4">
                  <a :href="submission.proof_url" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <ExternalLink class="w-4 h-4" /> Open screenshot
                  </a>
                </td>
                <td class="px-5 py-4">
                  <span :class="['inline-flex whitespace-nowrap rounded-full border px-2.5 py-1 text-xs font-semibold', statusClass(submission.status)]">{{ statusLabel(submission.status) }}</span>
                  <p v-if="submission.review_note" class="mt-2 max-w-xs text-xs text-gray-600 dark:text-gray-400">{{ submission.review_note }}</p>
                  <p v-if="submission.reviewer_name" class="mt-1 text-xs text-gray-500 dark:text-gray-400">By {{ submission.reviewer_name }} · {{ formatDate(submission.reviewed_at) }}</p>
                </td>
                <td class="px-5 py-4 text-right min-w-[190px]">
                  <div v-if="submission.status === 'pending_review'" class="flex justify-end gap-2">
                    <button @click="approve(submission)" class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-3 py-2 text-xs font-semibold text-white hover:bg-green-700">
                      <Check class="w-4 h-4" /> Approve
                    </button>
                    <button @click="openReject(submission)" class="rounded-lg border border-red-300 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-900/20">
                      Reject
                    </button>
                  </div>
                  <span v-else class="text-xs text-gray-500 dark:text-gray-400">Reviewed</span>
                </td>
              </tr>
              <tr v-if="submissions.data.length === 0">
                <td colspan="6" class="px-6 py-14 text-center">
                  <CircleAlert class="mx-auto h-8 w-8 text-gray-400" />
                  <p class="mt-3 font-medium text-gray-900 dark:text-white">No submissions found</p>
                  <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">New manual transfer screenshots will appear here for staff verification.</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <Pagination :data="submissions" resource-name="submissions" />
      </section>
    </div>

    <div v-if="rejectingSubmission" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="closeReject">
      <section role="dialog" aria-modal="true" aria-labelledby="reject-payment-title" class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl dark:bg-gray-800">
        <div class="flex items-start justify-between gap-4">
          <div>
            <h2 id="reject-payment-title" class="text-lg font-semibold text-gray-900 dark:text-white">Reject transfer proof</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Give the customer a clear reason so they can correct the issue and submit replacement proof.</p>
          </div>
          <button type="button" @click="closeReject" aria-label="Close" class="rounded-md p-1 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700"><X class="w-5 h-5" /></button>
        </div>
        <p v-if="serverErrors.reason" class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-300">{{ serverErrors.reason }}</p>
        <label class="mt-5 block text-sm font-medium text-gray-700 dark:text-gray-300">Rejection reason *</label>
        <textarea v-model="rejectionReason" rows="4" maxlength="1000" required placeholder="For example: the amount does not match, the receipt is unreadable, or the transfer is not visible in the receiving account." class="mt-2 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
        <div class="mt-5 flex justify-end gap-3">
          <button type="button" @click="closeReject" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</button>
          <button type="button" @click="submitReject" :disabled="submittingRejection || !rejectionReason.trim()" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50">
            {{ submittingRejection ? 'Saving…' : 'Reject submission' }}
          </button>
        </div>
      </section>
    </div>
  </AdminLayout>
</template>
