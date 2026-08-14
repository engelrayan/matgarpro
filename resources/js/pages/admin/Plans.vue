<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { money, num } from '@/composables/useFormat';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

interface Plan {
    id: number;
    code: string;
    name: string;
    description: string | null;
    price_per_order: number;
    billable_event: string;
    is_default: boolean;
    is_public: boolean;
    is_active: boolean;
    sort_order: number;
    stores_count: number;
}

const props = defineProps<{ plans: Plan[]; currency: string; defaultPrice: number }>();

const blank = {
    code: '',
    name: '',
    description: '',
    price_per_order: 0,
    billable_event: 'created',
    is_default: false,
    is_public: true,
    is_active: true,
    sort_order: 0,
};

const editingId = ref<number | null>(null);
const form = useForm({ ...blank });

const startCreate = () => {
    editingId.value = null;
    form.defaults({ ...blank });
    form.reset();
    form.clearErrors();
};

const startEdit = (plan: Plan) => {
    editingId.value = plan.id;
    form.clearErrors();
    Object.assign(form, {
        code: plan.code,
        name: plan.name,
        description: plan.description ?? '',
        price_per_order: Number(plan.price_per_order),
        billable_event: plan.billable_event,
        is_default: plan.is_default,
        is_public: plan.is_public,
        is_active: plan.is_active,
        sort_order: plan.sort_order,
    });
};

const submit = () => {
    if (editingId.value) {
        form.patch(`/admin/plans/${editingId.value}`, { preserveScroll: true });
    } else {
        form.post('/admin/plans', { preserveScroll: true, onSuccess: () => startCreate() });
    }
};

const destroy = (plan: Plan) => {
    // The server refuses this when stores are attached; asking here as well
    // keeps a misclick from becoming a validation error the operator has to
    // read to understand.
    if (confirm(`هتمسح خطة «${plan.name}». تمام؟`)) {
        router.delete(`/admin/plans/${plan.id}`, { preserveScroll: true });
    }
};

const EVENT_LABEL: Record<string, string> = {
    created: 'أول ما الطلب يتعمل',
    confirmed: 'لما الطلب يتأكد',
    delivered: 'لما الطلب يتوصّل',
};

const editingPlan = () => props.plans.find((p) => p.id === editingId.value);
</script>

