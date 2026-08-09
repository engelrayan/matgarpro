<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Check, Keyboard, LogOut, MessageCircle, Phone, ShoppingCart, TrendingUp } from 'lucide-vue-next';
import { computed } from 'vue';

interface Cart {
    id: number;
    customer_name: string | null;
    customer_phone: string;
    governorate: string | null;
    quantity: number;
    value: string;
    product: string | null;
    variant: string | null;
    image: string | null;
    contacted_at: string | null;
    recovered: boolean;
    abandoned_at: string;
}

interface PaginatorLink { url: string | null; label: string; active: boolean }

const props = defineProps<{
    carts: { data: Cart[]; links: PaginatorLink[]; total: number };
    filter: string;
    currency: string;
    summary: { open: number; open_value: number; recovered: number };
}>();

const breadcrumbItems: BreadcrumbItem[] = [{ title: 'السلات المتروكة', href: '/carts' }];

const money = (v: string | number) =>
    Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// Pre-written so following up is one tap. A nudge, not a pitch — this person
// already chose the product and stopped at the form.
const whatsappUrl = (cart: Cart) => {
    const phone = cart.customer_phone.replace(/^0/, '20');
    const lines = [
        `أهلاً${cart.customer_name ? ' ' + cart.customer_name : ''} 👋`,
        cart.product
            ? `شفت إنك كنت بتطلب ${cart.product}${cart.variant ? ` (${cart.variant})` : ''}.`
            : 'شفت إنك كنت بتعمل طلب عندنا.',
        'حصلت مشكلة في الطلب؟ أقدر أكمّله لك دلوقتي.',
        'الدفع عند الاستلام زي ما هو.',
    ];

    return `https://wa.me/${phone}?text=${encodeURIComponent(lines.join('\n'))}`;
};

const markContacted = (cart: Cart) =>
    router.patch(`/carts/${cart.id}/contacted`, {}, { preserveScroll: true });

const go = (filter: string) =>
    router.get('/carts', { filter }, { preserveState: true, preserveScroll: true, replace: true });

// Shown in place of an empty box. A merchant lands here before anything has
// been abandoned, so the space is better spent explaining the feature than on
// a placeholder.
const howItWorks = [
    { icon: Keyboard, title: 'العميل بيكتب رقمه', body: 'أول ما يبدأ يملا الفورم، بنحفظ اللي كتبه — الاسم والرقم والمحافظة.' },
    { icon: LogOut, title: 'ويسيب الصفحة', body: 'اتلهى، أو النت قطع، أو اتردد. الطلب ضاع والرقم موجود.' },
    { icon: MessageCircle, title: 'إنت بتكلّمه', body: 'زرار واحد بيفتح واتساب برسالة فيها اسمه والمنتج اللي كان عايزه.' },
];

