<script setup lang="ts">
import AreaChart from '@/components/charts/AreaChart.vue';
import FunnelBars from '@/components/charts/FunnelBars.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, Check, Copy, ExternalLink, Globe, ImagePlus, Package, Plus, ShoppingBag } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface SeriesPoint {
    date: string;
    label: string;
    orders: number;
    revenue: number;
    visits: number;
}

const props = defineProps<{
    store: { name: string; currency: string; url: string; host: string; logo_url: string | null };
    range: string;
    insights: {
        kpis: {
            orders: number;
            revenue: number;
            profit: { amount: number; cost: number; known_ratio: number };
            aov: number;
            visits: number;
            conversion: number;
            delivery_rate: number;
            cancel_rate: number;
            pending: number;
        };
        series: SeriesPoint[];
        funnel: { views: number; checkout_starts: number; orders: number };
        statuses: { status: string; count: number }[];
        top_products: { name: string; qty: number; revenue: number }[];
        low_stock: { id: number; name: string; stock: number }[];
    };
    setup: { has_product: boolean; has_domain: boolean; has_logo: boolean; has_order: boolean };
    recent: { id: number; number: number; customer_name: string; total: string; status: string; status_label: string; created_at: string }[];
    products_active: number;
}>();

const breadcrumbItems: BreadcrumbItem[] = [{ title: 'لوحة التحكم', href: '/dashboard' }];

const money = (v: number) => v.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const num = (v: number) => v.toLocaleString('en-US');

const RANGES = [
    { key: 'today', label: 'النهارده' },
    { key: '7d', label: '٧ أيام' },
    { key: '30d', label: '٣٠ يوم' },
    { key: '90d', label: '٣ شهور' },
];

const setRange = (key: string) =>
    router.get('/dashboard', { range: key }, { preserveState: true, preserveScroll: true, replace: true });

const STATUS_BADGE: Record<string, string> = {
    pending: 'badge-warning',
    confirmed: 'badge-info',
    shipped: 'badge-info',
    delivered: 'badge-success',
    cancelled: 'badge-danger',
    returned: 'badge-danger',
};

const STATUS_LABEL: Record<string, string> = {
    pending: 'قيد المراجعة',
    confirmed: 'تم التأكيد',
    shipped: 'تم الشحن',
    delivered: 'تم التوصيل',
    cancelled: 'ملغي',
    returned: 'مرتجع',
};

const remainingSteps = computed(() =>
    [
        { done: props.setup.has_product, title: 'أضف أول منتج', body: 'اسم وسعر وصورة وخلاص.', href: '/products/create', icon: Package },
        { done: props.setup.has_logo, title: 'ارفع لوجو المتجر', body: 'بيظهر للعميل في كل صفحة.', href: '/settings/store', icon: ImagePlus },
        { done: props.setup.has_domain, title: 'اربط دومينك', body: 'دقيقة، والشهادة بتتظبط لوحدها.', href: '/settings/domains', icon: Globe },
    ].filter((s) => !s.done),
);

const initial = computed(() => props.store.name.trim().charAt(0) || 'م');

const revenueSeries = computed(() => props.insights.series.map((p) => ({ label: p.label, value: p.revenue })));
const ordersSeries = computed(() => props.insights.series.map((p) => ({ label: p.label, value: p.orders })));
const visitsSeries = computed(() => props.insights.series.map((p) => ({ label: p.label, value: p.visits })));

const funnelStages = computed(() => [
    { label: 'زيارات', value: props.insights.funnel.views },
    { label: 'بدأوا الطلب', value: props.insights.funnel.checkout_starts },
    { label: 'أتمّوا الطلب', value: props.insights.funnel.orders },
]);

const statusTotal = computed(() => props.insights.statuses.reduce((sum, s) => sum + s.count, 0));

// No tracking data at all reads as "0% conversion", which is alarming and
// wrong. Say the number is not there yet instead.
const hasTraffic = computed(() => props.insights.funnel.views > 0);

