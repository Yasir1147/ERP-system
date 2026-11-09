export type SectionKey = 'title' | 'logo' | 'identity' | 'grid' | 'footer';
export type CardSection = {
    x: number;
    y: number;
    width: number;
    height: number;
    font_size: number;
    visible: boolean;
};
export type CardLayout = {
    width: number;
    height: number;
    font: string;
    font_size: number;
    title: string;
    text_color: string;
    border_color: string;
    header_color: string;
    rest_color: string;
    rest_day: number;
    show_ot_site: boolean;
    show_signature: boolean;
    attendance_label: string;
    site_label: string;
    overtime_label: string;
    admin_label: string;
    employee_label: string;
    sections: Record<SectionKey, CardSection>;
};
export interface CardTemplate {
    id: number | null;
    name: string;
    version: number;
    layout: CardLayout;
    logoUrl: string;
    signatureUrl: string | null;
}
export interface CardDay {
    day: number;
    date: string;
    mark: string;
    dayValue: number;
    leave: number;
    site: string;
    projectName: string | null;
    otSite: string;
    otProjectName: string | null;
    overtimeMinutes: number;
    overtime: string;
    restLabel: string | null;
    note: string | null;
}
export interface AttendanceCard {
    employeeId: number;
    name: string;
    code: string;
    profession: string;
    month: string;
    monthLabel: string;
    year: number;
    days: CardDay[];
    presentDays: number;
    absentDays: number;
    leaveDays: number;
    overtimeMinutes: number;
    overtimeLabel: string;
}
export interface CardFilters {
    month: string;
    templateId: number | null;
    employeeType: string;
    employeeIds: number[];
    allEmployees: boolean;
    perPage: number;
}
export interface CardReport {
    filters: CardFilters;
    template: CardTemplate;
    cards: AttendanceCard[];
}
export function cardQuery(filters: CardFilters): string {
    const params = new URLSearchParams({ month: filters.month, employee_type: filters.employeeType, per_page: String(filters.perPage) });
    if (filters.templateId) params.set('template_id', String(filters.templateId));
    if (filters.allEmployees) params.set('all_employees', '1');
    else filters.employeeIds.forEach((id) => params.append('employee_ids[]', String(id)));
    return params.toString();
}
export function sampleCard(layout: CardLayout): AttendanceCard {
    const days: CardDay[] = Array.from({ length: 31 }, (_, index) => {
        const day = index + 1;
        const date = new Date(2026, 9, day);
        const rest = date.getDay() === layout.rest_day;
        return {
            day,
            date: `2026-10-${String(day).padStart(2, '0')}`,
            mark: rest ? '' : 'P',
            dayValue: rest ? 0 : 1,
            leave: 0,
            site: rest ? '' : 'PR 267',
            projectName: rest ? null : 'Sample Project',
            otSite: '',
            otProjectName: null,
            overtimeMinutes: rest ? 0 : 90,
            overtime: rest ? '' : '1:30',
            restLabel: rest ? date.toLocaleDateString('en-US', { weekday: 'long' }) : null,
            note: null,
        };
    });
    const present = days.reduce((sum, d) => sum + d.dayValue, 0);
    return {
        employeeId: 0,
        name: 'SAMPLE EMPLOYEE',
        code: '001',
        profession: 'FOREMAN / TILE FIXER',
        month: 'Oct',
        monthLabel: 'October 2026',
        year: 2026,
        days,
        presentDays: present,
        absentDays: 0,
        leaveDays: 0,
        overtimeMinutes: present * 90,
        overtimeLabel: `${present * 1.5} hr`,
    };
}
