<script setup lang="ts">
import Pagination from '@/components/admin/Pagination.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { num } from '@/composables/useFormat';
import { Head, Link, router } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface LogRow {
    id: number;
    admin_name: string;
    admin_email: string;
    action: string;
    summary: string;
    subject_label: string | null;
    subject_id: number | null;
    subject_url: string | null;
    changes: Record<string, { from: unknown; to: unknown }> | null;
    ip: string | null;
    created_at: string;
    created_ago: string;
}

const props = defineProps<{
    logs: { data: LogRow[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null; to: number | null };
    filters: { q: string; admin: string; action: string };
    admins: { id: number; name: string }[];
    actions: string[];
}>();

const form = ref({ ...props.filters });

let timer: ReturnType<typeof setTimeout>;
watch(
    form,
    (value) => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            router.get('/admin/activity', { ...value }, { preserveState: true, preserveScroll: true, replace: true });
        }, 300);
    },
    { deep: true },
);

const show = (value: unknown) => {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'boolean') return value ? 'نعم' : 'لأ';
    return String(value);
};
</script>

<template>
    <AdminLayout title="سجل التدقيق" :subtitle="`${num(logs.total)} حركة`">
        <Head title="سجل التدقيق" />

        <div class="mx-auto w-full max-w-5xl space-y-4">
            <div class="surface grid gap-3 p-4 sm:grid-cols-3">
                <div class="relative">
                    <Search class="absolute right-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input v-model="form.q" class="field pr-9" placeholder="ابحث في الوصف" />
                </div>
                <select v-model="form.admin" class="field">
                    <option value="">كل المشرفين</option>
                    <option v-for="a in admins" :key="a.id" :value="a.id">{{ a.name }}</option>
                </select>
                <select v-model="form.action" class="field">
                    <option value="">كل الأنواع</option>
                    <option v-for="a in actions" :key="a" :value="a">{{ a }}</option>
                </select>
            </div>

            <!--
                A flat list, newest first, rather than a table: each row's
                interesting part is a sentence plus a diff, and neither fits a
                column layout without being truncated into uselessness.
            -->
            <div class="surface divide-y divide-border/60 overflow-hidden">
                <article v-for="log in logs.data" :key="log.id" class="p-5">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <p class="text-sm">{{ log.summary }}</p>
                        <span class="badge-neutral shrink-0 font-mono" dir="ltr">{{ log.action }}</span>
                    </div>

                    <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                        <span>{{ log.admin_name }}</span>
                        <span :title="log.created_at">{{ log.created_ago }}</span>
                        <span v-if="log.ip" class="font-mono" dir="ltr">{{ log.ip }}</span>
                        <Link v-if="log.subject_url" :href="log.subject_url" class="hover:text-foreground hover:underline">
                            {{ log.subject_label }} #{{ log.subject_id }}
                        </Link>
                        <span v-else-if="log.subject_label">{{ log.subject_label }} #{{ log.subject_id }}</span>
                    </div>

                    <ul v-if="log.changes" class="mt-3 space-y-1 rounded-xl bg-muted/60 p-3 text-xs">
                        <li v-for="(change, field) in log.changes" :key="field" class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-muted-foreground" dir="ltr">{{ field }}</span>
                            <span class="text-muted-foreground line-through">{{ show(change.from) }}</span>
                            <span>←</span>
                            <span class="font-medium">{{ show(change.to) }}</span>
                        </li>
                    </ul>
                </article>

                <p v-if="!logs.data.length" class="p-10 text-center text-muted-foreground">مفيش حركات بالفلاتر دي.</p>

                <div class="p-4">
                    <Pagination :links="logs.links" :total="logs.total" :from="logs.from" :to="logs.to" />
                </div>
            </div>

            <p class="text-xs text-muted-foreground">
                السجل ده بيتكتب بس — مفيش تعديل ولا مسح، لا من هنا ولا من أي مكان في الكود.
            </p>
        </div>
    </AdminLayout>
</template>
