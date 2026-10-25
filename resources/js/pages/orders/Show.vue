<script setup lang="ts">
import DamanRequiredDialog from '@/components/orders/DamanRequiredDialog.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle, ChevronLeft, ChevronRight, Copy, Check, LoaderCircle, Lock, MapPin,
    MessageCircle, Package, Phone, Printer, Send, ShieldAlert, ShieldCheck, Truck,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Item {
    name: string;
    variant_label: string | null;
    /** The quantity offer this line was sold under, as the customer read it. */
    offer_label: string | null;
    quantity: number;
    unit_price: string;
    total: string;
}

const props = defineProps<{
    order: {
        id: number; number: number; status: string; status_label: string;
        // Null while the order is hidden until a top-up (`locked`).
        locked: boolean;
        customer_name: string | null; customer_phone: string | null; customer_phone_alt: string | null;
        customer_email: string | null; governorate: string | null; city: string | null;
        address: string | null; note: string | null;
        subtotal: string; shipping_amount: string; discount_amount: string; total: string;
        payment_method: string; ip: string | null;
        tracking: Record<string, string> | null;
        created_at: string; created_ago: string;
        items: Item[];
        daman: {
            order_number: string | null; tracking_number: string | null;
            carrier: string | null; status: string | null; status_note: string | null;
            sent_at: string | null; error: string | null;
        } | null;
        whatsapp: {
            state: string | null; sent_at: string | null;
            replied_at: string | null; error: string | null;
        };
    };
    currency: string;
    daman_enabled: boolean;
    whatsapp_enabled: boolean;
    statuses: { value: string; label: string }[];
    customer_history: { orders: number; delivered: number; refused: number; delivery_rate: number | null };
    network_reputation: {
        delivered: number; refused: number; stores: number;
        delivery_rate: number | null; risky: boolean; summary: string | null;
    } | null;
    neighbours: { prev: number | null; next: number | null };
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'الطلبات', href: '/orders' },
    { title: `#${props.order.number}`, href: `/orders/${props.order.id}` },
];

const STATUS_BADGE: Record<string, string> = {
    pending: 'badge-warning',
    confirmed: 'badge-info',
    shipped: 'badge-info',
    delivered: 'badge-success',
    cancelled: 'badge-danger',
    returned: 'badge-danger',
};

const money = (v: string | number) =>
    Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const setStatus = (status: string) =>
    router.patch(`/orders/${props.order.id}`, { status }, { preserveScroll: true });

/* ── Copy ───────────────────────────────────────────────────────────────── */

const copied = ref<string | null>(null);

const copy = async (value: string, key: string) => {
    await navigator.clipboard.writeText(value);
    copied.value = key;
    setTimeout(() => (copied.value = null), 1500);
};

/* ── WhatsApp confirmation ──────────────────────────────────────────────── */

// Pre-filled so confirming an order is one tap, not a message typed forty times
// a day. wa.me needs an international number without the leading zero.
const whatsappUrl = computed(() => {
    const phone = (props.order.customer_phone ?? '').replace(/^0/, '20');
    const lines = [
        `أهلاً ${props.order.customer_name} 👋`,
        `طلبك رقم #${props.order.number} وصلنا:`,
        ...props.order.items.map((i) => `• ${i.quantity}× ${i.name}${i.variant_label ? ` (${i.variant_label})` : ''}`),
        `الإجمالي: ${money(props.order.total)} ${props.currency} — الدفع عند الاستلام`,
        'نأكّد الطلب؟',
    ];

    return `https://wa.me/${phone}?text=${encodeURIComponent(lines.join('\n'))}`;
});

const addressLine = computed(() =>
    [props.order.governorate, props.order.city, props.order.address].filter(Boolean).join('، '),
);

// A repeat refuser is a parcel the merchant pays to ship twice.
const isRisky = computed(() => props.customer_history.refused >= 2);

/* ── Daman ──────────────────────────────────────────────────────────────── */

