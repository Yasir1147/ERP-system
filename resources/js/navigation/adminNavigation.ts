import type { NavItem } from '@/types';
import {
    Banknote,
    BriefcaseBusiness,
    Building2,
    CalendarCheck,
    CircleDollarSign,
    FileCheck2,
    FileClock,
    LayoutGrid,
    Plane,
    ReceiptText,
    ShoppingCart,
    UserCog,
    Users,
} from 'lucide-vue-next';

export const adminNavigation: NavItem[] = [
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
            { title: 'Overview', href: '/attendance' },
            { title: 'Timesheet', href: '/attendance/timesheet' },
            { title: 'Statement', href: '/attendance/statement' },
            { title: 'Contracting Attendance', href: '/mark-attendance/contracting' },
            { title: 'Rope Access Attendance', href: '/mark-attendance/rope-access' },
        ],
    },
    {
        title: 'Employees',
        href: '/employees',
        icon: Users,
        items: [
            { title: 'Rope Access Employee', href: '/employees/rope_access' },
            { title: 'Contracting Employee', href: '/employees/contracting' },
        ],
    },
    {
        title: 'Leaves',
        href: '/employee-leaves',
        icon: Plane,
    },
    {
        title: 'Projects',
        href: '/projects',
        icon: BriefcaseBusiness,
        items: [
            { title: 'Overview', href: '/projects/overview' },
            { title: 'Rope Access Projects', href: '/projects/rope_access' },
            { title: 'Contracting Projects', href: '/projects/contracting' },
        ],
    },
    {
        title: 'Procurement',
        href: '/suppliers',
        icon: ShoppingCart,
        items: [
            { title: 'Suppliers', href: '/suppliers' },
            { title: 'Purchase Bills', href: '/purchase-bills' },
            { title: 'Equipment Register', href: '/equipment' },
        ],
    },
    {
        title: 'Payroll',
        href: '/payroll',
        icon: Banknote,
        items: [
            { title: 'Salary Settings', href: '/payroll' },
            { title: 'Payroll Report', href: '/payroll/report' },
        ],
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
        title: 'Cheques',
        href: '/cheques',
        icon: FileCheck2,
        items: [
            { title: 'Cheque Books & List', href: '/cheques' },
            { title: 'Prepare Cheque', href: '/cheques/create' },
            { title: 'Party Master', href: '/cheque-parties' },
            { title: 'Cheque Formats', href: '/cheque-formats' },
        ],
    },
    {
        title: 'Office Staff',
        href: '/office-staff',
        icon: Building2,
        items: [
            { title: 'Staff List', href: '/office-staff' },
            { title: 'Attendance Report', href: '/office-attendance/report' },
        ],
    },
    {
        title: 'Documents',
        href: '/employee-documents',
        icon: FileClock,
    },
    {
        title: 'Users',
        href: '/users',
        icon: UserCog,
    },
];

export const normalizedPath = (url: string): string => url.split('?')[0].split('#')[0] || '/';

export const pathMatches = (url: string, href: string): boolean => {
    const path = normalizedPath(url);

    return path === href || path.startsWith(`${href}/`);
};

export const findActiveModule = (url: string): NavItem => {
    const childMatch = adminNavigation.find((item) => item.items?.some((child) => pathMatches(url, child.href)));

    return childMatch ?? adminNavigation.find((item) => pathMatches(url, item.href)) ?? adminNavigation[0];
};
