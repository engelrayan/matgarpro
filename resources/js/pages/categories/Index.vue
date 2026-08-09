<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp, ExternalLink, ImagePlus, LoaderCircle, Plus, Trash2, X } from 'lucide-vue-next';
import { ref } from 'vue';

interface Category {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    image_url: string | null;
    is_active: boolean;
    sort_order: number;
    products_count: number;
    url: string;
}

const props = defineProps<{ categories: Category[] }>();

const breadcrumbItems: BreadcrumbItem[] = [{ title: 'الأقسام', href: '/categories' }];

/* ── Create ─────────────────────────────────────────────────────────────── */

const createForm = useForm({ name: '', description: '', image: null as File | null, is_active: true });
const showCreate = ref(false);
const createFile = ref<HTMLInputElement>();

const create = () =>
    createForm.post(route('categories.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            showCreate.value = false;
        },
    });

/* ── Edit in place ──────────────────────────────────────────────────────── */

const editingId = ref<number | null>(null);
const editForm = useForm({ name: '', description: '', image: null as File | null, remove_image: false, is_active: true });
const editFile = ref<HTMLInputElement>();

const startEdit = (category: Category) => {
    editingId.value = category.id;
    editForm.defaults({ name: category.name, description: category.description ?? '', image: null, remove_image: false, is_active: category.is_active });
    editForm.reset();
    editForm.clearErrors();
};

const saveEdit = (category: Category) =>
    editForm.post(route('categories.update', category.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => (editingId.value = null),
    });

/* ── Order & delete ─────────────────────────────────────────────────────── */

const busy = ref(false);

const move = (index: number, direction: -1 | 1) => {
    const target = index + direction;
    if (target < 0 || target >= props.categories.length) return;

    const ids = props.categories.map((c) => c.id);
    [ids[index], ids[target]] = [ids[target], ids[index]];

    busy.value = true;
    router.put(route('categories.reorder'), { ids }, {
        preserveScroll: true,
        onFinish: () => (busy.value = false),
    });
};

const confirmingDelete = ref<number | null>(null);

