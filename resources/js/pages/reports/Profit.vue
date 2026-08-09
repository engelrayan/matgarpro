<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, Info, TrendingDown } from 'lucide-vue-next';
import { computed } from 'vue';

interface Row {
    product_id: number | null;
    name: string;
    sold: number;
    returned: number;
    revenue: number;
    cost: number;
    return_cost: number;
    profit: number;
    margin: number | null;
    return_rate: number;
    has_cost: boolean;
}

const props = defineProps<{
    range: string;
    currency: string;
    return_cost: number;
    report: {
        totals: { revenue: number; cost: number; return_cost: number; profit: number; sold: number; returned: number };
        products: Row[];
        cost_coverage: { known: number; total: number; percent: number };
    };
}>();

const breadcrumbItems: BreadcrumbItem[] = [{ title: 'تقرير الربح', href: '/reports/profit' }];

const money = (v: number) =>
    Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const RANGES = [
    { key: '7d', label: '٧ أيام' },
    { key: '30d', label: '٣٠ يوم' },
    { key: '90d', label: '٣ شهور' },
    { key: 'all', label: 'من البداية' },
];

const setRange = (key: string) =>
    router.get('/reports/profit', { range: key }, { preserveState: true, preserveScroll: true, replace: true });

const t = computed(() => props.report.totals);

// Without costs the "profit" column is revenue wearing a different label. Say
// so rather than showing a confident wrong number.
const costsIncomplete = computed(() => props.report.cost_coverage.percent < 100);