const shipping = ref(false);

// Confirmed only, and only once: the same rule the grid's bulk button follows,
// enforced on the server either way. Shown before Daman is linked too — the
// click then says how to link it rather than the button not being there.
const canShipViaDaman = computed(
    () => !props.order.locked && props.order.status === 'confirmed' && !props.order.daman?.tracking_number,
);
const damanRequired = ref(false);

const shipViaDaman = () => {
    if (!props.daman_enabled) {
        damanRequired.value = true;
        return;
    }
    shipping.value = true;
    router.post('/orders-bulk/daman', { ids: [props.order.id] }, {
        preserveScroll: true,
        onFinish: () => (shipping.value = false),
    });
};

/* ── WhatsApp confirmation ──────────────────────────────────────────────── */

const WHATSAPP_STATE: Record<string, { label: string; badge: string }> = {
    sent: { label: 'مستني رد العميل', badge: 'badge-info' },
    confirmed: { label: 'العميل أكّد', badge: 'badge-success' },
    cancelled: { label: 'العميل لغى', badge: 'badge-danger' },
    failed: { label: 'الرسالة ماوصلتش', badge: 'badge-danger' },
    no_reply: { label: 'ماردّش — محتاج مكالمة', badge: 'badge-warning' },
};

const messaging = ref(false);

const sendWhatsapp = () => {
    messaging.value = true;
    router.post(`/orders/${props.order.id}/whatsapp`, {}, {
        preserveScroll: true,
        onFinish: () => (messaging.value = false),
    });
};
</script>


