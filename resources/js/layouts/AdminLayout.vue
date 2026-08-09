<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Activity,
    CreditCard,
    Globe,
    LayoutGrid,
    LogOut,
    Menu,
    Palette,
    ShieldCheck,
    Store,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/*
 * The platform panel's own shell.
 *
 * Deliberately NOT AppLayout. An operator and a merchant are looking at two
 * different applications through one browser, and the single most expensive
 * mistake available here is acting on the wrong one — suspending a store you
 * thought was your own test shop. So this chrome is dark where the merchant
 * dashboard is light, and it says "لوحة المنصة" at the top of every page.
 *
 * Also not the shadcn Sidebar: that one is tuned for the merchant app's
 * collapse behaviour, and a second consumer of it would mean every future
 * tweak has to be checked against two apps.
 */

defineProps<{ title: string; subtitle?: string }>();

interface AdminUser {
    name: string;
    email: string;
    role_label: string;
    is_super: boolean;
}

const page = usePage();
const admin = computed(() => page.props.auth?.admin as AdminUser | null);
const flash = computed(() => page.props.flash as { status?: string; error?: string });

const NAV = computed(() => [
    {
        label: 'المتابعة',
        items: [
            { title: 'نظرة عامة', href: '/admin', icon: LayoutGrid, exact: true },
            { title: 'المتاجر', href: '/admin/stores', icon: Store },
            { title: 'التجار', href: '/admin/merchants', icon: Users },
        ],
    },
    {
        label: 'المنصة',
        items: [
            { title: 'الثيمات', href: '/admin/themes', icon: Palette },
            { title: 'الدومينات', href: '/admin/domains', icon: Globe },
            ...(admin.value?.is_super ? [{ title: 'الخطط والأسعار', href: '/admin/plans', icon: CreditCard }] : []),
        ],
    },
    {
        label: 'الرقابة',
        items: [
            { title: 'سجل التدقيق', href: '/admin/activity', icon: Activity },
            ...(admin.value?.is_super ? [{ title: 'المشرفين', href: '/admin/admins', icon: ShieldCheck }] : []),
        ],
    },
]);

const currentPath = computed(() => new URL(page.url, 'http://x').pathname);

const isActive = (href: string, exact = false) =>
    exact ? currentPath.value === href : currentPath.value.startsWith(href);

const mobileOpen = ref(false);
watch(currentPath, () => (mobileOpen.value = false));

const logout = () => router.post('/admin/logout');
</script>

<template>
    <div class="min-h-screen bg-muted/40" dir="rtl">
        <!-- ── Rail ──────────────────────────────────────────────────────── -->
        <aside
            class="fixed inset-y-0 right-0 z-40 flex w-64 flex-col bg-jade-950 text-jade-50 transition-transform lg:translate-x-0"
            :class="mobileOpen ? 'translate-x-0' : 'translate-x-full'"
        >
            <div class="flex items-center justify-between gap-2 px-5 py-5">
                <div class="min-w-0">
                    <p class="text-sm font-bold tracking-tight">لوحة المنصة</p>
                    <p class="truncate text-xs text-jade-300">إدارة متاجر</p>
                </div>
                <button class="rounded-lg p-1.5 hover:bg-white/10 lg:hidden" @click="mobileOpen = false">
                    <X class="size-5" />
                </button>
            </div>

            <nav class="flex-1 space-y-6 overflow-y-auto px-3 pb-4">
                <div v-for="group in NAV" :key="group.label">
                    <p class="px-3 pb-2 text-[11px] font-medium uppercase tracking-wider text-jade-400">
                        {{ group.label }}
                    </p>
                    <ul class="space-y-0.5">
                        <li v-for="item in group.items" :key="item.href">
                            <Link
                                :href="item.href"
                                class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm transition-colors"
                                :class="
                                    isActive(item.href, item.exact)
                                        ? 'bg-white/10 font-medium text-white'
                                        : 'text-jade-200 hover:bg-white/5 hover:text-white'
                                "
                            >
                                <component :is="item.icon" class="size-4 shrink-0" />
                                {{ item.title }}
                            </Link>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="border-t border-white/10 p-3">
                <div class="px-2 pb-2">
                    <p class="truncate text-sm font-medium">{{ admin?.name }}</p>
                    <p class="truncate text-xs text-jade-300">{{ admin?.role_label }}</p>
                </div>
                <button
                    class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-sm text-jade-200 transition-colors hover:bg-white/5 hover:text-white"
                    @click="logout"
                >
                    <LogOut class="size-4" />
                    خروج
                </button>
            </div>
        </aside>

        <!-- Backdrop, mobile only. -->
        <div v-if="mobileOpen" class="fixed inset-0 z-30 bg-black/40 lg:hidden" @click="mobileOpen = false" />

        <!-- ── Content ───────────────────────────────────────────────────── -->
        <div class="lg:mr-64">
            <header class="sticky top-0 z-20 border-b border-border bg-card/80 backdrop-blur">
                <div class="flex items-center gap-3 px-4 py-4 md:px-6">
                    <button class="rounded-lg p-2 hover:bg-muted lg:hidden" @click="mobileOpen = true">
                        <Menu class="size-5" />
                    </button>
                    <div class="min-w-0 flex-1">
                        <h1 class="truncate text-lg font-bold tracking-tight">{{ title }}</h1>
                        <p v-if="subtitle" class="truncate text-sm text-muted-foreground">{{ subtitle }}</p>
                    </div>
                    <slot name="actions" />
                </div>
            </header>

            <!--
                Flash lives in the layout, not in each page: every admin action
                redirects back, and a page that forgot to render the result
                looks like a click that did nothing.
            -->
            <div v-if="flash?.status || flash?.error" class="px-4 pt-4 md:px-6">
                <p
                    class="rounded-xl px-4 py-3 text-sm"
                    :class="flash.error ? 'bg-destructive/10 text-destructive' : 'bg-success/10 text-success'"
                >
                    {{ flash.error ?? 'تمام، اتحفظ.' }}
                </p>
            </div>

            <main class="p-4 md:p-6">
                <slot />
            </main>
        </div>
    </div>
</template>
