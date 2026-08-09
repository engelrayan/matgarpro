<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp, Lock, LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';

interface Field {
    key: string;
    label: string;
    placeholder: string;
    enabled: boolean;
    required: boolean;
    locked: boolean;
    order: number;
}

const props = defineProps<{ fields: Field[] }>();

const breadcrumbItems: BreadcrumbItem[] = [{ title: 'فورم الطلب', href: '/settings/checkout' }];

const form = useForm({ fields: props.fields.map((f) => ({ ...f })) });

const move = (index: number, direction: -1 | 1) => {
    const target = index + direction;
    if (target < 0 || target >= form.fields.length) return;

    const list = form.fields;
    [list[index], list[target]] = [list[target], list[index]];
    // `order` is what the server sorts by, so rewrite it from the new positions
    // rather than trying to swap the two numbers.
    list.forEach((field, i) => (field.order = i + 1));
};

// Switching a field off has to switch "required" off with it — a hidden field
// the server still demands would make the form impossible to submit.
const toggleEnabled = (field: Field) => {
    if (field.locked) return;
    field.enabled = !field.enabled;
    if (!field.enabled) field.required = false;
};

const enabledCount = computed(() => form.fields.filter((f) => f.enabled).length);

const submit = () => form.put(route('checkout.update'), { preserveScroll: true });
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="فورم الطلب" />

        <SettingsLayout>
            <form @submit.prevent="submit" class="space-y-6">
                <HeadingSmall
                    title="فورم الطلب"
                    description="الحقول اللي العميل بيملاها عشان يكمّل الطلب."
                />

                <!-- The one number that actually predicts conversion. -->
                <div class="surface flex items-center justify-between p-4">
                    <span class="text-sm text-muted-foreground">حقول ظاهرة للعميل</span>
                    <span class="tabular text-lg font-bold" :class="enabledCount > 5 ? 'text-warning' : 'text-success'">
                        {{ enabledCount }}
                    </span>
                </div>

                <p v-if="enabledCount > 5" class="rounded-xl border border-warning/30 bg-warning/5 p-4 text-sm text-warning">
                    كل حقل زيادة بيقلّل عدد الطلبات اللي بتتكمّل. للدفع عند الاستلام،
                    الاسم والموبايل والعنوان بيكفّوا في الغالب.
                </p>

                <div class="space-y-3">
                    <div v-for="(field, index) in form.fields" :key="field.key" class="surface p-4">
                        <div class="flex items-start gap-3">
                            <div class="flex shrink-0 flex-col">
                                <button type="button" class="btn-ghost px-1.5 py-0.5" :disabled="index === 0" @click="move(index, -1)">
                                    <ChevronUp class="h-4 w-4" />
                                </button>
                                <button type="button" class="btn-ghost px-1.5 py-0.5" :disabled="index === form.fields.length - 1" @click="move(index, 1)">
                                    <ChevronDown class="h-4 w-4" />
                                </button>
                            </div>

                            <div class="min-w-0 flex-1 space-y-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-mono text-xs text-muted-foreground" dir="ltr">{{ field.key }}</span>
                                    <span v-if="field.locked" class="badge-neutral gap-1">
                                        <Lock class="h-3 w-3" />
                                        إجباري دايمًا
                                    </span>
                                </div>

                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label class="field-label">الاسم اللي بيظهر للعميل</label>
                                        <input v-model="field.label" class="field" :disabled="!field.enabled" />
                                        <InputError :message="(form.errors as Record<string, string>)[`fields.${index}.label`]" />
                                    </div>

                                    <div>
                                        <label class="field-label">نص مساعد</label>
                                        <input v-model="field.placeholder" class="field" :disabled="!field.enabled" />
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-5">
                                    <label class="flex cursor-pointer items-center gap-2 text-sm"
                                           :class="{ 'cursor-not-allowed opacity-60': field.locked }">
                                        <input type="checkbox" class="h-4 w-4 rounded border-input accent-primary"
                                               :checked="field.enabled" :disabled="field.locked"
                                               @change="toggleEnabled(field)" />
                                        ظاهر
                                    </label>

                                    <label class="flex cursor-pointer items-center gap-2 text-sm"
                                           :class="{ 'cursor-not-allowed opacity-60': field.locked || !field.enabled }">
                                        <input v-model="field.required" type="checkbox"
                                               class="h-4 w-4 rounded border-input accent-primary"
                                               :disabled="field.locked || !field.enabled" />
                                        إجباري
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" class="btn-primary" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                        حفظ
                    </button>
                    <span v-if="form.recentlySuccessful" class="text-sm text-success">اتحفظ</span>
                </div>
            </form>
        </SettingsLayout>
    </AppLayout>
</template>
