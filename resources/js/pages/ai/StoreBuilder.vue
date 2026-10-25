<script setup lang="ts">
import { AiGenerationError, useAiGeneration } from '@/composables/useAiGeneration';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Check, ChevronDown, Image, LayoutTemplate, LoaderCircle, Palette, PenLine, Sparkles } from 'lucide-vue-next';
import { computed, onBeforeUnmount, reactive, ref } from 'vue';

/*
 * Set the whole store up from a few lines about the business.
 *
 * Questions, then a short wait, then the shop itself: the plan is put into
 * the store as drafts the moment it arrives — pages into the builder, starter
 * products as drafts with photos, categories empty — and the merchant lands
 * on ai/StorePreview, looking at their real storefront in the theme the plan
 * chose. Nothing a customer sees changes until they press نشر there.
 */

interface ThemeOption {
    key: string;
    name: string;
    description: string;
    preview_url: string | null;
}

const props = defineProps<{
    storeName: string;
    moods: string[];
    themes: ThemeOption[];
    currentTheme: string;
    triesLeft: number;
    /** What they typed on the landing page, carried through signup. */
    prefill?: string | null;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'تصميم المتجر', href: '/builder' },
    { title: 'جهّزلي المتجر', href: '/ai/store-builder' },
];

const answers = reactive({
    business: props.prefill ?? '',
    audience: '',
    mood: '',
    shipping: '',
    social: { facebook: '', instagram: '', tiktok: '', youtube: '', whatsapp: '' },
});

const state = ref<'ask' | 'working'>('ask');
const error = ref('');
const showSocial = ref(false);

const { run } = useAiGeneration();

// Runs left for this store, ever (config/ai.php → store_builder.total_limit).
const tries = ref(props.triesLeft);
const triesLabel = computed(() =>
    tries.value === 1 ? 'باقي محاولة واحدة' : tries.value === 2 ? 'باقي محاولتين' : `باقي ${tries.value} محاولات`,
);

/*
 * What is happening while they wait, one line at a time. Paced to the real
 * call (10–20s on Haiku) rather than tied to it: the API reports nothing
 * until it is done, and a bar that jumps from 0 to 100 tells nobody anything.
 * The last step holds until the plan is actually back.
 */
const STEPS = [
    { icon: PenLine, label: 'بنقرا اللي كتبته عن متجرك' },
    { icon: Palette, label: 'بنختار الشكل والألوان' },
    { icon: LayoutTemplate, label: 'بنكتب الصفحة الرئيسية والأقسام' },
    { icon: Image, label: 'بنجيب صور لمنتجاتك' },
    { icon: Sparkles, label: 'بنرتّب متجرك عشان تشوفه' },
];
const step = ref(0);
let timer: ReturnType<typeof setInterval> | undefined;

const startSteps = () => {
    step.value = 0;
    timer = setInterval(() => {
        if (step.value < STEPS.length - 1) step.value++;
    }, 3500);
};
const stopSteps = () => timer && clearInterval(timer);
onBeforeUnmount(stopSteps);

