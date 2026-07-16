<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import {
    Banknote,
    BriefcaseBusiness,
    Building2,
    CalendarCheck,
    CircleDollarSign,
    FileCheck2,
    LayoutGrid,
    Plane,
    ReceiptText,
    UserCog,
    Users,
} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Attendance',
        href: '/attendance',
        icon: CalendarCheck,
        items: [
            {
                title: 'Overview',
                href: '/attendance',
            },
            {
                title: 'Timesheet',
                href: '/attendance/timesheet',
            },
            {
                title: 'Contracting Attendance',
                href: '/mark-attendance/contracting',
            },
            {
                title: 'Rope Access Attendance',
                href: '/mark-attendance/rope-access',
            },
        ],
    },
    {
        title: 'Employees',
        href: '/employees',
        icon: Users,
        items: [
            {
                title: 'Rope Access Employee',
                href: '/employees/rope_access',
            },
            {
                title: 'Contracting Employee',
                href: '/employees/contracting',
            },
        ],
    },
    {
        title: 'Leaves',
        href: '/employee-leaves',
        icon: Plane,
    },
    {
        title: 'Fines',
        href: '/fines',
        icon: ReceiptText,
    },
    {
        title: 'Expenses',
        href: '/expenses',
        icon: CircleDollarSign,
    },
    {
        title: 'Office Staff',
        href: '/office-staff',
        icon: Building2,
        items: [
            {
                title: 'Staff List',
                href: '/office-staff',
            },
            {
                title: 'Attendance Report',
                href: '/office-attendance/report',
            },
        ],
    },
    {
        title: 'Payroll',
        href: '/payroll',
        icon: Banknote,
        items: [
            {
                title: 'Salary Settings',
                href: '/payroll',
            },
            {
                title: 'Payroll Report',
                href: '/payroll/report',
            },
        ],
    },
    {
        title: 'Cheques',
        href: '/cheques',
        icon: FileCheck2,
        items: [
            {
                title: 'Cheque List',
                href: '/cheques',
            },
            {
                title: 'Prepare Cheque',
                href: '/cheques/create',
            },
            {
                title: 'Party Master',
                href: '/cheque-parties',
            },
            {
                title: 'Cheque Formats',
                href: '/cheque-formats',
            },
        ],
    },
    {
        title: 'Projects',
        href: '/projects',
        icon: BriefcaseBusiness,
        items: [
            {
                title: 'Overview',
                href: '/projects/overview',
            },
            {
                title: 'Rope Access Projects',
                href: '/projects/rope_access',
            },
            {
                title: 'Contracting Projects',
                href: '/projects/contracting',
            },
        ],
    },
    {
        title: 'Users',
        href: '/users',
        icon: UserCog,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
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
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
