<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Check, Copy, LoaderCircle, MessageCircle, Wallet } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/*
 * The merchant's balance, what an order costs them, and adding money.
 *
 * Topping up is a choice from fixed amounts, never a typed number: the same
 * screen has to work unchanged when online payment arrives, and a payment
 * gateway is never handed an amount typed by hand. Until then the money moves
 * by InstaPay and an operator confirms it.
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

const props = defineProps<{
    currency: string;
    balance: number;
    overdraftLimit: number;
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

const form = useForm({ amount: props.amounts[2] ?? props.amounts[0] });
const submit = () => form.post(route('billing.topups.store'), { preserveScroll: true });

const cancelling = ref(false);
const cancel = () => {
    if (!props.pending) return;
    cancelling.value = true;
    router.delete(route('billing.topups.destroy', props.pending.id), {
        preserveScroll: true,
        onFinish: () => (cancelling.value = false),
    });
};

const copied = ref(false);
const copyNumber = async () => {
    try {
        await navigator.clipboard.writeText(props.instapay);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        // Clipboard refused (an old browser, an iframe): the number is on screen.
    }
};

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

            <p v-if="balance < 0" class="rounded-xl bg-warning/10 px-4 py-3 text-sm text-warning">
                رصيدك بالسالب. المتجر بيفضل يستقبل طلبات لحد سالب {{ whole(overdraftLimit) }} {{ currency }}، اشحن قبلها.
            </p>

            <!-- ── Top up ────────────────────────────────────────────────── -->
            <section class="surface space-y-5 p-6">
                <h2 class="font-medium">اشحن رصيدك</h2>

                <!-- A request already waiting: what to do next, in order. -->
                <div v-if="pending" class="space-y-4">
                    <p class="rounded-xl bg-primary/5 px-4 py-3 text-sm">
                        طلب شحن بـ <strong class="tabular">{{ whole(pending.amount) }} {{ currency }}</strong> مستني التأكيد
                        <span class="text-muted-foreground">— {{ pending.created_at }}</span>
                    </p>

                    <ol class="space-y-4 text-sm">
                        <li class="flex gap-3">
                            <span class="grid size-6 shrink-0 place-items-center rounded-full bg-primary text-xs text-primary-foreground">١</span>
                            <div class="space-y-2">
                                <p>
                                    حوّل <strong class="tabular">{{ whole(pending.amount) }} {{ currency }}</strong> على إنستاباي للرقم ده:
                                </p>
                                <button type="button" class="btn-outline tabular py-1.5" dir="ltr" @click="copyNumber">
                                    {{ instapay }}
                                    <Check v-if="copied" class="h-4 w-4 text-success" />
                                    <Copy v-else class="h-4 w-4" />
                                </button>
                            </div>
                        </li>
                        <li class="flex gap-3">
                            <span class="grid size-6 shrink-0 place-items-center rounded-full bg-primary text-xs text-primary-foreground">٢</span>
                            <div class="space-y-2">
                                <p>ابعت صورة الإيصال على واتساب لنفس الرقم:</p>
                                <a v-if="pending.whatsapp_url" :href="pending.whatsapp_url" target="_blank" rel="noopener" class="btn-primary py-1.5">
                                    <MessageCircle class="h-4 w-4" />
                                    ابعت الإيصال على واتساب
                                </a>
                            </div>
                        </li>
                        <li class="flex gap-3">
                            <span class="grid size-6 shrink-0 place-items-center rounded-full bg-primary text-xs text-primary-foreground">٣</span>
                            <p>هنراجع التحويل ونضيف المبلغ لرصيدك.</p>
                        </li>
                    </ol>

                    <button type="button" class="btn-ghost text-sm text-destructive" :disabled="cancelling" @click="cancel">إلغاء الطلب</button>
                </div>

                <!-- Picking an amount. -->
                <form v-else class="space-y-4" @submit.prevent="submit">
                    <p class="text-sm text-muted-foreground">اختار المبلغ اللي هتشحن بيه:</p>
                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-6">
                        <button
                            v-for="amount in amounts"
                            :key="amount"
                            type="button"
                            class="tabular rounded-xl border px-2 py-3 text-sm font-semibold transition-colors"
                            :class="form.amount === amount ? 'border-primary bg-primary/10 text-primary' : 'border-border'"
                            @click="form.amount = amount"
                        >
                            {{ whole(amount) }}
                        </button>
                    </div>
                    <InputError :message="form.errors.amount" />

                    <button type="submit" class="btn-primary" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                        اشحن {{ whole(form.amount) }} {{ currency }}
                    </button>
                    <p class="text-xs text-muted-foreground">بعدها هتحوّل المبلغ على إنستاباي وتبعت الإيصال على واتساب.</p>
                </form>
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
