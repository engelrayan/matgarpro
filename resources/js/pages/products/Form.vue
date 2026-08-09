<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import AdvancedSettings, { type ProductSettings } from '@/components/products/AdvancedSettings.vue';
import LivePreview from '@/components/products/LivePreview.vue';
import ProfitCalculator from '@/components/products/ProfitCalculator.vue';
import RichTextEditor from '@/components/products/RichTextEditor.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronDown, ExternalLink, ImagePlus, LoaderCircle, Plus, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface OptionDef { name: string; values: string[] }
interface VariantRow { options: Record<string, string>; price: string | null; stock: number; sku: string | null }
interface ExistingImage { id: number; url: string }

interface ProductPayload {
    id: number;
    name: string; slug: string; description: string | null;
    price: string; compare_at_price: string | null; cost: string | null;
    sku: string | null; track_stock: boolean; stock: number;
    status: 'draft' | 'active';
    seo_title: string | null; seo_description: string | null;
    options: OptionDef[]; variants: VariantRow[];
    settings: ProductSettings;
    categories: number[];
    images: ExistingImage[]; url: string;
}

const props = defineProps<{
    product: ProductPayload | null;
    currency: string;
    storeName: string;
    /** Platform defaults, so a new product starts from the same shape. */
    settingDefaults: ProductSettings;
    categories: { id: number; name: string; is_active: boolean }[];
}>();

const isEdit = computed(() => props.product !== null);

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'المنتجات', href: '/products' },
    { title: props.product ? props.product.name : 'منتج جديد', href: '#' },
];

const form = useForm({
    name: props.product?.name ?? '',
    slug: props.product?.slug ?? '',
    description: props.product?.description ?? '',
    price: props.product?.price ?? '',
    compare_at_price: props.product?.compare_at_price ?? '',
    cost: props.product?.cost ?? '',
    sku: props.product?.sku ?? '',
    track_stock: props.product?.track_stock ?? true,
    stock: props.product?.stock ?? 0,
    status: props.product?.status ?? 'active',
    seo_title: props.product?.seo_title ?? '',
    seo_description: props.product?.seo_description ?? '',
    options: (props.product?.options ?? []) as OptionDef[],
    variants: (props.product?.variants ?? []) as VariantRow[],
    settings: { ...props.settingDefaults, ...(props.product?.settings ?? {}) } as ProductSettings,
    categories: [...(props.product?.categories ?? [])] as number[],
    kept_images: (props.product?.images ?? []).map((i) => i.id),
    images: [] as File[],
});

/* ── Images ─────────────────────────────────────────────────────────────── */

const existingImages = ref<ExistingImage[]>([...(props.product?.images ?? [])]);
const newPreviews = ref<{ file: File; url: string }[]>([]);
const fileInput = ref<HTMLInputElement>();

const addFiles = (files: FileList | null) => {
    if (!files) return;
    for (const file of Array.from(files)) {
        newPreviews.value.push({ file, url: URL.createObjectURL(file) });
    }
    form.images = newPreviews.value.map((p) => p.file);
};

const removeExisting = (id: number) => {
    existingImages.value = existingImages.value.filter((i) => i.id !== id);
    form.kept_images = existingImages.value.map((i) => i.id);
};

const removeNew = (index: number) => {
    URL.revokeObjectURL(newPreviews.value[index].url);
    newPreviews.value.splice(index, 1);
    form.images = newPreviews.value.map((p) => p.file);
};

/* ── Options & variants ─────────────────────────────────────────────────── */

const addOption = () => {
    if (form.options.length >= 3) return;
    form.options.push({ name: '', values: [] });
};

const removeOption = (index: number) => {
    form.options.splice(index, 1);
    rebuildVariants();
};

const valueDrafts = ref<Record<number, string>>({});

const addValue = (index: number) => {
    const raw = (valueDrafts.value[index] ?? '').trim();
    if (!raw) return;
    // Comma-separated paste is how merchants actually enter sizes.
    for (const value of raw.split(/[,،]/).map((v) => v.trim()).filter(Boolean)) {
        if (!form.options[index].values.includes(value)) form.options[index].values.push(value);
    }
    valueDrafts.value[index] = '';
    rebuildVariants();
};

const removeValue = (optionIndex: number, value: string) => {
    const option = form.options[optionIndex];
    option.values = option.values.filter((v) => v !== value);
    rebuildVariants();
};

