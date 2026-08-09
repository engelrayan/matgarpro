import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
    /**
     * The platform operator, when one is signed in. Null on every merchant
     * page — the two guards are independent, so both, either or neither may be
     * present on a given request.
     */
    admin: AdminUser | null;
}

export interface AdminUser {
    id: number;
    name: string;
    email: string;
    role: 'super' | 'staff';
    role_label: string;
    is_super: boolean;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    flash: { status?: string; error?: string };
    auth: Auth;
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;
