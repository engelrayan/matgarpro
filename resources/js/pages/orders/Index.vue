<script setup lang="ts">
import EditableCell from '@/components/orders/EditableCell.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { AlertTriangle, ArrowDown, ArrowUp, Check, Download, LoaderCircle, Printer, Search, Truck, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface DamanCell {
    order_number: string | null;
    tracking_number: string | null;
    carrier: string | null;
    status_note: string | null;
    error: string | null;
}

interface Order {
    id: number;
    number: number;
    customer_name: string;
    customer_phone: string;
    customer_phone_alt: string | null;
    governorate: string | null;
    city: string | null;
    address: string | null;
    note: string | null;
    total: string;
    status: string;
    items_summary: string;
    created_at: string;
    daman: DamanCell | null;
}

interface PaginatorLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    orders: { data: Order[]; links: PaginatorLink[]; total: number; from: number | null; to: number | null };
    filters: { status: string; q: string; gov: string; sort: string; dir: string };
    currency: string;
    counts: Record<string, number>;
    statuses: { value: string; label: string }[];
    governorates: string[];
    daman_enabled: boolean;
}>();

const page = usePage();

const breadcrumbItems: BreadcrumbItem[] = [{ title: 'الطلبات', href: '/orders' }];

const STATUS_BADGE: Record<string, string> = {
    pending: 'badge-warning',
    confirmed: 'badge-info',
    shipped: 'badge-info',
    delivered: 'badge-success',
    cancelled: 'badge-danger',
    returned: 'badge-danger',
};

/* ── Filters ────────────────────────────────────────────────────────────── */

const search = ref(props.filters.q);
let searchTimer: ReturnType<typeof setTimeout>;

watch(search, (value) => {
    clearTimeout(searchTimer);
    // Debounced: a request per keystroke is a request per keystroke the
    // merchant waits for.
    searchTimer = setTimeout(() => go({ q: value }), 350);
});

const go = (params: Record<string, string | undefined>) =>
    router.get('/orders', { ...props.filters, ...params }, { preserveState: true, preserveScroll: true, replace: true });

const sortBy = (column: string) =>
    go({ sort: column, dir: props.filters.sort === column && props.filters.dir === 'desc' ? 'asc' : 'desc' });

const tabs = computed(() => [
    { value: '', label: 'الكل', count: props.counts.all },
    ...props.statuses.map((s) => ({ value: s.value, label: s.label, count: props.counts[s.value] ?? 0 })),
]);

/* ── Selection & bulk ───────────────────────────────────────────────────── */

const selected = ref<number[]>([]);
const bulkStatus = ref('');

const allOnPage = computed(() => props.orders.data.map((o) => o.id));
const allSelected = computed(() => selected.value.length > 0 && selected.value.length === allOnPage.value.length);

const toggleAll = () => {
    selected.value = allSelected.value ? [] : [...allOnPage.value];
};

// Otherwise the selection silently refers to rows that left the screen when a
// filter changed, and the next bulk action hits the wrong orders.
watch(() => props.orders.data, () => (selected.value = []));

const applyBulk = () => {
    if (!bulkStatus.value || selected.value.length === 0) return;

    router.patch(
        '/orders-bulk/status',
        { ids: selected.value, status: bulkStatus.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                selected.value = [];
                bulkStatus.value = '';
            },
        },
    );
};

/* ── Daman ──────────────────────────────────────────────────────────────── */

const shipping = ref(false);

// Only confirmed orders are handed over — the server enforces it, and saying so
// here means the merchant knows why a selection of twenty sent twelve. A
// previously rejected order still counts: fixing the address and pressing again
// is the whole point of showing the reason on the row.
const shippableCount = computed(
    () =>
        props.orders.data.filter(
            (o) => selected.value.includes(o.id) && o.status === 'confirmed' && !o.daman?.tracking_number,
        ).length,
);

const shipViaDaman = () => {
    if (!shippableCount.value) return;

    shipping.value = true;
    router.post(
        '/orders-bulk/daman',
        { ids: selected.value },
        {
            preserveScroll: true,
            onSuccess: () => (selected.value = []),
            onFinish: () => (shipping.value = false),
        },
    );
};

const damanResult = computed(
    () => page.props.flash?.daman_result as
        | { sent: number; failed: number; skipped: number; errors: string[] }
        | undefined,
);

// Exports whatever the merchant is currently looking at, not the whole table:
// they filter to "قيد المراجعة · القاهرة" and expect that file.
const exportUrl = computed(() =>
    `/orders/export?status=${encodeURIComponent(props.filters.status)}&gov=${encodeURIComponent(props.filters.gov)}`,
);

const setStatus = (order: Order, status: string) =>
    router.patch(`/orders/${order.id}`, { status }, { preserveScroll: true, preserveState: true });

// The Daman column earns its width only once the store is actually shipping
// through it — or still has orders that went out that way before it was turned
// off, which must not lose their waybill numbers.
const showDaman = computed(() => props.daman_enabled || props.orders.data.some((o) => o.daman));

