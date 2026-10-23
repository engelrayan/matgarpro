<script setup lang="ts">
import { AiGenerationError, useAiGeneration } from '@/composables/useAiGeneration';
import { Check, ImagePlus, LoaderCircle, RotateCcw, Sparkles, TriangleAlert } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/**
 * What the writer hands back. The shape the form already speaks, so applying
 * it is assignment — no translation layer to drift.
 */
export interface ProductDraft {
    name: string;
    /** Sanitised on the server, same allow-list as a saved description. */
    description: string | null;
    seo_title: string;
    seo_description: string;
    options: { name: string; values: string[] }[];
    category_id: number | null;
    /** One line telling the merchant what to double-check, or empty. */
    heads_up: string;
}

const props = defineProps<{
    /** The product's photos as the form shows them: saved ones and just-picked ones. */
    images: { url: string }[];
    categories: { id: number; name: string }[];
}>();

const emit = defineEmits<{
    apply: [draft: ProductDraft];
    /** Photos picked here are the product's photos too — one upload, not two. */
    addImages: [files: FileList | null];
}>();

/*
 * The writer never saves. It fills the form, and the merchant saves the form
 * like anything they typed — so a wrong guess about material or sizes is
 * caught by the one person who knows the product.
 */

const MAX_PHOTOS = 4;

const state = ref<'idle' | 'working' | 'ready' | 'applied'>('idle');
const notes = ref('');
const error = ref('');
const draft = ref<ProductDraft | null>(null);
const fileInput = ref<HTMLInputElement>();

const photos = computed(() => props.images.slice(0, MAX_PHOTOS));
const canAsk = computed(() => photos.value.length > 0 || notes.value.trim() !== '');
const categoryName = computed(() => props.categories.find((c) => c.id === draft.value?.category_id)?.name ?? null);

const { run } = useAiGeneration();

/**
 * Shrink a photo in the browser before it is uploaded.
 *
 * A phone photo is 4–8 MB; the model looks at ~1000px of it. Sending the
 * original costs the merchant a slow upload on mobile data and can trip the
 * 5 MB limit for no gain. The server shrinks again regardless — this is only
 * about the upload.
 */
