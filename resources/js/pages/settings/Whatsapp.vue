<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { avatarTone, initial, listTime, type ConversationItem } from '@/components/inbox/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle, Check, CheckCheck, Link2Off,
    LoaderCircle, QrCode, Send, ShieldCheck, Smartphone, X,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

type Driver = 'platform' | 'waha';

interface Connected {
    connected: true;
    driver: Driver;
    sender_phone: string | null;
    is_active: boolean;
    auto_send: boolean;
    message_template: string;
    connected_at: string | null;
    last_sent_at: string | null;
    last_inbound_at: string | null;
    last_error: string | null;
}

const props = defineProps<{
    integration: Connected | { connected: false };
    placeholders: Record<string, string>;
    default_template: string;
    stats: { sent: number; awaiting: number; confirmed: number; cancelled: number; failed: number; no_reply: number };
    recent: ConversationItem[];
    platform: { available: boolean; preview: string; confirm_label: string; cancel_label: string; fee: number };
    waha: { available: boolean; fee: number; status: string | null };
    orderPrice: { now: number; after: number; intro: boolean };
    currency: string;
}>();

const page = usePage();
const breadcrumbItems: BreadcrumbItem[] = [{ title: 'واتساب', href: '/settings/whatsapp' }];

const link = computed(() => (props.integration.connected ? (props.integration as Connected) : null));

/*
 * On the platform's own number there are no keys, no wording to edit and no
 * webhook to paste — so those three sections step aside entirely rather than
 * sit there disabled, asking a question that has no answer.
 */
const isPlatform = computed(() => link.value?.driver === 'platform');

const usingPlatform = ref(false);

const usePlatform = () => {
    if (link.value && !confirm('هيتوقف الإرسال من رقمك الحالي، والرسايل هتطلع من رقم متجر برو الرسمي. نكمّل؟')) return;
    router.post(route('whatsapp.platform'), {}, {
        preserveScroll: true,
        onStart: () => (usingPlatform.value = true),
        onFinish: () => (usingPlatform.value = false),
    });
};

/* ── The store's own number, by QR (our WAHA server) ──────────────────────
 *
 * Linking is a conversation with WAHA, not a form: start the session, show
 * the QR it hands out (a new one every ~20 seconds), and watch for the
 * moment the phone has scanned it. Polled rather than pushed — this screen
 * is open for the minute it takes, and a poll is the whole mechanism.
 */
const isWaha = computed(() => link.value?.driver === 'waha');

const wahaStatus = ref<string | null>(props.waha.status);
const wahaQr = ref<string | null>(null);
const wahaPhone = ref<string | null>(link.value?.sender_phone ?? null);
const wahaProblem = ref<string | null>(null);
const startingWaha = ref(false);
let wahaTimer: ReturnType<typeof setTimeout> | undefined;

const pollWaha = async () => {
    clearTimeout(wahaTimer);

    try {
        const response = await fetch(route('whatsapp.waha.status'), { headers: { Accept: 'application/json' } });
        const state = await response.json();

        wahaStatus.value = state.status;
        wahaQr.value = state.qr ?? null;
        wahaPhone.value = state.phone ?? wahaPhone.value;
        wahaProblem.value = state.status === 'UNREACHABLE' ? state.message : null;

        // Linked: reload so the rest of the screen (wording, test, numbers)
        // reflects a working number, and stop asking.
        if (state.status === 'WORKING' && props.waha.status !== 'WORKING') {
            router.reload();
            return;
        }
    } catch {
        wahaProblem.value = 'مقدرناش نوصل للسيرفر. هنحاول تاني.';
    }

    if (wahaStatus.value !== 'WORKING') {
        wahaTimer = setTimeout(pollWaha, 3000);
    }
};

onMounted(() => {
    if (isWaha.value && props.waha.status !== 'WORKING') pollWaha();
});
onBeforeUnmount(() => clearTimeout(wahaTimer));

const useWaha = () => {
    if (link.value && !confirm('هيتوقف الإرسال من الربط الحالي، وهتربط رقمك إنت بمسح QR. نكمّل؟')) return;
    router.post(route('whatsapp.waha'), {}, {
        preserveScroll: true,
        onStart: () => (startingWaha.value = true),
        onFinish: () => (startingWaha.value = false),
        onSuccess: () => pollWaha(),
    });
};

