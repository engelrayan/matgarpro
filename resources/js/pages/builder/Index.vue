<script setup lang="ts">
import BuilderField from '@/components/builder/BuilderField.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlignRight, ArrowRight, Check, Copyright, Eye, EyeOff, Grid3x3, GripVertical,
    Heading, Image, LayoutGrid, Loader2, Megaphone, MessageCircleQuestion, Monitor, PanelBottom,
    PanelTop, Plus, Presentation, Quote, RotateCcw, Share2, ShieldCheck, ShoppingCart, Shuffle,
    Smartphone, Sparkles, Star, Tablet, Tag, Timer, Trash2, Type, Undo2, Youtube,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

/*
 * The store builder.
 *
 * Three panes and one rule: the middle one is the real storefront in an iframe,
 * not a mock-up. A builder that draws its own approximation of the page is a
 * builder that lies — the merchant arranges something beautiful and then finds
 * out what their shop actually looks like from a customer.
 *
 * Everything typed here is a draft. `published_sections` is only written by the
 * نشر button, so a merchant can rearrange their whole home page during a
 * campaign without a single customer seeing a half-finished layout.
 */

interface Section {
    id: string;
    type: string;
    visible: boolean;
    settings: Record<string, unknown>;
}

interface Definition {
    type: string;
    name: string;
    description: string;
    icon: string;
    limit: number | null;
    locked?: boolean;
    fields: {
        key: string;
        type: string;
        label: string;
        default?: unknown;
        when?: Record<string, unknown>;
    }[];
}

const props = defineProps<{
    page: string;
    pageLabel: string;
    pages: { key: string; label: string; published: string | null; dirty: boolean }[];
    sections: Section[];
    catalogue: Definition[];
    previewUrl: string;
    storeUrl: string;
    currency: string;
    categories: { id: number; name: string }[];
}>();

// Only the icons the registry actually names. A wildcard import of
// lucide-vue-next would defeat tree-shaking and pull the whole set into the
// bundle for the sake of twenty glyphs.
const ICONS: Record<string, unknown> = {
    Presentation, ShieldCheck, Tag, LayoutGrid, Star, Grid3x3, Image, Type,
    MessageCircleQuestion, Youtube, Quote, Timer, Sparkles, ShoppingCart,
    AlignRight, Shuffle, Heading, Megaphone, PanelTop, PanelBottom, Share2, Copyright,
};

/*
 * A deep copy via JSON, not `structuredClone`.
 *
 * Vue hands props over as reactive proxies, and `structuredClone` refuses to
 * clone a Proxy — it throws DataCloneError before the page has finished
 * mounting. JSON is the right tool here anyway: a section list is exactly what
 * comes out of a JSON column and goes back into one, so there is nothing in it
 * that JSON cannot carry.
 */
const clone = <T,>(value: T): T => JSON.parse(JSON.stringify(value ?? null));

const sections = ref<Section[]>(clone(props.sections));
const selectedId = ref<string | null>(sections.value[0]?.id ?? null);
const showCatalogue = ref(false);
const saving = ref(false);
const savedAt = ref<string | null>(null);
const device = ref<'desktop' | 'tablet' | 'mobile'>('desktop');
const frame = ref<HTMLIFrameElement | null>(null);
const frameKey = ref(0);

const definitionFor = (type: string) => props.catalogue.find((d) => d.type === type);
const selected = computed(() => sections.value.find((s) => s.id === selectedId.value) ?? null);
const selectedDefinition = computed(() => (selected.value ? definitionFor(selected.value.type) : null));

/**
 * A field with a `when` clause is only shown while its condition holds — the
 * hero's slide list is meaningless when the source is "من المنتجات", and a
 * panel full of controls that do nothing is how a merchant stops trusting the
 * ones that do.
 */
const visibleFields = computed(() => {
    if (!selectedDefinition.value || !selected.value) return [];

    return selectedDefinition.value.fields.filter((field) => {
        if (!field.when) return true;
        return Object.entries(field.when).every(([key, value]) => selected.value!.settings[key] === value);
    });
});