const columns = computed(() => [
    { key: 'number', label: '#' },
    { key: 'customer_name', label: 'العميل' },
    { key: null, label: 'التليفون' },
    { key: 'governorate', label: 'المحافظة' },
    { key: null, label: 'العنوان' },
    { key: null, label: 'الطلب' },
    { key: 'total', label: 'الإجمالي' },
    { key: 'status', label: 'الحالة' },
    ...(showDaman.value ? [{ key: null, label: 'ضمان' }] : []),
    { key: 'created_at', label: 'التاريخ' },
]);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="الطلبات" />

        <div class="space-y-4 p-4 md:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-bold tracking-tight">الطلبات</h1>

                <div class="flex flex-wrap items-center gap-2">
                    <a :href="exportUrl" class="btn-outline shrink-0">
                        <Download class="size-4" />
                        تصدير Excel
                    </a>

                    <div class="relative w-full max-w-xs">
                        <Search class="absolute right-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <input v-model="search" class="field pr-9" placeholder="اسم، تليفون، أو رقم طلب" />
                    </div>
                </div>
            </div>

            <!-- ── Status tabs ────────────────────────────────────────── -->
            <div class="flex flex-wrap items-center gap-1.5">
                <button
                    v-for="tab in tabs"
                    :key="tab.value"
                    class="rounded-xl border px-3 py-1.5 text-sm transition-colors"
                    :class="filters.status === tab.value
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'border-border bg-card text-muted-foreground hover:text-foreground'"
                    @click="go({ status: tab.value })"
                >
                    {{ tab.label }}
                    <span class="tabular mr-1 opacity-70">{{ tab.count }}</span>
                </button>

                <select
                    v-if="governorates.length"
                    class="field w-auto py-1.5 text-sm"
                    :value="filters.gov"
                    @change="go({ gov: ($event.target as HTMLSelectElement).value })"
                >
                    <option value="">كل المحافظات</option>
                    <option v-for="gov in governorates" :key="gov" :value="gov">{{ gov }}</option>
                </select>
            </div>

            <!-- ── What the last hand-over to Daman did ────────────────
                 The per-order reasons, not just a count: a merchant fixing
                 three addresses needs to know which three.
            -->
            <div
                v-if="damanResult"
                class="rounded-xl border p-4 text-sm"
                :class="damanResult.failed
                    ? 'border-warning/30 bg-warning/5'
                    : 'border-success/30 bg-success/5'"
            >
                <p class="flex items-center gap-2 font-medium">
                    <Truck class="size-4 shrink-0" />
                    <span>
                        اتبعت <span class="tabular">{{ damanResult.sent }}</span> طلب لضمان.
                        <template v-if="damanResult.failed">
                            <span class="tabular">{{ damanResult.failed }}</span> ماتبعتوش.
                        </template>
                        <template v-if="damanResult.skipped">
                            <span class="tabular">{{ damanResult.skipped }}</span> اتخطّوا (مش متأكدين أو اتبعتوا قبل كده).
                        </template>
                    </span>
                </p>

                <ul v-if="damanResult.errors.length" class="mt-2 space-y-1 pr-6 text-xs text-muted-foreground">
                    <li v-for="(error, i) in damanResult.errors" :key="i" class="flex items-start gap-1.5">
                        <AlertTriangle class="mt-0.5 size-3 shrink-0 text-warning" />
                        {{ error }}
                    </li>
                </ul>
            </div>

            <!-- ── Bulk bar ───────────────────────────────────────────── -->
            <div v-if="selected.length" class="surface flex flex-wrap items-center gap-3 p-3">
                <span class="text-sm font-medium">{{ selected.length }} طلب متحدد</span>

                <select v-model="bulkStatus" class="field w-auto py-1.5 text-sm">
                    <option value="">غيّر الحالة إلى…</option>
                    <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>

                <button class="btn-primary py-1.5" :disabled="!bulkStatus" @click="applyBulk">
                    <Check class="size-4" />
                    نفّذ
                </button>

                <!-- Confirmed and not already sent, counted here rather than
                     discovered after the click: a merchant who ticks the whole
                     page should see that only twelve of the twenty will go. -->
                <button
                    v-if="daman_enabled"
                    class="btn-primary py-1.5"
                    :disabled="shipping || !shippableCount"
                    :title="shippableCount ? '' : 'الطلبات المتحددة إما مش متأكدة أو اتبعتت لضمان قبل كده'"
                    @click="shipViaDaman"
                >
                    <LoaderCircle v-if="shipping" class="size-4 animate-spin" />
                    <Truck v-else class="size-4" />
                    شحن عبر ضمان
                    <span v-if="shippableCount" class="tabular opacity-80">({{ shippableCount }})</span>
                </button>

                <a :href="`/orders/waybills?ids=${selected.join(',')}`" target="_blank" class="btn-outline py-1.5">
                    <Printer class="size-4" />
                    اطبع البوالص
                </a>

                <button class="btn-ghost py-1.5" @click="selected = []">
                    <X class="size-4" />
                    إلغاء التحديد
                </button>
            </div>

            <!-- ── Grid ───────────────────────────────────────────────── -->
            <div class="surface overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[72rem] border-collapse text-sm">
                        <thead class="bg-muted/50">
                            <tr>
                                <th class="w-10 border-b border-border px-3 py-2.5">
                                    <input
                                        type="checkbox"
                                        class="size-4 rounded border-input accent-primary"
                                        :checked="allSelected"
                                        @change="toggleAll"
                                    />
                                </th>
                                <th
                                    v-for="column in columns"
                                    :key="column.label"
                                    class="whitespace-nowrap border-b border-border px-3 py-2.5 text-right font-medium text-muted-foreground"
                                >
                                    <button
                                        v-if="column.key"
                                        class="inline-flex items-center gap-1 hover:text-foreground"
                                        @click="sortBy(column.key)"
                                    >
                                        {{ column.label }}
                                        <ArrowUp v-if="filters.sort === column.key && filters.dir === 'asc'" class="size-3" />
                                        <ArrowDown v-else-if="filters.sort === column.key" class="size-3" />
                                    </button>
                                    <span v-else>{{ column.label }}</span>
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="order in orders.data"
                                :key="order.id"
                                class="transition-colors"
                                :class="selected.includes(order.id) ? 'bg-primary/5' : 'hover:bg-muted/30'"
                            >
                                <td class="border-b border-border px-3 py-2">
                                    <input
                                        v-model="selected"
                                        type="checkbox"
                                        :value="order.id"
                                        class="size-4 rounded border-input accent-primary"
                                    />
                                </td>

                                <td class="tabular whitespace-nowrap border-b border-border px-3 py-2 font-semibold">
                                    <a :href="`/orders/${order.id}`" class="text-primary hover:underline">#{{ order.number }}</a>
                                </td>

                                <EditableCell :order-id="order.id" field="customer_name" :model-value="order.customer_name" />
                                <EditableCell :order-id="order.id" field="customer_phone" :model-value="order.customer_phone" type="tel" />
                                <EditableCell :order-id="order.id" field="governorate" :model-value="order.governorate" />
                                <EditableCell :order-id="order.id" field="address" :model-value="order.address" />

                                <td class="max-w-64 border-b border-border px-3 py-2 text-muted-foreground">
                                    <span class="block truncate" :title="order.items_summary">{{ order.items_summary }}</span>
                                </td>

                                <td class="tabular whitespace-nowrap border-b border-border px-3 py-2 text-left font-semibold" dir="ltr">
                                    {{ order.total }}
                                </td>

                                <td class="border-b border-border px-3 py-2">
                                    <select
                                        class="cursor-pointer rounded-lg border-0 py-1 pr-1 text-xs focus:outline-none focus:ring-2 focus:ring-ring/30"
                                        :class="STATUS_BADGE[order.status]"
                                        :value="order.status"
                                        @change="setStatus(order, ($event.target as HTMLSelectElement).value)"
                                    >
                                        <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                                    </select>
                                </td>

                                <!-- Two numbers, and the merchant needs both:
                                     ضمان's own order number is what support
                                     asks for, the tracking number is what the
                                     courier's call centre recognises. -->
                                <td v-if="showDaman" class="whitespace-nowrap border-b border-border px-3 py-2">
                                    <template v-if="order.daman?.tracking_number">
                                        <span class="tabular block font-medium" dir="ltr">{{ order.daman.tracking_number }}</span>
                                        <span class="block text-[11px] text-muted-foreground">
                                            {{ order.daman.carrier ?? 'ضمان' }}
                                            <span v-if="order.daman.order_number" class="tabular" dir="ltr">
                                                · {{ order.daman.order_number }}
                                            </span>
                                        </span>
                                    </template>

                                    <span
                                        v-else-if="order.daman?.error"
                                        class="badge-danger cursor-help"
                                        :title="order.daman.error"
                                    >
                                        اترفض
                                    </span>

                                    <span v-else class="text-muted-foreground/40">—</span>
                                </td>

                                <td class="tabular whitespace-nowrap border-b border-border px-3 py-2 text-xs text-muted-foreground" dir="ltr">
                                    {{ order.created_at }}
                                </td>
                            </tr>

                            <tr v-if="!orders.data.length">
                                <td :colspan="columns.length + 1" class="px-6 py-16 text-center text-muted-foreground">
                                    مفيش طلبات هنا.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-3">
                    <p class="text-xs text-muted-foreground">
                        <span class="tabular">{{ orders.from ?? 0 }}–{{ orders.to ?? 0 }}</span>
                        من <span class="tabular">{{ orders.total }}</span>
                        · دوس على أي خانة عشان تعدّلها
                    </p>
                </div>
            </div>

            <div v-if="orders.links.length > 3" class="flex flex-wrap justify-center gap-1">
                <component
                    v-for="(link, i) in orders.links"
                    :key="i"
                    :is="link.url ? 'a' : 'span'"
                    :href="link.url ?? undefined"
                    class="rounded-lg px-3 py-1.5 text-sm"
                    :class="link.active
                        ? 'bg-primary font-bold text-primary-foreground'
                        : link.url ? 'text-muted-foreground hover:bg-muted' : 'text-muted-foreground/40'"
                    v-html="link.label"
                />
            </div>
        </div>
    </AppLayout>
</template>
