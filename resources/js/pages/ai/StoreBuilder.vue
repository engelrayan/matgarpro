<script setup lang="ts">
import { AiGenerationError, useAiGeneration } from '@/composables/useAiGeneration';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Check,
    ChevronDown,
    ExternalLink,
    LoaderCircle,
    Phone,
    Plus,
    RefreshCw,
    RotateCcw,
    Shield,
    Sparkles,
    TriangleAlert,
    Truck,
    Wallet,
    X,
} from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

/*
 * Set the whole store up from a few lines about the business.
 *
 * Three screens and one promise: nothing here changes what customers see by
 * itself. The plan goes into the builder as drafts, categories are created
 * empty (and an empty category never shows), and the one thing that does go
 * live at once — the look — is a separate tick box that says so.
 */

interface StorePlan {
    theme: string;
    theme_reason: string;
    about: string;
    announcement: string;
    categories: { name: string; description: string }[];
    trust_items: { icon: string; title: string; body: string }[];
    titles: { deals: string; categories: string; products: string };
    /** Sanitised on the server with the description allow-list. */
    story: { heading: string; body: string };
    faq: { question: string; answer: string }[];
    heads_up: string;
}

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
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'تصميم المتجر', href: '/builder' },
    { title: 'جهّزلي المتجر', href: '/ai/store-builder' },
];

const answers = reactive({
    business: '',
    audience: '',
    mood: '',
    shipping: '',
    social: { facebook: '', instagram: '', tiktok: '', youtube: '', whatsapp: '' },
});

const state = ref<'ask' | 'working' | 'review'>('ask');
const error = ref('');
const showSocial = ref(false);
const plan = ref<StorePlan | null>(null);
const planId = ref('');

// The merchant's own edits to the plan before it is applied.
const categories = ref<string[]>([]);
const newCategory = ref('');
const applyTheme = ref(true);
const applying = ref(false);
const applyError = ref('');

const { run } = useAiGeneration();

const suggestedTheme = computed(() => props.themes.find((t) => t.key === plan.value?.theme) ?? null);
const isCurrentTheme = computed(() => plan.value?.theme === props.currentTheme);

const ask = async () => {
    if (!answers.business.trim() || state.value === 'working') return;

    state.value = 'working';
    error.value = '';

    try {
        const result = await run<StorePlan>('/ai/store-plans', JSON.parse(JSON.stringify(answers)));
        plan.value = result.draft;
        planId.value = result.id;
        categories.value = result.draft.categories.map((c) => c.name);
        applyTheme.value = !isCurrentTheme.value;
        state.value = 'review';
    } catch (e) {
        error.value = e instanceof AiGenerationError ? e.message : 'حصلت مشكلة. جرّب تاني.';
        state.value = 'ask';
    }
};

const addCategory = () => {
    const name = newCategory.value.trim();
    if (name && !categories.value.includes(name) && categories.value.length < 8) categories.value.push(name);
    newCategory.value = '';
};

const apply = () => {
    applying.value = true;
    applyError.value = '';

    router.post(
        `/ai/store-plans/${planId.value}/apply`,
        { apply_theme: applyTheme.value && !isCurrentTheme.value, categories: categories.value },
        {
            onError: (errors) => (applyError.value = Object.values(errors)[0] ?? 'حصلت مشكلة. جرّب تاني.'),
            onFinish: () => (applying.value = false),
        },
    );
};