const ask = async () => {
    if (!answers.business.trim() || state.value === 'working' || tries.value === 0) return;

    state.value = 'working';
    error.value = '';
    startSteps();

    try {
        const result = await run<unknown>('/ai/store-plans', JSON.parse(JSON.stringify(answers)));
        tries.value = Math.max(0, tries.value - 1);
        step.value = STEPS.length - 1;

        // Straight into the store as drafts; the server answers with the
        // preview page.
        router.post(`/ai/store-plans/${result.id}/apply`, {}, {
            onError: (errors) => {
                stopSteps();
                error.value = Object.values(errors)[0] ?? 'حصلت مشكلة. جرّب تاني.';
                state.value = 'ask';
            },
        });
    } catch (e) {
        stopSteps();
        error.value = e instanceof AiGenerationError ? e.message : 'حصلت مشكلة. جرّب تاني.';
        state.value = 'ask';
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="جهّزلي المتجر" />

        <div class="mx-auto max-w-3xl space-y-6 px-4 py-6">
            <header class="flex items-start gap-3">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                    <Sparkles class="h-5 w-5" />
                </span>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">جهّزلي المتجر</h1>
                    <p class="text-sm text-muted-foreground">
                        احكيلنا عن {{ storeName }} في كام سطر، وإحنا نختار الشكل ونعمل الأقسام ونكتب الصفحة الرئيسية ونجهّز منتجات مثال بصور. هتشوف متجرك بعينك، ومفيش حاجة هتظهر
                        للزبون غير لما تدوس نشر.
                    </p>
                </div>
            </header>

            <!-- ══ 1. The questions ═══════════════════════════════════════ -->
            <form v-if="state === 'ask'" class="surface space-y-5 p-6" @submit.prevent="ask">
                <div>
                    <label class="field-label" for="business">بتبيع إيه؟</label>
                    <textarea
                        id="business"
                        v-model="answers.business"
                        class="field"
                        rows="3"
                        maxlength="600"
                        required
                        placeholder="مثلاً: هدوم رجالي كاجوال — تيشيرتات وبناطيل وجواكت، قطن مصري، أسعار من ٢٠٠ لـ ٨٠٠ جنيه"
                        :disabled="state === 'working'"
                    />
                </div>

                <div>
                    <label class="field-label" for="audience">
                        مين زباينك؟
                        <span class="font-normal text-muted-foreground">— اختياري</span>
                    </label>
                    <input
                        id="audience"
                        v-model="answers.audience"
                        class="field"
                        maxlength="300"
                        placeholder="مثلاً: شباب من ١٨ لـ ٣٥ سنة"
                        :disabled="state === 'working'"
                    />
                </div>

                <div>
                    <p class="field-label">عايز المتجر يبان إزاي؟</p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="mood in ['', ...moods]"
                            :key="mood"
                            type="button"
                            class="rounded-full border px-3.5 py-1.5 text-sm transition-colors"
                            :class="answers.mood === mood ? 'border-primary bg-primary/10 text-primary' : 'border-border text-muted-foreground'"
                            :disabled="state === 'working'"
                            @click="answers.mood = mood"
                        >
                            {{ mood || 'سيبها علينا' }}
                        </button>
                    </div>
                </div>

                <div>
                    <label class="field-label" for="shipping">
                        الشحن والاستبدال عندك عاملين إزاي؟
                        <span class="font-normal text-muted-foreground">— اختياري</span>
                    </label>
                    <textarea
                        id="shipping"
                        v-model="answers.shipping"
                        class="field"
                        rows="2"
                        maxlength="600"
                        placeholder="مثلاً: بنشحن لكل المحافظات في ٢ لـ ٤ أيام، والاستبدال خلال ١٤ يوم"
                        :disabled="state === 'working'"
                    />
                    <p class="field-hint">اللي تكتبه هنا بس هو اللي هيتقال للزبون عن الشحن والاستبدال — مش هنألّف مواعيد ولا وعود.</p>
                </div>

                <div class="rounded-xl border border-border">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-4 py-3 text-right text-sm"
                        @click="showSocial = !showSocial"
                    >
                        <span>حساباتك على السوشيال <span class="text-muted-foreground">— اختياري</span></span>
                        <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': showSocial }" />
                    </button>
                    <div v-show="showSocial" class="grid gap-3 border-t border-border p-4 sm:grid-cols-2">
                        <input
                            v-model="answers.social.facebook"
                            class="field"
                            dir="ltr"
                            placeholder="https://facebook.com/..."
                            :disabled="state === 'working'"
                        />
                        <input
                            v-model="answers.social.instagram"
                            class="field"
                            dir="ltr"
                            placeholder="https://instagram.com/..."
                            :disabled="state === 'working'"
                        />
                        <input
                            v-model="answers.social.tiktok"
                            class="field"
                            dir="ltr"
                            placeholder="https://tiktok.com/@..."
                            :disabled="state === 'working'"
                        />
                        <input
                            v-model="answers.social.whatsapp"
                            class="field"
                            dir="ltr"
                            placeholder="رقم الواتساب 01012345678"
                            :disabled="state === 'working'"
                        />
                    </div>
                </div>

                <p v-if="error" class="field-error mt-0">{{ error }}</p>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="btn-primary" :disabled="!answers.business.trim() || state === 'working' || tries === 0">
                        <LoaderCircle v-if="state === 'working'" class="h-4 w-4 animate-spin" />
                        <Sparkles v-else class="h-4 w-4" />
                        {{ state === 'working' ? 'بنجهّز متجرك…' : 'جهّزلي المتجر' }}
                    </button>
                    <span v-if="state === 'working'" class="text-xs text-muted-foreground">ده بياخد أقل من دقيقة.</span>
                    <span v-else-if="tries === 0" class="text-xs text-muted-foreground">
                        استخدمت كل المحاولات المتاحة. تقدر تعدّل كل حاجة بنفسك من
                        <Link href="/builder" class="font-medium text-primary hover:underline">تصميم المتجر</Link>.
                    </span>
                    <span v-else class="text-xs text-muted-foreground">{{ triesLabel }}</span>
                </div>
            </form>

            <!-- ══ 2. Building ════════════════════════════════════════════ -->
            <section v-else class="ai-ring rounded-[22px]" aria-live="polite">
                <div class="relative overflow-hidden rounded-[20.5px] bg-card px-6 py-10 md:px-10">
                    <div aria-hidden="true" class="pointer-events-none absolute -end-24 -top-24 size-72 rounded-full bg-[hsl(205_85%_66%/0.14)] blur-3xl" />
                    <div aria-hidden="true" class="pointer-events-none absolute -bottom-28 start-1/4 size-64 rounded-full bg-[hsl(330_75%_72%/0.12)] blur-3xl" />

                    <div class="relative mx-auto max-w-md text-center">
                        <span class="mx-auto grid size-16 place-items-center rounded-[18px] bg-gradient-to-br from-jade-500 via-[hsl(205_75%_55%)] to-[hsl(330_65%_62%)] text-white shadow-[0_8px_24px_-8px_hsl(205_75%_45%/0.55)]">
                            <Sparkles class="size-8 animate-pulse" />
                        </span>
                        <h2 class="mt-5 text-2xl font-bold tracking-tight">
                            <span class="ai-text">الذكاء الاصطناعي</span> بيجهّز {{ storeName }}
                        </h2>
                        <p class="mt-1.5 text-[15px] text-muted-foreground">أقل من دقيقة، وهتشوف متجرك بعينك.</p>

                        <ol class="mt-8 space-y-3 text-start">
                            <li
                                v-for="(s, i) in STEPS"
                                :key="s.label"
                                class="flex items-center gap-3 rounded-2xl px-4 py-3 transition-all duration-500"
                                :class="i < step ? 'bg-foreground/[0.03] text-muted-foreground' : i === step ? 'bg-primary/[0.07] font-semibold text-foreground' : 'opacity-40'"
                            >
                                <span class="grid size-8 shrink-0 place-items-center rounded-full" :class="i < step ? 'bg-success/15 text-success' : 'bg-primary/10 text-primary'">
                                    <Check v-if="i < step" class="size-4" />
                                    <LoaderCircle v-else-if="i === step" class="size-4 animate-spin" />
                                    <component :is="s.icon" v-else class="size-4" />
                                </span>
                                <span class="text-[15px]">{{ s.label }}</span>
                            </li>
                        </ol>
                    </div>
                </div>
            </section>

            <Link v-if="state === 'ask'" href="/builder" class="btn-ghost">رجوع لتصميم المتجر</Link>
        </div>
    </AppLayout>
</template>
