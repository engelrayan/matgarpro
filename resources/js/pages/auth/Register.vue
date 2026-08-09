<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';

defineProps<{ platformDomain: string }>();

const form = useForm({
    name: '',
    store_name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

/*
 * Preview the free sub-domain as they type. It makes the store feel real before
 * they have committed to anything, and explains the field better than hint text.
 *
 * Latin-only, matching the server: an Arabic name reduces to nothing here just
 * as Str::slug() reduces it to an empty string, so the preview falls back the
 * same way the backend does instead of promising an address it will not issue.
 */
const slugPreview = computed(() => {
    const slug = form.store_name
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');

    return slug || 'your-store';
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthBase title="اعمل حسابك" description="دقيقة واحدة ويكون عندك متجر شغّال">
        <Head title="حساب جديد" />

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <label class="field-label" for="name">اسمك</label>
                <input
                    id="name"
                    v-model="form.name"
                    class="field"
                    required
                    autofocus
                    tabindex="1"
                    autocomplete="name"
                    placeholder="محمود ممدوح"
                />
                <InputError :message="form.errors.name" />
            </div>

            <div>
                <label class="field-label" for="store_name">اسم المتجر</label>
                <input
                    id="store_name"
                    v-model="form.store_name"
                    class="field"
                    required
                    tabindex="2"
                    placeholder="متجر محمود"
                />
                <p class="field-hint">
                    عنوان متجرك:
                    <span class="font-mono text-foreground" dir="ltr">{{ slugPreview }}.{{ platformDomain }}</span>
                    — وتقدر تربط دومينك الخاص بعدين.
                </p>
                <InputError :message="form.errors.store_name" />
            </div>

            <div>
                <label class="field-label" for="email">البريد الإلكتروني</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    dir="ltr"
                    class="field"
                    required
                    tabindex="3"
                    autocomplete="email"
                    placeholder="you@example.com"
                />
                <InputError :message="form.errors.email" />
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="field-label" for="password">كلمة المرور</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        dir="ltr"
                        class="field"
                        required
                        tabindex="4"
                        autocomplete="new-password"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div>
                    <label class="field-label" for="password_confirmation">تأكيدها</label>
                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        dir="ltr"
                        class="field"
                        required
                        tabindex="5"
                        autocomplete="new-password"
                    />
                    <InputError :message="form.errors.password_confirmation" />
                </div>
            </div>

            <button type="submit" class="btn-primary sheen w-full py-3" tabindex="6" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                ابدأ مجانًا
            </button>

            <p class="text-center text-xs text-muted-foreground">
                من غير كارت ائتمان · متجرك يفضل ليك
            </p>

            <p class="pt-1 text-center text-sm text-muted-foreground">
                عندك حساب بالفعل؟
                <TextLink :href="route('login')" :tabindex="7" class="font-medium">سجّل دخول</TextLink>
            </p>
        </form>
    </AuthBase>
</template>
