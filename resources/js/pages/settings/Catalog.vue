<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, Check, Copy, ExternalLink, Info, RefreshCw } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Issue {
    level: 'error' | 'warning';
    text: string;
    action: { label: string; url: string };
}

const props = defineProps<{
    feeds: { meta: string; google: string; tiktok: string };
    readiness: { total: number; ready: number; issues: Issue[] };
}>();

const breadcrumbItems: BreadcrumbItem[] = [{ title: 'الكاتالوج', href: '/settings/catalog' }];

const copied = ref<string | null>(null);

const copy = async (value: string, key: string) => {
    await navigator.clipboard.writeText(value);
    copied.value = key;
    setTimeout(() => (copied.value = null), 1600);
};

/*
 * Meta leads because that is where this market's ad money goes. The other two
 * read the same feed, so they are secondary cards rather than equals — giving
 * three platforms identical weight would hide which one to start with.
 */
const meta = {
    key: 'meta' as const,
    name: 'فيسبوك وإنستجرام',
    subtitle: 'كاتالوج ميتا — وده اللي بيشغّل إعلانات الكتالوج الديناميكية',
    steps: [
        'افتح <b>Commerce Manager</b> من business.facebook.com',
        'اضغط <b>Add Catalog</b> → اختار <b>E-commerce</b> → <b>Upload Product Info</b>',
        'من داخل الكاتالوج: <b>Data Sources</b> → <b>Add Items</b> → <b>Scheduled Feed</b>',
        'الزق اللينك في خانة الرابط',
        'اختار التحديث <b>Hourly</b> — عشان الأسعار والمخزون يفضلوا مظبوطين',
        'اضغط <b>Start Upload</b> — الفحص الأول بياخد ١٠ لـ ٣٠ دقيقة',
        'آخر خطوة: <b>Settings</b> → <b>Connect Pixel</b> واربط البيكسل بالكاتالوج',
    ],
    note: 'من غير الخطوة الأخيرة الكاتالوج هيشتغل، بس إعلانات الكتالوج الديناميكية مش هتعرف مين شاف أنهي منتج.',
    docs: 'https://business.facebook.com/commerce',
};

const others = [
    {
        key: 'google' as const,
        name: 'Google Shopping',
        subtitle: 'منتجاتك في نتايج تسوّق جوجل',
        steps: [
            'اعمل حساب على <b>Google Merchant Center</b>',
            'وثّق ملكية الدومين — جوجل بيطلب ده قبل أي منتج',
            '<b>Products</b> → <b>Feeds</b> → علامة <b>+</b>',
            'اختار <b>Scheduled fetch</b> والزق اللينك',
            'حدّد البلد <b>مصر</b> واللغة <b>العربية</b>',
        ],
        note: 'جوجل بيطلب سياسة شحن واسترجاع مكتوبة على المتجر قبل الموافقة.',
        docs: 'https://merchants.google.com',
    },
    {
        key: 'tiktok' as const,
        name: 'تيك توك',
        subtitle: 'كاتالوج تيك توك للتسوّق',
        steps: [
            'من <b>TikTok Ads Manager</b>: <b>Assets</b> → <b>Catalogs</b>',
            'اعمل كاتالوج جديد واختار <b>مصر</b> و<b>EGP</b>',
            '<b>Add products</b> → <b>Scheduled feed</b> والزق اللينك',
            'اربط الكاتالوج بالبيكسل من <b>Catalog settings</b>',
        ],
        note: null,
        docs: 'https://ads.tiktok.com',
    },
];

