<script setup lang="ts">
import TextLink from '@/components/TextLink.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, MailCheck } from 'lucide-vue-next';

defineProps<{ status?: string }>();

const form = useForm({});

const submit = () => form.post(route('verification.send'));
</script>

<template>
    <AuthLayout title="أكّد بريدك الإلكتروني" description="بعتنالك لينك على بريدك — افتحه عشان تكمّل">
        <Head title="تأكيد البريد" />

        <div v-if="status === 'verification-link-sent'" class="mb-6 flex items-start gap-2 rounded-xl border border-success/25 bg-success/5 p-4 text-sm text-success">
            <MailCheck class="mt-0.5 size-4 shrink-0" />
            بعتنا لينك جديد على بريدك.
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <p class="text-sm leading-relaxed text-muted-foreground">
                مالقيتش الرسالة؟ بصّ في البريد المهمل (Spam)، أو ابعتها تاني.
            </p>

            <button type="submit" class="btn-outline w-full py-3" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                ابعت اللينك تاني
            </button>

            <p class="pt-2 text-center text-sm text-muted-foreground">
                <TextLink :href="route('logout')" method="post" as="button" class="font-medium">
                    تسجيل الخروج
                </TextLink>
            </p>
        </form>
    </AuthLayout>
</template>
