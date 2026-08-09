<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, ShieldCheck } from 'lucide-vue-next';

/*
 * Sign-in for the platform panel.
 *
 * No "forgot password", no "create account", no link back to the merchant
 * login. None of those flows exist for operators — accounts are provisioned
 * with `php artisan admin:create` from the server — and rendering a link to a
 * route that is not there is how a login page teaches an attacker where to
 * push.
 */
defineProps<{ error?: string }>();

const form = useForm({ email: '', password: '' });

const submit = () => form.post('/admin/login', { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="دخول لوحة المنصة" />

    <div class="flex min-h-screen items-center justify-center bg-jade-950 p-4" dir="rtl">
        <div class="w-full max-w-sm">
            <div class="mb-6 flex flex-col items-center gap-3 text-center">
                <span class="flex size-12 items-center justify-center rounded-2xl bg-white/10 text-jade-100">
                    <ShieldCheck class="size-6" />
                </span>
                <div>
                    <h1 class="text-xl font-bold text-white">لوحة المنصة</h1>
                    <p class="mt-1 text-sm text-jade-300">دخول المشرفين فقط</p>
                </div>
            </div>

            <form class="surface space-y-4 p-6" @submit.prevent="submit">
                <p v-if="error" class="rounded-xl bg-destructive/10 px-4 py-3 text-sm text-destructive">
                    {{ error }}
                </p>

                <div>
                    <label class="field-label" for="email">الإيميل</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="field"
                        dir="ltr"
                        required
                        autofocus
                        autocomplete="username"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div>
                    <label class="field-label" for="password">الباسورد</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="field"
                        dir="ltr"
                        required
                        autocomplete="current-password"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <button type="submit" class="btn-primary w-full" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                    دخول
                </button>
            </form>
        </div>
    </div>
</template>