const destroy = (category: Category) => {
    busy.value = true;
    router.delete(route('categories.destroy', category.id), {
        preserveScroll: true,
        onFinish: () => {
            busy.value = false;
            confirmingDelete.value = null;
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="الأقسام" />

        <div class="mx-auto max-w-3xl space-y-5 p-4 md:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">الأقسام</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        بتساعد العميل يلاقي اللي بيدوّر عليه بدل ما يلف في كل المنتجات.
                    </p>
                </div>

                <button class="btn-primary" @click="showCreate = !showCreate">
                    <Plus class="size-4" />
                    قسم جديد
                </button>
            </div>

            <!-- ── Create ─────────────────────────────────────────────── -->
            <form v-if="showCreate" class="surface space-y-4 p-5" @submit.prevent="create">
                <div>
                    <label class="field-label" for="new-name">اسم القسم</label>
                    <input id="new-name" v-model="createForm.name" class="field" placeholder="رجالي" required />
                    <InputError :message="createForm.errors.name" />
                </div>

                <div>
                    <label class="field-label" for="new-desc">وصف قصير</label>
                    <input id="new-desc" v-model="createForm.description" class="field" />
                </div>

                <div class="flex items-center gap-3">
                    <button type="button" class="btn-outline" @click="createFile?.click()">
                        <ImagePlus class="size-4" />
                        {{ createForm.image ? 'تم اختيار صورة' : 'صورة القسم' }}
                    </button>
                    <input ref="createFile" type="file" accept="image/*" class="hidden"
                           @change="createForm.image = ($event.target as HTMLInputElement).files?.[0] ?? null" />
                    <InputError :message="createForm.errors.image" />
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" class="btn-primary" :disabled="createForm.processing">
                        <LoaderCircle v-if="createForm.processing" class="size-4 animate-spin" />
                        أضف القسم
                    </button>
                    <button type="button" class="btn-ghost" @click="showCreate = false">إلغاء</button>
                </div>
            </form>

            <!-- ── List ───────────────────────────────────────────────── -->
            <div v-if="categories.length" class="space-y-3">
                <div v-for="(category, index) in categories" :key="category.id" class="surface p-4">
                    <!-- Edit mode -->
                    <form v-if="editingId === category.id" class="space-y-4" @submit.prevent="saveEdit(category)">
                        <div>
                            <label class="field-label">اسم القسم</label>
                            <input v-model="editForm.name" class="field" required />
                            <InputError :message="editForm.errors.name" />
                        </div>

                        <div>
                            <label class="field-label">وصف قصير</label>
                            <input v-model="editForm.description" class="field" />
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <button type="button" class="btn-outline" @click="editFile?.click()">
                                <ImagePlus class="size-4" />
                                غيّر الصورة
                            </button>
                            <input ref="editFile" type="file" accept="image/*" class="hidden"
                                   @change="editForm.image = ($event.target as HTMLInputElement).files?.[0] ?? null; editForm.remove_image = false" />

                            <label v-if="category.image_url" class="flex cursor-pointer items-center gap-2 text-sm">
                                <input v-model="editForm.remove_image" type="checkbox" class="size-4 rounded border-input accent-primary" />
                                شيل الصورة
                            </label>

                            <label class="flex cursor-pointer items-center gap-2 text-sm">
                                <input v-model="editForm.is_active" type="checkbox" class="size-4 rounded border-input accent-primary" />
                                ظاهر في المتجر
                            </label>
                        </div>

                        <div class="flex items-center gap-3">
                            <button type="submit" class="btn-primary" :disabled="editForm.processing">
                                <LoaderCircle v-if="editForm.processing" class="size-4 animate-spin" />
                                حفظ
                            </button>
                            <button type="button" class="btn-ghost" @click="editingId = null">إلغاء</button>
                        </div>
                    </form>

                    <!-- Row -->
                    <div v-else class="flex items-center gap-3">
                        <div class="flex shrink-0 flex-col">
                            <button class="btn-ghost px-1.5 py-0.5" :disabled="index === 0 || busy" @click="move(index, -1)">
                                <ChevronUp class="size-4" />
                            </button>
                            <button class="btn-ghost px-1.5 py-0.5" :disabled="index === categories.length - 1 || busy" @click="move(index, 1)">
                                <ChevronDown class="size-4" />
                            </button>
                        </div>

                        <img v-if="category.image_url" :src="category.image_url" alt=""
                             class="size-12 shrink-0 rounded-xl border border-border object-cover" />
                        <div v-else class="size-12 shrink-0 rounded-xl bg-muted" />

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium">{{ category.name }}</span>
                                <span v-if="!category.is_active" class="badge-neutral">مخفي</span>
                            </div>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                {{ category.products_count }} منتج · <span class="font-mono" dir="ltr">/c/{{ category.slug }}</span>
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-1">
                            <a :href="category.url" target="_blank" rel="noopener" class="btn-ghost px-2" title="شوفه في المتجر">
                                <ExternalLink class="size-4" />
                            </a>
                            <button class="btn-ghost px-3" @click="startEdit(category)">تعديل</button>
                            <button class="btn-ghost px-2 text-destructive" @click="confirmingDelete = category.id">
                                <Trash2 class="size-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Deleting a section must never read as deleting its
                         products; say so before they click. -->
                    <div v-if="confirmingDelete === category.id"
                         class="mt-4 rounded-xl border border-destructive/30 bg-destructive/5 p-4">
                        <p class="text-sm">
                            تحذف «{{ category.name }}»؟
                            <span class="text-muted-foreground">المنتجات مش هتتحذف — هتفضل في المتجر من غير قسم.</span>
                        </p>
                        <div class="mt-3 flex items-center gap-2">
                            <button class="btn-danger" :disabled="busy" @click="destroy(category)">احذف</button>
                            <button class="btn-ghost" @click="confirmingDelete = null">
                                <X class="size-4" />
                                تراجع
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <p v-else class="surface p-10 text-center text-sm text-muted-foreground">
                لسه مفيش أقسام. لو منتجاتك قليلة مش لازم تعملها.
            </p>
        </div>
    </AppLayout>
</template>