const copied = ref(false);
const copyUrl = async () => {
    await navigator.clipboard.writeText(props.store.url);
    copied.value = true;
    setTimeout(() => (copied.value = false), 1500);
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="لوحة التحكم" />

        <div class="mx-auto w-full max-w-7xl space-y-5 p-4 md:p-6">
            <!-- ── Store header ─────────────────────────────────────────── -->
            <section class="surface-lux bg-aurora overflow-hidden p-6">
                <div class="flex flex-wrap items-center gap-5">
                    <img v-if="store.logo_url" :src="store.logo_url" alt=""
                         class="size-14 shrink-0 rounded-2xl border border-border object-cover" />
                    <div v-else class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-primary text-2xl font-bold text-primary-foreground">
                        {{ initial }}
                    </div>

                    <div class="min-w-0 flex-1">
                        <h1 class="truncate text-2xl font-bold tracking-tight">{{ store.name }}</h1>
                        <button
                            class="mt-1.5 inline-flex max-w-full items-center gap-2 rounded-lg bg-card/70 px-2.5 py-1 font-mono text-sm text-muted-foreground transition-colors hover:text-foreground"
                            dir="ltr" @click="copyUrl"
                        >
                            <Check v-if="copied" class="size-3.5 shrink-0 text-success" />
                            <Copy v-else class="size-3.5 shrink-0" />
                            <span class="truncate">{{ store.host }}</span>
                        </button>
                    </div>

                    <a :href="store.url" target="_blank" rel="noopener" class="btn-primary sheen shrink-0">
                        <ExternalLink class="size-4" />
                        افتح المتجر
                    </a>
                </div>
            </section>

            <!-- ── Setup ─────────────────────────────────────────────────── -->
            <section v-if="remainingSteps.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link v-for="step in remainingSteps" :key="step.href" :href="step.href"
                      class="surface group flex items-center gap-4 p-5 transition-all hover:shadow-e2">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-gold-500/10 text-gold-700 dark:text-gold-300">
                        <component :is="step.icon" class="size-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">{{ step.title }}</p>
                        <p class="mt-0.5 truncate text-sm text-muted-foreground">{{ step.body }}</p>
                    </div>
                    <ArrowLeft class="size-4 shrink-0 text-muted-foreground transition-transform group-hover:-translate-x-1" />
                </Link>
            </section>

            <!-- ── Range picker ──────────────────────────────────────────── -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">الأداء</h2>
                <div class="flex rounded-xl border border-border bg-card p-1">
                    <button v-for="r in RANGES" :key="r.key" class="rounded-lg px-3 py-1.5 text-sm transition-colors"
                            :class="range === r.key ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                            @click="setRange(r.key)">
                        {{ r.label }}
                    </button>
                </div>
            </div>

            <!-- ── KPI row ───────────────────────────────────────────────── -->
            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="surface-lux flex flex-col justify-center p-5">
                    <span class="text-sm text-muted-foreground">مبيعات اتسلّمت</span>
                    <span class="text-foil tabular mt-1.5 text-3xl font-bold tracking-tight">{{ money(insights.kpis.revenue) }}</span>
                    <span class="mt-1 text-xs text-muted-foreground">{{ store.currency }}</span>
                </div>

                <div class="surface flex flex-col justify-center p-5">
                    <span class="text-sm text-muted-foreground">صافي الربح</span>
                    <span class="tabular mt-1.5 text-3xl font-bold tracking-tight text-success">{{ money(insights.kpis.profit.amount) }}</span>
                    <!-- Say how much of the revenue actually has a cost behind
                         it, otherwise uncosted products silently read as pure profit. -->
                    <span class="mt-1 text-xs text-muted-foreground">
                        <template v-if="insights.kpis.profit.known_ratio >= 100">بعد خصم التكلفة</template>
                        <template v-else-if="insights.kpis.revenue > 0">
                            {{ insights.kpis.profit.known_ratio }}% بس من المبيعات متسجّل ليها تكلفة
                        </template>
                        <template v-else>سجّل تكلفة منتجاتك عشان يظهر</template>
                    </span>
                </div>

                <div class="surface flex flex-col justify-center p-5">
                    <span class="text-sm text-muted-foreground">الطلبات</span>
                    <span class="tabular mt-1.5 text-3xl font-bold tracking-tight">{{ num(insights.kpis.orders) }}</span>
                    <span class="mt-1 text-xs text-muted-foreground">متوسط الطلب {{ money(insights.kpis.aov) }}</span>
                </div>

                <div class="surface flex flex-col justify-center p-5">
                    <span class="text-sm text-muted-foreground">معدل التحويل</span>
                    <span class="tabular mt-1.5 text-3xl font-bold tracking-tight" :class="hasTraffic ? '' : 'text-muted-foreground'">
                        {{ hasTraffic ? insights.kpis.conversion + '%' : '—' }}
                    </span>
                    <span class="mt-1 text-xs text-muted-foreground">
                        {{ hasTraffic ? num(insights.kpis.visits) + ' زيارة' : 'لسه مفيش زيارات' }}
                    </span>
                </div>
            </section>

            <!-- ── Secondary KPIs ────────────────────────────────────────── -->
            <section class="grid gap-4 sm:grid-cols-3">
                <div class="surface flex items-center justify-between p-4">
                    <span class="text-sm text-muted-foreground">نسبة التسليم</span>
                    <span class="tabular text-xl font-semibold" :class="insights.kpis.delivery_rate >= 70 ? 'text-success' : insights.kpis.delivery_rate > 0 ? 'text-warning' : ''">
                        {{ insights.kpis.delivery_rate }}%
                    </span>
                </div>
                <div class="surface flex items-center justify-between p-4">
                    <span class="text-sm text-muted-foreground">نسبة الإلغاء</span>
                    <span class="tabular text-xl font-semibold" :class="insights.kpis.cancel_rate > 30 ? 'text-destructive' : ''">
                        {{ insights.kpis.cancel_rate }}%
                    </span>
                </div>
                <Link href="/orders" class="surface flex items-center justify-between p-4 transition-colors hover:bg-muted/40">
                    <span class="text-sm text-muted-foreground">مستنية تأكيد</span>
                    <span class="tabular text-xl font-semibold" :class="insights.kpis.pending > 0 ? 'text-warning' : ''">
                        {{ num(insights.kpis.pending) }}
                    </span>
                </Link>
            </section>

            <!-- ── Charts ────────────────────────────────────────────────── -->
            <section class="grid gap-5 lg:grid-cols-3">
                <div class="surface p-5">
                    <h3 class="mb-4 text-sm font-medium text-muted-foreground">المبيعات</h3>
                    <AreaChart :points="revenueSeries" :format="money" color="hsl(var(--gold-500))" />
                </div>
                <div class="surface p-5">
                    <h3 class="mb-4 text-sm font-medium text-muted-foreground">الطلبات</h3>
                    <AreaChart :points="ordersSeries" color="hsl(var(--primary))" />
                </div>
                <div class="surface p-5">
                    <h3 class="mb-4 text-sm font-medium text-muted-foreground">الزيارات</h3>
                    <AreaChart :points="visitsSeries" color="hsl(var(--info))" />
                </div>
            </section>

            <!-- ── Funnel · statuses · top products · low stock ───────────── -->
            <section class="grid gap-5 lg:grid-cols-2">
                <div class="surface p-5">
                    <h3 class="mb-5 font-semibold">رحلة العميل</h3>
                    <FunnelBars v-if="hasTraffic" :stages="funnelStages" />
                    <p v-else class="py-6 text-center text-sm text-muted-foreground">
                        أول ما حد يزور صفحة منتج، هتشوف هنا كام واحد وصل وكام أتمّ الطلب.
                    </p>
                </div>

                <div class="surface p-5">
                    <h3 class="mb-5 font-semibold">حالات الطلبات</h3>
                    <div v-if="insights.statuses.length" class="space-y-3">
                        <div v-for="s in insights.statuses" :key="s.status">
                            <div class="mb-1.5 flex items-center justify-between text-sm">
                                <span :class="STATUS_BADGE[s.status]">{{ STATUS_LABEL[s.status] }}</span>
                                <span class="tabular font-semibold">{{ s.count }}</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-muted">
                                <div class="h-full rounded-full bg-primary/70" :style="{ width: `${(s.count / statusTotal) * 100}%` }" />
                            </div>
                        </div>
                    </div>
                    <p v-else class="py-6 text-center text-sm text-muted-foreground">مفيش طلبات في الفترة دي.</p>
                </div>

                <div class="surface p-5">
                    <h3 class="mb-4 font-semibold">الأكثر مبيعًا</h3>
                    <div v-if="insights.top_products.length" class="divide-y divide-border">
                        <div v-for="(p, i) in insights.top_products" :key="p.name" class="flex items-center gap-3 py-2.5">
                            <span class="tabular flex size-7 shrink-0 items-center justify-center rounded-lg bg-muted text-xs font-bold">{{ i + 1 }}</span>
                            <span class="min-w-0 flex-1 truncate text-sm">{{ p.name }}</span>
                            <span class="tabular shrink-0 text-sm text-muted-foreground">{{ p.qty }} قطعة</span>
                            <span class="tabular w-24 shrink-0 text-left text-sm font-semibold" dir="ltr">{{ money(p.revenue) }}</span>
                        </div>
                    </div>
                    <p v-else class="py-6 text-center text-sm text-muted-foreground">لسه مفيش مبيعات.</p>
                </div>

                <div class="surface p-5">
                    <h3 class="mb-4 flex items-center gap-2 font-semibold">
                        <AlertTriangle v-if="insights.low_stock.length" class="size-4 text-warning" />
                        أوشك على النفاذ
                    </h3>
                    <div v-if="insights.low_stock.length" class="divide-y divide-border">
                        <Link v-for="p in insights.low_stock" :key="p.id" :href="`/products/${p.id}/edit`"
                              class="flex items-center justify-between py-2.5 transition-colors hover:text-primary">
                            <span class="min-w-0 flex-1 truncate text-sm">{{ p.name }}</span>
                            <span class="tabular shrink-0 text-sm font-semibold" :class="p.stock === 0 ? 'text-destructive' : 'text-warning'">
                                {{ p.stock === 0 ? 'خلص' : p.stock + ' متبقي' }}
                            </span>
                        </Link>
                    </div>
                    <p v-else class="py-6 text-center text-sm text-muted-foreground">
                        {{ products_active > 0 ? 'المخزون كويس.' : 'لسه مفيش منتجات.' }}
                    </p>
                </div>
            </section>

            <!-- ── Recent orders ─────────────────────────────────────────── -->
            <section class="surface overflow-hidden">
                <div class="flex items-center justify-between border-b border-border px-5 py-4">
                    <h2 class="font-semibold">آخر الطلبات</h2>
                    <Link v-if="recent.length" href="/orders" class="text-sm font-medium text-primary hover:underline">كل الطلبات</Link>
                </div>

                <div v-if="!recent.length" class="px-6 py-12 text-center">
                    <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-muted">
                        <ShoppingBag class="size-6 text-muted-foreground" />
                    </span>
                    <p class="mt-4 font-medium">لسه مفيش طلبات</p>
                    <p class="mx-auto mt-1.5 max-w-xs text-sm text-muted-foreground">
                        أول ما تضيف منتج وتشارك اللينك، الطلبات هتبان هنا على طول.
                    </p>
                    <Link v-if="!setup.has_product" href="/products/create" class="btn-primary mt-6">
                        <Plus class="size-4" />
                        أضف أول منتج
                    </Link>
                </div>

                <div v-else class="divide-y divide-border">
                    <Link v-for="order in recent" :key="order.id" href="/orders"
                          class="flex items-center gap-4 px-5 py-3.5 transition-colors hover:bg-muted/50">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted text-sm font-semibold">
                            {{ order.customer_name.trim().charAt(0) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">{{ order.customer_name }}</p>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                <span class="tabular">#{{ order.number }}</span> · {{ order.created_at }}
                            </p>
                        </div>
                        <span :class="STATUS_BADGE[order.status]" class="shrink-0">{{ order.status_label }}</span>
                        <span class="tabular w-20 shrink-0 text-left font-semibold" dir="ltr">{{ order.total }}</span>
                    </Link>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
