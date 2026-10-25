<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ExternalLink, LoaderCircle, Monitor, Package, PencilLine, RotateCcw, Smartphone, Sparkles, TriangleAlert } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/*
 * The AI's store plan, shown as the store.
 *
 * The real storefront in an iframe — the drafted pages, the starter products
 * with their photos, in the theme the plan chose — through the builder's own
 * preview token, so it is exactly what نشر will put live and nothing a
 * customer can see yet. Words on a review screen ask the merchant to imagine
 * the shop; this shows it.
 */
const props = defineProps<{
    generationId: string;
    storeName: string;
    storeUrl: string;
    previewUrl: string;
    theme: { key: string; name: string; reason: string };
    headsUp: string;
    productCount: number;
    published: boolean;
    triesLeft: number;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'جهّزلي المتجر', href: '/ai/store-builder' },
    { title: 'معاينة متجرك', href: '#' },
];

const device = ref<'mobile' | 'desktop'>('mobile');
const loaded = ref(false);
const publishing = ref(false);

const triesLabel = computed(() =>
    props.triesLeft === 1 ? 'باقي محاولة واحدة' : props.triesLeft === 2 ? 'باقي محاولتين' : `باقي ${props.triesLeft} محاولات`,
);

const publish = () => {
    publishing.value = true;
    router.post(`/ai/store-plans/${props.generationId}/publish`, {}, {
        preserveScroll: true,
        onFinish: () => (publishing.value = false),
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="معاينة متجرك" />

        <div class="mx-auto w-full max-w-7xl space-y-5 px-4 pb-10 md:px-6">
            <!-- ── What this is, and the one decision on the page ───────── -->
            <section class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="min-w-0">
                    <p class="inline-flex items-center gap-1.5 text-[13px] font-semibold">
                        <Sparkles class="size-4 text-primary" />
                        <span class="ai-text">جهّزه الذكاء الاصطناعي</span>
                    </p>
                    <h1 class="text-large-title mt-1">
                        <template v-if="published">{{ storeName }} اتنشر 🎉</template>
                        <template v-else>ده متجرك</template>
                    </h1>
                    <p class="mt-1 max-w-2xl text-[15px] leading-relaxed text-muted-foreground">
                        بشكل <span class="font-semibold text-foreground">{{ theme.name }}</span><template v-if="theme.reason"> — {{ theme.reason }}</template>
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <a v-if="published" :href="storeUrl" target="_blank" rel="noopener" class="ios-btn-lg ios-btn-filled pressable">
                        <ExternalLink class="size-[18px]" />
                        افتح متجرك
                    </a>
                    <button v-else type="button" class="ios-btn-lg ios-btn-filled pressable" :disabled="publishing" @click="publish">
                        <LoaderCircle v-if="publishing" class="size-[18px] animate-spin" />
                        <Sparkles v-else class="size-[18px]" />
                        انشر المتجر
                    </button>
                    <Link href="/builder/home" class="ios-btn-lg ios-btn-gray pressable">
                        <PencilLine class="size-[18px]" />
                        عدّل بنفسك
                    </Link>
                    <Link v-if="!published && triesLeft > 0" href="/ai/store-builder" class="ios-btn-lg ios-btn-gray pressable">
                        <RotateCcw class="size-[18px]" />
                        اكتب تاني
                        <span class="text-[13px] font-normal text-muted-foreground">({{ triesLabel }})</span>
                    </Link>
                </div>
            </section>

            <!-- ── Notes worth reading before نشر ──────────────────────── -->
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div v-if="productCount" class="surface flex items-start gap-3 p-4 text-[14px] leading-relaxed">
                    <span class="ios-tile bg-primary text-primary-foreground"><Package class="size-[18px]" /></span>
                    <p class="min-w-0 text-muted-foreground">
                        <span class="font-semibold text-foreground">{{ productCount }} منتجات مثال بصور حقيقية</span>
                        عشان تشوف شكل متجرك مليان. محفوظة مسودة، ومش هتظهر للزبون غير لما تحط صورتك وسعرك وتفعّلها من
                        <Link href="/products" class="font-medium text-primary hover:underline">المنتجات</Link>.
                    </p>
                </div>
                <div v-if="headsUp" class="surface flex items-start gap-3 p-4 text-[14px] leading-relaxed">
                    <span class="ios-tile bg-warning text-white"><TriangleAlert class="size-[18px]" /></span>
                    <p class="min-w-0 text-muted-foreground"><span class="font-semibold text-foreground">قبل ما تنشر:</span> {{ headsUp }}</p>
                </div>
            </div>

            <!-- ── The store ────────────────────────────────────────────── -->
            <div class="flex justify-center">
                <div class="ios-segmented" role="group" aria-label="حجم المعاينة">
                    <button type="button" class="ios-segment gap-1.5 px-4" :aria-pressed="device === 'mobile'" @click="device = 'mobile'">
                        <Smartphone class="size-4" /> موبايل
                    </button>
                    <button type="button" class="ios-segment gap-1.5 px-4" :aria-pressed="device === 'desktop'" @click="device = 'desktop'">
                        <Monitor class="size-4" /> كمبيوتر
                    </button>
                </div>
            </div>

            <div class="flex justify-center">
                <!-- A phone, because that is where nearly every customer will
                     see it first: an Instagram or TikTok ad, on a phone. -->
                <div
                    class="relative overflow-hidden bg-card transition-all duration-500 ease-[var(--ease-ios)]"
                    :class="
                        device === 'mobile'
                            ? 'h-[760px] w-[390px] max-w-full rounded-[46px] shadow-[0_0_0_10px_hsl(var(--foreground)/0.9),0_30px_80px_-20px_hsl(var(--foreground)/0.45)]'
                            : 'h-[760px] w-full rounded-2xl shadow-[0_0_0_1px_hsl(var(--foreground)/0.08),0_30px_80px_-30px_hsl(var(--foreground)/0.35)]'
                    "
                >
                    <div v-if="device === 'desktop'" class="flex h-9 items-center gap-1.5 border-b border-border bg-muted/60 px-4" dir="ltr">
                        <span class="size-2.5 rounded-full bg-[#ff5f57]" /><span class="size-2.5 rounded-full bg-[#febc2e]" /><span class="size-2.5 rounded-full bg-[#28c840]" />
                        <span class="mx-auto truncate rounded-md bg-card px-3 py-0.5 text-[12px] text-muted-foreground">{{ storeUrl.replace(/^https?:\/\//, '') }}</span>
                    </div>

                    <div v-if="!loaded" class="ai-shimmer absolute inset-0" :class="device === 'desktop' ? 'top-9' : ''" />

                    <iframe
                        :src="previewUrl"
                        :title="`معاينة ${storeName}`"
                        class="block w-full border-0 bg-background"
                        :class="device === 'desktop' ? 'h-[calc(100%-2.25rem)]' : 'h-full'"
                        @load="loaded = true"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
