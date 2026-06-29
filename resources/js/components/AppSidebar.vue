<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeftRightIcon, BookOpen, Box, Calculator, FolderGit2, Landmark, LayoutGrid, NotebookPen, ShoppingCart, Truck, Utensils } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';
import menus from '@/routes/menus';
import suppliers from '@/routes/suppliers';
import materials from '@/routes/materials';
import purchase from '@/routes/purchase';
import overhead from '@/routes/overhead';
import account from '@/routes/account';
import accountMapping from '@/routes/account-mapping';
import journalEntry from '@/routes/journal';
import journal from '@/routes/journal';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Menu',
        href: menus.index(),
        icon: Utensils,
    },
    {
        title: 'Supplier',
        href: suppliers.index(),
        icon: Truck,
    },
    {
        title: 'Material',
        href: materials.index(),
        icon: Box,
    },
    {
        title: 'Purchase',
        href: purchase.index(),
        icon: ShoppingCart,
    },
    {
        title: 'Overhead',
        href: overhead.index(),
        icon: Calculator,
    },
    {
    title: 'Finance',
    icon: Landmark,
    children: [
        {
            title: 'Account',
            href: account.index(),
            icon: BookOpen,
        },
        {
            title: 'Mapping',
            href: accountMapping.index(),
            icon: ArrowLeftRightIcon,
        },
        {
            title: 'Jurnal',
            href: journal.index(),
            icon: NotebookPen,
        },
    ],
}
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
