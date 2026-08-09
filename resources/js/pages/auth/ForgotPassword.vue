<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

defineProps<{ status?: string }>();

const form = useForm({ email: '' });

const submit = () => form.post(route('password.email'));
</script>

<template>
    <AuthLayout title="نسيت كلمة المرور؟" description="اكتب بريدك وهنبعتلك لينك تغيّرها منه">
        <Head title="نسيت كلمة المرور" />

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
                    autocomplete="email"
                    placeholder="you@example.com"
                />
                <InputError :message="form.errors.email" />
            </div>

            <button type="submit" class="btn-primary w-full py-3" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                ابعتلي اللينك
            </button>

            <p class="pt-2 text-center text-sm text-muted-foreground">
                فاكرها؟
                <TextLink :href="route('login')" class="font-medium">ارجع لتسجيل الدخول</TextLink>
            </p>
        </form>
    </AuthLayout>
</template>
