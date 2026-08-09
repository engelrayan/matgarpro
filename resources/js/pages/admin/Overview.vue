<script setup lang="ts">
import StatTile from '@/components/admin/StatTile.vue';
import AreaChart from '@/components/charts/AreaChart.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { compact, money, num } from '@/composables/useFormat';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, Package, ShoppingBag, Store, Users, Wallet } from 'lucide-vue-next';
import { computed } from 'vue';

interface SeriesPoint {
    date: string;
    label: string;
    orders: number;
    gmv: number;
    earnings: number;
    stores: number;
}

const props = defineProps<{
    range: string;
    currency: string;
    insights: {
        kpis: Record<string, number>;
        series: SeriesPoint[];
        stores: { total: number; active: number; draft: number; suspended: number; billing_suspended: number };
        orders: { status: string; label: string; count: number }[];
        themes: { key: string; name: string; primary: string; stores: number; share: number }[];
        domains: { total: number; active: number; pending: number; failed: number };
        top_stores: { id: number; name: string; slug: string; orders: number; revenue: number }[];
        newest_stores: { id: number; name: string; slug: string; status: string; orders_count: number; merchant: string; created_at: string }[];
        attention: {
            overdrawn: { id: number; name: string; slug: string; balance: number }[];
            failed_domains: { id: number; domain: string; store: string; store_id: number; error: string }[];
            empty_stores: { id: number; name: string; slug: string; created_at: string }[];
        };
    };
}>();

const RANGES = [
    { key: 'today', label: 'النهارده' },
    { key: '7d', label: '٧ أيام' },
    { key: '30d', label: '٣٠ يوم' },
    { key: '90d', label: '٣ شهور' },
    { key: '365d', label: 'سنة' },
];

const setRange = (key: string) =>
    router.get('/admin', { range: key }, { preserveState: true, preserveScroll: true, replace: true });

const k = computed(() => props.insights.kpis);

const gmvSeries = computed(() => props.insights.series.map((p) => ({ label: p.label, value: p.gmv })));
const earningsSeries = computed(() => props.insights.series.map((p) => ({ label: p.label, value: p.earnings })));
const ordersSeries = computed(() => props.insights.series.map((p) => ({ label: p.label, value: p.orders })));
const storesSeries = computed(() => props.insights.series.map((p) => ({ label: p.label, value: p.stores })));

const ordersTotal = computed(() => props.insights.orders.reduce((sum, o) => sum + o.count, 0));

const STATUS_BADGE: Record<string, string> = {
    pending: 'badge-warning',
    confirmed: 'badge-info',
    shipped: 'badge-info',
    delivered: 'badge-success',
    cancelled: 'badge-danger',
    returned: 'badge-danger',
};

const STORE_BADGE: Record<string, string> = {
    active: 'badge-success',
    draft: 'badge-neutral',
    suspended: 'badge-danger',
};

const STORE_LABEL: Record<string, string> = {
    active: 'شغّال',
    draft: 'مسودة',
    suspended: 'موقوف',
};

// Three separate lists, one count — the header number has to match what the
// operator is about to read underneath it.
const attentionCount = computed(
    () =>
        props.insights.attention.overdrawn.length +
        props.insights.attention.failed_domains.length +
        props.insights.attention.empty_stores.length,
);
</script>

