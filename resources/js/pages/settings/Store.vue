<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { ImagePlus, LoaderCircle, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    store: { name: string; description: string | null; logo_url: string | null; platform_host: string };
}>();

const breadcrumbItems: BreadcrumbItem[] = [{ title: 'المتجر', href: '/settings/store' }];

const form = useForm({
    name: props.store.name,
    description: props.store.description ?? '',
    logo: null as File | null,
    remove_logo: false,
});

const fileInput = ref<HTMLInputElement>();
const preview = ref<string | null>(null);

const shownLogo = computed(() => {
    if (form.remove_logo) return null;
    return preview.value ?? props.store.logo_url;
});

const pickLogo = (files: FileList | null) => {
    if (!files?.length) return;
    form.logo = files[0];
    form.remove_logo = false;
    preview.value = URL.createObjectURL(files[0]);
};

const removeLogo = () => {
    form.logo = null;
    form.remove_logo = true;
    if (preview.value) {
        URL.revokeObjectURL(preview.value);
        preview.value = null;
    }
};

// The first letter is the fallback mark the storefront draws when there is no
// logo, so the preview here has to show the same thing.
const initial = computed(() => form.name.trim().charAt(0) || 'م');

const submit = () =>
    form.post(route('store.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.remove_logo = false;
            form.logo = null;
        },
    });
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="المتجر" />

        <SettingsLayout>
            <form @submit.prevent="submit" class="space-y-6">
                <HeadingSmall title="بيانات المتجر" description="الاسم واللوجو اللي بيظهروا للعميل في متجرك." />

                <div>
                    <label class="field-label">لوجو المتجر</label>

                    <div class="flex items-center gap-4">
                        <img v-if="shownLogo" :src="shownLogo" alt=""
                             class="h-20 w-20 rounded-2xl border border-border object-cover" />
                        <div v-else
                             class="flex h-20 w-20 items-center justify-center rounded-2xl bg-primary text-2xl font-bold text-primary-foreground">
                            {{ initial }}
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="btn-outline" @click="fileInput?.click()">
                                <ImagePlus class="h-4 w-4" />
                                {{ shownLogo ? 'غيّر' : 'ارفع لوجو' }}
                            </button>
                            <button v-if="shownLogo" type="button" class="btn-ghost text-destructive" @click="removeLogo">
                                <Trash2 class="h-4 w-4" />
                                شيله
                            </button>
                        </div>

                        <input ref="fileInput" type="file" accept="image/*" class="hidden"
                               @change="pickLogo(($event.target as HTMLInputElement).files)" />
                    </div>

                    <p class="field-hint">مربّع يفضّل، وأقصى حجم ٢ ميجا. من غير لوجو هنستخدم أول حرف من اسم متجرك.</p>
                    <InputError :message="form.errors.logo" />
                </div>

                <div>
                    <label class="field-label" for="name">اسم المتجر</label>
                    <input id="name" v-model="form.name" class="field" required />
                    <InputError :message="form.errors.name" />
                </div>

                <div>
                    <label class="field-label" for="description">وصف قصير</label>
                    <textarea id="description" v-model="form.description" class="field" rows="3" />
                    <p class="field-hint">بيظهر في صفحة المتجر وفي نتايج جوجل.</p>
                    <InputError :message="form.errors.description" />
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