<template>
    <AdminLayout title="الخطط والأسعار" subtitle="ده اللي بيحدد كل متجر بيدفع كام">
        <Head title="الخطط والأسعار" />

        <div class="mx-auto w-full max-w-6xl space-y-5">
            <div class="grid gap-5 lg:grid-cols-3">
                <!-- ── Plans ─────────────────────────────────────────────── -->
                <div class="space-y-3 lg:col-span-2">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-muted-foreground">
                            السعر الافتراضي لو المتجر مالوش خطة: <span class="tabular">{{ money(defaultPrice) }}</span>
                            {{ currency }}
                        </p>
                        <button class="btn-outline" @click="startCreate">
                            <Plus class="size-4" />
                            خطة جديدة
                        </button>
                    </div>

                    <div
                        v-for="plan in plans"
                        :key="plan.id"
                        class="surface p-5 transition-shadow"
                        :class="editingId === plan.id ? 'ring-2 ring-primary' : ''"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold">{{ plan.name }}</p>
                                    <span v-if="plan.is_default" class="badge-gold">افتراضية</span>
                                    <span v-if="!plan.is_active" class="badge-neutral">معطّلة</span>
                                    <span v-if="!plan.is_public" class="badge-info">مخفية</span>
                                </div>
                                <p class="font-mono text-xs text-muted-foreground" dir="ltr">{{ plan.code }}</p>
                                <p v-if="plan.description" class="mt-1 text-sm text-muted-foreground">
                                    {{ plan.description }}
                                </p>
                            </div>

                            <div class="flex items-center gap-1">
                                <button class="btn-outline px-3 py-1.5 text-xs" @click="startEdit(plan)">تعديل</button>
                                <button
                                    class="btn-ghost px-2 py-1.5 text-destructive"
                                    :title="plan.stores_count ? 'فيه متاجر عليها' : 'امسح'"
                                    @click="destroy(plan)"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </div>
                        </div>

                        <dl class="mt-4 grid grid-cols-2 gap-4 border-t border-border pt-4 text-sm">
                            <div>
                                <dt class="text-xs text-muted-foreground">سعر الطلب</dt>
                                <dd class="tabular font-medium">{{ money(plan.price_per_order) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">متاجر عليها</dt>
                                <dd class="tabular font-medium">{{ num(plan.stores_count) }}</dd>
                            </div>
                        </dl>

                        <p class="mt-3 text-xs text-muted-foreground">
                            بتتحسب {{ EVENT_LABEL[plan.billable_event] }}.
                        </p>
                    </div>
                </div>

                <!-- ── Editor ────────────────────────────────────────────── -->
                <div>
                    <form class="surface-lux sticky top-24 space-y-4 p-5" @submit.prevent="submit">
                        <p class="text-sm font-medium">
                            {{ editingId ? `تعديل «${editingPlan()?.name}»` : 'خطة جديدة' }}
                        </p>

                        <p v-if="editingId && editingPlan()?.stores_count" class="rounded-xl bg-warning/10 px-3 py-2 text-xs text-warning">
                            التعديل هيأثر على {{ num(editingPlan()!.stores_count) }} متجر من الطلب الجاي.
                            الفواتير القديمة مش بتتغيّر.
                        </p>

                        <InputError :message="form.errors.plan" />
                        <InputError :message="form.errors.is_default" />

                        <div>
                            <label class="field-label" for="name">الاسم</label>
                            <input id="name" v-model="form.name" class="field" required />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div>
                            <label class="field-label" for="code">الكود</label>
                            <input id="code" v-model="form.code" class="field" dir="ltr" required />
                            <InputError :message="form.errors.code" />
                        </div>

                        <div>
                            <label class="field-label" for="description">الوصف</label>
                            <textarea id="description" v-model="form.description" class="field" rows="2" />
                            <InputError :message="form.errors.description" />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="field-label" for="price">سعر الطلب</label>
                                <input id="price" v-model="form.price_per_order" type="number" step="0.01" min="0" class="field" />
                                <InputError :message="form.errors.price_per_order" />
                            </div>
                            <div>
                                <label class="field-label" for="sort">الترتيب</label>
                                <input id="sort" v-model="form.sort_order" type="number" min="0" class="field" />
                                <InputError :message="form.errors.sort_order" />
                            </div>
                        </div>

                        <div>
                            <label class="field-label" for="event">إمتى بتتحسب</label>
                            <select id="event" v-model="form.billable_event" class="field">
                                <option value="created">أول ما الطلب يتعمل</option>
                                <option value="confirmed">لما الطلب يتأكد</option>
                                <option value="delivered">لما الطلب يتوصّل</option>
                            </select>
                            <InputError :message="form.errors.billable_event" />
                        </div>

                        <div class="space-y-2 border-t border-border pt-3 text-sm">
                            <label class="flex items-center gap-2">
                                <input v-model="form.is_active" type="checkbox" class="rounded border-input" />
                                شغّالة
                            </label>
                            <label class="flex items-center gap-2">
                                <input v-model="form.is_public" type="checkbox" class="rounded border-input" />
                                تظهر للتجار
                            </label>
                            <label class="flex items-center gap-2">
                                <input v-model="form.is_default" type="checkbox" class="rounded border-input" />
                                افتراضية للمتاجر الجديدة
                            </label>
                        </div>

                        <div class="flex gap-2">
                            <button type="submit" class="btn-primary flex-1" :disabled="form.processing">
                                {{ editingId ? 'احفظ' : 'أنشئ' }}
                            </button>
                            <button v-if="editingId" type="button" class="btn-outline" @click="startCreate">إلغاء</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