<template>
    <AdminLayout title="نظرة عامة" subtitle="كل اللي بيحصل في المنصة">
        <Head title="نظرة عامة" />

        <div class="mx-auto w-full max-w-7xl space-y-5">
            <!-- ── Range ─────────────────────────────────────────────────── -->
            <div class="flex flex-wrap items-center gap-1 rounded-xl border border-border bg-card p-1">
                <button
                    v-for="r in RANGES"
                    :key="r.key"
                    class="rounded-lg px-3 py-1.5 text-sm transition-colors"
                    :class="range === r.key ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                    @click="setRange(r.key)"
                >
                    {{ r.label }}
                </button>
            </div>

            <!-- ── Headline ──────────────────────────────────────────────── -->
            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatTile
                    label="المتاجر"
                    :value="num(k.stores_total)"
                    :hint="`${num(k.stores_active)} شغّال · ${num(k.stores_new)} جديد في الفترة`"
                    :icon="Store"
                    href="/admin/stores"
                />
                <StatTile
                    label="التجار"
                    :value="num(k.merchants_total)"
                    :hint="`${num(k.merchants_new)} اشترك في الفترة`"
                    :icon="Users"
                    href="/admin/merchants"
                />
                <StatTile
                    label="الطلبات في الفترة"
                    :value="num(k.orders_total)"
                    :hint="`${num(k.orders_all_time)} إجمالي من البداية`"
                    :icon="ShoppingBag"
                />
                <StatTile label="المنتجات" :value="num(k.products_total)" hint="على كل المتاجر" :icon="Package" />
            </section>

            <!-- ── Money ─────────────────────────────────────────────────── -->
            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <StatTile
                    lux
                    label="مبيعات التجار (موصّلة)"
                    :value="`${money(k.gmv)} ${currency}`"
                    hint="ده فلوس التجار مش فلوسنا"
                />
                <StatTile
                    lux
                    label="دخل المنصة في الفترة"
                    :value="`${money(k.earnings)} ${currency}`"
                    :hint="`${money(k.earnings_all_time)} ${currency} من البداية`"
                />
                <StatTile
                    lux
                    label="أرصدة المحافظ"
                    :value="`${money(k.wallet_balance)} ${currency}`"
                    :hint="k.wallets_negative ? `${num(k.wallets_negative)} متجر رصيده بالسالب` : 'مفيش رصيد سالب'"
                    :icon="Wallet"
                />
            </section>

            <!-- ── Charts ────────────────────────────────────────────────── -->
            <section class="grid gap-4 lg:grid-cols-2">
                <div class="surface p-5">
                    <p class="mb-3 text-sm font-medium">مبيعات التجار</p>
                    <AreaChart :points="gmvSeries" :format="(v) => `${money(v)} ${currency}`" />
                </div>
                <div class="surface p-5">
                    <p class="mb-3 text-sm font-medium">دخل المنصة</p>
                    <AreaChart
                        :points="earningsSeries"
                        color="hsl(var(--gold-500))"
                        :format="(v) => `${money(v)} ${currency}`"
                    />
                </div>
                <div class="surface p-5">
                    <p class="mb-3 text-sm font-medium">الطلبات</p>
                    <AreaChart :points="ordersSeries" color="hsl(var(--info))" :format="compact" />
                </div>
                <div class="surface p-5">
                    <p class="mb-3 text-sm font-medium">متاجر جديدة</p>
                    <AreaChart :points="storesSeries" color="hsl(var(--success))" :format="num" />
                </div>
            </section>

            <!-- ── Breakdown ─────────────────────────────────────────────── -->
            <section class="grid gap-4 lg:grid-cols-3">
                <div class="surface p-5">
                    <p class="mb-4 text-sm font-medium">حالة المتاجر</p>
                    <ul class="space-y-2.5 text-sm">
                        <li class="flex items-center justify-between">
                            <span class="badge-success">شغّال</span>
                            <span class="tabular font-medium">{{ num(insights.stores.active) }}</span>
                        </li>
                        <li class="flex items-center justify-between">
                            <span class="badge-neutral">مسودة</span>
                            <span class="tabular font-medium">{{ num(insights.stores.draft) }}</span>
                        </li>
                        <li class="flex items-center justify-between">
                            <span class="badge-danger">موقوف</span>
                            <span class="tabular font-medium">{{ num(insights.stores.suspended) }}</span>
                        </li>
                        <li class="flex items-center justify-between border-t border-border pt-2.5">
                            <span class="text-muted-foreground">موقوف لأسباب مالية</span>
                            <span class="tabular font-medium">{{ num(insights.stores.billing_suspended) }}</span>
                        </li>
                        <li v-if="k.demo_stores" class="flex items-center justify-between">
                            <span class="text-muted-foreground">متاجر عرض (مش محسوبة فوق)</span>
                            <span class="tabular font-medium">{{ num(k.demo_stores) }}</span>
                        </li>
                    </ul>
                </div>

                <div class="surface p-5">
                    <p class="mb-4 text-sm font-medium">الطلبات حسب الحالة</p>
                    <ul v-if="ordersTotal" class="space-y-2.5 text-sm">
                        <li v-for="row in insights.orders" :key="row.status" class="flex items-center justify-between">
                            <span :class="STATUS_BADGE[row.status]">{{ row.label }}</span>
                            <span class="tabular font-medium">{{ num(row.count) }}</span>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">مفيش طلبات في الفترة دي.</p>
                </div>

                <div class="surface p-5">
                    <div class="mb-4 flex items-center justify-between">
                        <p class="text-sm font-medium">الدومينات</p>
                        <Link href="/admin/domains" class="text-xs text-muted-foreground hover:text-foreground">
                            كلها
                        </Link>
                    </div>
                    <ul class="space-y-2.5 text-sm">
                        <li class="flex items-center justify-between">
                            <span class="badge-success">شغّال</span>
                            <span class="tabular font-medium">{{ num(insights.domains.active) }}</span>
                        </li>
                        <li class="flex items-center justify-between">
                            <span class="badge-warning">تحت المراجعة</span>
                            <span class="tabular font-medium">{{ num(insights.domains.pending) }}</span>
                        </li>
                        <li class="flex items-center justify-between">
                            <span class="badge-danger">فشل</span>
                            <span class="tabular font-medium">{{ num(insights.domains.failed) }}</span>
                        </li>
                        <li class="flex items-center justify-between border-t border-border pt-2.5">
                            <span class="text-muted-foreground">الزيارات في الفترة</span>
                            <span class="tabular font-medium">{{ num(k.visits) }}</span>
                        </li>
                    </ul>
                </div>
            </section>

            <!-- ── Needs attention ───────────────────────────────────────── -->
            <section v-if="attentionCount" class="surface p-5">
                <div class="mb-4 flex items-center gap-2">
                    <AlertTriangle class="size-4 text-warning" />
                    <p class="text-sm font-medium">محتاج متابعة</p>
                    <span class="badge-warning">{{ num(attentionCount) }}</span>
                </div>

                <div class="grid gap-5 md:grid-cols-3">
                    <div v-if="insights.attention.overdrawn.length">
                        <p class="mb-2 text-xs text-muted-foreground">رصيد بالسالب — مش بيقبلوا طلبات</p>
                        <ul class="space-y-1.5 text-sm">
                            <li v-for="s in insights.attention.overdrawn" :key="s.id">
                                <Link :href="`/admin/stores/${s.id}`" class="flex justify-between gap-2 hover:underline">
                                    <span class="truncate">{{ s.name }}</span>
                                    <span class="tabular shrink-0 text-destructive">{{ money(s.balance) }}</span>
                                </Link>
                            </li>
                        </ul>
                    </div>

                    <div v-if="insights.attention.failed_domains.length">
                        <p class="mb-2 text-xs text-muted-foreground">دومينات فشلت</p>
                        <ul class="space-y-1.5 text-sm">
                            <li v-for="d in insights.attention.failed_domains" :key="d.id" class="truncate">
                                <Link :href="`/admin/stores/${d.store_id}`" class="hover:underline" dir="ltr">
                                    {{ d.domain }}
                                </Link>
                            </li>
                        </ul>
                    </div>

                    <div v-if="insights.attention.empty_stores.length">
                        <p class="mb-2 text-xs text-muted-foreground">متاجر بدون منتجات</p>
                        <ul class="space-y-1.5 text-sm">
                            <li v-for="s in insights.attention.empty_stores" :key="s.id">
                                <Link :href="`/admin/stores/${s.id}`" class="flex justify-between gap-2 hover:underline">
                                    <span class="truncate">{{ s.name }}</span>
                                    <span class="shrink-0 text-xs text-muted-foreground">{{ s.created_at }}</span>
                                </Link>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- ── Tables ────────────────────────────────────────────────── -->
            <section class="grid gap-4 lg:grid-cols-2">
                <div class="surface overflow-hidden">
                    <p class="border-b border-border p-5 text-sm font-medium">أعلى المتاجر في الفترة</p>
                    <table v-if="insights.top_stores.length" class="w-full text-sm">
                        <thead class="text-xs text-muted-foreground">
                            <tr class="border-b border-border">
                                <th class="px-5 py-2.5 text-right font-medium">المتجر</th>
                                <th class="px-5 py-2.5 text-left font-medium">طلبات</th>
                                <th class="px-5 py-2.5 text-left font-medium">مبيعات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="s in insights.top_stores" :key="s.id" class="border-b border-border/60 last:border-0">
                                <td class="px-5 py-2.5">
                                    <Link :href="`/admin/stores/${s.id}`" class="hover:underline">{{ s.name }}</Link>
                                </td>
                                <td class="tabular px-5 py-2.5 text-left">{{ num(s.orders) }}</td>
                                <td class="tabular px-5 py-2.5 text-left">{{ money(s.revenue) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="p-5 text-sm text-muted-foreground">مفيش مبيعات في الفترة دي.</p>
                </div>

                <div class="surface overflow-hidden">
                    <p class="border-b border-border p-5 text-sm font-medium">أحدث المتاجر</p>
                    <ul>
                        <li
                            v-for="s in insights.newest_stores"
                            :key="s.id"
                            class="flex items-center justify-between gap-3 border-b border-border/60 px-5 py-3 last:border-0"
                        >
                            <div class="min-w-0">
                                <Link :href="`/admin/stores/${s.id}`" class="truncate text-sm font-medium hover:underline">
                                    {{ s.name }}
                                </Link>
                                <p class="truncate text-xs text-muted-foreground">
                                    {{ s.merchant }} · {{ s.created_at }}
                                </p>
                            </div>
                            <span :class="STORE_BADGE[s.status]" class="shrink-0">{{ STORE_LABEL[s.status] }}</span>
                        </li>
                    </ul>
                </div>
            </section>

            <!-- ── Themes ────────────────────────────────────────────────── -->
            <section class="surface p-5">
                <div class="mb-4 flex items-center justify-between">
                    <p class="text-sm font-medium">توزيع الثيمات</p>
                    <Link href="/admin/themes" class="text-xs text-muted-foreground hover:text-foreground">التفاصيل</Link>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="t in insights.themes"
                        :key="t.key"
                        :href="`/admin/themes?theme=${t.key}`"
                        class="flex items-center gap-3 rounded-xl border border-border p-3 transition-colors hover:bg-muted/60"
                    >
                        <span class="size-8 shrink-0 rounded-lg" :style="{ background: `hsl(${t.primary})` }" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ t.name }}</p>
                            <p class="text-xs text-muted-foreground">{{ num(t.stores) }} متجر · {{ t.share }}%</p>
                        </div>
                    </Link>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
