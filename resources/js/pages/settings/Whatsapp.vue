<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle, ArrowDownLeft, ArrowUpRight, Check, Copy, Link2Off,
    LoaderCircle, MessageCircle, Send, ShieldCheck, X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Driver = 'wapilot' | 'whats360' | 'cloud_api';

interface Connected {
    connected: true;
    driver: Driver;
    base_url: string | null;
    sender_phone: string | null;
    is_active: boolean;
    auto_send: boolean;
    message_template: string;
    template_name: string | null;
    template_language: string;
    has_app_secret: boolean;
    webhook_url: string;
    verify_token: string;
    connected_at: string | null;
    last_sent_at: string | null;
    last_inbound_at: string | null;
    last_error: string | null;
}

interface RecentMessage {
    direction: 'in' | 'out';
    phone: string;
    body: string;
    status: string;
    intent: string | null;
    error: string | null;
    at: string;
}

const props = defineProps<{
    integration: Connected | { connected: false };
    placeholders: Record<string, string>;
    default_template: string;
    stats: { sent: number; awaiting: number; confirmed: number; cancelled: number; failed: number };
    recent: RecentMessage[];
}>();

const page = usePage();
const breadcrumbItems: BreadcrumbItem[] = [{ title: 'واتساب', href: '/settings/whatsapp' }];

const link = computed(() => (props.integration.connected ? (props.integration as Connected) : null));

const FLASH_MESSAGES: Record<string, string> = {
    'whatsapp-connected': 'الواتساب اتربط. جرّب تبعت رسالة تجربة.',
    'whatsapp-message-saved': 'اتحفظ.',
    'whatsapp-enabled': 'الإرسال اشتغل تاني.',
    'whatsapp-disabled': 'الإرسال اتوقف مؤقتًا.',
    'whatsapp-disconnected': 'اتفك الربط.',
};

const flash = computed(() => {
    const raw = page.props.flash?.status as string | undefined;
    return raw ? (FLASH_MESSAGES[raw] ?? raw) : null;
});

const flashError = computed(() => page.props.flash?.error as string | undefined);

/* ── Connecting ─────────────────────────────────────────────────────────── */

const DRIVERS: { value: Driver; label: string; blurb: string; warn: string }[] = [
    {
        value: 'whats360',
        label: 'Whats360',
        blurb: 'بوابة عربية، بتربط رقمك بمسح QR وتكتب أي رسالة من غير موافقات.',
        warn: 'واتساب مش عارف بيها — الرقم ممكن يتحظر من غير إنذار.',
    },
    {
        value: 'wapilot',
        label: 'Wapilot',
        blurb: 'بوابة غير رسمية على واتساب ويب. الربط في دقيقة، ورسايل حرة.',
        warn: 'نفس خطر الحظر — واتساب مابيعرفش عن البوابات دي حاجة.',
    },
    {
        value: 'cloud_api',
        label: 'واتساب الرسمي (ميتا)',
        blurb: 'Cloud API من ميتا مباشرة، من غير وسيط زي 360dialog. رقمك في أمان.',
        warn: 'الرسالة اللي إنت تبدأها لازم تكون قالب معتمد من ميتا.',
    },
];

const driver = ref<Driver>(link.value?.driver ?? 'whats360');

const keyForm = useForm({
    driver: driver.value,
    token: '',
    instance: '',
    instance_id: '',
    base_url: link.value?.base_url ?? '',
    access_token: '',
    phone_number_id: '',
    app_secret: '',
});

const CREDENTIAL_FIELDS = ['token', 'instance', 'instance_id', 'access_token', 'phone_number_id', 'app_secret'] as const;

const connect = () => {
    keyForm.driver = driver.value;
    keyForm.put(route('whatsapp.update'), {
        preserveScroll: true,
        onSuccess: () => keyForm.reset(...CREDENTIAL_FIELDS),
    });
};

const disconnect = () => {
    if (!confirm('هتفك ربط الواتساب. سجل الرسايل هيفضل زي ما هو.')) return;
    router.delete(route('whatsapp.destroy'), { preserveScroll: true });
};

const toggle = () => router.patch(route('whatsapp.toggle'), {}, { preserveScroll: true });

/* ── The message ────────────────────────────────────────────────────────── */