// How many of each type are already placed, so the catalogue can grey out the
// ones at their ceiling instead of letting the server silently drop them.
const countOf = (type: string) => sections.value.filter((s) => s.type === type).length;
const atLimit = (definition: Definition) =>
    definition.limit !== null && countOf(definition.type) >= definition.limit;

// ── Autosave ─────────────────────────────────────────────────────────────
let timer: ReturnType<typeof setTimeout>;

watch(
    sections,
    () => {
        clearTimeout(timer);
        saving.value = true;

        timer = setTimeout(() => {
            router.put(
                `/builder/${props.page}`,
                { sections: sections.value },
                {
                    preserveState: true,
                    preserveScroll: true,
                    // Nothing on this screen is rendered from the response —
                    // the draft is already in `sections`, and re-reading it
                    // would fight whatever the merchant typed while it was in
                    // flight.
                    only: [],
                    onSuccess: () => {
                        savedAt.value = new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
                        reloadPreview();
                    },
                    onFinish: () => (saving.value = false),
                },
            );
        }, 600);
    },
    { deep: true },
);

// ── Preview bridge ───────────────────────────────────────────────────────
const previewSrc = computed(() => `${props.previewUrl}&v=${frameKey.value}`);

// Cross-origin: the frame's own location is unreachable from here, so a reload
// is a new `src`. The bridge script inside restores the scroll position, which
// is what makes a reload-per-keystroke tolerable.
const reloadPreview = () => frameKey.value++;

const onMessage = (event: MessageEvent) => {
    if (event.data?.source !== 'matgar-preview') return;
    if (event.data.type === 'select') selectedId.value = event.data.id;
};

watch(selectedId, (id) => {
    frame.value?.contentWindow?.postMessage(
        { source: 'matgar-builder', type: 'select', id },
        new URL(props.storeUrl).origin,
    );
});

onMounted(() => window.addEventListener('message', onMessage));
onUnmounted(() => {
    window.removeEventListener('message', onMessage);
    clearTimeout(timer);
});

// ── Section operations ───────────────────────────────────────────────────
const randomId = () => Math.random().toString(36).slice(2, 14);

const addSection = (definition: Definition) => {
    if (atLimit(definition)) return;

    const settings = Object.fromEntries(definition.fields.map((f) => [f.key, clone(f.default ?? null)]));
    const section: Section = { id: randomId(), type: definition.type, visible: true, settings };

    sections.value = [...sections.value, section];
    selectedId.value = section.id;
    showCatalogue.value = false;
};

const removeSection = (section: Section) => {
    if (definitionFor(section.type)?.locked) return;
    sections.value = sections.value.filter((s) => s.id !== section.id);
    if (selectedId.value === section.id) selectedId.value = sections.value[0]?.id ?? null;
};

const toggleVisible = (section: Section) => {
    if (definitionFor(section.type)?.locked) return;
    section.visible = !section.visible;
};

// ── Drag to reorder ──────────────────────────────────────────────────────
const dragIndex = ref<number | null>(null);
const overIndex = ref<number | null>(null);

const onDrop = () => {
    if (dragIndex.value === null || overIndex.value === null || dragIndex.value === overIndex.value) {
        dragIndex.value = overIndex.value = null;
        return;
    }

    const next = [...sections.value];
    const [moved] = next.splice(dragIndex.value, 1);
    next.splice(overIndex.value, 0, moved);

    sections.value = next;
    dragIndex.value = overIndex.value = null;
};

// ── Publish ──────────────────────────────────────────────────────────────
const publishing = ref(false);

const publish = () =>
    router.post(`/builder/${props.page}/publish`, {}, {
        preserveScroll: true,
        onStart: () => (publishing.value = true),
        onFinish: () => (publishing.value = false),
    });

const discard = () => {
    if (!confirm('هترجع لآخر نسخة منشورة وتلغي كل اللي عملته. تمام؟')) return;
    router.post(`/builder/${props.page}/discard`, {}, { onSuccess: () => location.reload() });
};

const resetToDefaults = () => {
    if (!confirm('هيرجع ترتيب المنصة الافتراضي. لسه هتحتاج تدوس نشر عشان ينزل للزباين.')) return;
    router.post(`/builder/${props.page}/reset`, {}, { onSuccess: () => location.reload() });
};

