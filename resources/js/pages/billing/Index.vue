<script setup lang="ts">
import TopupPanel from '@/components/billing/TopupPanel.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { Check, Wallet } from 'lucide-vue-next';
import { computed } from 'vue';

/*
 * The merchant's balance, what an order costs them, and adding money (the
 * steps themselves are TopupPanel's, shared with the dashboard).
 */

interface Prices {
    in_intro: boolean;
    intro_days_left: number | null;
    intro_ends_at: string | null;
    order: number;
    with_confirmation: number;
    order_after: number;
    with_confirmation_after: number;
}

defineProps<{
    currency: string;
    balance: number;
    overdraftLimit: number;
    hidesNewOrders: boolean;
    locked: { count: number; value: number };
    prices: Prices;
    amounts: number[];
    instapay: string;
    pending: { id: number; amount: number; created_at: string; whatsapp_url: string | null } | null;
    requests: { id: number; amount: number; status: string; status_label: string; note: string | null; created_at: string }[];
    transactions: { id: number; description: string | null; amount: number; balance_after: number; created_at: string }[];
}>();

const page = usePage();
const breadcrumbItems: BreadcrumbItem[] = [{ title: 'رصيدك', href: '/billing' }];

const FLASH: Record<string, string> = {
    'topup-requested': 'اتسجّل طلب الشحن. حوّل المبلغ وابعت الإيصال، وهنأكّده.',
    'topup-cancelled': 'اتلغى طلب الشحن.',
};
const flash = computed(() => FLASH[page.props.flash?.status as string] ?? null);

const money = (v: number) => v.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const whole = (v: number) => v.toLocaleString('en-US');

const STATUS_CLASS: Record<string, string> = {
    pending: 'badge-warning',
    confirmed: 'badge-success',
    rejected: 'badge-danger',
    cancelled: 'badge-neutral',
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="رصيدك" />

        <div class="mx-auto max-w-3xl space-y-6 px-4 py-6">
            <h1 class="text-2xl font-bold tracking-tight">رصيدك</h1>

            <p v-if="flash" class="flex items-center gap-2 rounded-xl bg-success/10 px-4 py-3 text-sm text-success">
                <Check class="h-4 w-4" />
                {{ flash }}
            </p>

            <!-- ── Balance ───────────────────────────────────────────────── -->
            <section class="surface flex items-center gap-4 p-6">
                <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-primary/10 text-primary">
                    <Wallet class="h-6 w-6" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm text-muted-foreground">رصيدك دلوقتي</p>
                    <p class="tabular text-3xl font-bold tracking-tight" :class="{ 'text-destructive': balance < 0 }" dir="ltr">
                        {{ money(balance) }} <span class="text-base font-medium text-muted-foreground">{{ currency }}</span>
                    </p>
                </div>
            </section>

            <p v-if="locked.count" class="rounded-xl bg-warning/10 px-4 py-3 text-sm text-warning">
                عندك <strong class="tabular">{{ whole(locked.count) }}</strong> طلب مخفي بقيمة
                <strong class="tabular">{{ money(locked.value) }}</strong> {{ currency }}. أول ما الشحن يتأكد، بيانات العملاء تظهر
                ورسايل التأكيد تتبعت لوحدها — الأقدم الأول.
            </p>
            <p v-else-if="hidesNewOrders" class="rounded-xl bg-warning/10 px-4 py-3 text-sm text-warning">
                رصيدك خلص. الطلبات الجديدة بتوصل عادي، بس بيانات العميل بتفضل مخفية لحد ما تشحن.
            </p>
            <p v-else-if="balance < 0" class="rounded-xl bg-warning/10 px-4 py-3 text-sm text-warning">
                رصيدك بالسالب. بعد سالب {{ whole(overdraftLimit) }} {{ currency }} الطلبات الجديدة بتوصل عادي، بس بيانات العميل بتفضل
                مخفية لحد ما تشحن.
            </p>

            <!-- ── Top up ────────────────────────────────────────────────── -->
            <section class="surface space-y-5 p-6">
                <h2 class="font-medium">اشحن رصيدك</h2>
                <TopupPanel :currency="currency" :amounts="amounts" :instapay="instapay" :pending="pending" />
            </section>

            <!-- ── What an order costs ───────────────────────────────────── -->
            <section class="surface space-y-3 p-6">
                <h2 class="font-medium">الطلب بيتحسب عليك كام؟</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-right text-muted-foreground">
                                <th class="pb-2 font-medium"></th>
                                <th class="pb-2 font-medium">طلب عادي</th>
                                <th class="pb-2 font-medium">طلب بتأكيد واتساب</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-if="prices.in_intro">
                                <td class="py-2">
                                    دلوقتي <span class="text-muted-foreground">(باقي {{ prices.intro_days_left }} يوم)</span>
                                </td>
                                <td class="tabular py-2 font-semibold">{{ money(prices.order) }}</td>
                                <td class="tabular py-2 font-semibold">{{ money(prices.with_confirmation) }}</td>
                            </tr>
                            <tr>
                                <td class="py-2">{{ prices.in_intro ? `من ${prices.intro_ends_at}` : 'دلوقتي' }}</td>
                                <td class="tabular py-2">{{ money(prices.order_after) }}</td>
                                <td class="tabular py-2">{{ money(prices.with_confirmation_after) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-muted-foreground">
                    رسوم التأكيد بتتحسب بس لما رسالة واتساب تتبعت للعميل فعلاً، ومرة واحدة للطلب. مافيش اشتراك شهري ولا نسبة من مبيعاتك.
                </p>
            </section>

            <!-- ── History ───────────────────────────────────────────────── -->
            <section v-if="requests.length" class="surface space-y-3 p-6">
                <h2 class="font-medium">طلبات الشحن</h2>
                <div class="divide-y divide-border">
                    <div v-for="r in requests" :key="r.id" class="flex flex-wrap items-center gap-3 py-2.5 text-sm">
                        <span class="tabular text-muted-foreground">{{ r.created_at }}</span>
                        <span class="tabular flex-1 font-semibold">{{ whole(r.amount) }} {{ currency }}</span>
                        <span :class="STATUS_CLASS[r.status] ?? 'badge-neutral'">{{ r.status_label }}</span>
                        <p v-if="r.note" class="w-full text-xs text-muted-foreground">{{ r.note }}</p>
                    </div>
                </div>
            </section>

            <section v-if="transactions.length" class="surface space-y-3 p-6">
                <h2 class="font-medium">آخر الحركات</h2>
                <div class="divide-y divide-border">
                    <div v-for="t in transactions" :key="t.id" class="flex items-center gap-3 py-2.5 text-sm">
                        <span class="tabular shrink-0 text-muted-foreground">{{ t.created_at }}</span>
                        <span class="min-w-0 flex-1 truncate">{{ t.description }}</span>
                        <span class="tabular shrink-0 font-semibold" :class="t.amount < 0 ? 'text-destructive' : 'text-success'" dir="ltr">
                            {{ t.amount > 0 ? '+' : '' }}{{ money(t.amount) }}
                        </span>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