const variantKey = (options: Record<string, string>) =>
    Object.keys(options).sort().map((k) => `${k}=${options[k]}`).join('|');

/**
 * Regenerate the combination matrix, carrying over price/stock for any
 * combination that still exists. Rebuilding from scratch on every keystroke
 * would wipe the stock counts the merchant just typed.
 */
const rebuildVariants = () => {
    const valid = form.options.filter((o) => o.name.trim() && o.values.length);

    if (!valid.length) {
        form.variants = [];
        return;
    }

    const previous = new Map(form.variants.map((v) => [variantKey(v.options), v]));

    let combos: Record<string, string>[] = [{}];
    for (const option of valid) {
        combos = combos.flatMap((combo) =>
            option.values.map((value) => ({ ...combo, [option.name.trim()]: value })),
        );
    }

    form.variants = combos.map((options) => {
        const carried = previous.get(variantKey(options));
        return carried ?? { options, price: null, stock: 0, sku: null };
    });
};

const variantLabel = (options: Record<string, string>) => Object.values(options).join(' · ');

/* ── Preview ────────────────────────────────────────────────────────────── */

// Kept images first, then newly picked ones — the same order the server saves
// them in, so the preview's cover is the one that ends up on the real page.
const previewImages = computed(() => [
    ...existingImages.value.map((i) => ({ url: i.url })),
    ...newPreviews.value.map((p) => ({ url: p.url })),
]);

const showAdvanced = ref(false);

