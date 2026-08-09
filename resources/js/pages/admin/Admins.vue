<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { num } from '@/composables/useFormat';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { KeyRound, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface AdminRow {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    is_active: boolean;
    actions_count: number;
    last_login_at: string | null;
    last_login_ip: string | null;
    created_at: string;
}

defineProps<{
    admins: AdminRow[];
    roles: { value: string; label: string; hint: string }[];
}>();

const page = usePage();
const me = computed(() => page.props.auth?.admin as { id: number } | null);

// ---- Create -------------------------------------------------------------
const showCreate = ref(false);
const createForm = useForm({ name: '', email: '', password: '', password_confirmation: '', role: 'staff' });
const create = () =>
    createForm.post('/admin/admins', {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            showCreate.value = false;
        },
    });

// ---- Edit ---------------------------------------------------------------
const editingId = ref<number | null>(null);
const editForm = useForm({ name: '', role: 'staff', is_active: true });

const startEdit = (admin: AdminRow) => {
    editingId.value = admin.id;
    editForm.clearErrors();
    editForm.name = admin.name;
    editForm.role = admin.role;
    editForm.is_active = admin.is_active;
};

const saveEdit = () =>
    editForm.patch(`/admin/admins/${editingId.value}`, {
        preserveScroll: true,
        onSuccess: () => (editingId.value = null),
    });

// ---- Password -----------------------------------------------------------
const passwordId = ref<number | null>(null);
const passwordForm = useForm({ password: '', password_confirmation: '' });

const savePassword = () =>
    passwordForm.patch(`/admin/admins/${passwordId.value}/password`, {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset();
            passwordId.value = null;
        },
    });
</script>

