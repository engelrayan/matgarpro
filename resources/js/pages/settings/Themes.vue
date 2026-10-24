<script setup lang="ts">
import ThemePreview, { type PreviewProduct } from '@/components/ThemePreview.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Check, ExternalLink, Eye, LoaderCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Theme {
    key: string;
    name: string;
    description: string;
    tags: string[];
    radius: string;
    layout: string;
    header: string;
    card: string;
    palette: Record<string, string>;
    /** A live showroom store on this theme, opened in a new tab. */
    preview_url: string | null;
    /** Three products from that showroom, drawn in the card's preview. */
    products: PreviewProduct[];
    font_family: string;
    font_url: string;
}

const props = defineProps<{
    themes: Theme[];
    current: string;
    storeUrl: string;
}>();

const breadcrumbItems: BreadcrumbItem[] = [{ title: 'الثيمات', href: '/settings/themes' }];

const selected = ref(props.current);
const saving = ref<string | null>(null);

const apply = (key: string) => {
    if (key === selected.value) return;

    saving.value = key;
    router.put(
        route('themes.update'),
        { theme: key },
        {
            preserveScroll: true,
            onSuccess: () => (selected.value = key),
            onFinish: () => (saving.value = null),
        },
    );
};

const active = computed(() => props.themes.find((t) => t.key === selected.value));
const others = computed(() => props.themes.filter((t) => t.key !== selected.value));

// Each preview is set in its theme's own typeface — the font is half of what
// tells one shop from another. Several themes share a font, so link each once.
const fontUrls = computed(() => [...new Set(props.themes.map((t) => t.font_url))]);

// The palette is stored as raw `H S% L%` so Tailwind can compose it with
// opacity; a swatch needs it wrapped back into a colour.
const hsl = (value: string) => `hsl(${value})`;
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="الثيمات">
            <link v-for="url in fontUrls" :key="url" rel="stylesheet" :href="url" />
        </Head>

        <SettingsLayout width="wide">
            <div class="space-y-6">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight">ثيم المتجر</h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            اختار الشكل اللي يناسب منتجاتك. التغيير بيظهر على المتجر فورًا.
                        </p>
                    </div>

                    <a :href="storeUrl" target="_blank" rel="noopener" class="btn-outline shrink-0">
                        <ExternalLink class="size-4" />
                        افتح متجرك
                    </a>
                </div>

                <!-- The theme in use, pulled out of the grid: a merchant opening
                     this page is asking "what am I on now?" before "what else is
                     there?", and hunting for a tick across six cards is work. -->
                <div v-if="active" class="surface-lux grid overflow-hidden p-0 md:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)]">
                    <ThemePreview
                        :palette="active.palette"
                        :radius="active.radius"
                        :header="active.header"
                        :card="active.card"
                        :font-family="active.font_family"
                        :products="active.products"
                        size="lg"
                        class="border-b border-border md:border-b-0 md:border-e"
                    />

                    <div class="flex flex-col justify-center gap-4 p-5 md:p-6">
                        <div>
                            <span class="badge-success gap-1">
                                <Check class="size-3" />
                                مفعّل دلوقتي
                            </span>
                            <p class="mt-3 text-xl font-bold tracking-tight">{{ active.name }}</p>
                            <p class="mt-1 text-sm text-muted-foreground">{{ active.description }}</p>
                        </div>

                        <div class="flex flex-wrap items-center gap-1.5">
                            <span v-for="token in ['primary', 'accent', 'background']" :key="token"
                                  class="size-6 rounded-full border border-border"
                                  :style="{ background: hsl(active.palette[token]) }" />
                            <span v-for="tag in active.tags" :key="tag" class="badge-neutral ms-1">{{ tag }}</span>
                        </div>

                        <a v-if="active.preview_url" :href="active.preview_url" target="_blank" rel="noopener"
                           class="btn-outline self-start">
                            <Eye class="size-4" />
                            شوف مثال كامل
                        </a>
                    </div>
                </div>

                <p class="text-sm font-medium text-muted-foreground">ثيمات تانية</p>

                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <!-- A div, not a button: the card holds its own actions,
                         and interactive elements cannot nest inside a button. -->
                    <div
                        v-for="theme in others"
                        :key="theme.key"
                        class="surface flex flex-col overflow-hidden p-0 text-right transition-all hover:shadow-e2"
                    >
                        <!-- A miniature of the real storefront in the theme's own
                             tokens, typeface and card shape, over real products:
                             a swatch row alone tells a merchant almost nothing
                             about the result. -->
                        <ThemePreview
                            :palette="theme.palette"
                            :radius="theme.radius"
                            :header="theme.header"
                            :card="theme.card"
                            :font-family="theme.font_family"
                            :products="theme.products"
                        />

                        <div class="flex flex-1 flex-col border-t border-border p-4">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-semibold">{{ theme.name }}</span>
                                <LoaderCircle v-if="saving === theme.key" class="size-4 animate-spin text-primary" />
                            </div>

                            <p class="mt-1.5 text-sm text-muted-foreground">{{ theme.description }}</p>

                            <div class="mt-3 flex flex-wrap gap-1.5">
                                <span v-for="tag in theme.tags" :key="tag" class="badge-neutral">{{ tag }}</span>
                            </div>

                            <!-- mt-auto: cards in a row end level, whatever
                                 the length of the text above. -->
                            <div class="mt-auto flex items-center gap-2 pt-4">
                                <!-- Preview first. Activating changes what live
                                     customers see, so the safe action is the one
                                     that should fall under the thumb. -->
                                <a
                                    v-if="theme.preview_url"
                                    :href="theme.preview_url"
                                    target="_blank"
                                    rel="noopener"
                                    class="btn-outline flex-1"
                                >
                                    <Eye class="size-4" />
                                    معاينة
                                </a>

                                <button
                                    type="button"
                                    class="btn-primary flex-1"
                                    :disabled="saving !== null"
                                    @click="apply(theme.key)"
                                >
                                    تفعيل
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