const submit = () => {
    const url = isEdit.value ? `/products/${props.product!.id}` : '/products';
    form.post(url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            newPreviews.value.forEach((p) => URL.revokeObjectURL(p.url));
            newPreviews.value = [];
            form.images = [];
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="isEdit ? product!.name : 'منتج جديد'" />

        <form @submit.prevent="submit" class="mx-auto max-w-6xl px-4 py-6">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-bold tracking-tight">
                    {{ isEdit ? 'تعديل المنتج' : 'منتج جديد' }}
                </h1>

                <div class="flex items-center gap-2">
                    <a v-if="isEdit" :href="product!.url" target="_blank" class="btn-outline">
                        <ExternalLink class="h-4 w-4" />
                        شوفه في المتجر
                    </a>
                    <button type="submit" class="btn-primary" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                        {{ isEdit ? 'حفظ' : 'أضف المنتج' }}
                    </button>
                </div>
            </div>

            <!-- Two columns: the fields a merchant fills in the middle, and a
                 sticky rail on the side showing what those fields produce. One
                 tall column made the page read as empty and buried the money
                 settings below the fold. -->
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div class="space-y-6">
                    <!-- ── Basics ───────────────────────────────────────────── -->
                    <section class="surface space-y-5 p-6">
                    <div>
                        <label class="field-label" for="name">اسم المنتج</label>
                        <input id="name" v-model="form.name" class="field" required />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="field-label" for="price">السعر ({{ currency }})</label>
                            <input id="price" v-model="form.price" class="field tabular" inputmode="decimal" required />
                            <InputError :message="form.errors.price" />
                        </div>

                        <div>
                            <label class="field-label" for="compare">سعر قبل الخصم</label>
                            <input id="compare" v-model="form.compare_at_price" class="field tabular" inputmode="decimal" />
                            <p class="field-hint">هيبان مشطوب جنب السعر.</p>
                            <InputError :message="form.errors.compare_at_price" />
                        </div>
                    </div>

                    <div>
                        <label class="field-label">الوصف</label>
                        <RichTextEditor v-model="form.description" />
                        <InputError :message="form.errors.description" />
                    </div>
                </section>

                <!-- ── Images ───────────────────────────────────────────── -->
                <section class="surface p-6">
                    <HeadingSmall title="الصور" description="أول صورة هي اللي بتظهر في الإعلان وفي قايمة المنتجات." />

                    <div class="mt-5 flex flex-wrap gap-3">
                        <div v-for="image in existingImages" :key="image.id" class="group relative">
                            <img :src="image.url" alt="" class="h-24 w-24 rounded-xl border border-border object-cover" />
                            <button type="button" class="absolute -left-1.5 -top-1.5 rounded-full bg-destructive p-1 text-destructive-foreground shadow-e1"
                                    @click="removeExisting(image.id)">
                                <X class="h-3 w-3" />
                            </button>
                        </div>

                        <div v-for="(preview, index) in newPreviews" :key="preview.url" class="group relative">
                            <img :src="preview.url" alt="" class="h-24 w-24 rounded-xl border border-border object-cover" />
                            <button type="button" class="absolute -left-1.5 -top-1.5 rounded-full bg-destructive p-1 text-destructive-foreground shadow-e1"
                                    @click="removeNew(index)">
                                <X class="h-3 w-3" />
                            </button>
                        </div>

                        <button type="button"
                                class="flex h-24 w-24 flex-col items-center justify-center gap-1.5 rounded-xl border-2 border-dashed border-border text-muted-foreground transition-colors hover:border-primary hover:text-primary"
                                @click="fileInput?.click()">
                            <ImagePlus class="h-5 w-5" />
                            <span class="text-xs">أضف</span>
                        </button>

                        <input ref="fileInput" type="file" accept="image/*" multiple class="hidden"
                               @change="addFiles(($event.target as HTMLInputElement).files)" />
                    </div>

                    <InputError :message="form.errors.images" />
                </section>

                <!-- ── Options ──────────────────────────────────────────── -->
                <section class="surface p-6">
                    <HeadingSmall title="المقاسات والألوان" description="سيبها فاضية لو المنتج نوع واحد بس." />

                    <div v-if="form.options.length" class="mt-5 space-y-4">
                        <div v-for="(option, index) in form.options" :key="index" class="rounded-xl border border-border p-4">
                            <div class="flex items-center gap-2">
                                <input v-model="option.name" class="field flex-1" placeholder="اللون" @blur="rebuildVariants" />
                                <button type="button" class="btn-ghost px-2 text-destructive" @click="removeOption(index)">
                                    <X class="h-4 w-4" />
                                </button>
                            </div>

                            <div class="mt-3 flex flex-wrap gap-2">
                                <span v-for="value in option.values" :key="value"
                                      class="badge-neutral gap-1.5 py-1.5 pl-1.5 pr-2.5">
                                    {{ value }}
                                    <button type="button" @click="removeValue(index, value)">
                                        <X class="h-3 w-3" />
                                    </button>
                                </span>
                            </div>

                            <input v-model="valueDrafts[index]" class="field mt-3" placeholder="أحمر، أزرق — واحد ورا التاني أو بينهم فاصلة"
                                   @keydown.enter.prevent="addValue(index)" @blur="addValue(index)" />
                        </div>
                    </div>

                    <button v-if="form.options.length < 3" type="button" class="btn-outline mt-5" @click="addOption">
                        <Plus class="h-4 w-4" />
                        أضف خاصية
                    </button>
                </section>

                <!-- ── Variant matrix ───────────────────────────────────── -->
                <section v-if="form.variants.length" class="surface p-6">
                    <HeadingSmall title="الأنواع" :description="`${form.variants.length} نوع. سيب السعر فاضي عشان ياخد سعر المنتج.`" />

                    <div class="mt-5 overflow-x-auto">
                        <table class="w-full min-w-[30rem] text-sm">
                            <thead>
                                <tr class="border-b border-border text-right text-muted-foreground">
                                    <th class="pb-2 font-medium">النوع</th>
                                    <th class="pb-2 font-medium">السعر</th>
                                    <th class="pb-2 font-medium">الكمية</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="(variant, index) in form.variants" :key="index">
                                    <td class="py-2 pl-3 font-medium">{{ variantLabel(variant.options) }}</td>
                                    <td class="py-2 pl-3">
                                        <input v-model="variant.price" class="field tabular w-28 py-1.5" inputmode="decimal"
                                               :placeholder="String(form.price || '—')" />
                                    </td>
                                    <td class="py-2">
                                        <input v-model="variant.stock" class="field tabular w-24 py-1.5" inputmode="numeric" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                    <!-- ── SEO ──────────────────────────────────────────── -->
                    <section class="surface p-6">
                        <button type="button" class="flex w-full items-center justify-between text-right" @click="showAdvanced = !showAdvanced">
                            <span class="font-medium">الظهور في جوجل</span>
                            <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': showAdvanced }" />
                        </button>

                        <div v-show="showAdvanced" class="mt-6 space-y-5">
                            <div>
                                <label class="field-label" for="slug">رابط المنتج</label>
                                <input id="slug" v-model="form.slug" class="field" dir="ltr" placeholder="tshirt-black" />
                                <p class="field-hint">سيبه فاضي وهنعمله من الاسم.</p>
                                <InputError :message="form.errors.slug" />
                            </div>

                            <div>
                                <label class="field-label" for="seo_title">عنوان جوجل</label>
                                <input id="seo_title" v-model="form.seo_title" class="field" />
                            </div>

                            <div>
                                <label class="field-label" for="seo_description">وصف جوجل</label>
                                <textarea id="seo_description" v-model="form.seo_description" class="field" rows="2" />
                            </div>
                        </div>
                    </section>
                </div>

                <!-- ══ Side rail ══════════════════════════════════════════ -->
                <aside class="space-y-5 lg:sticky lg:top-6 lg:self-start">
                    <LivePreview
                        :name="form.name"
                        :price="form.price"
                        :compare-at-price="form.compare_at_price"
                        :description="form.description"
                        :images="previewImages"
                        :options="form.options"
                        :currency="currency"
                        :store-name="storeName"
                    />

                    <!-- ── Status ───────────────────────────────────────── -->
                    <section class="surface p-5">
                        <p class="text-sm font-medium">حالة المنتج</p>
                        <div class="mt-3 flex rounded-xl border border-border p-1">
                            <button v-for="option in [{ v: 'active', l: 'منشور' }, { v: 'draft', l: 'مسودّة' }]" :key="option.v"
                                    type="button"
                                    class="flex-1 rounded-lg px-3 py-1.5 text-sm transition-colors"
                                    :class="form.status === option.v ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'"
                                    @click="form.status = option.v as 'draft' | 'active'">
                                {{ option.l }}
                            </button>
                        </div>
                        <p class="mt-2.5 text-xs text-muted-foreground">المسودّة مابتظهرش للعملاء.</p>
                    </section>

                    <!-- ── Money ────────────────────────────────────────── -->
                    <section class="surface space-y-4 p-5">
                        <p class="text-sm font-medium">التكلفة والربح</p>

                        <div>
                            <label class="field-label" for="cost">تكلفة المنتج عليك</label>
                            <input id="cost" v-model="form.cost" class="field tabular" inputmode="decimal" />
                            <p class="field-hint">مش بيظهر للعميل.</p>
                        </div>

                        <ProfitCalculator :price="form.price" :cost="form.cost" :currency="currency" />
                    </section>

                    <!-- ── Stock ────────────────────────────────────────── -->
                    <section class="surface space-y-4 p-5">
                        <p class="text-sm font-medium">المخزون</p>

                        <label class="flex cursor-pointer items-center gap-3">
                            <input v-model="form.track_stock" type="checkbox" class="size-4 rounded border-input accent-primary" />
                            <span class="text-sm">تتبّع الكمية</span>
                        </label>

                        <div v-if="form.track_stock && !form.options.length">
                            <label class="field-label" for="stock">الكمية المتاحة</label>
                            <input id="stock" v-model="form.stock" class="field tabular" inputmode="numeric" />
                            <InputError :message="form.errors.stock" />
                        </div>

                        <p v-else-if="form.track_stock" class="text-xs text-muted-foreground">
                            الكمية بتتحدد لكل مقاس/لون في الجدول.
                        </p>

                        <div>
                            <label class="field-label" for="sku">كود المنتج (SKU)</label>
                            <input id="sku" v-model="form.sku" class="field" dir="ltr" />
                        </div>
                    </section>

                    <!-- ── Categories ───────────────────────────────────── -->
                    <section v-if="categories.length" class="surface p-5">
                        <p class="text-sm font-medium">الأقسام</p>
                        <p class="field-hint mt-0 mb-3">المنتج ممكن يبقى في أكتر من قسم.</p>

                        <div class="space-y-2">
                            <label v-for="category in categories" :key="category.id"
                                   class="flex cursor-pointer items-center gap-2.5 text-sm">
                                <input v-model="form.categories" type="checkbox" :value="category.id"
                                       class="size-4 rounded border-input accent-primary" />
                                <span>{{ category.name }}</span>
                                <span v-if="!category.is_active" class="badge-neutral">مخفي</span>
                            </label>
                        </div>
                    </section>

                    <AdvancedSettings v-model="form.settings" />
                </aside>

                <div class="flex items-center justify-between">
                    <Link href="/products" class="btn-ghost">رجوع</Link>
                    <button type="submit" class="btn-primary" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                        {{ isEdit ? 'حفظ' : 'أضف المنتج' }}
                    </button>
                </div>
            </div>
        </form>
    </AppLayout>
</template>