<template>
    <AdminLayout title="المشرفين" subtitle="مين يقدر يدخل اللوحة ويعمل إيه">
        <Head title="المشرفين" />

        <div class="mx-auto w-full max-w-4xl space-y-5">
            <div class="flex justify-end">
                <button class="btn-primary" @click="showCreate = !showCreate">
                    <Plus class="size-4" />
                    مشرف جديد
                </button>
            </div>

            <!-- ── Create ────────────────────────────────────────────────── -->
            <form v-if="showCreate" class="surface space-y-4 p-5" @submit.prevent="create">
                <p class="text-sm font-medium">مشرف جديد</p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="field-label" for="c-name">الاسم</label>
                        <input id="c-name" v-model="createForm.name" class="field" required />
                        <InputError :message="createForm.errors.name" />
                    </div>
                    <div>
                        <label class="field-label" for="c-email">الإيميل</label>
                        <input id="c-email" v-model="createForm.email" type="email" class="field" dir="ltr" required />
                        <InputError :message="createForm.errors.email" />
                    </div>
                    <div>
                        <label class="field-label" for="c-password">الباسورد</label>
                        <input id="c-password" v-model="createForm.password" type="password" class="field" dir="ltr" required />
                        <p class="field-hint">١٢ حرف على الأقل، وفيها حروف وأرقام ورموز.</p>
                        <InputError :message="createForm.errors.password" />
                    </div>
                    <div>
                        <label class="field-label" for="c-confirm">تأكيد الباسورد</label>
                        <input
                            id="c-confirm"
                            v-model="createForm.password_confirmation"
                            type="password"
                            class="field"
                            dir="ltr"
                            required
                        />
                    </div>
                </div>

                <div>
                    <label class="field-label">الدور</label>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label
                            v-for="role in roles"
                            :key="role.value"
                            class="cursor-pointer rounded-xl border p-3 text-sm transition-colors"
                            :class="createForm.role === role.value ? 'border-primary bg-primary/5' : 'border-border hover:bg-muted'"
                        >
                            <input v-model="createForm.role" type="radio" :value="role.value" class="sr-only" />
                            <span class="block font-medium">{{ role.label }}</span>
                            <span class="mt-0.5 block text-xs text-muted-foreground">{{ role.hint }}</span>
                        </label>
                    </div>
                    <InputError :message="createForm.errors.role" />
                </div>

                <button type="submit" class="btn-primary" :disabled="createForm.processing">أضف</button>
            </form>

            <!-- ── List ──────────────────────────────────────────────────── -->
            <div class="space-y-3">
                <div v-for="admin in admins" :key="admin.id" class="surface p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold">{{ admin.name }}</p>
                                <span :class="admin.role === 'super' ? 'badge-gold' : 'badge-neutral'">
                                    {{ admin.role_label }}
                                </span>
                                <span v-if="!admin.is_active" class="badge-danger">موقوف</span>
                                <span v-if="me?.id === admin.id" class="badge-info">أنت</span>
                            </div>
                            <p class="truncate text-sm text-muted-foreground" dir="ltr">{{ admin.email }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                <template v-if="admin.last_login_at">
                                    آخر دخول {{ admin.last_login_at }}
                                    <span v-if="admin.last_login_ip" class="font-mono" dir="ltr">
                                        ({{ admin.last_login_ip }})
                                    </span>
                                </template>
                                <template v-else>لسه مدخلش</template>
                                · {{ num(admin.actions_count) }} حركة في السجل
                            </p>
                        </div>

                        <!--
                            Your own row has no controls. Editing your own role
                            or switching yourself off is a one-click way to lock
                            the platform out of its own panel — see AdminController.
                        -->
                        <div v-if="me?.id !== admin.id" class="flex items-center gap-1">
                            <button class="btn-outline px-3 py-1.5 text-xs" @click="startEdit(admin)">تعديل</button>
                            <button
                                class="btn-ghost px-2 py-1.5"
                                title="غيّر الباسورد"
                                @click="passwordId = passwordId === admin.id ? null : admin.id"
                            >
                                <KeyRound class="size-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Edit -->
                    <form
                        v-if="editingId === admin.id"
                        class="mt-4 space-y-3 border-t border-border pt-4"
                        @submit.prevent="saveEdit"
                    >
                        <InputError :message="editForm.errors.role" />

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="field-label" :for="`e-name-${admin.id}`">الاسم</label>
                                <input :id="`e-name-${admin.id}`" v-model="editForm.name" class="field" required />
                                <InputError :message="editForm.errors.name" />
                            </div>
                            <div>
                                <label class="field-label" :for="`e-role-${admin.id}`">الدور</label>
                                <select :id="`e-role-${admin.id}`" v-model="editForm.role" class="field">
                                    <option v-for="role in roles" :key="role.value" :value="role.value">
                                        {{ role.label }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="editForm.is_active" type="checkbox" class="rounded border-input" />
                            الحساب شغّال
                        </label>

                        <div class="flex gap-2">
                            <button type="submit" class="btn-primary" :disabled="editForm.processing">احفظ</button>
                            <button type="button" class="btn-outline" @click="editingId = null">إلغاء</button>
                        </div>
                    </form>

                    <!-- Password -->
                    <form
                        v-if="passwordId === admin.id"
                        class="mt-4 space-y-3 border-t border-border pt-4"
                        @submit.prevent="savePassword"
                    >
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="field-label" :for="`p-${admin.id}`">باسورد جديد</label>
                                <input
                                    :id="`p-${admin.id}`"
                                    v-model="passwordForm.password"
                                    type="password"
                                    class="field"
                                    dir="ltr"
                                    required
                                />
                                <InputError :message="passwordForm.errors.password" />
                            </div>
                            <div>
                                <label class="field-label" :for="`pc-${admin.id}`">تأكيد</label>
                                <input
                                    :id="`pc-${admin.id}`"
                                    v-model="passwordForm.password_confirmation"
                                    type="password"
                                    class="field"
                                    dir="ltr"
                                    required
                                />
                            </div>
                        </div>
                        <button type="submit" class="btn-primary" :disabled="passwordForm.processing">غيّر الباسورد</button>
                    </form>
                </div>
            </div>

            <p class="text-xs text-muted-foreground">
                الحسابات بتتوقف ومبتتمسحش، عشان سجل التدقيق يفضل مربوط باسم. أول مشرف بيتعمل من السيرفر بأمر
                <span class="font-mono" dir="ltr">php artisan admin:create</span>.
            </p>
        </div>
    </AdminLayout>
</template>