const blockers = computed(() => props.readiness.issues.filter((i) => i.level === 'error'));
const percent = computed(() =>
    props.readiness.total ? Math.round((props.readiness.ready / props.readiness.total) * 100) : 0,
);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="الكاتالوج" />

        <SettingsLayout width="wide">
            <div class="space-y-5">
                <!-- ── Header + readiness on one row ───────────────────────
                     The status belongs beside the title, not stacked under it:
                     "is my catalogue publishable" is the question the page
                     exists to answer. -->
                <div class="grid gap-5 lg:grid-cols-[1fr_20rem]">
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight">كاتالوج المنتجات</h1>
                        <p class="mt-2 max-w-xl text-sm leading-relaxed text-muted-foreground">
                            ارفع منتجاتك مرة واحدة هنا، وفيسبوك وجوجل وتيك توك بيسحبوها لوحدهم.
                            كل ما تغيّر سعر أو مخزون، الكاتالوج بيتحدّث معاك — مش محتاج ترفع تاني.
                        </p>

                        <p class="mt-3 flex items-center gap-2 text-xs text-muted-foreground">
                            <RefreshCw class="size-3.5" />
                            المنصات بتسحب اللينك لوحدها كل ساعة
                        </p>
                    </div>

                    <div class="surface p-5" :class="{ 'border-destructive/30': blockers.length }">
                        <div class="flex items-baseline justify-between">
                            <p class="text-sm font-medium">جاهز للنشر</p>
                            <p class="tabular text-2xl font-bold" :class="blockers.length ? 'text-destructive' : 'text-success'">
                                {{ readiness.ready }}<span class="text-sm text-muted-foreground">/{{ readiness.total }}</span>
                            </p>
                        </div>

                        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-muted">
                            <div class="h-full rounded-full transition-all"
                                 :class="blockers.length ? 'bg-destructive' : 'bg-success'"
                                 :style="{ width: `${percent}%` }" />
                        </div>

                        <div v-if="readiness.issues.length" class="mt-4 space-y-2">
                            <div v-for="(issue, i) in readiness.issues" :key="i" class="text-xs">
                                <p class="flex items-start gap-1.5"
                                   :class="issue.level === 'error' ? 'text-destructive' : 'text-warning'">
                                    <AlertTriangle class="mt-0.5 size-3.5 shrink-0" />
                                    <span class="leading-relaxed">{{ issue.text }}</span>
                                </p>
                                <a :href="issue.action.url"
                                   class="mt-1 inline-flex items-center gap-1 pr-5 font-medium text-primary hover:underline">
                                    {{ issue.action.label }}
                                    <ArrowLeft class="size-3" />
                                </a>
                            </div>
                        </div>

                        <p v-else class="mt-4 flex items-center gap-1.5 text-xs text-success">
                            <Check class="size-3.5" />
                            كل منتجاتك جاهزة للنشر.
                        </p>
                    </div>
                </div>

                <!-- ── Meta ────────────────────────────────────────────────
                     Wide card, steps in two columns: seven numbered lines in a
                     single narrow stack is a page a merchant scrolls past. -->
                <section class="surface-lux p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-lg font-semibold">{{ meta.name }}</h2>
                                <span class="badge-gold">ابدأ من هنا</span>
                            </div>
                            <p class="mt-1 text-sm text-muted-foreground">{{ meta.subtitle }}</p>
                        </div>

                        <a :href="meta.docs" target="_blank" rel="noopener" class="btn-ghost shrink-0 px-3 py-1.5 text-xs">
                            <ExternalLink class="size-3.5" />
                            افتح المنصة
                        </a>
                    </div>

                    <div class="mt-4 flex gap-2">
                        <input :value="feeds.meta" readonly dir="ltr"
                               class="field flex-1 font-mono text-xs"
                               @focus="($event.target as HTMLInputElement).select()" />
                        <button class="btn-primary shrink-0" @click="copy(feeds.meta, 'meta')">
                            <Check v-if="copied === 'meta'" class="size-4" />
                            <Copy v-else class="size-4" />
                            {{ copied === 'meta' ? 'اتنسخ' : 'انسخ اللينك' }}
                        </button>
                    </div>

                    <ol class="mt-6 grid gap-x-8 gap-y-3.5 md:grid-cols-2">
                        <li v-for="(step, i) in meta.steps" :key="i" class="flex gap-3 text-sm">
                            <span class="tabular flex size-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">
                                {{ i + 1 }}
                            </span>
                            <!-- Button labels stay in English inside <b>: that
                                 is what the merchant is hunting for on a screen
                                 that is not in Arabic. -->
                            <span class="pt-0.5 leading-relaxed" v-html="step"></span>
                        </li>
                    </ol>

                    <p class="mt-5 flex items-start gap-2 rounded-xl bg-muted/60 p-3 text-xs leading-relaxed text-muted-foreground">
                        <Info class="mt-0.5 size-3.5 shrink-0" />
                        {{ meta.note }}
                    </p>
                </section>

                <!-- ── Google & TikTok, side by side ───────────────────── -->
                <div class="grid gap-5 md:grid-cols-2">
                    <section v-for="platform in others" :key="platform.key" class="surface flex flex-col p-6">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 class="font-semibold">{{ platform.name }}</h2>
                                <p class="mt-1 text-sm text-muted-foreground">{{ platform.subtitle }}</p>
                            </div>
                            <a :href="platform.docs" target="_blank" rel="noopener" class="btn-ghost shrink-0 px-2 py-1.5">
                                <ExternalLink class="size-3.5" />
                            </a>
                        </div>

                        <div class="mt-4 flex gap-2">
                            <input :value="feeds[platform.key]" readonly dir="ltr"
                                   class="field flex-1 font-mono text-[11px]"
                                   @focus="($event.target as HTMLInputElement).select()" />
                            <button class="btn-outline shrink-0 px-3" @click="copy(feeds[platform.key], platform.key)">
                                <Check v-if="copied === platform.key" class="size-4 text-success" />
                                <Copy v-else class="size-4" />
                            </button>
                        </div>

                        <ol class="mt-5 space-y-3">
                            <li v-for="(step, i) in platform.steps" :key="i" class="flex gap-2.5 text-sm">
                                <span class="tabular flex size-5 shrink-0 items-center justify-center rounded-full bg-muted text-[11px] font-bold">
                                    {{ i + 1 }}
                                </span>
                                <span class="pt-px leading-relaxed" v-html="step"></span>
                            </li>
                        </ol>

                        <p v-if="platform.note"
                           class="mt-auto flex items-start gap-2 pt-5 text-xs leading-relaxed text-muted-foreground">
                            <Info class="mt-0.5 size-3.5 shrink-0" />
                            {{ platform.note }}
                        </p>
                    </section>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
