<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const form = useForm({ password: '' });

const submit = () =>
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
</script>

<template>
    <AuthLayout title="أكّد كلمة المرور" description="الخطوة دي فيها بيانات حساسة — اكتب كلمة مرورك عشان تكمّل">
        <Head title="تأكيد كلمة المرور" />

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <label class="field-label" for="password">كلمة المرور</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    dir="ltr"
                    class="field"
                    required
                    autofocus
                    autocomplete="current-password"
                />
                <InputError :message="form.errors.password" />
            </div>

            <button type="submit" class="btn-primary w-full py-3" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                تأكيد
            </button>
        </form>
    </AuthLayout>
</template>
