<script setup lang="ts">
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
    palette: Record<string, string>;
    /** A live showroom store on this theme, opened in a new tab. */
    preview_url: string | null;
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

// The palette is stored as raw `H S% L%` so Tailwind can compose it with
// opacity; a swatch needs it wrapped back into a colour.
const hsl = (value: string) => `hsl(${value})`;
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="الثيمات" />

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
                <div v-if="active" class="surface-lux flex flex-wrap items-center gap-4 p-5">
                    <div class="flex gap-1.5">
                        <span v-for="token in ['primary', 'accent', 'background']" :key="token"
                              class="size-8 rounded-full border border-border"
                              :style="{ background: hsl(active.palette[token]) }" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold">{{ active.name }}</span>
                            <span class="badge-success gap-1">
                                <Check class="size-3" />
                                مفعّل دلوقتي
                            </span>
                        </div>
                        <p class="mt-0.5 text-sm text-muted-foreground">{{ active.description }}</p>
                    </div>

                    <a v-if="active.preview_url" :href="active.preview_url" target="_blank" rel="noopener"
                       class="btn-ghost shrink-0">
                        <Eye class="size-4" />
                        شوف مثال
                    </a>
                </div>

                <p class="text-sm font-medium text-muted-foreground">ثيمات تانية</p>

                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <!-- A div, not a button: the card holds its own actions,
                         and interactive elements cannot nest inside a button. -->
                    <div
                        v-for="theme in others"
                        :key="theme.key"
                        class="surface overflow-hidden p-0 text-right transition-all hover:shadow-e2"
                    >
                        <!-- A miniature of the real storefront, painted with the
                             theme's own tokens: the swatch row alone tells a
                             merchant almost nothing about the result. -->
                        <div
                            class="p-4"
                            :style="{
                                background: hsl(theme.palette.background),
                                color: hsl(theme.palette.foreground),
                            }"
                        >
                            <div class="flex items-center gap-2">
                                <span
                                    class="flex size-6 items-center justify-center text-[10px] font-bold"
                                    :style="{
                                        background: hsl(theme.palette.primary),
                                        color: hsl(theme.palette['primary-foreground']),
                                        borderRadius: theme.radius,
                                    }"
                                >م</span>
                                <span class="text-xs font-semibold">متجرك</span>
                            </div>

                            <div class="mt-3 grid grid-cols-3 gap-2">
                                <div
                                    v-for="n in 3"
                                    :key="n"
                                    class="overflow-hidden border"
                                    :style="{
                                        borderColor: hsl(theme.palette.border),
                                        borderRadius: theme.radius,
                                        background: hsl(theme.palette.card),
                                    }"
                                >
                                    <div class="aspect-square" :style="{ background: hsl(theme.palette.muted) }" />
                                    <div class="p-1.5">
                                        <div class="h-1 w-3/4 rounded-full" :style="{ background: hsl(theme.palette['muted-foreground']), opacity: 0.4 }" />
                                        <div class="mt-1 h-1.5 w-1/2 rounded-full" :style="{ background: hsl(theme.palette.primary) }" />
                                    </div>
                                </div>
                            </div>

                            <div
                                class="mt-3 flex h-6 items-center justify-center text-[10px] font-medium"
                                :style="{
                                    background: hsl(theme.palette.primary),
                                    color: hsl(theme.palette['primary-foreground']),
                                    borderRadius: theme.radius,
                                }"
                            >اطلب دلوقتي</div>
                        </div>

                        <div class="border-t border-border p-4">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-semibold">{{ theme.name }}</span>
                                <LoaderCircle v-if="saving === theme.key" class="size-4 animate-spin text-primary" />
                            </div>

                            <p class="mt-1.5 text-sm text-muted-foreground">{{ theme.description }}</p>

                            <div class="mt-3 flex flex-wrap gap-1.5">
                                <span v-for="tag in theme.tags" :key="tag" class="badge-neutral">{{ tag }}</span>
                            </div>

                            <div class="mt-4 flex items-center gap-2">
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
