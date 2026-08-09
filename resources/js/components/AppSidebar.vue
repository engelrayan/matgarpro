<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain, { type NavGroup } from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Boxes, Globe, LayoutGrid, LayoutList, LayoutTemplate, MessageCircle, Package, Palette, Radio, ScrollText, ShoppingBag, ShoppingCart, Store, TrendingUp, Truck } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

/*
 * Every destination lives here, in three groups by what the merchant is doing.
 *
 * Settings used to be reachable only through a second nav inside the settings
 * pages, so half the app was hidden behind a link that looked like a leaf. Two
 * navigations for one app is one too many — this is now the only one.
 *
 * Within each group, ordered by how often a working merchant opens it: orders
 * every hour, products every few days, the domain once.
 */
const navGroups: NavGroup[] = [
    {
        label: 'متجرك',
        items: [
            { title: 'لوحة التحكم', href: '/dashboard', icon: LayoutGrid },
            { title: 'الطلبات', href: '/orders', icon: ShoppingBag },
            { title: 'السلات المتروكة', href: '/carts', icon: ShoppingCart },
            { title: 'تقرير الربح', href: '/reports/profit', icon: TrendingUp },
            { title: 'المنتجات', href: '/products', icon: Package },
            { title: 'الأقسام', href: '/categories', icon: LayoutList },
        ],
    },
    {
        label: 'التصميم',
        items: [
            // First in its group on purpose: the builder is where a merchant
            // shapes the shop, and the theme is one decision inside that.
            { title: 'تصميم المتجر', href: '/builder', icon: LayoutTemplate },
            { title: 'الثيمات', href: '/settings/themes', icon: Palette },
            { title: 'فورم الطلب', href: '/settings/checkout', icon: ScrollText },
            { title: 'البيكسل', href: '/settings/pixels', icon: Radio },
            { title: 'الكاتالوج', href: '/settings/catalog', icon: Boxes },
        ],
    },
    {
        label: 'الإعدادات',
        items: [
            { title: 'بيانات المتجر', href: '/settings/store', icon: Store },
            { title: 'واتساب', href: '/settings/whatsapp', icon: MessageCircle },
            { title: 'الشحن مع ضمان', href: '/settings/daman', icon: Truck },
            { title: 'الدومين والحساب', href: '/settings/general', icon: Globe },
        ],
    },
];

const footerNavItems: NavItem[] = [];
</script>

<template>
    <!--
        `side="right"`, not the component's default left: the whole app is RTL,
        and a left rail puts navigation on the far side of where an Arabic
        reader starts every line.

        Kept on `collapsible="icon"`. Its collapsed state only hides the labels
        instead of narrowing the rail — the starter kit's width utilities lose
        to the base `w-[--sidebar-width]` — but the layout stays correct in both
        states. Switching to `offcanvas` does narrow it, and then the expanded
        rail overlaps the page content, which is the worse of the two.
    -->
    <Sidebar collapsible="icon" variant="inset" side="right">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :groups="navGroups" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