const shrink = async (url: string): Promise<Blob | null> => {
    try {
        const source = await (await fetch(url)).blob();
        const bitmap = await createImageBitmap(source);
        const scale = Math.min(1, 1280 / Math.max(bitmap.width, bitmap.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);

        const context = canvas.getContext('2d');
        if (!context) return source;

        // White under transparent PNGs, which JPEG would otherwise turn black.
        context.fillStyle = '#fff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close();

        return await new Promise((resolve) => canvas.toBlob((blob) => resolve(blob ?? source), 'image/jpeg', 0.85));
    } catch {
        // A photo the browser cannot read is skipped, not fatal: the others,
        // or the merchant's notes, may be enough.
        return null;
    }
};

const ask = async () => {
    if (!canAsk.value || state.value === 'working') return;

    state.value = 'working';
    error.value = '';
    draft.value = null;

    try {
        const body = new FormData();
        body.append('notes', notes.value.trim());

        const blobs = await Promise.all(photos.value.map((photo) => shrink(photo.url)));
        blobs.forEach((blob, index) => blob && body.append('images[]', blob, `photo-${index}.jpg`));

        draft.value = (await run<ProductDraft>('/ai/product-drafts', body)).draft;
        state.value = 'ready';
    } catch (e) {
        error.value = e instanceof AiGenerationError ? e.message : 'حصلت مشكلة. جرّب تاني.';
        state.value = 'idle';
    }
};

const apply = () => {
    if (!draft.value) return;
    emit('apply', draft.value);
    state.value = 'applied';
};

const startOver = () => {
    draft.value = null;
    error.value = '';
    state.value = 'idle';
};
</script>

<template>
    <section class="surface p-6">
        <div class="flex items-start gap-3">
            <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                <Sparkles class="h-4 w-4" />
            </span>
            <div>
                <h3 class="text-base font-medium">نكتبلك بيانات المنتج؟</h3>
                <p class="text-sm text-muted-foreground">
                    ارفع صور المنتج واكتب كلمتين عنه لو حابب، وإحنا نكتبلك الاسم والوصف والمقاسات والألوان. انت بتراجع وتعدّل قبل ما تحفظ.
                </p>
            </div>
        </div>

        <!-- ── Done: one line, out of the way ─────────────────────────── -->
        <div v-if="state === 'applied'" class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-success/10 px-4 py-3">
            <p class="flex items-center gap-2 text-sm text-success">
                <Check class="h-4 w-4" />
                حطّينا الكلام في المنتج. راجعه وعدّل اللي محتاجه، وبعدين دوس حفظ.
            </p>
            <button type="button" class="btn-ghost px-3 py-1.5" @click="startOver">اكتب تاني</button>
        </div>

        <!-- ── The draft, for a look before it goes in ──────────────────── -->
        <div v-else-if="state === 'ready' && draft" class="mt-5 space-y-4">
            <p v-if="draft.heads_up" class="flex items-start gap-2 rounded-xl bg-warning/10 px-4 py-3 text-sm text-warning">
                <TriangleAlert class="mt-0.5 h-4 w-4 shrink-0" />
                <span>{{ draft.heads_up }}</span>
            </p>

            <div class="space-y-3 rounded-xl border border-border p-4">
                <p class="text-lg font-semibold">{{ draft.name }}</p>

                <!-- Sanitised server-side with the storefront's own allow-list. -->
                <div
                    v-if="draft.description"
                    class="space-y-2 text-sm leading-relaxed text-foreground/90 [&_h3]:mt-3 [&_h3]:font-semibold [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:pr-5"
                    v-html="draft.description"
                />

                <div v-if="draft.options.length || categoryName" class="flex flex-wrap gap-2 pt-1">
                    <span v-for="option in draft.options" :key="option.name" class="badge-neutral">
                        {{ option.name }}: {{ option.values.join('، ') }}
                    </span>
                    <span v-if="categoryName" class="badge-info">القسم: {{ categoryName }}</span>
                </div>

                <div v-if="draft.seo_title" class="border-t border-border pt-3 text-xs">
                    <p class="text-muted-foreground">هيظهر في جوجل كده:</p>
                    <p class="mt-1 font-medium text-info">{{ draft.seo_title }}</p>
                    <p class="text-muted-foreground">{{ draft.seo_description }}</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="btn-primary" @click="apply">
                    <Check class="h-4 w-4" />
                    حط الكلام ده في المنتج
                </button>
                <button type="button" class="btn-outline" @click="ask">
                    <RotateCcw class="h-4 w-4" />
                    اكتب تاني
                </button>
                <button type="button" class="btn-ghost" @click="startOver">إلغاء</button>
            </div>
            <p class="field-hint">هيتكتب مكان الاسم والوصف اللي فوق، ومفيش حاجة بتتحفظ غير لما تدوس حفظ.</p>
        </div>

        <!-- ── Asking ────────────────────────────────────────────────────── -->
        <div v-else class="mt-5 space-y-4">
            <div class="flex flex-wrap gap-2">
                <img
                    v-for="photo in photos"
                    :key="photo.url"
                    :src="photo.url"
                    alt=""
                    class="h-16 w-16 rounded-lg border border-border object-cover"
                />
                <button
                    type="button"
                    class="flex h-16 w-16 flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed border-border text-muted-foreground transition-colors hover:border-primary hover:text-primary"
                    :disabled="state === 'working'"
                    @click="fileInput?.click()"
                >
                    <ImagePlus class="h-4 w-4" />
                    <span class="text-[11px]">صور</span>
                </button>
                <input
                    ref="fileInput"
                    type="file"
                    accept="image/*"
                    multiple
                    class="hidden"
                    @change="emit('addImages', ($event.target as HTMLInputElement).files)"
                />
            </div>
            <p v-if="images.length > MAX_PHOTOS" class="field-hint mt-0">هنبص على أول {{ MAX_PHOTOS }} صور بس.</p>

            <div>
                <label class="field-label" for="ai-notes">
                    كلمتين عن المنتج
                    <span class="font-normal text-muted-foreground">— اختياري</span>
                </label>
                <textarea
                    id="ai-notes"
                    v-model="notes"
                    class="field"
                    rows="2"
                    maxlength="500"
                    placeholder="مثلاً: جاكيت جلد رجالي، ٣ ألوان، خامة كويسة"
                    :disabled="state === 'working'"
                />
            </div>

            <p v-if="error" class="field-error mt-0">{{ error }}</p>

            <div class="flex flex-wrap items-center gap-3">
                <button type="button" class="btn-primary" :disabled="!canAsk || state === 'working'" @click="ask">
                    <LoaderCircle v-if="state === 'working'" class="h-4 w-4 animate-spin" />
                    <Sparkles v-else class="h-4 w-4" />
                    {{ state === 'working' ? 'بنكتب…' : 'اكتبلي البيانات' }}
                </button>
                <span v-if="state === 'working'" class="text-xs text-muted-foreground">ده بياخد حوالي نص دقيقة.</span>
                <span v-else-if="!canAsk" class="text-xs text-muted-foreground">ارفع صورة للمنتج أو اكتب عنه كلمتين الأول.</span>
            </div>
        </div>
    </section>
</template>
