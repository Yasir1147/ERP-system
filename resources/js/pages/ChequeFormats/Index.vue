<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Copy, FilePlus2, Pencil, Search, Trash2, Upload } from 'lucide-vue-next';
import { ref } from 'vue';
import type { BankOption } from './types';

interface FormatRow {
    id: number;
    name: string;
    bankName: string;
    createdBy: string | null;
    fieldCount: number;
    updatedAt: string;
}

const props = defineProps<{
    formats: FormatRow[];
    pagination: {
        currentPage: number;
        lastPage: number;
        perPage: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    filters: { search: string; bankId: string; perPage: number };
    banks: BankOption[];
}>();

const page = usePage();
const search = ref(props.filters.search);
const bankId = ref(props.filters.bankId);
const perPage = ref(String(props.filters.perPage));

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Cheque Formats', href: '/cheque-formats' }];

const reload = (pageNumber = 1) => {
    router.get(
        '/cheque-formats',
        {
            search: search.value.trim() || undefined,
            bank_id: bankId.value || undefined,
            per_page: perPage.value,
            page: pageNumber,
        },
        { preserveState: true, replace: true },
    );
};

const clearFilters = () => {
    search.value = '';
    bankId.value = '';
    perPage.value = '10';
    reload();
};

const duplicateFormat = (format: FormatRow) => {
    if (!window.confirm(`Create a separate copy of ${format.name}?`)) return;
    router.post(`/cheque-formats/${format.id}/duplicate`);
};

const deleteFormat = (format: FormatRow) => {
    if (!window.confirm(`Delete ${format.name}? This cannot be undone.`)) return;
    router.delete(`/cheque-formats/${format.id}`);
};
</script>

<template>
    <Head title="Cheque Format List" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="cheque-format-module flex h-full min-w-0 flex-1 flex-col gap-4 p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-normal">Cheque Format List</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Configure reusable bank cheque layouts for accurate future printing.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button type="button" variant="outline" disabled title="Predefined formats are not available yet">
                        <Upload class="size-4" />Import Predefined (Coming Soon)
                    </Button>
                    <Button as-child>
                        <Link href="/cheque-formats/create"><FilePlus2 class="size-4" />Add New Cheque Format</Link>
                    </Button>
                </div>
            </div>

            <div v-if="page.props.flash?.success" class="rounded-md border border-green-600/30 bg-green-600/10 px-4 py-3 text-sm text-green-700">
                {{ page.props.flash.success }}
            </div>

            <section class="rounded-lg border bg-card shadow-sm">
                <form
                    class="grid gap-3 border-b p-4 md:grid-cols-[minmax(220px,1fr)_minmax(200px,0.6fr)_120px_auto_auto] md:items-end"
                    @submit.prevent="reload()"
                >
                    <div class="grid gap-1.5">
                        <Label for="format-search">Search format name</Label>
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input id="format-search" v-model="search" class="pl-9" placeholder="Search cheque formats" />
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="bank-filter">Bank</Label>
                        <select id="bank-filter" v-model="bankId" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                            <option value="">All banks</option>
                            <option v-for="bank in banks" :key="bank.id" :value="String(bank.id)">{{ bank.name }}</option>
                        </select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="per-page">Rows</Label>
                        <select id="per-page" v-model="perPage" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                            <option v-for="size in [10, 15, 25, 50]" :key="size" :value="String(size)">{{ size }}</option>
                        </select>
                    </div>
                    <Button type="submit">Apply Filters</Button>
                    <Button type="button" variant="outline" @click="clearFilters">Clear</Button>
                </form>

                <div v-if="pagination.total === 0" class="flex min-h-56 flex-col items-center justify-center gap-3 p-6 text-center">
                    <FilePlus2 class="size-9 text-muted-foreground" />
                    <div>
                        <p class="font-medium">No cheque formats found</p>
                        <p class="mt-1 text-sm text-muted-foreground">Add a bank and create your first cheque layout.</p>
                    </div>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[820px] text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">Cheque Format Name</th>
                                <th class="px-4 py-3 font-medium">Bank Name</th>
                                <th class="px-4 py-3 font-medium">Created By</th>
                                <th class="px-4 py-3 font-medium">Fields</th>
                                <th class="px-4 py-3 font-medium">Last Updated</th>
                                <th class="px-4 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="format in formats" :key="format.id" class="hover:bg-muted/30">
                                <td class="px-4 py-3 font-medium">{{ format.name }}</td>
                                <td class="px-4 py-3">{{ format.bankName }}</td>
                                <td class="px-4 py-3">{{ format.createdBy || '-' }}</td>
                                <td class="px-4 py-3">{{ format.fieldCount }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ format.updatedAt }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1">
                                        <Button as-child size="icon" variant="ghost" title="Edit cheque format">
                                            <Link :href="`/cheque-formats/${format.id}/edit`"><Pencil class="size-4" /></Link>
                                        </Button>
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            title="Duplicate cheque format"
                                            @click="duplicateFormat(format)"
                                            ><Copy class="size-4"
                                        /></Button>
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                            title="Delete cheque format"
                                            @click="deleteFormat(format)"
                                            ><Trash2 class="size-4"
                                        /></Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="pagination.total" class="flex flex-col gap-3 border-t p-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-muted-foreground">Showing {{ pagination.from }}-{{ pagination.to }} of {{ pagination.total }} formats</p>
                    <div v-if="pagination.lastPage > 1" class="flex gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            :disabled="pagination.currentPage === 1"
                            @click="reload(pagination.currentPage - 1)"
                            >Previous</Button
                        >
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            :disabled="pagination.currentPage === pagination.lastPage"
                            @click="reload(pagination.currentPage + 1)"
                            >Next</Button
                        >
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
