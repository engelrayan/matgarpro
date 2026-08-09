<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import StatTile from '@/components/admin/StatTile.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { money, num } from '@/composables/useFormat';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ExternalLink, ShieldAlert } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    store: {
        id: number;
        name: string;
        slug: string;
        description: string | null;
        logo_url: string | null;
        url: string;
        platform_host: string;
        status: string;
        suspension_reason: string | null;
        billing_status: string;
        currency: string;
        balance: number;
        billing_plan_id: number | null;
        price_per_order_override: string | null;
        price_per_order: number;
        price_source: string;
        can_accept_orders: boolean;
        is_demo: boolean;
        theme: string;
        created_at: string;
    };
    merchant: { id: number; name: string; email: string; joined: string; stores_count: number };
    stats: Record<string, number>;
    domains: { id: number; domain: string; status: string; is_primary: boolean; ssl_issued_at: string | null; last_error: string | null }[];
    wallet: { id: number; type: string; amount: number; balance_after: number; description: string | null; by: string | null; created_at: string }[];
    usage: { id: number; type: string; amount: number; price_source: string; occurred_at: string }[];
    activity: { id: number; admin_name: string; summary: string; created_at: string }[];
    plans: { id: number; name: string; code: string; price_per_order: string }[];
    themes: { key: string; name: string; description: string; palette: Record<string, string> }[];
    currency: string;
    defaultPrice: number;
}>();

// ---- Status -------------------------------------------------------------
const statusForm = useForm({ status: props.store.status, reason: props.store.suspension_reason ?? '' });
const saveStatus = () => statusForm.patch(`/admin/stores/${props.store.id}/status`, { preserveScroll: true });

// ---- Billing ------------------------------------------------------------
const billingForm = useForm({
    billing_plan_id: props.store.billing_plan_id,
    // An empty string, not 0: the override is nullable and the two mean
    // different things — "inherit the plan" versus "this store pays nothing".
    price_per_order_override: props.store.price_per_order_override ?? '',
    billing_status: props.store.billing_status,
});
const saveBilling = () =>
    billingForm
        .transform((data) => ({ ...data, price_per_order_override: data.price_per_order_override === '' ? null : data.price_per_order_override }))
        .patch(`/admin/stores/${props.store.id}/billing`, { preserveScroll: true });

const effectivePrice = computed(() => {
    if (billingForm.price_per_order_override !== '' && billingForm.price_per_order_override !== null) {
        return Number(billingForm.price_per_order_override);
    }
    const plan = props.plans.find((p) => p.id === billingForm.billing_plan_id);
    return plan ? Number(plan.price_per_order) : props.defaultPrice;
});

// ---- Wallet -------------------------------------------------------------
const walletForm = useForm({ direction: 'credit', amount: '', note: '' });
const saveWallet = () =>
    walletForm.post(`/admin/stores/${props.store.id}/wallet`, {
        preserveScroll: true,
        onSuccess: () => walletForm.reset(),
    });

const projectedBalance = computed(() => {
    const amount = Number(walletForm.amount || 0);
    return props.store.balance + (walletForm.direction === 'credit' ? amount : -amount);
});

// ---- Theme --------------------------------------------------------------
const themeForm = useForm({ theme: props.store.theme });
const saveTheme = (key: string) => {
    themeForm.theme = key;
    themeForm.patch(`/admin/stores/${props.store.id}/theme`, { preserveScroll: true });
};

const STATUS_BADGE: Record<string, string> = {
    active: 'badge-success',
    draft: 'badge-neutral',
    suspended: 'badge-danger',
};

const DOMAIN_BADGE: Record<string, string> = {
    active: 'badge-success',
    pending: 'badge-warning',
    failed: 'badge-danger',
};

const TX_LABEL: Record<string, string> = {
    topup: 'شحن',
    order_fee: 'رسوم طلب',
    subscription_fee: 'اشتراك',
    refund: 'استرجاع',
    adjustment: 'تعديل يدوي',
};
</script>