// Only meaningful once something has actually been recovered; a rate out of
// zero attempts is a number that misleads.
const recoveryRate = computed(() => {
    const total = props.summary.open + props.summary.recovered;

    return total > 0 ? Math.round((props.summary.recovered / total) * 100) : null;
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="السلات المتروكة" />

        <div class="mx-auto w-full max-w-7xl space-y-5 p-4 md:p-6">
            <!-- ── Header + the number that decides the next hour ──────── -->
            <div class="grid gap-5 lg:grid-cols-[1fr_22rem]">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">السلات المتروكة</h1>
                    <p class="mt-2 max-w-xl text-sm leading-relaxed text-muted-foreground">
                        ناس اختارت منتج وكتبت رقمها وماكمّلتش. دول أرخص مبيعات ممكن تعملها —
                        العميل خلاص عاجبه المنتج، فاضل رسالة واحدة.
                    </p>

                    <div class="mt-4 flex flex-wrap gap-1.5">
                        <button
                            v-for="tab in [
                                { key: 'open', label: 'مفتوحة', count: summary.open },
                                { key: 'recovered', label: 'اترجعت', count: summary.recovered },
                            ]"
                            :key="tab.key"
                            class="rounded-xl border px-3 py-1.5 text-sm transition-colors"
                            :class="filter === tab.key
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border bg-card text-muted-foreground hover:text-foreground'"
                            @click="go(tab.key)"
                        >
                            {{ tab.label }}
                            <span class="tabular mr-1 opacity-70">{{ tab.count }}</span>
                        </button>
                    </div>
                </div>

                <div class="surface-lux halo-gold relative flex flex-col justify-center p-6">
                    <p class="text-sm text-muted-foreground">فلوس مستنية على الطاولة</p>
                    <p class="text-foil tabular mt-2 text-4xl font-bold tracking-tight">
                        {{ money(summary.open_value) }}
                        <span class="text-base text-muted-foreground">{{ currency }}</span>
                    </p>
                    <p class="mt-2 text-xs text-muted-foreground">
                        <span class="tabular">{{ summary.open }}</span> سلة مفتوحة
                        <template v-if="recoveryRate !== null">
                            · استرجعت <span class="tabular font-medium text-success">{{ recoveryRate }}%</span> لحد دلوقتي
                        </template>
                    </p>
                </div>
            </div>

            <!-- ── The list ────────────────────────────────────────────── -->
            <div v-if="carts.data.length" class="grid gap-3 xl:grid-cols-2">
                <div
                    v-for="cart in carts.data"
                    :key="cart.id"
                    class="surface flex flex-wrap items-center gap-4 p-4 transition-opacity"
                    :class="{ 'opacity-55': cart.contacted_at && !cart.recovered }"
                >
                    <img v-if="cart.image" :src="cart.image" alt=""
                         class="size-14 shrink-0 rounded-xl border border-border object-cover" />
                    <div v-else class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-muted">
                        <ShoppingCart class="size-5 text-muted-foreground" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">
                            {{ cart.customer_name || 'من غير اسم' }}
                            <span v-if="cart.governorate" class="text-sm font-normal text-muted-foreground">
                                · {{ cart.governorate }}
                            </span>
                        </p>
                        <p class="tabular mt-0.5 text-sm text-muted-foreground" dir="ltr">{{ cart.customer_phone }}</p>
                        <p class="mt-1 truncate text-xs text-muted-foreground">
                            {{ cart.quantity }}× {{ cart.product ?? 'منتج اتشال' }}
                            <span v-if="cart.variant">({{ cart.variant }})</span>
                            · {{ cart.abandoned_at }}
                        </p>
                    </div>

                    <div class="tabular shrink-0 text-left">
                        <p class="font-bold" dir="ltr">{{ money(cart.value) }}</p>
                        <p class="text-xs text-muted-foreground">{{ currency }}</p>
                    </div>

                    <div class="flex w-full shrink-0 items-center gap-2 sm:w-auto">
                        <span v-if="cart.recovered" class="badge-success">اترجعت</span>

                        <template v-else>
                            <span v-if="cart.contacted_at" class="badge-neutral shrink-0">
                                اتكلمنا {{ cart.contacted_at }}
                            </span>

                            <a :href="whatsappUrl(cart)" target="_blank" rel="noopener"
                               class="btn-primary flex-1 sm:flex-none" @click="markContacted(cart)">
                                <MessageCircle class="size-4" />
                                كلّمه
                            </a>

                            <a :href="`tel:${cart.customer_phone}`" class="btn-ghost px-2" title="اتصل">
                                <Phone class="size-4" />
                            </a>

                            <button v-if="!cart.contacted_at" class="btn-ghost px-2" title="علّم إني كلمته"
                                    @click="markContacted(cart)">
                                <Check class="size-4" />
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <!--
                Empty state as an explainer, not a placeholder.
                A merchant lands here before anything has been abandoned, so the
                space is better spent showing how the feature works — and what
                the message they will send looks like — than on a grey box.
            -->
            <div v-else class="surface overflow-hidden">
                <div class="border-b border-border px-6 py-5 text-center">
                    <p class="font-medium">
                        {{ filter === 'recovered' ? 'لسه مفيش سلة اترجعت' : 'مفيش سلات متروكة دلوقتي' }}
                    </p>
                    <p class="mx-auto mt-1.5 max-w-md text-sm leading-relaxed text-muted-foreground">
                        كل ما حد يبدأ يملا فورم الطلب ويسيبه، هيظهر هنا برقمه وقيمة سلته.
                    </p>
                </div>

                <div class="grid gap-px bg-border md:grid-cols-3">
                    <div v-for="(step, i) in howItWorks" :key="step.title" class="bg-card p-6">
                        <div class="flex items-center gap-3">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <component :is="step.icon" class="size-5" />
                            </span>
                            <span class="tabular text-2xl font-bold text-muted-foreground/25">٠{{ i + 1 }}</span>
                        </div>
                        <p class="mt-4 font-semibold">{{ step.title }}</p>
                        <p class="mt-1.5 text-sm leading-relaxed text-muted-foreground">{{ step.body }}</p>
                    </div>
                </div>

                <!-- The actual message, so the merchant knows what goes out
                     before the first one ever does. -->
                <div class="border-t border-border bg-muted/30 p-6">
                    <p class="mb-3 flex items-center gap-2 text-sm font-medium">
                        <TrendingUp class="size-4 text-primary" />
                        الرسالة اللي هتتبعت
                    </p>

                    <div class="max-w-md rounded-2xl rounded-tr-sm bg-card p-4 text-sm leading-relaxed shadow-e1">
                        <p>أهلاً سارة 👋</p>
                        <p class="mt-1">شفت إنك كنت بتطلب قميص قطن مصري (أبيض · L).</p>
                        <p class="mt-1">حصلت مشكلة في الطلب؟ أقدر أكمّله لك دلوقتي.</p>
                        <p class="mt-1">الدفع عند الاستلام زي ما هو.</p>
                    </div>

                    <p class="mt-3 text-xs text-muted-foreground">
                        بتتكتب لوحدها باسم العميل والمنتج اللي كان مختاره — وإنت تعدّل فيها زي ما تحب قبل ما تبعتها.
                    </p>
                </div>
            </div>

            <div v-if="carts.links.length > 3" class="flex flex-wrap justify-center gap-1">
                <component
                    v-for="(link, i) in carts.links"
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