// The trust bar's icon choices, as the storefront draws them.
const ICONS: Record<string, unknown> = { check: Check, truck: Truck, refresh: RefreshCw, shield: Shield, phone: Phone, wallet: Wallet };
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
                        احكيلنا عن {{ storeName }} في كام سطر، وإحنا نختار الشكل ونعمل الأقسام ونكتب الصفحة الرئيسية. كل حاجة بتتحط مسودة، ومش هتظهر
                        للزبون غير لما تراجعها وتدوس نشر.
                    </p>
                </div>
            </header>

            <!-- ══ 1. The questions ═══════════════════════════════════════ -->
            <form v-if="state !== 'review'" class="surface space-y-5 p-6" @submit.prevent="ask">
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
                    <button type="submit" class="btn-primary" :disabled="!answers.business.trim() || state === 'working' || triesLeft === 0">
                        <LoaderCircle v-if="state === 'working'" class="h-4 w-4 animate-spin" />
                        <Sparkles v-else class="h-4 w-4" />
                        {{ state === 'working' ? 'بنجهّز متجرك…' : 'جهّزلي المتجر' }}
                    </button>
                    <span v-if="state === 'working'" class="text-xs text-muted-foreground">ده بياخد أقل من دقيقة.</span>
                    <span v-else-if="triesLeft === 0" class="text-xs text-muted-foreground">خلصت المحاولات بتاعة النهارده. جرّب تاني بكرة.</span>
                </div>
            </form>

            <!-- ══ 2. The plan, for review ═════════════════════════════════ -->
            <template v-else-if="plan">
                <p v-if="plan.heads_up" class="flex items-start gap-2 rounded-xl bg-warning/10 px-4 py-3 text-sm text-warning">
                    <TriangleAlert class="mt-0.5 h-4 w-4 shrink-0" />
                    <span>{{ plan.heads_up }}</span>
                </p>

                <!-- The look -->
                <section class="surface space-y-3 p-6">
                    <h2 class="font-medium">الشكل</h2>
                    <div v-if="suggestedTheme" class="rounded-xl border border-border p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-lg font-semibold">{{ suggestedTheme.name }}</p>
                            <a
                                v-if="suggestedTheme.preview_url"
                                :href="suggestedTheme.preview_url"
                                target="_blank"
                                class="btn-ghost px-2.5 py-1.5 text-sm"
                            >
                                <ExternalLink class="h-4 w-4" />
                                شوف شكله على متجر حقيقي
                            </a>
                        </div>
                        <p class="text-sm text-muted-foreground">{{ suggestedTheme.description }}</p>
                        <p v-if="plan.theme_reason" class="mt-2 text-sm">{{ plan.theme_reason }}</p>
                    </div>

                    <p v-if="isCurrentTheme" class="text-sm text-muted-foreground">ده نفس شكل متجرك دلوقتي، مفيش حاجة هتتغير.</p>
                    <label v-else class="flex cursor-pointer items-start gap-3">
                        <input v-model="applyTheme" type="checkbox" class="mt-0.5 size-4 rounded border-input accent-primary" />
                        <span class="text-sm">
                            غيّر شكل المتجر للشكل ده
                            <span class="block text-xs text-muted-foreground">
                                الشكل بيتغير على المتجر على طول، مش مسودة. تقدر ترجّعه في أي وقت من إعدادات الشكل.
                            </span>
                        </span>
                    </label>
                </section>

                <!-- Categories -->
                <section class="surface space-y-3 p-6">
                    <h2 class="font-medium">الأقسام</h2>
                    <p class="text-sm text-muted-foreground">هتتعمل فاضية، ومش هتظهر للزبون غير لما تحط فيها منتجات. شيل أو زوّد براحتك.</p>
                    <div class="flex flex-wrap gap-2">
                        <span v-for="(name, index) in categories" :key="name" class="badge-neutral gap-1.5 py-1.5 pl-1.5 pr-2.5 text-sm">
                            {{ name }}
                            <button type="button" @click="categories.splice(index, 1)"><X class="h-3 w-3" /></button>
                        </span>
                        <span v-if="!categories.length" class="text-sm text-muted-foreground">مفيش أقسام جديدة.</span>
                    </div>
                    <div v-if="categories.length < 8" class="flex gap-2">
                        <input v-model="newCategory" class="field" maxlength="60" placeholder="قسم تاني…" @keydown.enter.prevent="addCategory" />
                        <button type="button" class="btn-outline" @click="addCategory"><Plus class="h-4 w-4" /></button>
                    </div>
                </section>

                <!-- What the pages will say -->
                <section class="surface space-y-5 p-6">
                    <h2 class="font-medium">الكلام اللي هيتكتب</h2>

                    <div>
                        <p class="text-xs text-muted-foreground">الشريط الملوّن فوق خالص</p>
                        <p class="mt-1 rounded-lg bg-primary px-3 py-2 text-center text-sm text-primary-foreground">
                            {{ plan.announcement || 'الدفع عند الاستلام' }}
                        </p>
                    </div>

                    <div v-if="plan.trust_items.length">
                        <p class="text-xs text-muted-foreground">مميزات متجرك</p>
                        <div class="mt-1 grid gap-2 sm:grid-cols-2">
                            <div
                                v-for="item in plan.trust_items"
                                :key="item.title"
                                class="flex items-start gap-2 rounded-lg border border-border px-3 py-2 text-sm"
                            >
                                <component :is="ICONS[item.icon] ?? Check" class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                                <span>
                                    <strong>{{ item.title }}</strong>
                                    <span class="block text-xs text-muted-foreground">{{ item.body }}</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div v-if="plan.story.body">
                        <p class="text-xs text-muted-foreground">عن المتجر</p>
                        <p class="mt-1 font-semibold">{{ plan.story.heading }}</p>
                        <!-- Sanitised server-side with the storefront's own allow-list. -->
                        <div class="space-y-2 text-sm leading-relaxed" v-html="plan.story.body" />
                    </div>

                    <div v-if="plan.faq.length">
                        <p class="text-xs text-muted-foreground">أسئلة وأجوبة</p>
                        <dl class="mt-1 divide-y divide-border rounded-lg border border-border">
                            <div v-for="item in plan.faq" :key="item.question" class="px-3 py-2 text-sm">
                                <dt class="font-medium">{{ item.question }}</dt>
                                <dd class="text-muted-foreground">{{ item.answer }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div v-if="plan.about">
                        <p class="text-xs text-muted-foreground">آخر الصفحة</p>
                        <p class="mt-1 text-sm">{{ plan.about }}</p>
                    </div>
                </section>

                <!-- What happens on the click, said before the click -->
                <section class="surface space-y-4 p-6">
                    <ul class="list-disc space-y-1.5 pr-5 text-sm text-muted-foreground">
                        <li>
                            الصفحة الرئيسية وأعلى وأسفل الصفحة هيتحطوا <strong class="text-foreground">مسودة</strong> في تصميم المتجر. الزبون مش
                            هيشوفهم غير لما تدوس «نشر» في كل صفحة.
                        </li>
                        <li>لو عندك تعديلات مش منشورة في الصفحات دي، هيتكتب مكانها.</li>
                        <li>تقدر تعدّل أي كلمة بعد كده من تصميم المتجر.</li>
                    </ul>

                    <p v-if="applyError" class="field-error mt-0">{{ applyError }}</p>

                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" class="btn-primary" :disabled="applying" @click="apply">
                            <LoaderCircle v-if="applying" class="h-4 w-4 animate-spin" />
                            حطّه في المتجر كمسودة
                        </button>
                        <button type="button" class="btn-outline" :disabled="applying" @click="ask">
                            <RotateCcw class="h-4 w-4" />
                            اكتب تاني
                        </button>
                        <button type="button" class="btn-ghost" :disabled="applying" @click="state = 'ask'">رجوع للأسئلة</button>
                    </div>
                </section>
            </template>

            <Link href="/builder" class="btn-ghost">رجوع لتصميم المتجر</Link>
        </div>
    </AppLayout>
</template>