<template>
    <AdminLayout :title="store.name" :subtitle="`تاجر: ${merchant.name} · من ${store.created_at}`">
        <Head :title="store.name" />

        <template #actions>
            <a :href="store.url" target="_blank" rel="noopener" class="btn-outline shrink-0">
                <ExternalLink class="size-4" />
                افتح المتجر
            </a>
        </template>

        <div class="mx-auto max-w-7xl space-y-5">
            <!-- ── Can it sell right now? ────────────────────────────────── -->
            <div
                v-if="!store.can_accept_orders"
                class="flex items-start gap-3 rounded-2xl border border-destructive/30 bg-destructive/5 p-4"
            >
                <ShieldAlert class="mt-0.5 size-5 shrink-0 text-destructive" />
                <div class="text-sm">
                    <p class="font-medium text-destructive">المتجر ده مش بيقبل طلبات دلوقتي.</p>
                    <p class="mt-0.5 text-muted-foreground">
                        <template v-if="store.is_demo">متجر عرض — الطلبات عليه متوقفة بالتصميم.</template>
                        <template v-else-if="store.status !== 'active'">الحالة: {{ store.status }}.</template>
                        <template v-else-if="store.billing_status === 'suspended'">موقوف مالياً.</template>
                        <template v-else>الرصيد نزل تحت حد السماح.</template>
                    </p>
                </div>
            </div>

            <!-- ── Stats ─────────────────────────────────────────────────── -->
            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatTile label="الطلبات" :value="num(stats.orders)" :hint="`${num(stats.delivered)} موصّل`" />
                <StatTile label="المنتجات" :value="num(stats.products)" />
                <StatTile lux label="مبيعات المتجر" :value="`${money(stats.gmv)} ${currency}`" hint="الموصّل فقط" />
                <StatTile
                    lux
                    label="الرصيد"
                    :value="`${money(store.balance)} ${currency}`"
                    :hint="`اتحصّل منه ${money(stats.billed)} · شحن ${money(stats.topups)}`"
                />
            </section>

            <div class="grid gap-5 lg:grid-cols-3">
                <!-- ── Left column: the four actions ─────────────────────── -->
                <div class="space-y-5 lg:col-span-2">
                    <!-- Status -->
                    <section class="surface p-5">
                        <p class="mb-4 text-sm font-medium">حالة المتجر</p>

                        <div class="flex flex-wrap gap-2">
                            <label
                                v-for="opt in [
                                    { value: 'active', label: 'شغّال' },
                                    { value: 'draft', label: 'مسودة' },
                                    { value: 'suspended', label: 'موقوف' },
                                ]"
                                :key="opt.value"
                                class="cursor-pointer rounded-xl border px-4 py-2 text-sm transition-colors"
                                :class="
                                    statusForm.status === opt.value
                                        ? 'border-primary bg-primary/5 font-medium'
                                        : 'border-border hover:bg-muted'
                                "
                            >
                                <input v-model="statusForm.status" type="radio" :value="opt.value" class="sr-only" />
                                {{ opt.label }}
                            </label>
                        </div>

                        <div v-if="statusForm.status === 'suspended'" class="mt-4">
                            <label class="field-label" for="reason">سبب الإيقاف</label>
                            <input
                                id="reason"
                                v-model="statusForm.reason"
                                class="field"
                                placeholder="بيتسجل في سجل التدقيق وبيفضل متسجل"
                            />
                            <InputError :message="statusForm.errors.reason" />
                        </div>

                        <p v-else-if="store.suspension_reason" class="mt-3 text-xs text-muted-foreground">
                            سبب الإيقاف الحالي: {{ store.suspension_reason }}
                        </p>

                        <button class="btn-primary mt-4" :disabled="statusForm.processing" @click="saveStatus">
                            احفظ الحالة
                        </button>
                    </section>

                    <!-- Billing -->
                    <section class="surface-lux p-5">
                        <p class="mb-4 text-sm font-medium">التسعير والخطة</p>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="field-label" for="plan">الخطة</label>
                                <select id="plan" v-model="billingForm.billing_plan_id" class="field">
                                    <option :value="null">بدون خطة</option>
                                    <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                                        {{ plan.name }} — {{ money(Number(plan.price_per_order)) }}
                                    </option>
                                </select>
                                <InputError :message="billingForm.errors.billing_plan_id" />
                            </div>

                            <div>
                                <label class="field-label" for="override">سعر خاص للطلب</label>
                                <input
                                    id="override"
                                    v-model="billingForm.price_per_order_override"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="field"
                                    placeholder="سيبه فاضي عشان يمشي على الخطة"
                                />
                                <p class="field-hint">فاضي = سعر الخطة. صفر = المتجر ده مش بيدفع.</p>
                                <InputError :message="billingForm.errors.price_per_order_override" />
                            </div>

                            <div>
                                <label class="field-label" for="billing_status">الحالة المالية</label>
                                <select id="billing_status" v-model="billingForm.billing_status" class="field">
                                    <option value="active">سليم</option>
                                    <option value="grace">فترة سماح</option>
                                    <option value="suspended">موقوف مالياً</option>
                                </select>
                                <InputError :message="billingForm.errors.billing_status" />
                            </div>

                            <div class="flex flex-col justify-end">
                                <p class="rounded-xl bg-muted px-4 py-3 text-sm">
                                    السعر الفعّال:
                                    <span class="tabular font-semibold">{{ money(effectivePrice) }} {{ currency }}</span>
                                    لكل طلب
                                </p>
                            </div>
                        </div>

                        <button class="btn-primary mt-4" :disabled="billingForm.processing" @click="saveBilling">
                            احفظ التسعير
                        </button>
                    </section>

                    <!-- Wallet -->
                    <section class="surface p-5">
                        <p class="text-sm font-medium">تعديل الرصيد</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            كل تعديل بيتكتب في دفتر المتجر وفي سجل التدقيق باسمك. مفيش تراجع — التصحيح بيتعمل بقيد مضاد.
                        </p>

                        <div class="mt-4 grid gap-4 sm:grid-cols-3">
                            <div>
                                <label class="field-label" for="direction">النوع</label>
                                <select id="direction" v-model="walletForm.direction" class="field">
                                    <option value="credit">إضافة رصيد</option>
                                    <option value="debit">خصم رصيد</option>
                                </select>
                            </div>

                            <div>
                                <label class="field-label" for="amount">المبلغ</label>
                                <input
                                    id="amount"
                                    v-model="walletForm.amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    class="field"
                                />
                                <InputError :message="walletForm.errors.amount" />
                            </div>

                            <div class="flex flex-col justify-end">
                                <p class="rounded-xl bg-muted px-4 py-3 text-sm">
                                    هيبقى:
                                    <span class="tabular font-semibold" :class="projectedBalance < 0 ? 'text-destructive' : ''">
                                        {{ money(projectedBalance) }}
                                    </span>
                                </p>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="field-label" for="note">السبب</label>
                            <input id="note" v-model="walletForm.note" class="field" placeholder="تحويل بنكي #… / تعويض عن …" />
                            <InputError :message="walletForm.errors.note" />
                        </div>

                        <button class="btn-gold mt-4" :disabled="walletForm.processing" @click="saveWallet">
                            نفّذ التعديل
                        </button>
                    </section>

                    <!-- Theme -->
                    <section class="surface p-5">
                        <p class="text-sm font-medium">الثيم</p>
                        <p class="mt-1 text-xs text-muted-foreground">تغيير الثيم بيظهر على المتجر فوراً وبيتسجل باسمك.</p>

                        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <button
                                v-for="theme in themes"
                                :key="theme.key"
                                class="flex items-center gap-3 rounded-xl border p-3 text-right transition-colors"
                                :class="
                                    store.theme === theme.key
                                        ? 'border-primary bg-primary/5'
                                        : 'border-border hover:bg-muted'
                                "
                                :disabled="themeForm.processing"
                                @click="saveTheme(theme.key)"
                            >
                                <span class="flex shrink-0 gap-1">
                                    <span class="size-6 rounded-md" :style="{ background: `hsl(${theme.palette.primary})` }" />
                                    <span class="size-6 rounded-md" :style="{ background: `hsl(${theme.palette.accent})` }" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium">{{ theme.name }}</span>
                                    <span class="block truncate text-xs text-muted-foreground">{{ theme.description }}</span>
                                </span>
                            </button>
                        </div>
                        <InputError :message="themeForm.errors.theme" />
                    </section>
                </div>

                <!-- ── Right column: context ─────────────────────────────── -->
                <div class="space-y-5">
                    <section class="surface p-5">
                        <p class="mb-3 text-sm font-medium">التاجر</p>
                        <Link :href="`/admin/merchants/${merchant.id}`" class="font-medium hover:underline">
                            {{ merchant.name }}
                        </Link>
                        <p class="mt-0.5 truncate text-sm text-muted-foreground" dir="ltr">{{ merchant.email }}</p>
                        <p class="mt-2 text-xs text-muted-foreground">
                            اشترك {{ merchant.joined }} · {{ num(merchant.stores_count) }} متجر
                        </p>

                        <div class="mt-4 border-t border-border pt-3 text-sm">
                            <p class="flex justify-between gap-2">
                                <span class="text-muted-foreground">الحالة</span>
                                <span :class="STATUS_BADGE[store.status]">{{ store.status }}</span>
                            </p>
                            <p class="mt-2 flex justify-between gap-2">
                                <span class="text-muted-foreground">الدومين المجاني</span>
                                <span class="truncate font-mono text-xs" dir="ltr">{{ store.platform_host }}</span>
                            </p>
                        </div>
                    </section>

                    <section class="surface p-5">
                        <div class="mb-3 flex items-center justify-between">
                            <p class="text-sm font-medium">الدومينات</p>
                            <Link href="/admin/domains" class="text-xs text-muted-foreground hover:text-foreground">
                                الكل
                            </Link>
                        </div>
                        <ul v-if="domains.length" class="space-y-2.5">
                            <li v-for="d in domains" :key="d.id" class="text-sm">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="truncate font-mono text-xs" dir="ltr">{{ d.domain }}</span>
                                    <span :class="DOMAIN_BADGE[d.status]" class="shrink-0">{{ d.status }}</span>
                                </div>
                                <p v-if="d.last_error" class="mt-0.5 text-xs text-muted-foreground">{{ d.last_error }}</p>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-muted-foreground">مفيش دومين مربوط.</p>
                    </section>

                    <section class="surface overflow-hidden">
                        <p class="border-b border-border p-5 text-sm font-medium">آخر حركات الرصيد</p>
                        <ul v-if="wallet.length" class="max-h-96 overflow-y-auto">
                            <li v-for="t in wallet" :key="t.id" class="border-b border-border/60 px-5 py-3 last:border-0">
                                <div class="flex items-center justify-between gap-2 text-sm">
                                    <span>{{ TX_LABEL[t.type] ?? t.type }}</span>
                                    <span class="tabular font-medium" :class="t.amount < 0 ? 'text-destructive' : 'text-success'">
                                        {{ t.amount > 0 ? '+' : '' }}{{ money(t.amount) }}
                                    </span>
                                </div>
                                <p class="mt-0.5 truncate text-xs text-muted-foreground">
                                    {{ t.description }}
                                    <template v-if="t.by"> · {{ t.by }}</template>
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ t.created_at }} · الرصيد بعدها {{ money(t.balance_after) }}
                                </p>
                            </li>
                        </ul>
                        <p v-else class="p-5 text-sm text-muted-foreground">مفيش حركات.</p>
                    </section>

                    <section class="surface overflow-hidden">
                        <p class="border-b border-border p-5 text-sm font-medium">آخر رسوم اتحسبت</p>
                        <ul v-if="usage.length">
                            <li v-for="u in usage" :key="u.id" class="flex items-center justify-between gap-2 border-b border-border/60 px-5 py-2.5 text-sm last:border-0">
                                <span class="text-muted-foreground">{{ u.occurred_at }}</span>
                                <span class="tabular">{{ money(u.amount) }}</span>
                            </li>
                        </ul>
                        <p v-else class="p-5 text-sm text-muted-foreground">مفيش رسوم لسه.</p>
                    </section>

                    <section class="surface overflow-hidden">
                        <div class="flex items-center justify-between border-b border-border p-5">
                            <p class="text-sm font-medium">اللي اتعمل على المتجر ده</p>
                            <Link href="/admin/activity" class="text-xs text-muted-foreground hover:text-foreground">
                                السجل الكامل
                            </Link>
                        </div>
                        <ul v-if="activity.length">
                            <li v-for="a in activity" :key="a.id" class="border-b border-border/60 px-5 py-3 last:border-0">
                                <p class="text-sm">{{ a.summary }}</p>
                                <p class="mt-0.5 text-xs text-muted-foreground">{{ a.admin_name }} · {{ a.created_at }}</p>
                            </li>
                        </ul>
                        <p v-else class="p-5 text-sm text-muted-foreground">محدش عمل حاجة على المتجر ده لسه.</p>
                    </section>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
