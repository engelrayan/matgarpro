<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle, ChevronLeft, ChevronRight, Copy, Check, LoaderCircle, MapPin,
    MessageCircle, Package, Phone, Printer, Send, ShieldAlert, ShieldCheck, Truck, User,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Item {
    name: string;
    variant_label: string | null;
    quantity: number;
    unit_price: string;
    total: string;
}

const props = defineProps<{
    order: {
        id: number; number: number; status: string; status_label: string;
        customer_name: string; customer_phone: string; customer_phone_alt: string | null;
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
    const phone = props.order.customer_phone.replace(/^0/, '20');
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
// enforced on the server either way.
const canShipViaDaman = computed(
    () => props.daman_enabled && props.order.status === 'confirmed' && !props.order.daman?.tracking_number,
);

const shipViaDaman = () => {
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

        <div class="mx-auto max-w-6xl space-y-5 p-4 md:p-6">
            <!-- ── Header ─────────────────────────────────────────────── -->
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-1">
                    <Link v-if="neighbours.next" :href="`/orders/${neighbours.next}`" class="btn-ghost px-2" title="الطلب اللي بعده">
                        <ChevronRight class="size-4" />
                    </Link>
                    <Link v-if="neighbours.prev" :href="`/orders/${neighbours.prev}`" class="btn-ghost px-2" title="الطلب اللي قبله">
                        <ChevronLeft class="size-4" />
                    </Link>
                </div>

                <div class="min-w-0">
                    <h1 class="tabular text-2xl font-bold tracking-tight">طلب #{{ order.number }}</h1>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        {{ order.created_at }} · {{ order.created_ago }}
                    </p>
                </div>

                <div class="mr-auto flex flex-wrap items-center gap-2">
                    <button v-if="canShipViaDaman" class="btn-primary" :disabled="shipping" @click="shipViaDaman">
                        <LoaderCircle v-if="shipping" class="size-4 animate-spin" />
                        <Truck v-else class="size-4" />
                        شحن عبر ضمان
                    </button>

                    <a :href="whatsappUrl" target="_blank" rel="noopener" class="btn-primary">
                        <MessageCircle class="size-4" />
                        أكّد على واتساب
                    </a>
                    <a :href="`/orders/waybills?ids=${order.id}`" target="_blank" class="btn-outline">
                        <Printer class="size-4" />
                        بوليصة
                    </a>
                </div>
            </div>

            <!-- ── Status rail ────────────────────────────────────────── -->
            <div class="surface flex flex-wrap items-center gap-2 p-4">
                <span class="text-sm text-muted-foreground">الحالة</span>
                <button
                    v-for="s in statuses"
                    :key="s.value"
                    class="rounded-xl border px-3 py-1.5 text-sm transition-colors"
                    :class="s.value === order.status
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'border-border text-muted-foreground hover:text-foreground'"
                    @click="setStatus(s.value)"
                >{{ s.label }}</button>
            </div>

            <div class="grid gap-5 lg:grid-cols-[1fr_20rem]">
                <!-- ── Main ───────────────────────────────────────────── -->
                <div class="space-y-5">
                    <section class="surface overflow-hidden">
                        <h2 class="border-b border-border px-5 py-3.5 font-semibold">
                            <Package class="ml-2 inline size-4" />
                            المنتجات
                        </h2>

                        <div class="divide-y divide-border">
                            <div v-for="(item, i) in order.items" :key="i" class="flex items-center gap-4 px-5 py-3.5">
                                <span class="tabular flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-sm font-semibold">
                                    {{ item.quantity }}×
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium">{{ item.name }}</p>
                                    <p v-if="item.variant_label" class="mt-0.5 text-xs text-muted-foreground">
                                        {{ item.variant_label }}
                                    </p>
                                </div>
                                <span class="tabular shrink-0 text-sm text-muted-foreground" dir="ltr">
                                    {{ money(item.unit_price) }}
                                </span>
                                <span class="tabular w-24 shrink-0 text-left font-semibold" dir="ltr">
                                    {{ money(item.total) }}
                                </span>
                            </div>
                        </div>

                        <dl class="space-y-2 border-t border-border bg-muted/30 px-5 py-4 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">المنتجات</dt>
                                <dd class="tabular" dir="ltr">{{ money(order.subtotal) }}</dd>
                            </div>
                            <div v-if="Number(order.shipping_amount) > 0" class="flex justify-between">
                                <dt class="text-muted-foreground">الشحن</dt>
                                <dd class="tabular" dir="ltr">{{ money(order.shipping_amount) }}</dd>
                            </div>
                            <div v-if="Number(order.discount_amount) > 0" class="flex justify-between text-success">
                                <dt>الخصم</dt>
                                <dd class="tabular" dir="ltr">−{{ money(order.discount_amount) }}</dd>
                            </div>
                            <div class="flex items-baseline justify-between border-t border-border pt-2">
                                <dt class="font-semibold">المطلوب تحصيله</dt>
                                <dd class="tabular text-lg font-bold" dir="ltr">
                                    {{ money(order.total) }} {{ currency }}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section v-if="order.note" class="surface p-5">
                        <h2 class="font-semibold">ملاحظة العميل</h2>
                        <p class="mt-2 text-sm leading-relaxed text-muted-foreground">{{ order.note }}</p>
                    </section>

                    <!-- Where the order came from. Recorded at checkout, and the
                         only way to tell which campaign actually paid off. -->
                    <section v-if="order.tracking && Object.keys(order.tracking).length" class="surface p-5">
                        <h2 class="font-semibold">مصدر الزيارة</h2>
                        <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                            <div v-for="(value, key) in order.tracking" :key="key" class="flex gap-2">
                                <dt class="font-mono text-xs text-muted-foreground" dir="ltr">{{ key }}</dt>
                                <dd class="min-w-0 flex-1 truncate" :title="value">{{ value }}</dd>
                            </div>
                        </dl>
                    </section>
                </div>

                <!-- ── Side ───────────────────────────────────────────── -->
                <aside class="space-y-5">
                    <section class="surface p-5">
                        <h2 class="font-semibold">
                            <User class="ml-2 inline size-4" />
                            العميل
                        </h2>

                        <p class="mt-3 font-medium">{{ order.customer_name }}</p>

                        <button class="mt-2 flex w-full items-center gap-2 rounded-lg bg-muted/60 px-3 py-2 text-sm transition-colors hover:bg-muted"
                                @click="copy(order.customer_phone, 'phone')">
                            <Phone class="size-3.5 shrink-0 text-muted-foreground" />
                            <span class="tabular flex-1 text-right" dir="ltr">{{ order.customer_phone }}</span>
                            <Check v-if="copied === 'phone'" class="size-3.5 text-success" />
                            <Copy v-else class="size-3.5 text-muted-foreground" />
                        </button>

                        <button v-if="order.customer_phone_alt"
                                class="mt-1.5 flex w-full items-center gap-2 rounded-lg bg-muted/60 px-3 py-2 text-sm transition-colors hover:bg-muted"
                                @click="copy(order.customer_phone_alt!, 'alt')">
                            <Phone class="size-3.5 shrink-0 text-muted-foreground" />
                            <span class="tabular flex-1 text-right" dir="ltr">{{ order.customer_phone_alt }}</span>
                            <Check v-if="copied === 'alt'" class="size-3.5 text-success" />
                            <Copy v-else class="size-3.5 text-muted-foreground" />
                        </button>

                        <button class="mt-3 flex w-full items-start gap-2 rounded-lg bg-muted/60 px-3 py-2 text-right text-sm transition-colors hover:bg-muted"
                                @click="copy(addressLine, 'address')">
                            <MapPin class="mt-0.5 size-3.5 shrink-0 text-muted-foreground" />
                            <span class="flex-1 leading-relaxed">{{ addressLine || '—' }}</span>
                            <Check v-if="copied === 'address'" class="mt-0.5 size-3.5 text-success" />
                            <Copy v-else class="mt-0.5 size-3.5 text-muted-foreground" />
                        </button>
                    </section>

                    <!-- ── Network record ──────────────────────────────────
                         Above the store's own history on purpose: a customer
                         who is new here but has refused four parcels elsewhere
                         is exactly the order a merchant would otherwise ship
                         blind. Only appears once the platform has actually
                         delivered something to this number.
                    -->
                    <section
                        v-if="network_reputation"
                        class="surface p-5"
                        :class="network_reputation.risky ? 'border-destructive/50 bg-destructive/[0.03]' : 'border-success/40'"
                    >
                        <div class="flex items-start gap-2">
                            <ShieldAlert v-if="network_reputation.risky" class="mt-0.5 size-4 shrink-0 text-destructive" />
                            <ShieldCheck v-else class="mt-0.5 size-4 shrink-0 text-success" />
                            <div class="min-w-0">
                                <h2 class="font-semibold">سجله على المنصة</h2>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    من {{ network_reputation.stores }} متجر على متجر برو
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                            <div>
                                <p class="tabular text-lg font-bold text-success">{{ network_reputation.delivered }}</p>
                                <p class="text-xs text-muted-foreground">استلم</p>
                            </div>
                            <div>
                                <p class="tabular text-lg font-bold" :class="network_reputation.refused ? 'text-destructive' : ''">
                                    {{ network_reputation.refused }}
                                </p>
                                <p class="text-xs text-muted-foreground">رفض</p>
                            </div>
                            <div>
                                <p class="tabular text-lg font-bold">{{ network_reputation.delivery_rate }}%</p>
                                <p class="text-xs text-muted-foreground">نسبة التسليم</p>
                            </div>
                        </div>

                        <p
                            v-if="network_reputation.summary"
                            class="mt-3 rounded-lg p-2.5 text-xs leading-relaxed"
                            :class="network_reputation.risky
                                ? 'bg-destructive/5 text-destructive'
                                : 'bg-success/5 text-success'"
                        >
                            {{ network_reputation.summary }}
                        </p>

                        <!-- The boundary, said out loud. A merchant seeing
                             another shop's data would be a problem; they should
                             know this is not that. -->
                        <p class="mt-3 text-[11px] leading-relaxed text-muted-foreground">
                            أرقام مجمّعة عن الرقم ده بس — مافيش أي بيانات عن متاجر تانية أو مشترياته منها.
                        </p>
                    </section>

                    <!-- The number that decides whether this parcel is worth
                         shipping. Scoped to this store's own history. -->
                    <section class="surface p-5" :class="{ 'border-destructive/40': isRisky }">
                        <h2 class="font-semibold">سجل العميل</h2>

                        <p v-if="customer_history.orders === 0" class="mt-2 text-sm text-muted-foreground">
                            أول طلب ليه عندك.
                        </p>

                        <template v-else>
                            <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                                <div>
                                    <p class="tabular text-lg font-bold">{{ customer_history.orders }}</p>
                                    <p class="text-xs text-muted-foreground">طلب قبل كده</p>
                                </div>
                                <div>
                                    <p class="tabular text-lg font-bold text-success">{{ customer_history.delivered }}</p>
                                    <p class="text-xs text-muted-foreground">اتسلّم</p>
                                </div>
                                <div>
                                    <p class="tabular text-lg font-bold" :class="customer_history.refused ? 'text-destructive' : ''">
                                        {{ customer_history.refused }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">رفض</p>
                                </div>
                            </div>

                            <p v-if="customer_history.delivery_rate !== null" class="mt-3 text-center text-sm">
                                نسبة التسليم
                                <span class="tabular font-bold">{{ customer_history.delivery_rate }}%</span>
                            </p>

                            <p v-if="isRisky" class="mt-3 flex items-start gap-1.5 rounded-lg bg-destructive/5 p-2.5 text-xs text-destructive">
                                <AlertTriangle class="mt-0.5 size-3.5 shrink-0" />
                                رفض {{ customer_history.refused }} طلبات قبل كده — أكّد معاه قبل ما تشحن.
                            </p>
                        </template>
                    </section>

                    <!-- The confirmation conversation.
                         Separate from the order status because "never answered"
                         and "not looked at yet" are different problems, and the
                         merchant's follow-up list is built from the difference. -->
                    <section v-if="whatsapp_enabled || order.whatsapp.state" class="surface p-5">
                        <h2 class="flex flex-wrap items-center gap-2 font-semibold">
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

                        <dl v-if="order.whatsapp.sent_at" class="mt-3 space-y-2 text-sm text-muted-foreground">
                            <div class="flex justify-between gap-3">
                                <dt>اتبعتت</dt>
                                <dd class="tabular text-foreground" dir="ltr">{{ order.whatsapp.sent_at }}</dd>
                            </div>
                            <div v-if="order.whatsapp.replied_at" class="flex justify-between gap-3">
                                <dt>رد</dt>
                                <dd class="tabular text-foreground" dir="ltr">{{ order.whatsapp.replied_at }}</dd>
                            </div>
                        </dl>

                        <p v-if="order.whatsapp.error" class="mt-3 flex items-start gap-1.5 rounded-lg bg-destructive/5 p-2.5 text-xs leading-relaxed text-destructive">
                            <AlertTriangle class="mt-0.5 size-3.5 shrink-0" />
                            {{ order.whatsapp.error }}
                        </p>

                        <button
                            v-if="whatsapp_enabled"
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
                    <section v-if="order.daman" class="surface p-5">
                        <h2 class="flex items-center gap-2 font-semibold">
                            <Truck class="size-4" />
                            الشحن عبر ضمان
                        </h2>

                        <template v-if="order.daman.tracking_number">
                            <button class="mt-3 flex w-full items-center gap-2 rounded-lg bg-muted/60 px-3 py-2 text-sm transition-colors hover:bg-muted"
                                    @click="copy(order.daman.tracking_number!, 'awb')">
                                <span class="shrink-0 text-xs text-muted-foreground">البوليصة</span>
                                <span class="tabular flex-1 text-left font-medium" dir="ltr">{{ order.daman.tracking_number }}</span>
                                <Check v-if="copied === 'awb'" class="size-3.5 text-success" />
                                <Copy v-else class="size-3.5 text-muted-foreground" />
                            </button>

                            <dl class="mt-3 space-y-2 text-sm text-muted-foreground">
                                <div v-if="order.daman.order_number" class="flex justify-between gap-3">
                                    <dt>رقم طلب ضمان</dt>
                                    <dd class="tabular text-foreground" dir="ltr">{{ order.daman.order_number }}</dd>
                                </div>
                                <div v-if="order.daman.carrier" class="flex justify-between gap-3">
                                    <dt>شركة الشحن</dt>
                                    <dd class="text-foreground">{{ order.daman.carrier }}</dd>
                                </div>
                                <div v-if="order.daman.status_note" class="flex justify-between gap-3">
                                    <dt>آخر حالة</dt>
                                    <dd class="text-foreground">{{ order.daman.status_note }}</dd>
                                </div>
                                <div v-if="order.daman.sent_at" class="flex justify-between gap-3">
                                    <dt>اتبعت</dt>
                                    <dd class="tabular text-foreground" dir="ltr">{{ order.daman.sent_at }}</dd>
                                </div>
                            </dl>
                        </template>

                        <p v-else-if="order.daman.error" class="mt-3 flex items-start gap-1.5 rounded-lg bg-destructive/5 p-2.5 text-xs leading-relaxed text-destructive">
                            <AlertTriangle class="mt-0.5 size-3.5 shrink-0" />
                            {{ order.daman.error }}
                        </p>
                    </section>

                    <section class="surface p-5 text-sm">
                        <h2 class="font-semibold">تفاصيل</h2>
                        <dl class="mt-3 space-y-2 text-muted-foreground">
                            <div class="flex justify-between gap-3">
                                <dt>طريقة الدفع</dt>
                                <dd class="text-foreground">الدفع عند الاستلام</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt>الحالة</dt>
                                <dd><span :class="STATUS_BADGE[order.status]">{{ order.status_label }}</span></dd>
                            </div>
                            <div v-if="order.ip" class="flex justify-between gap-3">
                                <dt>IP</dt>
                                <dd class="font-mono text-xs text-foreground" dir="ltr">{{ order.ip }}</dd>
                            </div>
                        </dl>
                    </section>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
