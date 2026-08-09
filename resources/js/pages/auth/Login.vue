<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthBase title="ادخل على حسابك" description="اكتب بريدك وكلمة المرور">
        <Head title="تسجيل الدخول" />

        <div v-if="status" class="mb-6 rounded-xl border border-success/25 bg-success/5 p-4 text-sm text-success">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <label class="field-label" for="email">البريد الإلكتروني</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    dir="ltr"
                    class="field"
                    required
                    autofocus
                    tabindex="1"
                    autocomplete="email"
                    placeholder="you@example.com"
                />
                <InputError :message="form.errors.email" />
            </div>

            <div>
                <div class="mb-1.5 flex items-baseline justify-between">
                    <label class="field-label mb-0" for="password">كلمة المرور</label>
                    <TextLink v-if="canResetPassword" :href="route('password.request')" class="text-xs" tabindex="5">
                        نسيت كلمة المرور؟
                    </TextLink>
                </div>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    dir="ltr"
                    class="field"
                    required
                    tabindex="2"
                    autocomplete="current-password"
                />
                <InputError :message="form.errors.password" />
            </div>

            <label class="flex w-fit cursor-pointer items-center gap-2.5 text-sm">
                <input
                    v-model="form.remember"
                    type="checkbox"
                    class="size-4 rounded border-input accent-primary"
                    tabindex="3"
                />
                فكّرني على الجهاز ده
            </label>

            <button type="submit" class="btn-primary w-full py-3" tabindex="4" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                دخول
            </button>

            <p class="pt-2 text-center text-sm text-muted-foreground">
                معندكش حساب؟
                <TextLink :href="route('register')" :tabindex="6" class="font-medium">اعمل واحد مجانًا</TextLink>
            </p>
        </form>
    </AuthBase>
</template>