const FRAME_WIDTH = { desktop: '100%', tablet: '820px', mobile: '390px' };
</script>

<template>
    <Head :title="`تصميم ${pageLabel}`" />

    <div class="flex h-screen flex-col bg-muted/40" dir="rtl">
        <!-- ── Top bar ──────────────────────────────────────────────────── -->
        <header class="flex shrink-0 items-center gap-3 border-b border-border bg-card px-4 py-2.5">
            <Link href="/dashboard" class="btn-ghost px-2.5 py-1.5" title="رجوع للوحة">
                <ArrowRight class="size-4" />
            </Link>

            <div class="flex items-center gap-1 rounded-xl bg-muted p-1">
                <Link
                    v-for="p in pages"
                    :key="p.key"
                    :href="`/builder/${p.key}`"
                    class="relative rounded-lg px-3 py-1.5 text-sm transition-colors"
                    :class="p.key === page ? 'bg-card font-medium shadow-e1' : 'text-muted-foreground hover:text-foreground'"
                >
                    {{ p.label }}
                    <!-- A page with unpublished work, marked so a merchant does
                         not leave a draft behind on a page they stopped editing. -->
                    <span v-if="p.dirty" class="absolute -left-0.5 -top-0.5 size-2 rounded-full bg-warning"></span>
                </Link>
            </div>

            <div class="mr-auto flex items-center gap-2">
                <div class="flex items-center gap-1 rounded-xl bg-muted p-1">
                    <button
                        v-for="[key, Icon] in [['desktop', Monitor], ['tablet', Tablet], ['mobile', Smartphone]] as const"
                        :key="key"
                        class="rounded-lg p-1.5 transition-colors"
                        :class="device === key ? 'bg-card shadow-e1' : 'text-muted-foreground hover:text-foreground'"
                        @click="device = key"
                    >
                        <component :is="Icon" class="size-4" />
                    </button>
                </div>

                <p class="min-w-24 text-left text-xs text-muted-foreground">
                    <span v-if="saving" class="inline-flex items-center gap-1"><Loader2 class="size-3 animate-spin" /> بيحفظ…</span>
                    <span v-else-if="savedAt" class="inline-flex items-center gap-1"><Check class="size-3" /> اتحفظ {{ savedAt }}</span>
                    <span v-else>مسودة</span>
                </p>

                <button class="btn-ghost px-2.5 py-1.5" title="ألغي تعديلاتك وارجع لآخر نسخة نشرتها" @click="discard">
                    <Undo2 class="size-4" />
                </button>
                <button class="btn-ghost px-2.5 py-1.5" title="ابدأ من الشكل الجاهز من الأول" @click="resetToDefaults">
                    <RotateCcw class="size-4" />
                </button>

                <button class="btn-primary" :disabled="publishing" @click="publish">
                    <Loader2 v-if="publishing" class="size-4 animate-spin" />
                    نشر
                </button>
            </div>
        </header>

        <div class="flex min-h-0 flex-1">
            <!-- ── Sections ─────────────────────────────────────────────── -->
            <aside class="flex w-72 shrink-0 flex-col border-l border-border bg-card">
                <div class="flex items-center justify-between border-b border-border px-4 py-3">
                    <!-- "أجزاء", never "أقسام": a category of products is an
                         قسم, and a merchant reading "أضف قسم" could not tell
                         which of the two they were about to add. -->
                    <p class="text-sm font-semibold">أجزاء {{ pageLabel }}</p>
                    <button class="btn-ghost px-2 py-1" title="أضف جزء جديد للصفحة" @click="showCatalogue = !showCatalogue">
                        <Plus class="size-4" />
                    </button>
                </div>

                <!-- Catalogue -->
                <div v-if="showCatalogue" class="max-h-72 overflow-y-auto border-b border-border bg-muted/40 p-2">
                    <button
                        v-for="definition in catalogue"
                        :key="definition.type"
                        class="flex w-full items-start gap-2.5 rounded-xl p-2.5 text-right transition-colors disabled:opacity-40 hover:bg-card"
                        :disabled="atLimit(definition)"
                        @click="addSection(definition)"
                    >
                        <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <component :is="ICONS[definition.icon] ?? Type" class="size-4" />
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-medium">{{ definition.name }}</span>
                            <span class="block text-xs leading-snug text-muted-foreground">{{ definition.description }}</span>
                        </span>
                    </button>
                </div>

                <!-- List -->
                <ul class="flex-1 overflow-y-auto p-2">
                    <li
                        v-for="(section, index) in sections"
                        :key="section.id"
                        draggable="true"
                        class="mb-1 rounded-xl border transition-colors"
                        :class="[
                            selectedId === section.id ? 'border-primary bg-primary/5' : 'border-transparent hover:bg-muted',
                            overIndex === index && dragIndex !== index ? 'border-dashed border-primary' : '',
                        ]"
                        @dragstart="dragIndex = index"
                        @dragover.prevent="overIndex = index"
                        @drop="onDrop"
                        @dragend="onDrop"
                    >
                        <div class="flex items-center gap-1 p-2">
                            <GripVertical class="size-4 shrink-0 cursor-grab text-muted-foreground" />

                            <button
                                class="min-w-0 flex-1 truncate text-right text-sm"
                                :class="section.visible ? '' : 'text-muted-foreground line-through'"
                                @click="selectedId = section.id"
                            >
                                {{ definitionFor(section.type)?.name ?? section.type }}
                            </button>

                            <!-- Locked sections keep their controls visible but
                                 inert, so it is obvious they exist and obvious
                                 why they cannot be removed. -->
                            <button
                                class="p-1 text-muted-foreground transition-colors hover:text-foreground disabled:opacity-25"
                                :disabled="definitionFor(section.type)?.locked"
                                :title="definitionFor(section.type)?.locked ? 'الجزء ده أساسي، مايتشالش' : 'اخفيه من الصفحة'"
                                @click="toggleVisible(section)"
                            >
                                <component :is="section.visible ? Eye : EyeOff" class="size-3.5" />
                            </button>

                            <button
                                class="p-1 text-muted-foreground transition-colors hover:text-destructive disabled:opacity-25"
                                :disabled="definitionFor(section.type)?.locked"
                                @click="removeSection(section)"
                            >
                                <Trash2 class="size-3.5" />
                            </button>
                        </div>
                    </li>
                </ul>
            </aside>

            <!-- ── Preview ──────────────────────────────────────────────── -->
            <main class="flex min-w-0 flex-1 justify-center overflow-auto bg-muted/60 p-4">
                <div
                    class="h-full overflow-hidden rounded-2xl border border-border bg-card shadow-e2 transition-all duration-300"
                    :style="{ width: FRAME_WIDTH[device], maxWidth: '100%' }"
                >
                    <iframe
                        :key="frameKey"
                        ref="frame"
                        :src="previewSrc"
                        class="size-full"
                        title="معاينة المتجر"
                    ></iframe>
                </div>
            </main>

            <!-- ── Settings ─────────────────────────────────────────────── -->
            <aside class="flex w-80 shrink-0 flex-col border-r border-border bg-card">
                <template v-if="selected && selectedDefinition">
                    <div class="border-b border-border px-4 py-3">
                        <p class="text-sm font-semibold">{{ selectedDefinition.name }}</p>
                        <p class="mt-0.5 text-xs leading-snug text-muted-foreground">
                            {{ selectedDefinition.description }}
                        </p>
                    </div>

                    <div class="flex-1 space-y-4 overflow-y-auto p-4">
                        <BuilderField
                            v-for="field in visibleFields"
                            :key="field.key"
                            :field="field"
                            :categories="categories"
                            :model-value="selected.settings[field.key]"
                            @update:model-value="selected.settings[field.key] = $event"
                        />

                        <p v-if="visibleFields.length === 0" class="text-sm text-muted-foreground">
                            الجزء ده مالوش إعدادات — بيعرض بيانات متجرك زي ما هي.
                        </p>
                    </div>
                </template>

                <div v-else class="flex flex-1 items-center justify-center p-6 text-center">
                    <p class="text-sm text-muted-foreground">
                        اختار جزء من القايمة اللي على اليمين، أو دوس على أي حتة في معاينة المتجر.
                    </p>
                </div>
            </aside>
        </div>
    </div>
</template>
