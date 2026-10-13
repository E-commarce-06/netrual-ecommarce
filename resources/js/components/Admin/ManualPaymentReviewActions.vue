<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Check, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface ReasonTemplate {
    id: number;
    label: string;
    reason: string;
}

const props = defineProps<{
    submissionId: number;
    status: string;
    orderLabel: string;
    reasonTemplates: ReasonTemplate[];
}>();

const page = usePage();
const serverErrors = computed(
    () => page.props.errors as Record<string, string>,
);
const rejecting = ref(false);
const submitting = ref(false);
const selectedTemplateId = ref('');
const rejectionReason = ref('');
const saveAsTemplate = ref(false);
const templateLabel = ref('');

const openReject = () => {
    selectedTemplateId.value = '';
    rejectionReason.value = '';
    saveAsTemplate.value = false;
    templateLabel.value = '';
    rejecting.value = true;
};

const closeReject = () => {
    if (submitting.value) return;
    rejecting.value = false;
};

const selectReasonTemplate = () => {
    const template = props.reasonTemplates.find(
        (item) => String(item.id) === selectedTemplateId.value,
    );
    if (!template) return;

    rejectionReason.value = template.reason;
    templateLabel.value = template.label;
};

const approve = () => {
    if (
        !window.confirm(
            `Before approving ${props.orderLabel}, verify the settled funds in the receiving bank or wallet account. Approval records this order as paid. Continue?`,
        )
    )
        return;

    router.post(
        `/admin/sales/manual-payments/${props.submissionId}/approve`,
        { verified_settled: true },
        {
            preserveScroll: true,
        },
    );
};

const submitReject = () => {
    if (rejectionReason.value.trim().length < 3) return;

    submitting.value = true;
    router.post(
        `/admin/sales/manual-payments/${props.submissionId}/reject`,
        {
            reason: rejectionReason.value.trim(),
            save_as_template: saveAsTemplate.value,
            template_label: templateLabel.value.trim(),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                rejecting.value = false;
            },
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
};
</script>

<template>
    <div v-if="status === 'pending_review'" class="flex flex-wrap gap-2">
        <button
            type="button"
            @click="approve"
            class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-3 py-2 text-xs font-semibold text-white hover:bg-green-700"
        >
            <Check class="h-4 w-4" /> Approve payment
        </button>
        <button
            type="button"
            @click="openReject"
            class="rounded-lg border border-red-300 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-900/20"
        >
            Reject proof
        </button>
    </div>

    <div
        v-if="rejecting"
        class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4"
        @click.self="closeReject"
    >
        <section
            role="dialog"
            aria-modal="true"
            aria-labelledby="manual-payment-reject-title"
            class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl dark:bg-gray-800"
        >
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2
                        id="manual-payment-reject-title"
                        class="text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Reject transfer proof
                    </h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Give the customer a clear reason. A receipt is evidence
                        only; approval must wait until settled funds are
                        verified.
                    </p>
                </div>
                <button
                    type="button"
                    @click="closeReject"
                    aria-label="Close"
                    class="rounded-md p-1 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <p
                v-if="serverErrors.reason"
                class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-300"
            >
                {{ serverErrors.reason }}
            </p>
            <p
                v-if="serverErrors.template_label"
                class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-300"
            >
                {{ serverErrors.template_label }}
            </p>

            <label
                class="mt-5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >Use a saved reason</label
            >
            <select
                v-model="selectedTemplateId"
                @change="selectReasonTemplate"
                class="mt-2 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
            >
                <option value="">Write a custom reason…</option>
                <option
                    v-for="template in reasonTemplates"
                    :key="template.id"
                    :value="String(template.id)"
                >
                    {{ template.label }}
                </option>
            </select>

            <label
                class="mt-4 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >Rejection reason *</label
            >
            <textarea
                v-model="rejectionReason"
                rows="4"
                maxlength="1000"
                required
                placeholder="Explain what needs to be corrected before the customer resubmits evidence."
                class="mt-2 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
            />

            <label
                class="mt-4 flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300"
            >
                <input
                    v-model="saveAsTemplate"
                    type="checkbox"
                    class="mt-0.5 rounded border-gray-300 text-blue-600"
                />
                <span>Save this reason for future use</span>
            </label>
            <div v-if="saveAsTemplate" class="mt-3">
                <label
                    class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >Template name *</label
                >
                <input
                    v-model="templateLabel"
                    maxlength="120"
                    type="text"
                    placeholder="For example: Wrong transfer amount"
                    class="mt-2 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                />
            </div>

            <div class="mt-5 flex justify-end gap-3">
                <button
                    type="button"
                    @click="closeReject"
                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    @click="submitReject"
                    :disabled="
                        submitting ||
                        rejectionReason.trim().length < 3 ||
                        (saveAsTemplate && templateLabel.trim().length < 2)
                    "
                    class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {{ submitting ? 'Saving…' : 'Reject submission' }}
                </button>
            </div>
        </section>
    </div>
</template>
