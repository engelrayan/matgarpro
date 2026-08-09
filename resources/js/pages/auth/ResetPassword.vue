<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const props = defineProps<{ token: string; email: string }>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () =>
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
</script>

<template>
    <AuthLayout title="كلمة مرور جديدة" description="اختار كلمة مرور جديدة لحسابك">
        <Head title="تغيير كلمة المرور" />

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <label class="field-label" for="email">البريد الإلكتروني</label>
                <!-- Read-only: it comes from the reset link, and editing it
                     would only fail validation against the token. -->
                <input id="email" v-model="form.email" type="email" dir="ltr" class="field" readonly />
                <InputError :message="form.errors.email" />
            </div>

            <div>
                <label class="field-label" for="password">كلمة المرور الجديدة</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    dir="ltr"
                    class="field"
                    required
                    autofocus
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
                    autocomplete="new-password"
                />
                <InputError :message="form.errors.password_confirmation" />
            </div>

            <button type="submit" class="btn-primary w-full py-3" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                غيّر كلمة المرور
            </button>
        </form>
    </AuthLayout>
</template>