const messageForm = useForm({
    message_template: link.value?.message_template ?? props.default_template,
    auto_send: link.value?.auto_send ?? true,
    template_name: link.value?.template_name ?? '',
    template_language: link.value?.template_language ?? 'ar',
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
        '{items}': '• 2× قميص قطن (أبيض · L)\n• 1× حزام جلد',
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

/* ── Webhook ────────────────────────────────────────────────────────────── */

const copied = ref<string | null>(null);

const copy = async (value: string, key: string) => {
    await navigator.clipboard.writeText(value);
    copied.value = key;
    setTimeout(() => (copied.value = null), 1500);
};

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
                            أول ما يجيلك طلب، العميل بيوصله رسالة من رقم متجرك. يرد ١ يتأكّد الطلب
                            لوحده، يرد ٢ يتلغي. اللي مايردش يفضل «قيد المراجعة» عشان تكلّمه.
                        </p>
                    </div>

                    <div v-if="link" class="flex flex-wrap items-center gap-2">
                        <span v-if="link.is_active" class="badge-success gap-1">
                            <ShieldCheck class="size-3" />
                            شغّال
                        </span>
                        <span v-else class="badge-neutral">متوقّف</span>

                        <span class="badge-info">
                            {{ DRIVERS.find((d) => d.value === link!.driver)?.label ?? link.driver }}
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
                        <!-- ── Gateway ────────────────────────────────── -->
                        <section class="surface p-5">
                            <h2 class="flex items-center gap-2 font-semibold">
                                <MessageCircle class="size-4" />
                                {{ link ? 'الرقم المربوط' : 'اربط رقمك' }}
                            </h2>

                            <div class="mt-4 grid gap-2 sm:grid-cols-3">
                                <button
                                    v-for="option in DRIVERS"
                                    :key="option.value"
                                    type="button"
                                    class="flex h-full flex-col rounded-xl border p-3 text-right transition-colors"
                                    :class="driver === option.value ? 'border-primary bg-primary/5' : 'border-border hover:bg-muted/40'"
                                    @click="driver = option.value"
                                >
                                    <span class="flex items-center gap-1.5 text-sm font-medium">
                                        <Check v-if="driver === option.value" class="size-3.5 shrink-0 text-primary" />
                                        {{ option.label }}
                                    </span>
                                    <span class="mt-1 block text-xs leading-relaxed text-muted-foreground">
                                        {{ option.blurb }}
                                    </span>
                                    <!-- Pinned to the bottom so three cards of
                                         different text length still line up. -->
                                    <span class="mt-auto flex items-start gap-1 pt-2 text-[11px] leading-relaxed text-warning">
                                        <AlertTriangle class="mt-0.5 size-3 shrink-0" />
                                        {{ option.warn }}
                                    </span>
                                </button>
                            </div>

                            <form class="mt-4 space-y-3" @submit.prevent="connect">
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <template v-if="driver === 'wapilot'">
                                        <div>
                                            <label class="mb-1.5 block text-sm font-medium">التوكن</label>
                                            <input v-model="keyForm.token" class="field font-mono text-xs" dir="ltr"
                                                   :placeholder="link?.driver === 'wapilot' ? 'سيبه فاضي لو مش هتغيّره' : 'token'" autocomplete="off" />
                                            <InputError :message="keyForm.errors.token" />
                                        </div>
                                        <div>
                                            <label class="mb-1.5 block text-sm font-medium">الـ instance</label>
                                            <input v-model="keyForm.instance" class="field font-mono text-xs" dir="ltr"
                                                   placeholder="instance3853" autocomplete="off" />
                                            <InputError :message="keyForm.errors.instance" />
                                        </div>
                                    </template>

                                    <template v-else-if="driver === 'whats360'">
                                        <div>
                                            <label class="mb-1.5 block text-sm font-medium">التوكن (API token)</label>
                                            <input v-model="keyForm.token" class="field font-mono text-xs" dir="ltr"
                                                   :placeholder="link?.driver === 'whats360' ? 'سيبه فاضي لو مش هتغيّره' : 'your-api-token'" autocomplete="off" />
                                            <InputError :message="keyForm.errors.token" />
                                        </div>
                                        <div>
                                            <label class="mb-1.5 block text-sm font-medium">Instance ID (الجهاز)</label>
                                            <input v-model="keyForm.instance_id" class="field font-mono text-xs" dir="ltr"
                                                   placeholder="device_abc123" autocomplete="off" />
                                            <InputError :message="keyForm.errors.instance_id" />
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="mb-1.5 block text-sm font-medium">
                                                رابط الخدمة
                                                <span class="font-normal text-muted-foreground">— اختياري</span>
                                            </label>
                                            <input v-model="keyForm.base_url" class="field font-mono text-xs" dir="ltr"
                                                   placeholder="https://whats360.live" autocomplete="off" />
                                            <p class="field-hint">
                                                سيبه فاضي وهنستخدم whats360.live. غيّره بس لو لوحتك على دومين تاني.
                                            </p>
                                            <InputError :message="keyForm.errors.base_url" />
                                        </div>
                                    </template>

                                    <template v-else>
                                        <div>
                                            <label class="mb-1.5 block text-sm font-medium">Access token</label>
                                            <input v-model="keyForm.access_token" class="field font-mono text-xs" dir="ltr"
                                                   :placeholder="link?.driver === 'cloud_api' ? 'سيبه فاضي لو مش هتغيّره' : 'EAAG…'" autocomplete="off" />
                                            <InputError :message="keyForm.errors.access_token" />
                                        </div>
                                        <div>
                                            <label class="mb-1.5 block text-sm font-medium">Phone number ID</label>
                                            <input v-model="keyForm.phone_number_id" class="field font-mono text-xs" dir="ltr"
                                                   placeholder="1234567890" autocomplete="off" />
                                            <InputError :message="keyForm.errors.phone_number_id" />
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="mb-1.5 block text-sm font-medium">
                                                App secret
                                                <span class="font-normal text-muted-foreground">— اختياري بس مهم</span>
                                            </label>
                                            <input v-model="keyForm.app_secret" class="field font-mono text-xs" dir="ltr"
                                                   :placeholder="link?.has_app_secret ? 'محفوظ — سيبه فاضي لو مش هتغيّره' : 'app secret'" autocomplete="off" />
                                            <p class="field-hint">
                                                من غيره أي حد يعرف رابط الـ webhook يقدر يبعتلنا رد مزوّر ويلغي طلبات.
                                            </p>
                                            <InputError :message="keyForm.errors.app_secret" />
                                        </div>
                                    </template>
                                </div>

                                <InputError :message="keyForm.errors.driver" />

                                <button type="submit" class="btn-primary" :disabled="keyForm.processing">
                                    <LoaderCircle v-if="keyForm.processing" class="size-4 animate-spin" />
                                    {{ link ? 'حفظ وإعادة الفحص' : 'اربط دلوقتي' }}
                                </button>
                            </form>
                        </section>

                        <!-- ── The message ────────────────────────────── -->
                        <section v-if="link" class="surface p-5">
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
                                            <div class="max-w-[22rem] rounded-2xl rounded-tr-sm bg-success/10 p-3 text-sm leading-relaxed whitespace-pre-wrap">
                                                {{ preview }}
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

                                <!-- Cloud API only: without an approved template
                                     there is no business-initiated message at all. -->
                                <div v-if="link.driver === 'cloud_api'" class="mt-4 rounded-xl border border-warning/30 bg-warning/5 p-3">
                                    <p class="flex items-start gap-1.5 text-xs font-medium text-warning">
                                        <AlertTriangle class="mt-0.5 size-3.5 shrink-0" />
                                        ميتا مابتسمحش تبدأ كلام مع عميل غير بقالب معتمد
                                    </p>
                                    <p class="mt-2 text-xs leading-relaxed text-muted-foreground">
                                        اعمل قالب في WhatsApp Manager نوع Utility، حط فيه ٥ متغيّرات بالترتيب ده:
                                        الاسم، رقم الطلب، المنتجات، الإجمالي، العملة — وزوّده زرارين رد سريع
                                        «أكّد الطلب» و«ألغِ الطلب». وبعدين حط اسمه هنا.
                                    </p>
                                    <div class="mt-3 grid gap-2 sm:grid-cols-[1fr_8rem]">
                                        <input v-model="messageForm.template_name" class="field font-mono text-xs" dir="ltr"
                                               placeholder="order_confirmation" />
                                        <input v-model="messageForm.template_language" class="field font-mono text-xs" dir="ltr"
                                               placeholder="ar" />
                                    </div>
                                    <p class="field-hint">
                                        سيبه فاضي وهنبعت نص عادي — ده هيشتغل بس مع عميل كلّمك في آخر ٢٤ ساعة.
                                    </p>
                                </div>

                                <div class="mt-4 flex items-center gap-3">
                                    <button type="submit" class="btn-primary" :disabled="messageForm.processing">
                                        <LoaderCircle v-if="messageForm.processing" class="size-4 animate-spin" />
                                        حفظ
                                    </button>
                                    <span v-if="messageForm.recentlySuccessful" class="text-sm text-success">اتحفظ</span>
                                </div>
                            </form>
                        </section>

                        <!-- ── Replies ────────────────────────────────── -->
                        <section v-if="link" class="surface p-5">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <h2 class="font-semibold">استقبال ردود العملاء</h2>
                                <p class="text-sm text-muted-foreground">
                                    من غيرها هتبعت وبس — الطلب هيفضل مستني مراجعتك.
                                </p>
                            </div>

                            <div class="mt-4 grid gap-3" :class="link.driver === 'cloud_api' ? 'lg:grid-cols-2' : ''">
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium">Webhook URL — حطه عند مزوّد الواتساب</label>
                                    <div class="flex gap-2">
                                        <input :value="link.webhook_url" readonly class="field min-w-0 flex-1 font-mono text-xs" dir="ltr" />
                                        <button class="btn-outline shrink-0" @click="copy(link.webhook_url, 'url')">
                                            <Check v-if="copied === 'url'" class="size-4 text-success" />
                                            <Copy v-else class="size-4" />
                                        </button>
                                    </div>
                                </div>

                                <div v-if="link.driver === 'cloud_api'">
                                    <label class="mb-1.5 block text-sm font-medium">Verify token — ميتا هتطلبه منك</label>
                                    <div class="flex gap-2">
                                        <input :value="link.verify_token" readonly class="field min-w-0 flex-1 font-mono text-xs" dir="ltr" />
                                        <button class="btn-outline shrink-0" @click="copy(link.verify_token, 'verify')">
                                            <Check v-if="copied === 'verify'" class="size-4 text-success" />
                                            <Copy v-else class="size-4" />
                                        </button>
                                    </div>
                                    <p class="field-hint">اشترك في حدث <span class="font-mono">messages</span> بس.</p>
                                </div>
                            </div>

                            <p v-if="link.driver !== 'cloud_api'" class="mt-3 flex items-start gap-1.5 rounded-xl border border-warning/30 bg-warning/5 p-3 text-xs leading-relaxed text-warning">
                                <AlertTriangle class="mt-0.5 size-3.5 shrink-0" />
                                البوابة دي مابتوقّعش الرسايل اللي بتبعتها لنا، فالرابط ده هو السر الوحيد —
                                متحطهوش في أي مكان عام.
                            </p>
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

                            <section v-if="recent.length" class="surface p-4">
                                <h2 class="text-sm font-semibold">آخر الرسايل</h2>

                                <ul class="mt-3 space-y-2.5">
                                    <li v-for="(message, i) in recent" :key="i" class="flex items-start gap-2">
                                        <ArrowUpRight v-if="message.direction === 'out'" class="mt-0.5 size-3.5 shrink-0 text-muted-foreground" />
                                        <ArrowDownLeft v-else class="mt-0.5 size-3.5 shrink-0 text-primary" />

                                        <div class="min-w-0 flex-1">
                                            <p class="flex flex-wrap items-center gap-1.5">
                                                <span class="tabular font-mono text-[11px] text-muted-foreground" dir="ltr">{{ message.phone }}</span>
                                                <span v-if="message.intent === 'confirm'" class="badge-success">أكّد</span>
                                                <span v-else-if="message.intent === 'cancel'" class="badge-danger">لغى</span>
                                                <span v-else-if="message.intent === 'unknown'" class="badge-neutral">مش مفهوم</span>
                                                <span v-else-if="message.status === 'failed'" class="badge-danger">ماوصلتش</span>
                                            </p>
                                            <p class="mt-0.5 truncate text-xs text-muted-foreground" :title="message.body">{{ message.body }}</p>
                                            <p v-if="message.error" class="mt-0.5 text-[11px] text-destructive">{{ message.error }}</p>
                                            <p class="text-[11px] text-muted-foreground/70">{{ message.at }}</p>
                                        </div>
                                    </li>
                                </ul>
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
                                    يرد ١ → الطلب يتأكّد لوحده. يرد ٢ → يتلغي.
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
