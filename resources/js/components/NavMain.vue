<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

export interface NavGroup {
    label: string;
    items: NavItem[];
}

defineProps<{
    groups: NavGroup[];
}>();

const page = usePage<SharedData>();

/**
 * Highlight the section, not just the exact page.
 *
 * `/products/create` and `/products/3/edit` are both "المنتجات" as far as a
 * merchant is concerned — an exact match leaves the whole rail unlit the moment
 * they open a form.
 */
const isActive = (href: string): boolean => {
    const url = page.url.split('?')[0];

    return url === href || url.startsWith(`${href}/`);
};
</script>

<template>
    <SidebarGroup v-for="group in groups" :key="group.label" class="px-2 py-0">
        <SidebarGroupLabel>{{ group.label }}</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in group.items" :key="item.href">
                <!-- `item.href`, matching the shared NavItem type. A local
                     interface here once declared `url` instead, so every link
                     rendered with no destination and nothing navigated. -->
                <SidebarMenuButton as-child :is-active="isActive(item.href)" :tooltip="item.title">
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