const losers = computed(() => props.report.products.filter((p) => p.profit < 0));
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="تقرير الربح" />

        <div class="mx-auto w-full max-w-7xl space-y-5 p-4 md:p-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">تقرير الربح</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        الطلبات اللي اتسلّمت فعلاً، ناقص تكلفة البضاعة وتكلفة المرتجع.
                    </p>
                </div>

                <div class="flex rounded-xl border border-border bg-card p-1">
                    <button v-for="r in RANGES" :key="r.key"
                            class="rounded-lg px-3 py-1.5 text-sm transition-colors"
                            :class="range === r.key ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                            @click="setRange(r.key)">{{ r.label }}</button>
                </div>
            </div>

            <!-- ── Totals ──────────────────────────────────────────────── -->
            <div class="grid gap-4 md:grid-cols-4">
                <div class="surface-lux p-5 md:col-span-1">
                    <p class="text-sm text-muted-foreground">صافي الربح</p>
                    <p class="tabular mt-2 text-3xl font-bold tracking-tight"
                       :class="t.profit >= 0 ? 'text-foil' : 'text-destructive'">
                        {{ money(t.profit) }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">{{ currency }}</p>
                </div>

                <div class="stat-tile">
                    <span class="stat-tile__value">{{ money(t.revenue) }}</span>
                    <span class="stat-tile__label">مبيعات اتسلّمت</span>
                </div>

                <div class="stat-tile">
                    <span class="stat-tile__value">−{{ money(t.cost) }}</span>
                    <span class="stat-tile__label">تكلفة البضاعة</span>
                </div>

                <div class="stat-tile">
                    <span class="stat-tile__value text-destructive">−{{ money(t.return_cost) }}</span>
                    <span class="stat-tile__label">
                        تكلفة المرتجع
                        <span class="tabular">({{ t.returned }})</span>
                    </span>
                </div>
            </div>

            <!-- The point of the whole report, said in one line. `gap-3` and a
                 fixed icon column: at Arabic line-heights the icon and the text
                 collide when they share inline flow. -->
            <div class="surface flex items-start gap-3 p-4 text-sm">
                <Info class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                <p class="min-w-0 flex-1 leading-relaxed text-muted-foreground">
                    الطلب المرتجع مش صفر — إنت دفعت شحن رايح وجاي. بنحسبه
                    <span class="tabular font-medium text-foreground">{{ money(return_cost) }} {{ currency }}</span>
                    على الطرد، وده رقم متحفّظ لحد ما ضمان يرجّعلنا التكلفة الحقيقية لكل شحنة.
                </p>
            </div>

            <div v-if="costsIncomplete" class="flex flex-wrap items-center gap-2 rounded-xl border border-warning/30 bg-warning/5 p-4 text-sm text-warning">
                <AlertTriangle class="size-4 shrink-0" />
                <span class="min-w-0 flex-1">
                    {{ report.cost_coverage.percent }}% بس من المبيعات ليها تكلفة مسجّلة — الربح تحت ده مش دقيق.
                </span>
                <a href="/products" class="btn-ghost shrink-0 px-3 py-1 text-xs">
                    سجّل التكلفة
                    <ArrowLeft class="size-3" />
                </a>
            </div>

            <!-- Products losing money, pulled out. Buried in a table nobody
                 sorts, this is the number a merchant never finds. -->
            <div v-if="losers.length" class="rounded-xl border border-destructive/30 bg-destructive/5 p-4">
                <p class="flex items-center gap-2 text-sm font-semibold text-destructive">
                    <TrendingDown class="size-4" />
                    {{ losers.length }} منتج بيخسّرك
                </p>
                <p class="mt-1.5 pr-6 text-xs leading-relaxed text-muted-foreground">
                    المرتجعات بتاكل ربحه. راجع السعر، أو صور المنتج، أو أكّد الطلبات قبل الشحن.
                </p>
            </div>

            <!--
                Nothing sold yet — so show what the report will say, worked
                through on real numbers. A merchant who has not seen this
                calculation before is exactly who the feature is for, and an
                empty table teaches them nothing.
            -->
            <div v-if="!report.products.length" class="surface overflow-hidden">
                <div class="border-b border-border px-6 py-5">
                    <p class="font-medium">مفيش طلبات اتسلّمت في الفترة دي</p>
                    <p class="mt-1.5 text-sm leading-relaxed text-muted-foreground">
                        أول ما تبدأ توصّل طلبات، هتلاقي هنا ربح كل منتج بعد التكلفة والمرتجع.
                        كده مثال على الحساب:
                    </p>
                </div>

                <div class="grid gap-px bg-border lg:grid-cols-[1fr_20rem]">
                    <div class="bg-card p-6">
                        <p class="text-sm font-medium">منتج بيتباع بـ ٤٠٠ وتكلفته ٢٥٠</p>

                        <div class="mt-4 space-y-2.5 text-sm">
                            <div v-for="line in [
                                { label: '٣ طلبات اتسلّمت', value: '+١٬٢٠٠', tone: 'text-success' },
                                { label: 'تكلفة البضاعة (٣ × ٢٥٠)', value: '−٧٥٠', tone: 'text-muted-foreground' },
                                { label: 'طلبين مرتجع — شحن رايح وجاي', value: '−١٢٠', tone: 'text-destructive' },
                            ]" :key="line.label" class="flex items-center justify-between gap-4">
                                <span class="text-muted-foreground">{{ line.label }}</span>
                                <span class="tabular font-medium" :class="line.tone" dir="ltr">{{ line.value }}</span>
                            </div>

                            <div class="flex items-center justify-between gap-4 border-t border-border pt-3">
                                <span class="font-semibold">صافي الربح</span>
                                <span class="tabular text-lg font-bold text-success" dir="ltr">٣٣٠</span>
                            </div>
                        </div>
                    </div>

                    <!-- The contrast that makes the point: same product, same
                         month, a number four times larger and wrong. -->
                    <div class="bg-card p-6">
                        <p class="text-sm text-muted-foreground">في أي منصة تانية</p>
                        <p class="tabular mt-2 text-3xl font-bold text-muted-foreground/50" dir="ltr">١٬٢٠٠</p>
                        <p class="mt-1 text-xs text-muted-foreground">«مبيعات»</p>

                        <p class="mt-5 rounded-xl bg-destructive/5 p-3 text-xs leading-relaxed text-destructive">
                            الرقم ده مش غلط — هو بس مش ربحك. الفرق بينه وبين الـ٣٣٠
                            هو تكلفة بضاعتك وشحن المرتجعات.
                        </p>
                    </div>
                </div>
            </div>

            <!-- ── Per product ─────────────────────────────────────────── -->
            <div v-else class="surface overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[52rem] text-sm">
                        <thead class="bg-muted/50">
                            <tr>
                                <th class="border-b border-border px-4 py-2.5 text-right font-medium text-muted-foreground">المنتج</th>
                                <th class="border-b border-border px-4 py-2.5 text-center font-medium text-muted-foreground">اتباع</th>
                                <th class="border-b border-border px-4 py-2.5 text-center font-medium text-muted-foreground">مرتجع</th>
                                <th class="border-b border-border px-4 py-2.5 text-left font-medium text-muted-foreground">مبيعات</th>
                                <th class="border-b border-border px-4 py-2.5 text-left font-medium text-muted-foreground">تكلفة</th>
                                <th class="border-b border-border px-4 py-2.5 text-left font-medium text-muted-foreground">صافي الربح</th>
                                <th class="border-b border-border px-4 py-2.5 text-center font-medium text-muted-foreground">الهامش</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-border">
                            <tr v-for="row in report.products" :key="row.name"
                                :class="{ 'bg-destructive/[0.03]': row.profit < 0 }">
                                <td class="px-4 py-3">
                                    <p class="font-medium">{{ row.name }}</p>
                                    <p v-if="!row.has_cost" class="mt-0.5 text-xs text-warning">تكلفته مش مسجّلة</p>
                                </td>
                                <td class="tabular px-4 py-3 text-center">{{ row.sold }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="tabular" :class="row.return_rate >= 25 ? 'font-bold text-destructive' : ''">
                                        {{ row.returned }}
                                    </span>
                                    <span v-if="row.returned" class="tabular block text-xs text-muted-foreground">
                                        {{ row.return_rate }}%
                                    </span>
                                </td>
                                <td class="tabular px-4 py-3 text-left" dir="ltr">{{ money(row.revenue) }}</td>
                                <td class="tabular px-4 py-3 text-left text-muted-foreground" dir="ltr">
                                    −{{ money(row.cost + row.return_cost) }}
                                </td>
                                <td class="tabular px-4 py-3 text-left font-bold" dir="ltr"
                                    :class="row.profit >= 0 ? 'text-success' : 'text-destructive'">
                                    {{ money(row.profit) }}
                                </td>
                                <td class="tabular px-4 py-3 text-center"
                                    :class="(row.margin ?? 0) < 0 ? 'text-destructive' : ''">
                                    {{ row.margin !== null ? row.margin + '%' : '—' }}
                                </td>
                            </tr>

                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
