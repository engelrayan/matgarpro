<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, Check, LoaderCircle, Plug, ShieldCheck, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface CapiPixel {
    id: number;
    pixel_id: string;
    has_token: boolean;
    last_event_at: string | null;
    last_error: string | null;
}

interface Signal {
    label: string;
    weight: number;
    coverage: number;
    fix: string | null;
    fix_url?: string;
}

const props = defineProps<{
    ids: { meta: string; tiktok: string; snapchat: string };
    capi: CapiPixel[];
    match_quality:
        | { available: false }
        | { available: true; orders_sampled: number; score: number; signals: Signal[] };
}>();

const page = usePage();

/* ── Connection test ────────────────────────────────────────────────────── */

const testing = ref<number | null>(null);

const testConnection = (pixel: CapiPixel) => {
    testing.value = pixel.id;
    router.post(route('pixels.test', pixel.id), {}, {
        preserveScroll: true,
        onFinish: () => (testing.value = null),
    });
};

const flash = computed(() => ({
    ok: page.props.flash?.status as string | undefined,
    error: page.props.flash?.error as string | undefined,
}));

/* ── Match quality ──────────────────────────────────────────────────────── */

const emq = computed(() => (props.match_quality.available ? props.match_quality : null));

const scoreTone = computed(() => {
    const score = emq.value?.score ?? 0;
    if (score >= 70) return { text: 'text-success', ring: 'stroke-success', label: 'ممتاز' };
    if (score >= 45) return { text: 'text-warning', ring: 'stroke-warning', label: 'متوسط' };
    return { text: 'text-destructive', ring: 'stroke-destructive', label: 'ضعيف' };
});

// Circumference of an r=52 circle, for the progress ring's dash offset.
const RING = 2 * Math.PI * 52;

const breadcrumbItems: BreadcrumbItem[] = [{ title: 'إعدادات البيكسل', href: '/settings/pixels' }];

const form = useForm({
    meta: props.ids.meta,
    tiktok: props.ids.tiktok,
    snapchat: props.ids.snapchat,
});

const save = () => form.put(route('pixels.update'), { preserveScroll: true });

const networks = [
    { key: 'meta' as const, label: 'facebook', hint: 'حط id الخاص بالفيسبوك بيكسل' },
    { key: 'tiktok' as const, label: 'tiktok', hint: 'حط id (المعرف) الخاص بالتيكتوك بيكسل' },
    { key: 'snapchat' as const, label: 'snapchat', hint: 'حط id (المعرف) الخاص بالسناب شات بيكسل' },
];

/* ── CAPI token, per Meta pixel ─────────────────────────────────────────── */

const tokenDrafts = ref<Record<number, string>>({});
const savingToken = ref<number | null>(null);

