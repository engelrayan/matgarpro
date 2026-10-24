<script setup lang="ts">
import AreaChart from '@/components/charts/AreaChart.vue';
import FunnelBars from '@/components/charts/FunnelBars.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, Check, ChevronLeft, Copy, ExternalLink, Gift, Globe, ImagePlus, Package, Plus, ShoppingBag } from 'lucide-vue-next';
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
    billing: {
        on_trial: boolean;
        trial_days_left: number | null;
        trial_ends_at: string | null;
        price_per_order: number;
        balance: number;
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

/*
 * The trial only becomes urgent near the end. Shown as a calm line for most of
 * the three months and as a warning in the last two weeks — a banner that
 * shouts from day one is one the merchant stops seeing by week two.
 */
const trialEnding = computed(
    () => props.billing.on_trial && (props.billing.trial_days_left ?? 0) <= 14,
);

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

        <!--
            Laid out as an iOS screen: a large title, then grouped cards on the
            canvas, each with a small grey caption over it. Every grid names a
            base column (`grid-cols-1`) — without one the implicit column grows
            to the widest nowrap line inside it, and the page scrolled sideways
            on a phone.
        -->
        <div class="mx-auto w-full max-w-7xl space-y-6 px-4 pb-6 md:space-y-7 md:px-6">
            <!-- ── Store header: large title ────────────────────────────── -->
            <section class="flex flex-wrap items-center gap-x-4 gap-y-4">
                <div class="flex min-w-0 flex-1 items-center gap-3.5">
                    <img v-if="store.logo_url" :src="store.logo_url" alt=""
                         class="size-14 shrink-0 rounded-[14px] object-cover shadow-[0_0_0_0.5px_hsl(var(--foreground)/0.1)]" />
                    <div v-else class="flex size-14 shrink-0 items-center justify-center rounded-[14px] bg-primary text-2xl font-bold text-primary-foreground">
                        {{ initial }}
                    </div>

                    <div class="min-w-0">
                        <h1 class="text-large-title truncate">{{ store.name }}</h1>
                        <button
                            class="-ms-1 mt-0.5 inline-flex max-w-full items-center gap-1.5 rounded-md px-1 text-[15px] text-muted-foreground transition-colors hover:text-foreground"
                            dir="ltr" @click="copyUrl"
                        >
                            <Check v-if="copied" class="size-3.5 shrink-0 text-success" />
                            <Copy v-else class="size-3.5 shrink-0" />
                            <span class="truncate">{{ store.host }}</span>
                        </button>
                    </div>
                </div>

                <a :href="store.url" target="_blank" rel="noopener" class="btn-primary w-full shrink-0 sm:w-auto">
                    <ExternalLink class="size-4" />
                    افتح المتجر
                </a>
            </section>

            <!--
                The free months, counted down.

                The merchant was promised three months and then half a pound an
                order. Both halves of that sentence live here, so the first
                charge is a number they have already read rather than a
                surprise on the day it lands.
            -->
            <section v-if="billing.on_trial"
                     class="surface flex items-start gap-3 p-4 text-[15px]"
                     :class="trialEnding ? 'bg-warning/[0.06] !shadow-[0_0_0_0.5px_hsl(var(--warning)/0.4)]' : ''">
                <span class="ios-tile" :class="trialEnding ? 'bg-warning' : 'bg-gold-500'">
                    <component :is="trialEnding ? AlertTriangle : Gift" class="size-[18px]" />
                </span>

                <p class="min-w-0 flex-1 leading-relaxed text-muted-foreground">
                    <span class="font-semibold" :class="trialEnding ? 'text-warning' : 'text-foreground'">
                        باقي <span class="tabular">{{ num(billing.trial_days_left ?? 0) }}</span> يوم مجاني
                    </span>
                    — بعد <span class="tabular">{{ billing.trial_ends_at }}</span> هتدفع
                    <span class="tabular font-medium text-foreground">{{ money(billing.price_per_order) }} {{ store.currency }}</span>
                    على الطلب. مافيش اشتراك شهري ولا نسبة من مبيعاتك.
                </p>
            </section>

            <!-- ── Setup: a grouped list, as in Settings ────────────────── -->
            <section v-if="remainingSteps.length">
                <h2 class="ios-caption mb-1.5">خطوات باقية</h2>
                <div class="ios-group grid grid-cols-1 lg:grid-cols-3">
                    <Link v-for="step in remainingSteps" :key="step.href" :href="step.href"
                          class="ios-row group min-h-[64px] transition-colors hover:bg-foreground/[0.03] active:bg-foreground/[0.06] lg:before:hidden">
                        <span class="ios-tile bg-gold-500 text-white">
                            <component :is="step.icon" class="size-[18px]" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-[16px] font-semibold leading-snug">{{ step.title }}</p>
                            <p class="truncate text-[13px] text-muted-foreground">{{ step.body }}</p>
                        </div>
                        <ChevronLeft class="size-5 shrink-0 text-muted-foreground/50 transition-transform group-hover:-translate-x-0.5" />
                    </Link>
                </div>
            </section>

            <!-- ── Range picker: a segmented control ─────────────────────── -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-title-3">الأداء</h2>
                <div class="ios-segmented w-full sm:w-auto" role="group" aria-label="الفترة">
                    <button v-for="r in RANGES" :key="r.key" type="button" class="ios-segment sm:px-4"
                            :aria-pressed="range === r.key"
                            @click="setRange(r.key)">
                        {{ r.label }}
                    </button>
                </div>
            </div>

            <!-- ── KPI row: two up on a phone, four on a desk ────────────── -->
            <section class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
                <div class="surface-lux flex min-w-0 flex-col p-4 md:p-5">
                    <span class="text-[13px] text-muted-foreground">مبيعات اتسلّمت</span>
                    <span class="text-foil tabular mt-1 truncate text-[22px] font-bold tracking-tight md:text-3xl">{{ money(insights.kpis.revenue) }}</span>
                    <span class="mt-0.5 text-[12px] text-muted-foreground">{{ store.currency }}</span>
                </div>

                <div class="surface flex min-w-0 flex-col p-4 md:p-5">
                    <span class="text-[13px] text-muted-foreground">صافي الربح</span>
                    <span class="tabular mt-1 truncate text-[22px] font-bold tracking-tight text-success md:text-3xl">{{ money(insights.kpis.profit.amount) }}</span>
                    <!-- Say how much of the revenue actually has a cost behind
                         it, otherwise uncosted products silently read as pure profit. -->
                    <span class="mt-0.5 text-[12px] leading-snug text-muted-foreground">
                        <template v-if="insights.kpis.profit.known_ratio >= 100">بعد خصم التكلفة</template>
                        <template v-else-if="insights.kpis.revenue > 0">
                            {{ insights.kpis.profit.known_ratio }}% بس من المبيعات متسجّل ليها تكلفة
                        </template>
                        <template v-else>سجّل تكلفة منتجاتك عشان يظهر</template>
                    </span>
                </div>

                <div class="surface flex min-w-0 flex-col p-4 md:p-5">
                    <span class="text-[13px] text-muted-foreground">الطلبات</span>
                    <span class="tabular mt-1 text-[22px] font-bold tracking-tight md:text-3xl">{{ num(insights.kpis.orders) }}</span>
                    <span class="mt-0.5 truncate text-[12px] text-muted-foreground">متوسط الطلب {{ money(insights.kpis.aov) }}</span>
                </div>

                <div class="surface flex min-w-0 flex-col p-4 md:p-5">
                    <span class="text-[13px] text-muted-foreground">معدل التحويل</span>
                    <span class="tabular mt-1 text-[22px] font-bold tracking-tight md:text-3xl" :class="hasTraffic ? '' : 'text-muted-foreground'">
                        {{ hasTraffic ? insights.kpis.conversion + '%' : '—' }}
                    </span>
                    <span class="mt-0.5 text-[12px] text-muted-foreground">
                        {{ hasTraffic ? num(insights.kpis.visits) + ' زيارة' : 'لسه مفيش زيارات' }}
                    </span>
                </div>
            </section>

            <!-- ── Secondary KPIs: one grouped list ───────────────────────── -->
            <section class="ios-group grid grid-cols-1 sm:grid-cols-3">
                <div class="ios-row justify-between sm:before:hidden">
                    <span class="text-[15px]">نسبة التسليم</span>
                    <span class="tabular text-[17px] font-semibold" :class="insights.kpis.delivery_rate >= 70 ? 'text-success' : insights.kpis.delivery_rate > 0 ? 'text-warning' : ''">
                        {{ insights.kpis.delivery_rate }}%
                    </span>
                </div>
                <div class="ios-row justify-between sm:before:hidden">
                    <span class="text-[15px]">نسبة الإلغاء</span>
                    <span class="tabular text-[17px] font-semibold" :class="insights.kpis.cancel_rate > 30 ? 'text-destructive' : ''">
                        {{ insights.kpis.cancel_rate }}%
                    </span>
                </div>
                <Link href="/orders?status=pending" class="ios-row justify-between transition-colors hover:bg-foreground/[0.03] active:bg-foreground/[0.06] sm:before:hidden">
                    <span class="text-[15px]">مستنية تأكيد</span>
                    <span class="flex items-center gap-1">
                        <span class="tabular text-[17px] font-semibold" :class="insights.kpis.pending > 0 ? 'text-warning' : ''">
                            {{ num(insights.kpis.pending) }}
                        </span>
                        <ChevronLeft class="size-5 text-muted-foreground/50" />
                    </span>
                </Link>
            </section>

            <!-- ── Charts ────────────────────────────────────────────────── -->
            <section class="grid grid-cols-1 gap-3 lg:grid-cols-3 lg:gap-4">
                <div class="surface p-4 md:p-5">
                    <h3 class="mb-3 text-[15px] font-semibold">المبيعات</h3>
                    <AreaChart :points="revenueSeries" :format="money" color="hsl(var(--gold-500))" />
                </div>
                <div class="surface p-4 md:p-5">
                    <h3 class="mb-3 text-[15px] font-semibold">الطلبات</h3>
                    <AreaChart :points="ordersSeries" color="hsl(var(--primary))" />
                </div>
                <div class="surface p-4 md:p-5">
                    <h3 class="mb-3 text-[15px] font-semibold">الزيارات</h3>
                    <AreaChart :points="visitsSeries" color="hsl(var(--info))" />
                </div>
            </section>

            <!-- ── Funnel · statuses · top products · low stock ───────────── -->
            <section class="grid grid-cols-1 gap-3 lg:grid-cols-2 lg:gap-4">
                <div class="surface p-4 md:p-5">
                    <h3 class="mb-4 text-[17px] font-semibold">رحلة العميل</h3>
                    <FunnelBars v-if="hasTraffic" :stages="funnelStages" />
                    <p v-else class="py-6 text-center text-[15px] text-muted-foreground">
                        أول ما حد يزور صفحة منتج، هتشوف هنا كام واحد وصل وكام أتمّ الطلب.
                    </p>
                </div>

                <div class="surface p-4 md:p-5">
                    <h3 class="mb-4 text-[17px] font-semibold">حالات الطلبات</h3>
                    <div v-if="insights.statuses.length" class="space-y-3">
                        <div v-for="s in insights.statuses" :key="s.status">
                            <div class="mb-1.5 flex items-center justify-between text-sm">
                                <span :class="STATUS_BADGE[s.status]">{{ STATUS_LABEL[s.status] }}</span>
                                <span class="tabular font-semibold">{{ s.count }}</span>
                            </div>
                            <div class="h-1.5 overflow-hidden rounded-full bg-foreground/[0.06]">
                                <div class="h-full rounded-full bg-primary/75 transition-[width] duration-700 ease-ios" :style="{ width: `${(s.count / statusTotal) * 100}%` }" />
                            </div>
                        </div>
                    </div>
                    <p v-else class="py-6 text-center text-[15px] text-muted-foreground">مفيش طلبات في الفترة دي.</p>
                </div>

                <div class="surface overflow-hidden">
                    <h3 class="px-4 pb-1 pt-4 text-[17px] font-semibold md:px-5 md:pt-5">الأكثر مبيعًا</h3>
                    <div v-if="insights.top_products.length" class="ios-group !rounded-none !shadow-none">
                        <div v-for="(p, i) in insights.top_products" :key="p.name" class="ios-row md:px-5">
                            <span class="tabular flex size-7 shrink-0 items-center justify-center rounded-lg bg-foreground/[0.06] text-xs font-bold">{{ i + 1 }}</span>
                            <span class="min-w-0 flex-1 truncate text-[15px]">{{ p.name }}</span>
                            <span class="tabular shrink-0 text-[13px] text-muted-foreground">{{ p.qty }} قطعة</span>
                            <span class="tabular w-20 shrink-0 text-left text-[15px] font-semibold md:w-24" dir="ltr">{{ money(p.revenue) }}</span>
                        </div>
                    </div>
                    <p v-else class="px-4 pb-8 pt-4 text-center text-[15px] text-muted-foreground">لسه مفيش مبيعات.</p>
                </div>

                <div class="surface overflow-hidden">
                    <h3 class="flex items-center gap-2 px-4 pb-1 pt-4 text-[17px] font-semibold md:px-5 md:pt-5">
                        <AlertTriangle v-if="insights.low_stock.length" class="size-4 text-warning" />
                        أوشك على النفاذ
                    </h3>
                    <div v-if="insights.low_stock.length" class="ios-group !rounded-none !shadow-none">
                        <Link v-for="p in insights.low_stock" :key="p.id" :href="`/products/${p.id}/edit`"
                              class="ios-row justify-between transition-colors hover:bg-foreground/[0.03] active:bg-foreground/[0.06] md:px-5">
                            <span class="min-w-0 flex-1 truncate text-[15px]">{{ p.name }}</span>
                            <span class="tabular shrink-0 text-[15px] font-semibold" :class="p.stock === 0 ? 'text-destructive' : 'text-warning'">
                                {{ p.stock === 0 ? 'خلص' : p.stock + ' متبقي' }}
                            </span>
                            <ChevronLeft class="size-5 shrink-0 text-muted-foreground/50" />
                        </Link>
                    </div>
                    <p v-else class="px-4 pb-8 pt-4 text-center text-[15px] text-muted-foreground">
                        {{ products_active > 0 ? 'المخزون كويس.' : 'لسه مفيش منتجات.' }}
                    </p>
                </div>
            </section>

            <!-- ── Recent orders ─────────────────────────────────────────── -->
            <section>
                <div class="mb-2 flex items-baseline justify-between">
                    <h2 class="text-title-3">آخر الطلبات</h2>
                    <Link v-if="recent.length" href="/orders" class="text-[15px] font-medium text-primary">كل الطلبات</Link>
                </div>

                <div v-if="!recent.length" class="surface px-6 py-12 text-center">
                    <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-foreground/[0.06]">
                        <ShoppingBag class="size-6 text-muted-foreground" />
                    </span>
                    <p class="mt-4 text-[17px] font-semibold">لسه مفيش طلبات</p>
                    <p class="mx-auto mt-1.5 max-w-xs text-[15px] text-muted-foreground">
                        أول ما تضيف منتج وتشارك اللينك، الطلبات هتبان هنا على طول.
                    </p>
                    <Link v-if="!setup.has_product" href="/products/create" class="btn-primary mt-6">
                        <Plus class="size-4" />
                        أضف أول منتج
                    </Link>
                </div>

                <div v-else class="ios-group">
                    <Link v-for="order in recent" :key="order.id" :href="`/orders/${order.id}`"
                          class="ios-row min-h-[60px] transition-colors hover:bg-foreground/[0.03] active:bg-foreground/[0.06]">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-[15px] font-semibold text-primary">
                            {{ order.customer_name.trim().charAt(0) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[16px] font-medium">{{ order.customer_name }}</p>
                            <p class="mt-0.5 truncate text-[13px] text-muted-foreground">
                                <span class="tabular">#{{ order.number }}</span> · {{ order.created_at }}
                            </p>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-1">
                            <span class="tabular text-[15px] font-semibold" dir="ltr">{{ order.total }}</span>
                            <span :class="STATUS_BADGE[order.status]" class="!px-2 !py-0.5 !text-[11px]">{{ order.status_label }}</span>
                        </div>
                        <ChevronLeft class="size-5 shrink-0 text-muted-foreground/40" />
                    </Link>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