/** The codes ran out with nobody scanning — WAHA parks the session. */
const wahaExpired = computed(() => wahaStatus.value === 'FAILED' || wahaStatus.value === 'STOPPED');

// Same request as the first link; on an existing session the server
// restarts it, which is what issues a fresh set of codes.
const renewWaha = () => {
    wahaQr.value = null;
    router.post(route('whatsapp.waha'), {}, {
        preserveScroll: true,
        onStart: () => (startingWaha.value = true),
        onFinish: () => (startingWaha.value = false),
        onSuccess: () => pollWaha(),
    });
};

const fmt = (n: number) => n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/** An order with this channel's confirmation, all in — what gets compared. */
const orderTotal = (fee: number) => (props.orderPrice.after > 0 ? fmt(props.orderPrice.now + fee) : 'ببلاش');

const priceBreakdown = (fee: number) => {
    if (props.orderPrice.after <= 0) return 'طلباتك ببلاش، والتأكيد كمان.';
    const parts = `${fmt(props.orderPrice.now)} الطلب + ${fmt(fee)} التأكيد`;

    return props.orderPrice.intro && props.orderPrice.after !== props.orderPrice.now
        ? `${parts} · بعد أول ٣ شهور ${fmt(props.orderPrice.after + fee)}`
        : parts;
};

const FLASH_MESSAGES: Record<string, string> = {
    'whatsapp-waha-started': 'افتح واتساب على موبايلك ← الأجهزة المرتبطة ← ربط جهاز، وامسح الكود.',
    'whatsapp-connected': 'الواتساب اتربط. جرّب تبعت رسالة تجربة.',
    'whatsapp-message-saved': 'اتحفظ.',
    'whatsapp-enabled': 'الإرسال اشتغل تاني.',
    'whatsapp-disabled': 'الإرسال اتوقف مؤقتًا.',
    'whatsapp-disconnected': 'اتفك الربط.',
    'whatsapp-platform': 'اشتغل على رقم متجر برو. أي طلب جديد هيوصل لعميله زرار «تأكيد الطلب».',
};

const flash = computed(() => {
    const raw = page.props.flash?.status as string | undefined;
    return raw ? (FLASH_MESSAGES[raw] ?? raw) : null;
});

const flashError = computed(() => page.props.flash?.error as string | undefined);

/* ── Switching off ──────────────────────────────────────────────────────── */

const disconnect = () => {
    if (!confirm('هتفك ربط الواتساب. سجل الرسايل هيفضل زي ما هو.')) return;
    router.delete(route('whatsapp.destroy'), { preserveScroll: true });
};

const toggle = () => router.patch(route('whatsapp.toggle'), {}, { preserveScroll: true });

/* ── The message ────────────────────────────────────────────────────────── */

const messageForm = useForm({
    message_template: link.value?.message_template ?? props.default_template,
    auto_send: link.value?.auto_send ?? true,
});

const saveMessage = () => messageForm.put(route('whatsapp.message'), { preserveScroll: true });

const insert = (token: string) => {
    messageForm.message_template += token;
};

/*
 * What the customer will actually read.
 *
 * Beside the box rather than under it: the whole reason the wording is editable
 * is that the merchant is judging tone, and tone is judged by looking at the
 * finished message, not at a string with braces in it.
 */
const preview = computed(() => {
    const sample: Record<string, string> = {
        '{store}': 'متجرك',
        '{name}': 'سارة عبد الله',
        '{number}': '1042',
        // On one line — the message is a poll title.
        '{items}': '2× قميص قطن (أبيض · L)، 1× حزام جلد',
        '{total}': '798.00',
        '{currency}': 'EGP',
    };

    return Object.entries(sample).reduce(
        (text, [token, value]) => text.split(token).join(value),
        messageForm.message_template,
    );
});

/* ── Test ───────────────────────────────────────────────────────────────── */

const testForm = useForm({ phone: '' });
const sendTest = () => testForm.post(route('whatsapp.test'), { preserveScroll: true });

/* ── Numbers ────────────────────────────────────────────────────────────── */

