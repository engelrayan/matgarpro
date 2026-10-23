<script setup lang="ts">
import { useSidebar } from '@/components/ui/sidebar';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Ellipsis, LayoutGrid, LayoutTemplate, Package, ShoppingBag } from 'lucide-vue-next';

/*
 * The phone's tab bar.
 *
 * A merchant on a phone lives in two screens — orders and products — and the
 * hamburger menu made both of them two taps and a slide-out away. Apple's
 * answer to that is five tabs along the bottom, inside the thumb's reach; the
 * rest of the app stays one tap further, behind "المزيد", which opens the
 * same sidebar the desktop shows.
 */
const tabs = [
    { title: 'الرئيسية', href: '/dashboard', icon: LayoutGrid },
    { title: 'الطلبات', href: '/orders', icon: ShoppingBag },
    { title: 'المنتجات', href: '/products', icon: Package },
    { title: 'التصميم', href: '/builder', icon: LayoutTemplate },
];

const page = usePage<SharedData>();
const { setOpenMobile } = useSidebar();

const isActive = (href: string) => {
    const url = page.url.split('?')[0];

    return url === href || url.startsWith(`${href}/`);
};
</script>

<template>
    <nav
        class="material hairline-t fixed inset-x-0 bottom-0 z-40 grid grid-cols-5 pb-[env(safe-area-inset-bottom)] md:hidden"
        aria-label="التنقل الرئيسي"
    >
        <Link
            v-for="tab in tabs"
            :key="tab.href"
            :href="tab.href"
            class="flex min-h-[52px] flex-col items-center justify-center gap-0.5 pt-1.5 text-[10.5px] font-medium transition-colors active:opacity-60"
            :class="isActive(tab.href) ? 'text-primary' : 'text-muted-foreground'"
            :aria-current="isActive(tab.href) ? 'page' : undefined"
        >
            <component :is="tab.icon" class="size-6" :stroke-width="isActive(tab.href) ? 2.3 : 1.8" />
            {{ tab.title }}
        </Link>

        <button
            type="button"
            class="flex min-h-[52px] flex-col items-center justify-center gap-0.5 pt-1.5 text-[10.5px] font-medium text-muted-foreground transition-colors active:opacity-60"
            @click="setOpenMobile(true)"
        >
            <Ellipsis class="size-6" :stroke-width="1.8" />
            المزيد
        </button>
    </nav>
</template>