<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`طلب #${order.number}`" />

        <div class="mx-auto w-full max-w-6xl space-y-5 px-4 pb-6 md:px-6">
            <!-- ── Header: large title, previous / next ─────────────────── -->
            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-0 flex-1">
                    <!-- `<bdi>`: bare, "#38" in an RTL line is printed "38#". -->
                    <h1 class="text-large-title">طلب <bdi class="tabular">#{{ order.number }}</bdi></h1>
                    <p class="mt-0.5 text-[15px] text-muted-foreground">
                        {{ order.created_at }} · {{ order.created_ago }}
                    </p>
                </div>

                <!-- Up and down through the list, as in Mail. -->
                <div class="ios-segmented shrink-0">
                    <Link v-if="neighbours.next" :href="`/orders/${neighbours.next}`" class="ios-segment !px-2.5" title="الطلب اللي بعده" aria-label="الطلب اللي بعده">
                        <ChevronRight class="size-5" />
                    </Link>
                    <span v-else class="ios-segment !px-2.5 opacity-30"><ChevronRight class="size-5" /></span>
                    <Link v-if="neighbours.prev" :href="`/orders/${neighbours.prev}`" class="ios-segment !px-2.5" title="الطلب اللي قبله" aria-label="الطلب اللي قبله">
                        <ChevronLeft class="size-5" />
                    </Link>
                    <span v-else class="ios-segment !px-2.5 opacity-30"><ChevronLeft class="size-5" /></span>
                </div>
            </div>

            <!-- ── Status ───────────────────────────────────────────────
                 One line that scrolls on a phone. The current status is the
                 filled pill; every other is one tap away. -->
            <section v-if="!order.locked">
                <h2 class="ios-caption mb-1.5">الحالة</h2>
                <div class="scrollbar-none -mx-4 flex gap-2 overflow-x-auto px-4 md:mx-0 md:flex-wrap md:px-0">
                    <button
                        v-for="s in statuses"
                        :key="s.value"
                        type="button"
                        class="flex min-h-[36px] shrink-0 items-center rounded-full px-4 text-[14px] font-medium transition-colors active:opacity-70"
                        :class="s.value === order.status
                            ? 'bg-primary font-semibold text-primary-foreground'
                            : 'bg-card text-foreground shadow-[0_0_0_0.5px_hsl(var(--foreground)/0.1)] hover:bg-foreground/[0.04]'"
                        :aria-pressed="s.value === order.status"
                        @click="setStatus(s.value)"
                    >{{ s.label }}</button>
                </div>
            </section>

            <!--
                Three blocks: the customer, the order, and everything else.
                On a desk the order fills the wide column and the other two
                stack beside it. On a phone the customer comes first — the
                merchant opened this order to call that person.
            -->
            <div class="grid grid-cols-1 gap-5 lg:grid-cols-[1fr_21rem] lg:grid-rows-[auto_1fr]">
                <!-- ── Hidden until a top-up ─────────────────────────────
                     The order is real and its parcel is waiting; who it is
                     for is what the top-up buys. -->
                <section v-if="order.locked" class="surface space-y-3 p-4 lg:col-start-2 lg:row-start-1">
                    <div class="flex items-center gap-3">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-warning/15 text-warning">
                            <Lock class="size-5" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-[17px] font-semibold leading-snug">بيانات العميل مخفية</p>
                            <p class="text-[13px] text-muted-foreground">
                                الطلب وصل ورصيدك تحت حد السماح. اشحن رصيدك والبيانات تظهر لوحدها.
                            </p>
                        </div>
                    </div>
                    <p v-if="order.governorate" class="flex items-center gap-1 text-[15px]"><MapPin class="size-4" /> {{ order.governorate }}</p>
                    <Link href="/billing" class="btn-primary w-full">اشحن رصيدك</Link>
                </section>

                <!-- ── Customer: a contact card ─────────────────────────── -->
                <section v-else class="surface overflow-hidden lg:col-start-2 lg:row-start-1">
                    <div class="flex items-center gap-3.5 px-4 pb-3 pt-4">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-gradient-to-b from-foreground/25 to-foreground/40 text-xl font-semibold text-white">
                            {{ (order.customer_name ?? '').trim().charAt(0) }}
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-[20px] font-semibold leading-tight">{{ order.customer_name }}</p>
                            <p class="text-[13px] text-muted-foreground">العميل</p>
                        </div>
                    </div>

                    <!-- The Contacts app's action row: the three things a
                         merchant does with this person, a thumb's width each. -->
                    <div class="grid grid-cols-3 gap-2 px-4 pb-4">
                        <a :href="`tel:${order.customer_phone}`"
                           class="flex flex-col items-center gap-1 rounded-[12px] bg-primary/10 py-2.5 text-[12px] font-medium text-primary transition-opacity active:opacity-60">
                            <Phone class="size-5" />
                            اتصل
                        </a>
                        <a :href="whatsappUrl" target="_blank" rel="noopener"
                           class="flex flex-col items-center gap-1 rounded-[12px] bg-primary/10 py-2.5 text-[12px] font-medium text-primary transition-opacity active:opacity-60">
                            <MessageCircle class="size-5" />
                            أكّد على واتساب
                        </a>
                        <a :href="`/orders/waybills?ids=${order.id}`" target="_blank"
                           class="flex flex-col items-center gap-1 rounded-[12px] bg-primary/10 py-2.5 text-[12px] font-medium text-primary transition-opacity active:opacity-60">
                            <Printer class="size-5" />
                            بوليصة
                        </a>
                    </div>

                    <div class="ios-group !rounded-none !shadow-none hairline-t">
                        <button class="ios-row w-full text-right transition-colors hover:bg-foreground/[0.03] active:bg-foreground/[0.06]"
                                @click="copy(order.customer_phone ?? '', 'phone')">
                            <div class="min-w-0 flex-1">
                                <p class="text-[13px] text-muted-foreground">الموبايل</p>
                                <p class="tabular text-[17px] text-primary" dir="ltr" style="text-align: right">{{ order.customer_phone }}</p>
                            </div>
                            <Check v-if="copied === 'phone'" class="size-4 text-success" />
                            <Copy v-else class="size-4 text-muted-foreground/60" />
                        </button>

                        <button v-if="order.customer_phone_alt"
                                class="ios-row w-full text-right transition-colors hover:bg-foreground/[0.03] active:bg-foreground/[0.06]"
                                @click="copy(order.customer_phone_alt!, 'alt')">
                            <div class="min-w-0 flex-1">
                                <p class="text-[13px] text-muted-foreground">رقم تاني</p>
                                <p class="tabular text-[17px] text-primary" dir="ltr" style="text-align: right">{{ order.customer_phone_alt }}</p>
                            </div>
                            <Check v-if="copied === 'alt'" class="size-4 text-success" />
                            <Copy v-else class="size-4 text-muted-foreground/60" />
                        </button>

                        <button class="ios-row w-full items-start text-right transition-colors hover:bg-foreground/[0.03] active:bg-foreground/[0.06]"
                                @click="copy(addressLine, 'address')">
                            <div class="min-w-0 flex-1">
                                <p class="flex items-center gap-1 text-[13px] text-muted-foreground"><MapPin class="size-3.5" /> العنوان</p>
                                <p class="text-[16px] leading-relaxed">{{ addressLine || '—' }}</p>
                            </div>
                            <Check v-if="copied === 'address'" class="mt-1 size-4 text-success" />
                            <Copy v-else class="mt-1 size-4 text-muted-foreground/60" />
                        </button>
                    </div>
                </section>

                <!-- ── The order ─────────────────────────────────────────── -->
                <div class="space-y-5 lg:col-start-1 lg:row-span-2 lg:row-start-1">
                    <section>
                        <h2 class="ios-caption mb-1.5 flex items-center gap-1.5"><Package class="size-3.5" /> المنتجات</h2>
                        <div class="ios-group">
                            <div v-for="(item, i) in order.items" :key="i" class="ios-row min-h-[60px]">
                                <span class="tabular flex size-10 shrink-0 items-center justify-center rounded-[10px] bg-foreground/[0.06] text-[15px] font-semibold">
                                    {{ item.quantity }}×
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-[16px] font-medium leading-snug">{{ item.name }}</p>
                                    <p class="mt-0.5 text-[13px] text-muted-foreground">
                                        <template v-if="item.variant_label">{{ item.variant_label }} · </template>
                                        <span class="tabular" dir="ltr">{{ money(item.unit_price) }}</span> للقطعة
                                    </p>
                                    <p v-if="item.offer_label" class="mt-1">
                                        <span class="badge bg-primary/10 text-[12px] font-semibold text-primary">عرض: {{ item.offer_label }}</span>
                                    </p>
                                </div>
                                <span class="tabular shrink-0 text-[16px] font-semibold" dir="ltr">
                                    {{ money(item.total) }}
                                </span>
                            </div>

                            <div class="ios-row justify-between text-[15px]">
                                <span class="text-muted-foreground">المنتجات</span>
                                <span class="tabular" dir="ltr">{{ money(order.subtotal) }}</span>
                            </div>
                            <div v-if="Number(order.shipping_amount) > 0" class="ios-row justify-between text-[15px]">
                                <span class="text-muted-foreground">الشحن</span>
                                <span class="tabular" dir="ltr">{{ money(order.shipping_amount) }}</span>
                            </div>
                            <div v-if="Number(order.discount_amount) > 0" class="ios-row justify-between text-[15px] text-success">
                                <span>الخصم</span>
                                <span class="tabular" dir="ltr">−{{ money(order.discount_amount) }}</span>
                            </div>
                            <div class="ios-row min-h-[56px] justify-between">
                                <span class="text-[17px] font-semibold">المطلوب تحصيله</span>
                                <span class="tabular text-[22px] font-bold tracking-tight" dir="ltr">
                                    {{ money(order.total) }} <span class="text-[13px] font-medium text-muted-foreground">{{ currency }}</span>
                                </span>
                            </div>
                        </div>
                    </section>

                    <section v-if="order.note">
                        <h2 class="ios-caption mb-1.5">ملاحظة العميل</h2>
                        <p class="surface px-4 py-3.5 text-[16px] leading-relaxed">{{ order.note }}</p>
                    </section>

                    <!-- Where the order came from. Recorded at checkout, and the
                         only way to tell which campaign actually paid off. -->
                    <section v-if="order.tracking && Object.keys(order.tracking).length">
                        <h2 class="ios-caption mb-1.5">مصدر الزيارة</h2>
                        <dl class="ios-group">
                            <div v-for="(value, key) in order.tracking" :key="key" class="ios-row min-h-[44px] justify-between">
                                <dt class="font-mono text-xs text-muted-foreground" dir="ltr">{{ key }}</dt>
                                <dd class="min-w-0 truncate text-[15px]" :title="value">{{ value }}</dd>
                            </div>
                        </dl>
                    </section>

                    <button v-if="canShipViaDaman" class="btn-primary w-full !min-h-[50px] !text-[17px]" :disabled="shipping" @click="shipViaDaman">
                        <LoaderCircle v-if="shipping" class="size-5 animate-spin" />
                        <Truck v-else class="size-5" />
                        شحن عبر ضمان
                    </button>
                </div>

                <!-- ── Everything else ───────────────────────────────────── -->
                <aside class="space-y-5 lg:col-start-2 lg:row-start-2">
                    <!-- ── Network record ──────────────────────────────────
                         Above the store's own history on purpose: a customer
                         who is new here but has refused four parcels elsewhere
                         is exactly the order a merchant would otherwise ship
                         blind. Only appears once the platform has actually
                         delivered something to this number.
                    -->
                    <section
                        v-if="network_reputation"
                        class="surface p-4"
                        :class="network_reputation.risky ? 'bg-destructive/[0.04] !shadow-[0_0_0_0.5px_hsl(var(--destructive)/0.45)]' : ''"
                    >
                        <div class="flex items-start gap-3">
                            <span class="ios-tile" :class="network_reputation.risky ? 'bg-destructive' : 'bg-success'">
                                <ShieldAlert v-if="network_reputation.risky" class="size-[18px]" />
                                <ShieldCheck v-else class="size-[18px]" />
                            </span>
                            <div class="min-w-0">
                                <h2 class="text-[17px] font-semibold leading-snug">سجله على المنصة</h2>
                                <p class="text-[13px] text-muted-foreground">
                                    من {{ network_reputation.stores }} متجر على متجر برو
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                            <div class="rounded-[10px] bg-foreground/[0.04] py-2">
                                <p class="tabular text-[20px] font-bold text-success">{{ network_reputation.delivered }}</p>
                                <p class="text-[12px] text-muted-foreground">استلم</p>
                            </div>
                            <div class="rounded-[10px] bg-foreground/[0.04] py-2">
                                <p class="tabular text-[20px] font-bold" :class="network_reputation.refused ? 'text-destructive' : ''">
                                    {{ network_reputation.refused }}
                                </p>
                                <p class="text-[12px] text-muted-foreground">رفض</p>
                            </div>
                            <div class="rounded-[10px] bg-foreground/[0.04] py-2">
                                <p class="tabular text-[20px] font-bold">{{ network_reputation.delivery_rate }}%</p>
                                <p class="text-[12px] text-muted-foreground">نسبة التسليم</p>
                            </div>
                        </div>

                        <p
                            v-if="network_reputation.summary"
                            class="mt-3 rounded-[10px] p-2.5 text-[13px] leading-relaxed"
                            :class="network_reputation.risky
                                ? 'bg-destructive/[0.08] text-destructive'
                                : 'bg-success/[0.08] text-success'"
                        >
                            {{ network_reputation.summary }}
                        </p>

                        <!-- The boundary, said out loud. A merchant seeing
                             another shop's data would be a problem; they should
                             know this is not that. -->
                        <p class="mt-3 text-[12px] leading-relaxed text-muted-foreground">
                            أرقام مجمّعة عن الرقم ده بس — مافيش أي بيانات عن متاجر تانية أو مشترياته منها.
                        </p>
                    </section>

                    <!-- The number that decides whether this parcel is worth
                         shipping. Scoped to this store's own history. -->
                    <section v-if="!order.locked" class="surface p-4" :class="{ 'bg-destructive/[0.04] !shadow-[0_0_0_0.5px_hsl(var(--destructive)/0.4)]': isRisky }">
                        <h2 class="text-[17px] font-semibold">سجل العميل</h2>

                        <p v-if="customer_history.orders === 0" class="mt-1.5 text-[15px] text-muted-foreground">
                            أول طلب ليه عندك.
                        </p>

                        <template v-else>
                            <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                                <div class="rounded-[10px] bg-foreground/[0.04] py-2">
                                    <p class="tabular text-[20px] font-bold">{{ customer_history.orders }}</p>
                                    <p class="text-[12px] text-muted-foreground">طلب قبل كده</p>
                                </div>
                                <div class="rounded-[10px] bg-foreground/[0.04] py-2">
                                    <p class="tabular text-[20px] font-bold text-success">{{ customer_history.delivered }}</p>
                                    <p class="text-[12px] text-muted-foreground">اتسلّم</p>
                                </div>
                                <div class="rounded-[10px] bg-foreground/[0.04] py-2">
                                    <p class="tabular text-[20px] font-bold" :class="customer_history.refused ? 'text-destructive' : ''">
                                        {{ customer_history.refused }}
                                    </p>
                                    <p class="text-[12px] text-muted-foreground">رفض</p>
                                </div>
                            </div>

                            <p v-if="customer_history.delivery_rate !== null" class="mt-3 text-center text-[15px]">
                                نسبة التسليم
                                <span class="tabular font-bold">{{ customer_history.delivery_rate }}%</span>
                            </p>

                            <p v-if="isRisky" class="mt-3 flex items-start gap-1.5 rounded-[10px] bg-destructive/[0.08] p-2.5 text-[13px] text-destructive">
                                <AlertTriangle class="mt-0.5 size-3.5 shrink-0" />
                                رفض {{ customer_history.refused }} طلبات قبل كده — أكّد معاه قبل ما تشحن.
                            </p>
                        </template>
                    </section>

                    <!-- The confirmation conversation.
                         Separate from the order status because "never answered"
                         and "not looked at yet" are different problems, and the
                         merchant's follow-up list is built from the difference. -->
                    <section v-if="whatsapp_enabled || order.whatsapp.state" class="surface p-4">
                        <h2 class="flex flex-wrap items-center gap-2 text-[17px] font-semibold">
                            <MessageCircle class="size-4" />
                            تأكيد واتساب
                            <span
                                v-if="order.whatsapp.state && WHATSAPP_STATE[order.whatsapp.state]"
                                :class="WHATSAPP_STATE[order.whatsapp.state].badge"
                            >
                                {{ WHATSAPP_STATE[order.whatsapp.state].label }}
                            </span>
                            <span v-else class="badge-neutral">ماتبعتش</span>
                        </h2>

                        <dl v-if="order.whatsapp.sent_at" class="mt-3 space-y-2 text-[15px] text-muted-foreground">
                            <div class="flex justify-between gap-3">
                                <dt>اتبعتت</dt>
                                <dd class="tabular text-foreground" dir="ltr">{{ order.whatsapp.sent_at }}</dd>
                            </div>
                            <div v-if="order.whatsapp.replied_at" class="flex justify-between gap-3">
                                <dt>رد</dt>
                                <dd class="tabular text-foreground" dir="ltr">{{ order.whatsapp.replied_at }}</dd>
                            </div>
                        </dl>

                        <p v-if="order.whatsapp.error" class="mt-3 flex items-start gap-1.5 rounded-[10px] bg-destructive/[0.08] p-2.5 text-[13px] leading-relaxed text-destructive">
                            <AlertTriangle class="mt-0.5 size-3.5 shrink-0" />
                            {{ order.whatsapp.error }}
                        </p>

                        <button
                            v-if="whatsapp_enabled && !order.locked"
                            class="btn-outline mt-3 w-full"
                            :disabled="messaging"
                            @click="sendWhatsapp"
                        >
                            <LoaderCircle v-if="messaging" class="size-4 animate-spin" />
                            <Send v-else class="size-4" />
                            {{ order.whatsapp.sent_at ? 'ابعتها تاني' : 'ابعت التأكيد' }}
                        </button>
                    </section>

                    <!-- The shipment as Daman sees it. Two numbers on purpose:
                         the Daman order number is what their support asks for,
                         the tracking number is the carrier's own waybill and
                         the only one a courier hotline recognises. -->
                    <section v-if="order.daman">
                        <h2 class="ios-caption mb-1.5 flex items-center gap-1.5"><Truck class="size-3.5" /> الشحن عبر ضمان</h2>

                        <div v-if="order.daman.tracking_number" class="ios-group">
                            <button class="ios-row w-full transition-colors hover:bg-foreground/[0.03] active:bg-foreground/[0.06]"
                                    @click="copy(order.daman.tracking_number!, 'awb')">
                                <span class="shrink-0 text-[15px] text-muted-foreground">البوليصة</span>
                                <span class="tabular flex-1 text-left text-[15px] font-medium" dir="ltr">{{ order.daman.tracking_number }}</span>
                                <Check v-if="copied === 'awb'" class="size-4 text-success" />
                                <Copy v-else class="size-4 text-muted-foreground/60" />
                            </button>
                            <div v-if="order.daman.order_number" class="ios-row justify-between text-[15px]">
                                <span class="text-muted-foreground">رقم طلب ضمان</span>
                                <span class="tabular" dir="ltr">{{ order.daman.order_number }}</span>
                            </div>
                            <div v-if="order.daman.carrier" class="ios-row justify-between text-[15px]">
                                <span class="text-muted-foreground">شركة الشحن</span>
                                <span>{{ order.daman.carrier }}</span>
                            </div>
                            <div v-if="order.daman.status_note" class="ios-row justify-between text-[15px]">
                                <span class="text-muted-foreground">آخر حالة</span>
                                <span>{{ order.daman.status_note }}</span>
                            </div>
                            <div v-if="order.daman.sent_at" class="ios-row justify-between text-[15px]">
                                <span class="text-muted-foreground">اتبعت</span>
                                <span class="tabular" dir="ltr">{{ order.daman.sent_at }}</span>
                            </div>
                        </div>

                        <p v-else-if="order.daman.error" class="surface flex items-start gap-1.5 p-3 text-[13px] leading-relaxed text-destructive">
                            <AlertTriangle class="mt-0.5 size-3.5 shrink-0" />
                            {{ order.daman.error }}
                        </p>
                    </section>

                    <section>
                        <h2 class="ios-caption mb-1.5">تفاصيل</h2>
                        <dl class="ios-group text-[15px]">
                            <div class="ios-row justify-between">
                                <dt class="text-muted-foreground">طريقة الدفع</dt>
                                <dd>الدفع عند الاستلام</dd>
                            </div>
                            <div class="ios-row justify-between">
                                <dt class="text-muted-foreground">الحالة</dt>
                                <dd><span :class="STATUS_BADGE[order.status]">{{ order.status_label }}</span></dd>
                            </div>
                            <div v-if="order.ip" class="ios-row justify-between">
                                <dt class="text-muted-foreground">IP</dt>
                                <dd class="font-mono text-xs" dir="ltr">{{ order.ip }}</dd>
                            </div>
                        </dl>
                    </section>
                </aside>
            </div>
        </div>

        <DamanRequiredDialog v-model:open="damanRequired" />
    </AppLayout>
</template>
