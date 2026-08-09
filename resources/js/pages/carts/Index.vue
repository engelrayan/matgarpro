<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Check, MessageCircle, Phone, ShoppingCart } from 'lucide-vue-next';

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

// Pre-written so following up is one tap. The tone is a nudge, not a sales
// pitch — this person already chose the product and stopped at the form.
const whatsappUrl = (cart: Cart) => {
    const phone = cart.customer_phone.replace(/^0/, '20');
    const lines = [
        `أهلاً${cart.customer_name ? ' ' + cart.customer_name : ''} 👋`,
        cart.product ? `شفت إنك كنت بتطلب ${cart.product}${cart.variant ? ` (${cart.variant})` : ''}.` : 'شفت إنك كنت بتعمل طلب عندنا.',
        'حصلت مشكلة في الطلب؟ أقدر أكمّله لك دلوقتي.',
        'الدفع عند الاستلام زي ما هو.',
    ];

    return `https://wa.me/${phone}?text=${encodeURIComponent(lines.join('\n'))}`;
};

const markContacted = (cart: Cart) =>
    router.patch(`/carts/${cart.id}/contacted`, {}, { preserveScroll: true });

const go = (filter: string) =>
    router.get('/carts', { filter }, { preserveState: true, preserveScroll: true, replace: true });
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="السلات المتروكة" />

        <div class="mx-auto max-w-6xl space-y-5 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">السلات المتروكة</h1>
                <p class="mt-1 text-sm leading-relaxed text-muted-foreground">
                    ناس اختارت منتج وكتبت رقمها وماكمّلتش. دول أرخص مبيعات ممكن تعملها —
                    رسالة واحدة بتفرق.
                </p>
            </div>

            <!-- What is on the table right now: the number that decides whether
                 this screen is worth the next hour. -->
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="surface-lux p-5">
                    <p class="text-sm text-muted-foreground">قيمة السلات المفتوحة</p>
                    <p class="text-foil tabular mt-2 text-3xl font-bold tracking-tight">
                        {{ money(summary.open_value) }}
                        <span class="text-sm text-muted-foreground">{{ currency }}</span>
                    </p>
                </div>

                <div class="stat-tile">
                    <span class="stat-tile__value">{{ summary.open }}</span>
                    <span class="stat-tile__label">سلة مفتوحة</span>
                </div>

                <div class="stat-tile">
                    <span class="stat-tile__value text-success">{{ summary.recovered }}</span>
                    <span class="stat-tile__label">اترجعت وبقت طلب</span>
                </div>
            </div>

            <div class="flex gap-1.5">
                <button
                    v-for="tab in [{ key: 'open', label: 'مفتوحة' }, { key: 'recovered', label: 'اترجعت' }]"
                    :key="tab.key"
                    class="rounded-xl border px-3 py-1.5 text-sm transition-colors"
                    :class="filter === tab.key
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'border-border bg-card text-muted-foreground hover:text-foreground'"
                    @click="go(tab.key)"
                >{{ tab.label }}</button>
            </div>

            <div v-if="carts.data.length" class="space-y-3">
                <div
                    v-for="cart in carts.data"
                    :key="cart.id"
                    class="surface flex flex-wrap items-center gap-4 p-4"
                    :class="{ 'opacity-60': cart.contacted_at && !cart.recovered }"
                >
                    <img v-if="cart.image" :src="cart.image" alt=""
                         class="size-14 shrink-0 rounded-xl border border-border object-cover" />
                    <div v-else class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-muted">
                        <ShoppingCart class="size-5 text-muted-foreground" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="font-medium">
                            {{ cart.customer_name || 'من غير اسم' }}
                            <span v-if="cart.governorate" class="text-sm font-normal text-muted-foreground">
                                · {{ cart.governorate }}
                            </span>
                        </p>
                        <p class="tabular mt-0.5 text-sm text-muted-foreground" dir="ltr">
                            {{ cart.customer_phone }}
                        </p>
                        <p class="mt-1 truncate text-xs text-muted-foreground">
                            {{ cart.quantity }}× {{ cart.product ?? 'منتج اتشال' }}
                            <span v-if="cart.variant">({{ cart.variant }})</span>
                            · ساب الصفحة {{ cart.abandoned_at }}
                        </p>
                    </div>

                    <div class="tabular shrink-0 text-left">
                        <p class="font-bold" dir="ltr">{{ money(cart.value) }}</p>
                        <p class="text-xs text-muted-foreground">{{ currency }}</p>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <span v-if="cart.recovered" class="badge-success">اترجعت</span>

                        <template v-else>
                            <span v-if="cart.contacted_at" class="badge-neutral">اتكلمنا {{ cart.contacted_at }}</span>

                            <a :href="whatsappUrl(cart)" target="_blank" rel="noopener"
                               class="btn-primary" @click="markContacted(cart)">
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

            <div v-else class="surface px-6 py-16 text-center">
                <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-muted">
                    <ShoppingCart class="size-6 text-muted-foreground" />
                </span>
                <p class="mt-4 font-medium">
                    {{ filter === 'recovered' ? 'لسه مفيش سلة اترجعت' : 'مفيش سلات متروكة' }}
                </p>
                <p class="mx-auto mt-1.5 max-w-sm text-sm text-muted-foreground">
                    لما حد يبدأ يملا فورم الطلب ويسيبه، هيظهر هنا برقمه عشان تكلّمه.
                </p>
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
