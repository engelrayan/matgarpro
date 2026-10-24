<script setup lang="ts">
import Pagination from '@/components/admin/Pagination.vue';
import { money, num } from '@/composables/useFormat';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Check, X } from 'lucide-vue-next';
import { ref } from 'vue';

/*
 * Top-up requests, matched by hand against InstaPay.
 *
 * The amount is editable on confirm because a transfer is what it is: a
 * merchant who picked 1,000 and sent 950 is credited 950, and the audit trail
 * records both numbers.
 */

interface TopupRow {
    id: number;
    amount: number;
    confirmed_amount: number | null;
    status: string;
    status_label: string;
    review_note: string | null;
    reviewer: string | null;
    created_at: string;
    reviewed_at: string | null;
    store: { id: number; name: string; slug: string; balance: number };
    merchant: string | null;
}

defineProps<{
    status: string;
    pendingCount: number;
    currency: string;
    requests: {
        data: TopupRow[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
        from: number | null;
        to: number | null;
    };
}>();

const TABS = [
    { key: 'pending', label: 'مستنية' },
    { key: 'confirmed', label: 'اتأكدت' },
    { key: 'rejected', label: 'اترفضت' },
    { key: 'all', label: 'الكل' },
];

const STATUS_CLASS: Record<string, string> = {
    pending: 'badge-warning',
    confirmed: 'badge-success',
    rejected: 'badge-danger',
    cancelled: 'badge-neutral',
};

const confirming = ref<number | null>(null);
const rejecting = ref<number | null>(null);

const confirmForm = useForm({ amount: '' });
const rejectForm = useForm({ note: '' });

const startConfirm = (row: TopupRow) => {
    rejecting.value = null;
    confirming.value = row.id;
    confirmForm.clearErrors();
    confirmForm.amount = String(row.amount);
};

const startReject = (row: TopupRow) => {
    confirming.value = null;
    rejecting.value = row.id;
    rejectForm.clearErrors();
    rejectForm.note = '';
};

const confirm = (row: TopupRow) =>
    confirmForm.post(route('admin.topups.confirm', row.id), {
        preserveScroll: true,
        onSuccess: () => (confirming.value = null),
    });

const reject = (row: TopupRow) =>
    rejectForm.post(route('admin.topups.reject', row.id), {
        preserveScroll: true,
        onSuccess: () => (rejecting.value = null),
    });

const setTab = (key: string) => router.get('/admin/topups', { status: key }, { preserveScroll: true });
</script>

<template>
    <AdminLayout title="طلبات الشحن" :subtitle="`${num(pendingCount)} مستني التأكيد`">
        <Head title="طلبات الشحن" />

        <div class="mx-auto w-full max-w-5xl space-y-4">
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="tab in TABS"
                    :key="tab.key"
                    type="button"
                    class="rounded-full border px-4 py-1.5 text-sm transition-colors"
                    :class="status === tab.key ? 'border-primary bg-primary/10 text-primary' : 'border-border text-muted-foreground'"
                    @click="setTab(tab.key)"
                >
                    {{ tab.label }}
                    <span v-if="tab.key === 'pending' && pendingCount" class="tabular">({{ num(pendingCount) }})</span>
                </button>
            </div>

            <p v-if="!requests.data.length" class="surface p-8 text-center text-sm text-muted-foreground">مفيش طلبات هنا.</p>

            <div v-for="row in requests.data" :key="row.id" class="surface space-y-3 p-4">
                <div class="flex flex-wrap items-start gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">
                            <Link :href="`/admin/stores/${row.store.id}`" class="hover:underline">{{ row.store.name }}</Link>
                            <span class="text-xs text-muted-foreground" dir="ltr"> {{ row.store.slug }}</span>
                        </p>
                        <p class="text-xs text-muted-foreground">
                            طلب #{{ row.id }} · {{ row.created_at }}
                            <template v-if="row.merchant"> · {{ row.merchant }}</template>
                            · الرصيد دلوقتي <span class="tabular" dir="ltr">{{ money(row.store.balance) }}</span>
                        </p>
                    </div>

                    <div class="text-left">
                        <p class="tabular text-lg font-bold" dir="ltr">{{ money(row.confirmed_amount ?? row.amount) }} {{ currency }}</p>
                        <p v-if="row.confirmed_amount !== null && row.confirmed_amount !== row.amount" class="tabular text-xs text-muted-foreground">
                            الطلب كان {{ money(row.amount) }}
                        </p>
                    </div>

                    <span :class="STATUS_CLASS[row.status] ?? 'badge-neutral'">{{ row.status_label }}</span>
                </div>

                <p v-if="row.reviewer" class="text-xs text-muted-foreground">
                    {{ row.reviewer }} · {{ row.reviewed_at }}<template v-if="row.review_note"> — {{ row.review_note }}</template>
                </p>

                <template v-if="row.status === 'pending'">
                    <div v-if="confirming === row.id" class="flex flex-wrap items-end gap-2 rounded-xl bg-muted/50 p-3">
                        <div>
                            <label class="field-label text-xs">المبلغ اللي وصل فعلاً</label>
                            <input v-model="confirmForm.amount" class="field tabular w-36" inputmode="decimal" dir="ltr" />
                        </div>
                        <button type="button" class="btn-primary" :disabled="confirmForm.processing" @click="confirm(row)">
                            <Check class="size-4" />
                            ضيفه للرصيد
                        </button>
                        <button type="button" class="btn-ghost" @click="confirming = null">رجوع</button>
                        <p v-if="confirmForm.errors.amount || confirmForm.errors.topup" class="w-full text-xs text-destructive">
                            {{ confirmForm.errors.amount || confirmForm.errors.topup }}
                        </p>
                    </div>

                    <div v-else-if="rejecting === row.id" class="flex flex-wrap items-end gap-2 rounded-xl bg-muted/50 p-3">
                        <div class="min-w-0 flex-1">
                            <label class="field-label text-xs">سبب الرفض (التاجر هيشوفه)</label>
                            <input v-model="rejectForm.note" class="field" maxlength="255" placeholder="مثلاً: مالقيناش تحويل بالمبلغ ده" />
                        </div>
                        <button type="button" class="btn-danger" :disabled="rejectForm.processing" @click="reject(row)">ارفض</button>
                        <button type="button" class="btn-ghost" @click="rejecting = null">رجوع</button>
                        <p v-if="rejectForm.errors.note || rejectForm.errors.topup" class="w-full text-xs text-destructive">
                            {{ rejectForm.errors.note || rejectForm.errors.topup }}
                        </p>
                    </div>

                    <div v-else class="flex gap-2">
                        <button type="button" class="btn-primary py-1.5" @click="startConfirm(row)">
                            <Check class="size-4" />
                            وصل — أكّد
                        </button>
                        <button type="button" class="btn-outline py-1.5" @click="startReject(row)">
                            <X class="size-4" />
                            ارفض
                        </button>
                    </div>
                </template>
            </div>

            <Pagination v-if="requests.total > 30" :links="requests.links" :total="requests.total" :from="requests.from" :to="requests.to" />
        </div>
    </AdminLayout>
</template>