// Named here rather than repeated in four near-identical blocks of markup.
const TILES = computed(() => [
    { label: 'أكّدوا', value: props.stats.confirmed, tone: 'text-success' },
    { label: 'مستنيين رد', value: props.stats.awaiting, tone: '' },
    { label: 'لغوا', value: props.stats.cancelled, tone: '' },
    { label: 'ماوصلتش', value: props.stats.failed, tone: props.stats.failed ? 'text-destructive' : '' },
]);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="واتساب" />

        <!--
            `wide`, not the settings default.

            This screen is three jobs side by side — keys, wording, and what the
            customers did with it — and a reading-width column stacks them into a
            page nobody scrolls to the bottom of, on a screen that had the room
            all along.
        -->
        <SettingsLayout width="wide">
            <div class="space-y-5">
                <!-- ── Header ─────────────────────────────────────────────
                     Status and the two dangerous buttons live up here, where a
                     merchant looks first to answer "is this on?".
                -->
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="max-w-2xl">
                        <h1 class="text-2xl font-bold tracking-tight">تأكيد الطلبات على واتساب</h1>
                        <p class="mt-1 text-sm leading-relaxed text-muted-foreground">
                            أول ما يجيلك طلب، العميل بيوصله رسالة على واتساب. يأكّد، والطلب يتأكّد
                            لوحده. يلغي، والطلب يتلغي. اللي مايردش يفضل «قيد المراجعة» عشان تكلّمه.
                        </p>
                    </div>

                    <div v-if="link" class="flex flex-wrap items-center gap-2">
                        <span v-if="link.is_active" class="badge-success gap-1">
                            <ShieldCheck class="size-3" />
                            شغّال
                        </span>
                        <span v-else class="badge-neutral">متوقّف</span>

                        <span class="badge-info">
                            {{ isPlatform ? 'رقم متجر برو' : 'رقمك بالكود' }}
                        </span>

                        <span v-if="link.sender_phone" class="tabular font-mono text-xs text-muted-foreground" dir="ltr">
                            {{ link.sender_phone }}
                        </span>

                        <button class="btn-outline py-1.5" @click="toggle">
                            {{ link.is_active ? 'وقّف' : 'شغّل' }}
                        </button>
                        <button class="btn-ghost py-1.5 text-destructive" @click="disconnect">
                            <Link2Off class="size-4" />
                            فك الربط
                        </button>
                    </div>
                </div>

                <div v-if="flash" class="flex items-start gap-2 rounded-xl border border-success/30 bg-success/5 p-3.5 text-sm text-success">
                    <Check class="mt-0.5 size-4 shrink-0" />
                    <span>{{ flash }}</span>
                </div>
                <div v-if="flashError" class="flex items-start gap-2 rounded-xl border border-destructive/30 bg-destructive/5 p-3.5 text-sm text-destructive">
                    <X class="mt-0.5 size-4 shrink-0" />
                    <span>{{ flashError }}</span>
                </div>

                <p v-if="link?.last_error" class="flex items-start gap-2 rounded-xl border border-warning/30 bg-warning/5 p-3.5 text-sm text-warning">
                    <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                    <span>آخر محاولة فشلت: {{ link.last_error }}</span>
                </p>

                <!-- Long strings on the left where they fit; the monitoring rail
                     on the right where a glance is enough. -->
                <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_21rem]">
                    <div class="min-w-0 space-y-5">
                        <!-- ── Which number the confirmations go out on ─────
                             One choice, side by side, each with what an order
                             costs with it — the two used to be separate cards
                             in different styles and did not read as a choice. -->
                        <section class="surface p-5">
                            <h2 class="font-semibold">رسايل التأكيد تطلع من…</h2>
                            <p class="mt-1 text-sm text-muted-foreground">اختار واحد. تقدر تغيّر في أي وقت.</p>

                            <div class="mt-4 grid gap-3 xl:grid-cols-2">
                                <!-- Meta: the platform's official number -->
                                <div
                                    class="flex flex-col rounded-2xl border-2 p-4 transition-colors"
                                    :class="isPlatform ? 'border-primary bg-primary/5' : 'border-border'"
                                >
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="flex items-center gap-2 font-semibold">
                                            <ShieldCheck class="size-4 text-primary" />
                                            رقم متجر برو الرسمي
                                        </p>
                                        <span v-if="isPlatform" class="badge-success gap-1"><Check class="size-3" /> المختار</span>
                                        <span v-else class="badge-neutral">واتساب الرسمي من ميتا</span>
                                    </div>
                                    <ul class="mt-3 space-y-1.5 text-sm">
                                        <li class="flex items-start gap-2"><Check class="mt-0.5 size-4 shrink-0 text-success" /> رقم موثّق، مش هيتحظر</li>
                                        <li class="flex items-start gap-2"><Check class="mt-0.5 size-4 shrink-0 text-success" /> زرار «تأكيد» وزرار «إلغاء»</li>
                                        <li class="flex items-start gap-2"><Check class="mt-0.5 size-4 shrink-0 text-success" /> من غير أي إعداد — دوسة واحدة</li>
                                    </ul>
                                    <p class="mt-2 text-xs leading-relaxed text-muted-foreground">
                                        الرسالة بتطلع باسم «متجر برو» وفيها اسم متجرك. الرد على العملاء بيبقى من موبايلك.
                                    </p>

                                    <div class="mt-auto pt-4">
                                        <p class="flex items-baseline gap-1.5">
                                            <span class="tabular text-2xl font-bold">{{ orderTotal(platform.fee) }}</span>
                                            <span class="text-xs text-muted-foreground">{{ currency }} للطلب بالتأكيد</span>
                                        </p>
                                        <p class="mt-0.5 text-[11px] text-muted-foreground">{{ priceBreakdown(platform.fee) }}</p>
                                        <button v-if="!isPlatform" class="btn-outline mt-3 w-full" :disabled="usingPlatform || !platform.available" @click="usePlatform">
                                            <LoaderCircle v-if="usingPlatform" class="size-4 animate-spin" />
                                            {{ !platform.available ? 'قريبًا' : link ? 'حوّل للرقم الرسمي' : 'فعّل الخدمة' }}
                                        </button>
                                    </div>
                                </div>

                                <!-- The store's own number, by QR -->
                                <div
                                    class="flex flex-col rounded-2xl border-2 p-4 transition-colors"
                                    :class="isWaha ? 'border-primary bg-primary/5' : 'border-border'"
                                >
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="flex items-center gap-2 font-semibold">
                                            <QrCode class="size-4 text-primary" />
                                            رقمك إنت
                                        </p>
                                        <span v-if="isWaha" class="badge-success gap-1"><Check class="size-3" /> المختار</span>
                                        <span v-else class="badge-neutral">بمسح كود زي واتساب ويب</span>
                                    </div>
                                    <ul class="mt-3 space-y-1.5 text-sm">
                                        <li class="flex items-start gap-2"><Check class="mt-0.5 size-4 shrink-0 text-success" /> الرسالة من رقم متجرك وباسمه</li>
                                        <li class="flex items-start gap-2"><Check class="mt-0.5 size-4 shrink-0 text-success" /> كل محادثاتك والرد عليها من «المحادثات»</li>
                                        <li class="flex items-start gap-2"><Check class="mt-0.5 size-4 shrink-0 text-success" /> استطلاع «تأكيد / إلغاء» يدوس عليه العميل</li>
                                    </ul>
                                    <p class="mt-2 flex items-start gap-1.5 text-xs leading-relaxed text-warning">
                                        <AlertTriangle class="mt-0.5 size-3.5 shrink-0" />
                                        مش رسمي من ميتا، وواتساب ممكن يقفل رقم بيبعت رسايل آلية كتير — الأأمن رقم مخصّص للمتجر.
                                    </p>

                                    <div class="mt-auto pt-4">
                                        <p class="flex items-baseline gap-1.5">
                                            <span class="tabular text-2xl font-bold">{{ orderTotal(waha.fee) }}</span>
                                            <span class="text-xs text-muted-foreground">{{ currency }} للطلب بالتأكيد</span>
                                        </p>
                                        <p class="mt-0.5 text-[11px] text-muted-foreground">{{ priceBreakdown(waha.fee) }}</p>
                                        <button v-if="!isWaha" class="btn-primary mt-3 w-full" :disabled="startingWaha || !waha.available" @click="useWaha">
                                            <LoaderCircle v-if="startingWaha" class="size-4 animate-spin" />
                                            <QrCode v-else class="size-4" />
                                            {{ !waha.available ? 'قريبًا' : link ? 'حوّل لرقمي' : 'اربط رقمي' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- ── The platform number ────────────────────────
                             First, because for most merchants it is the whole
                             answer: no gateway account, no keys, no template to
                             get approved — and the only option with buttons. -->
                        <section v-if="isPlatform" class="surface p-5">
                            <h2 class="flex items-center gap-2 font-semibold">
                                <ShieldCheck class="size-4 text-success" />
                                شغّال على رقم متجر برو الرسمي
                            </h2>
                            <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                                كل طلب جديد بيوصل لعميله الرسالة دي من رقم متجر برو الموثّق، وفيها اسم متجرك.
                                يدوس «{{ platform.confirm_label }}» والطلب يتأكّد هنا لوحده. اللي مايردش بنفكّره
                                مرة واحدة بعد كام ساعة، ولو برضه ماردّش بيظهر عندك في الطلبات بعلامة ☎ عشان تكلّمه.
                            </p>
                            <div class="mt-4 max-w-sm rounded-2xl bg-muted/50 p-3 text-sm leading-relaxed">
                                <p class="whitespace-pre-line">{{ platform.preview }}</p>
                                <div class="mt-3 grid gap-1.5 border-t border-border pt-2.5 text-center text-xs font-medium text-info">
                                    <span>{{ platform.confirm_label }}</span>
                                    <span>{{ platform.cancel_label }}</span>
                                </div>
                            </div>
                        </section>


                        <!-- ── Your own number, by QR (WAHA) ─────────────────
                             Linked: the number and how to unlink it. Linking:
                             the QR, refreshed until the phone has scanned it.
                             Not linked: what it is, what it costs, and the one
                             risk it carries, before anything is scanned. -->
                        <section v-if="isWaha" class="surface p-5">
                            <h2 class="flex items-center gap-2 font-semibold">
                                <Smartphone class="size-4" :class="wahaStatus === 'WORKING' ? 'text-success' : 'text-primary'" />
                                {{ wahaStatus === 'WORKING' ? 'رقمك متوصّل' : 'اربط رقمك بمسح الكود' }}
                            </h2>

                            <div v-if="wahaStatus === 'WORKING'" class="mt-3 flex flex-wrap items-center gap-3 text-sm">
                                <span class="tabular font-mono" dir="ltr">+{{ wahaPhone }}</span>
                                <span class="badge-success">شغّال</span>
                                <span class="text-muted-foreground">رسايل التأكيد بتطلع من رقمك، وتحتها استطلاع «تأكيد / إلغاء».</span>
                            </div>

                            <div v-else class="mt-4 flex flex-wrap items-start gap-5">
                                <div class="flex size-56 shrink-0 items-center justify-center rounded-2xl border border-border bg-white p-2">
                                    <!-- WAHA hands out a handful of codes over about two
                                         minutes, then gives up. Saying so, with a way to
                                         start again, beats a spinner that never ends. -->
                                    <div v-if="wahaExpired" class="px-4 text-center">
                                        <p class="text-sm font-medium text-foreground">الكود خلص وقته</p>
                                        <p class="mt-1 text-xs text-muted-foreground">محدش مسحه في الوقت. اعمل كود جديد وامسحه على طول.</p>
                                        <button class="btn-primary mt-3 text-sm" :disabled="startingWaha" @click="renewWaha">
                                            <LoaderCircle v-if="startingWaha" class="size-4 animate-spin" />
                                            <QrCode v-else class="size-4" />
                                            كود جديد
                                        </button>
                                    </div>
                                    <img v-else-if="wahaQr" :src="`data:image/png;base64,${wahaQr}`" alt="كود ربط واتساب" class="size-full" />
                                    <LoaderCircle v-else class="size-6 animate-spin text-muted-foreground" />
                                </div>
                                <ol class="min-w-0 flex-1 list-decimal space-y-2 pr-4 text-sm leading-relaxed">
                                    <li>افتح واتساب على الموبايل اللي عليه رقم متجرك.</li>
                                    <li>من النقط التلاتة (أو الإعدادات على آيفون) اختار <b>الأجهزة المرتبطة</b>.</li>
                                    <li>دوس <b>ربط جهاز</b> وامسح الكود ده.</li>
                                    <li class="text-muted-foreground">الكود بيتغيّر لوحده كل شوية — الصفحة بتجيب الجديد.</li>
                                </ol>
                            </div>
                            <p v-if="wahaProblem" class="mt-3 text-xs text-destructive">{{ wahaProblem }}</p>
                        </section>


                        <!-- ── The message ────────────────────────────── -->
                        <section v-if="link && !isPlatform" class="surface p-5">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <h2 class="font-semibold">الرسالة</h2>
                                <p class="text-sm text-muted-foreground">اكتبها بلهجتك — الأقواس بتتملي لوحدها.</p>
                            </div>

                            <form class="mt-4" @submit.prevent="saveMessage">
                                <!-- Editor and preview side by side: the wording
                                     is being judged for tone, and tone is judged
                                     on the finished message. -->
                                <div class="grid gap-4 lg:grid-cols-2">
                                    <div>
                                        <div class="mb-2 flex flex-wrap gap-1.5">
                                            <button
                                                v-for="(label, token) in placeholders"
                                                :key="token"
                                                type="button"
                                                class="rounded-lg border border-border px-2 py-1 text-xs text-muted-foreground transition-colors hover:bg-muted"
                                                :title="label"
                                                @click="insert(token)"
                                            >
                                                {{ token }}
                                            </button>
                                        </div>

                                        <textarea v-model="messageForm.message_template" class="field min-h-56 text-sm" />
                                        <InputError :message="messageForm.errors.message_template" />
                                    </div>

                                    <div class="min-w-0">
                                        <p class="mb-2 text-xs text-muted-foreground">شكلها عند العميل</p>
                                        <div class="rounded-xl bg-muted/40 p-3">
                                            <div v-if="!isWaha" class="max-w-[22rem] rounded-2xl rounded-tr-sm bg-success/10 p-3 text-sm leading-relaxed whitespace-pre-wrap">
                                                {{ preview }}
                                            </div>

                                            <!-- On our WAHA gateway it is one message: a poll whose
                                                 title is the text — drawn here so the merchant sees
                                                 that the customer taps, not types. -->
                                            <div v-else class="max-w-[22rem] rounded-2xl rounded-tr-sm bg-success/10 p-3 text-sm">
                                                <p class="leading-relaxed font-semibold whitespace-pre-wrap">{{ preview }}</p>
                                                <p class="mt-1 text-[11px] text-muted-foreground">اختار إجابة واحدة</p>
                                                <div class="mt-2.5 space-y-2">
                                                    <div v-for="option in ['✅ تأكيد الطلب', '❌ إلغاء الطلب']" :key="option" class="flex items-center gap-2">
                                                        <span class="size-4 shrink-0 rounded-full border-2 border-muted-foreground/40"></span>
                                                        <span>{{ option }}</span>
                                                    </div>
                                                </div>
                                                <p class="mt-2.5 border-t border-border pt-2 text-center text-[11px] text-info">العميل بيدوس على اختيار — من غير ما يكتب حاجة</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <label class="mt-4 flex cursor-pointer items-start gap-2 text-sm">
                                    <input v-model="messageForm.auto_send" type="checkbox" class="mt-0.5 size-4 rounded border-input accent-primary" />
                                    <span>
                                        ابعت أوتوماتيك أول ما يجي طلب
                                        <span class="mt-0.5 block text-xs text-muted-foreground">
                                            لو قفلتها، هتبعت بإيدك من صفحة الطلب.
                                        </span>
                                    </span>
                                </label>

                                <div class="mt-4 flex items-center gap-3">
                                    <button type="submit" class="btn-primary" :disabled="messageForm.processing">
                                        <LoaderCircle v-if="messageForm.processing" class="size-4 animate-spin" />
                                        حفظ
                                    </button>
                                    <span v-if="messageForm.recentlySuccessful" class="text-sm text-success">اتحفظ</span>
                                </div>
                            </form>
                        </section>

                    </div>

                    <!-- ── The rail ───────────────────────────────────── -->
                    <aside class="space-y-5">
                        <template v-if="link">
                            <section class="surface p-4">
                                <h2 class="text-sm font-semibold">النتيجة</h2>

                                <div class="mt-3 grid grid-cols-2 gap-2 text-center">
                                    <div v-for="tile in TILES" :key="tile.label" class="rounded-xl border border-border p-2.5">
                                        <p class="tabular text-lg font-bold" :class="tile.tone">{{ tile.value }}</p>
                                        <p class="mt-0.5 text-[11px] text-muted-foreground">{{ tile.label }}</p>
                                    </div>
                                </div>

                                <p v-if="link.last_sent_at" class="mt-3 text-[11px] leading-relaxed text-muted-foreground">
                                    آخر رسالة {{ link.last_sent_at }}
                                    <template v-if="link.last_inbound_at"><br />آخر رد {{ link.last_inbound_at }}</template>
                                </p>
                            </section>

                            <section class="surface p-4">
                                <h2 class="text-sm font-semibold">جرّب دلوقتي</h2>
                                <p class="mt-1 text-xs leading-relaxed text-muted-foreground">
                                    رسالة حقيقية بآخر طلب في متجرك.
                                </p>

                                <form class="mt-3 space-y-2" @submit.prevent="sendTest">
                                    <input v-model="testForm.phone" class="field font-mono text-sm" dir="ltr" placeholder="01xxxxxxxxx" />
                                    <button type="submit" class="btn-primary w-full" :disabled="testForm.processing || !testForm.phone">
                                        <LoaderCircle v-if="testForm.processing" class="size-4 animate-spin" />
                                        <Send v-else class="size-4" />
                                        ابعت
                                    </button>
                                </form>
                                <InputError :message="testForm.errors.phone" />
                            </section>

                            <!-- The latest conversations, as people rather than a log
                                 of messages; each opens its chat in the inbox. -->
                            <section v-if="recent.length" class="surface overflow-hidden">
                                <div class="flex items-center justify-between px-4 pt-4 pb-2">
                                    <h2 class="text-sm font-semibold">المحادثات</h2>
                                    <Link href="/inbox" class="text-xs font-medium text-primary hover:underline">افتحها كلها</Link>
                                </div>

                                <Link
                                    v-for="c in recent"
                                    :key="c.id"
                                    :href="`/inbox?c=${c.id}`"
                                    class="flex items-start gap-3 border-t border-border/60 px-4 py-2.5 transition-colors hover:bg-muted/60"
                                >
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white" :class="avatarTone(c.id)">
                                        {{ initial(c.name, c.phone) }}
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="flex items-baseline justify-between gap-2">
                                            <span class="truncate text-sm" :class="c.unread ? 'font-semibold' : 'font-medium'">{{ c.name ?? c.phone }}</span>
                                            <span class="shrink-0 text-[11px]" :class="c.unread ? 'font-semibold text-success' : 'text-muted-foreground'">{{ listTime(c.last_at) }}</span>
                                        </span>
                                        <span class="mt-0.5 flex items-center gap-2">
                                            <span class="flex min-w-0 flex-1 items-center gap-1 text-xs" :class="c.unread ? 'text-foreground' : 'text-muted-foreground'">
                                                <CheckCheck v-if="c.last_direction === 'out'" class="size-3 shrink-0 opacity-60" />
                                                <span class="truncate">{{ c.preview }}</span>
                                            </span>
                                            <span v-if="c.unread" class="flex h-4.5 min-w-4.5 shrink-0 items-center justify-center rounded-full bg-success px-1 text-[10px] font-semibold text-white">{{ c.unread }}</span>
                                        </span>
                                    </span>
                                </Link>
                            </section>
                        </template>

                        <!-- Before there is anything to show: what connecting
                             actually buys them. -->
                        <section v-else class="surface p-4">
                            <h2 class="text-sm font-semibold">هيحصل إيه بعد الربط</h2>
                            <ol class="mt-3 space-y-2.5 text-xs leading-relaxed text-muted-foreground">
                                <li class="flex gap-2">
                                    <span class="tabular flex size-5 shrink-0 items-center justify-center rounded-full bg-muted font-bold">١</span>
                                    العميل يطلب من متجرك، فتوصله رسالة من رقمك على طول.
                                </li>
                                <li class="flex gap-2">
                                    <span class="tabular flex size-5 shrink-0 items-center justify-center rounded-full bg-muted font-bold">٢</span>
                                    يدوس «تأكيد» → الطلب يتأكّد لوحده. يدوس «إلغاء» → يتلغي.
                                </li>
                                <li class="flex gap-2">
                                    <span class="tabular flex size-5 shrink-0 items-center justify-center rounded-full bg-muted font-bold">٣</span>
                                    اللي مايردش يفضل «قيد المراجعة» — وده اللي تكلّمه بنفسك.
                                </li>
                            </ol>
                        </section>
                    </aside>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