const saveToken = (pixel: CapiPixel) => {
    savingToken.value = pixel.id;
    router.patch(route('pixels.token', pixel.id), { access_token: tokenDrafts.value[pixel.id] ?? '' }, {
        preserveScroll: true,
        onSuccess: () => (tokenDrafts.value[pixel.id] = ''),
        onFinish: () => (savingToken.value = null),
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="إعدادات البيكسل" />

        <!-- `wide`, not the settings default. Three id boxes, a token per Meta
             pixel and a score are things to see together, not a column to
             scroll — and the ids themselves are short lines that were getting a
             full page width each. -->
        <SettingsLayout width="wide">
            <div class="space-y-5">
                <div class="max-w-2xl">
                    <h1 class="text-2xl font-bold tracking-tight">إعدادات البيكسل</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        تقدر تربط فيسبوك وتيك توك وسناب شات بيكسل من هنا.
                    </p>
                </div>

                <!-- Meta's own answer, verbatim. -->
                <div v-if="flash.ok" class="flex items-start gap-2 rounded-xl border border-success/30 bg-success/5 p-3.5 text-sm text-success">
                    <Check class="mt-0.5 size-4 shrink-0" />
                    {{ flash.ok }}
                </div>
                <div v-if="flash.error" class="flex items-start gap-2 rounded-xl border border-destructive/30 bg-destructive/5 p-3.5 text-sm text-destructive">
                    <X class="mt-0.5 size-4 shrink-0" />
                    {{ flash.error }}
                </div>

                <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_21rem]">
                    <div class="min-w-0 space-y-5">
                        <!-- The two mistakes that silently double a merchant's
                             numbers. Side by side and before the boxes, because
                             after them is too late. -->
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="rounded-xl border border-destructive/30 bg-destructive/5 p-3.5">
                                <p class="flex items-start gap-2 text-sm font-semibold text-destructive">
                                    <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                                    متربطش البيكسل بصفحة الشكر ولا بأي زرار
                                </p>
                                <p class="mt-2 pr-6 text-xs leading-relaxed text-muted-foreground">
                                    إحنا بنبعت كل الأحداث أوتوماتيك. لو ربطت حدث كمان على صفحة الشكر،
                                    الطلب هيتحسب مرتين وأرقامك كلها هتبقى ضعف الحقيقة.
                                </p>
                            </div>

                            <div class="rounded-xl border border-warning/30 bg-warning/5 p-3.5">
                                <p class="flex items-start gap-2 text-sm font-semibold text-warning">
                                    <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                                    حط الـ id بس — مش الكود كله
                                </p>
                                <p class="mt-2 pr-6 text-xs leading-relaxed text-muted-foreground">
                                    لو لزقت السكريبت كامل هناخد منه الأرقام لوحدنا، بس الأنضف إنك تحط
                                    المعرّف بس.
                                </p>
                            </div>
                        </div>

                        <!-- ── The ids ────────────────────────────────────
                             Three boxes across. Each holds a list of short
                             numeric lines, so a full page width per network was
                             width nobody could use and height everybody paid.
                        -->
                        <section class="surface p-5">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <h2 class="font-semibold">معرّفات البيكسل</h2>
                                <p class="text-sm text-muted-foreground">كل معرّف (ID) في سطر — تقدر تحط أكتر من واحد.</p>
                            </div>

                            <form class="mt-4" @submit.prevent="save">
                                <div class="grid gap-4 lg:grid-cols-3">
                                    <div v-for="network in networks" :key="network.key">
                                        <label class="mb-2 block font-mono text-sm font-semibold" :for="network.key">
                                            {{ network.label }}
                                        </label>

                                        <textarea
                                            :id="network.key"
                                            v-model="form[network.key]"
                                            class="field min-h-36 font-mono text-sm"
                                            dir="ltr"
                                            :placeholder="network.hint"
                                        />

                                        <InputError :message="form.errors[network.key]" />
                                    </div>
                                </div>

                                <div class="mt-4 flex items-center gap-3">
                                    <button type="submit" class="btn-primary" :disabled="form.processing">
                                        <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                                        حفظ
                                    </button>
                                    <span v-if="form.recentlySuccessful" class="text-sm text-success">اتحفظ</span>
                                </div>
                            </form>
                        </section>

                        <!-- ── Conversions API ─────────────────────────────
                             Kept apart from the boxes above: it needs a token per
                             Meta pixel, and folding it in is what makes this page
                             confusing.
                        -->
                        <section v-if="capi.length" class="surface p-5">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <h2 class="font-semibold">Conversion API</h2>
                                <p class="max-w-md text-sm text-muted-foreground">
                                    بيبعت الطلب من السيرفر كمان، فمانع الإعلانات في متصفح العميل مايضيّعش البيعة من أرقامك.
                                </p>
                            </div>

                            <div class="mt-4 grid gap-3 xl:grid-cols-2">
                                <div v-for="pixel in capi" :key="pixel.id" class="rounded-xl border border-border p-4">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-mono text-sm" dir="ltr">{{ pixel.pixel_id }}</span>
                                        <span v-if="pixel.has_token" class="badge-success gap-1">
                                            <ShieldCheck class="size-3" />
                                            مربوط
                                        </span>
                                        <span v-else class="badge-neutral">مش مربوط</span>
                                    </div>

                                    <p v-if="pixel.last_event_at" class="mt-1.5 text-xs text-muted-foreground">
                                        آخر حدث اتبعت {{ pixel.last_event_at }}
                                    </p>

                                    <p v-if="pixel.last_error" class="mt-1.5 flex items-start gap-1.5 text-xs text-destructive">
                                        <AlertTriangle class="mt-0.5 size-3.5 shrink-0" />
                                        {{ pixel.last_error }}
                                    </p>

                                    <div class="mt-3 flex gap-2">
                                        <input
                                            v-model="tokenDrafts[pixel.id]"
                                            class="field min-w-0 flex-1 font-mono text-xs"
                                            dir="ltr"
                                            :placeholder="pixel.has_token ? 'غيّر التوكن…' : 'Conversions API access token'"
                                        />
                                        <button
                                            class="btn-primary shrink-0"
                                            :disabled="savingToken === pixel.id || !tokenDrafts[pixel.id]"
                                            @click="saveToken(pixel)"
                                        >
                                            <LoaderCircle v-if="savingToken === pixel.id" class="size-4 animate-spin" />
                                            حفظ
                                        </button>
                                    </div>

                                    <!-- A real round-trip to Meta. A token can be
                                         perfectly well-formed and still revoked, and
                                         that is the failure merchants actually hit —
                                         usually weeks later, in lost attribution. -->
                                    <button
                                        v-if="pixel.has_token"
                                        class="btn-outline mt-2 w-full"
                                        :disabled="testing === pixel.id"
                                        @click="testConnection(pixel)"
                                    >
                                        <LoaderCircle v-if="testing === pixel.id" class="size-4 animate-spin" />
                                        <Plug v-else class="size-4" />
                                        اختبر الاتصال دلوقتي
                                    </button>
                                </div>
                            </div>
                        </section>
                    </div>

                    <!-- ── The rail ───────────────────────────────────────
                         The score and the event list are things to glance at
                         while working on the left, not steps in the work.
                    -->
                    <aside class="space-y-5">
                        <!-- ── Match quality ─────────────────────────────
                             Meta grades this in Events Manager days later and
                             never says how to fix it. We compute the same thing
                             from our own orders today — and we know exactly
                             which switch raises it.
                        -->
                        <section v-if="emq" class="surface p-4">
                            <!-- Ring above the words in a rail this narrow;
                                 side by side would squeeze the sentence into a
                                 column of two words. -->
                            <div class="flex flex-col items-center text-center">
                                <div class="relative size-24 shrink-0">
                                    <svg viewBox="0 0 120 120" class="size-full -rotate-90">
                                        <circle cx="60" cy="60" r="52" fill="none" stroke-width="10"
                                                class="stroke-muted" />
                                        <circle cx="60" cy="60" r="52" fill="none" stroke-width="10"
                                                stroke-linecap="round"
                                                :class="scoreTone.ring"
                                                :stroke-dasharray="RING"
                                                :stroke-dashoffset="RING * (1 - emq.score / 100)" />
                                    </svg>
                                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                                        <span class="tabular text-2xl font-bold" :class="scoreTone.text">{{ emq.score }}</span>
                                        <span class="text-[10px] text-muted-foreground">من ١٠٠</span>
                                    </div>
                                </div>

                                <h2 class="mt-2 text-sm font-semibold">
                                    جودة المطابقة —
                                    <span :class="scoreTone.text">{{ scoreTone.label }}</span>
                                </h2>
                            </div>

                            <p class="mt-2 text-xs leading-relaxed text-muted-foreground">
                                كل ما البيانات اللي بنبعتها مع الطلب تزيد، كل ما فيسبوك عرف يربط
                                مبيعات أكتر بإعلانك — وده الفرق بين حملة شكلها خسرانة وحملة مربحة.
                            </p>
                            <p class="mt-1 text-[11px] text-muted-foreground">
                                محسوبة من آخر <span class="tabular">{{ emq.orders_sampled }}</span> طلب في متجرك.
                            </p>

                            <div class="mt-4 space-y-3">
                                <div v-for="signal in emq.signals" :key="signal.label">
                                    <div class="flex items-center justify-between gap-2 text-xs">
                                        <span>{{ signal.label }}</span>
                                        <span class="tabular shrink-0 text-muted-foreground">{{ signal.coverage }}%</span>
                                    </div>

                                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-muted">
                                        <div class="h-full rounded-full transition-all"
                                             :class="signal.coverage >= 70 ? 'bg-success' : signal.coverage >= 30 ? 'bg-warning' : 'bg-destructive'"
                                             :style="{ width: `${signal.coverage}%` }" />
                                    </div>

                                    <!-- Only where it is actually low: advice next
                                         to a signal already at 100% is noise. -->
                                    <p v-if="signal.fix && signal.coverage < 70"
                                       class="mt-1.5 flex items-start gap-1.5 text-[11px] leading-relaxed text-muted-foreground">
                                        <ArrowLeft class="mt-0.5 size-3 shrink-0" />
                                        <span>
                                            {{ signal.fix }}
                                            <a v-if="signal.fix_url" :href="signal.fix_url"
                                               class="font-medium text-primary hover:underline">افتح الإعداد</a>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </section>

                        <!-- What we already fire, so nobody adds it a second
                             time by hand — the mistake the red box warns about. -->
                        <section class="surface p-4">
                            <h2 class="text-sm font-semibold">الأحداث اللي بنبعتها أوتوماتيك</h2>
                            <ul class="mt-3 space-y-2 text-xs text-muted-foreground">
                                <li v-for="event in [
                                    ['PageView', 'كل صفحة في المتجر'],
                                    ['ViewContent', 'لما حد يفتح صفحة منتج'],
                                    ['InitiateCheckout', 'لما يبدأ يملا فورم الطلب'],
                                    ['Purchase', 'لما الطلب يتسجّل'],
                                ]" :key="event[0]" class="flex items-start gap-2">
                                    <Check class="mt-0.5 size-3.5 shrink-0 text-success" />
                                    <span>
                                        <span class="font-mono text-[11px] text-foreground">{{ event[0] }}</span>
                                        — {{ event[1] }}
                                    </span>
                                </li>
                            </ul>
                        </section>
                    </aside>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
