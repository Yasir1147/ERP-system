<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { FilePlus2, FileText, Pencil, Printer, Search, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import type { ChequePartyOption } from './types';

interface ChequeRow {
    id: number;
    chequeNumber: string | null;
    date: string;
    partyName: string;
    payeeName: string;
    formatName: string;
    amount: number;
    voucherNumber: string | null;
    status: string;
    statusLabel: string;
    createdBy: string | null;
}
interface SimpleOption {
    id: number;
    name: string;
}

const props = defineProps<{
    cheques: ChequeRow[];
    pagination: { currentPage: number; lastPage: number; perPage: number; total: number; from: number | null; to: number | null };
    filters: { search: string; formatId: string; partyId: string; perPage: number };
    formats: SimpleOption[];
    parties: ChequePartyOption[];
}>();

const page = usePage();
const search = ref(props.filters.search);
const formatId = ref(props.filters.formatId);
const partyId = ref(props.filters.partyId);
const perPage = ref(String(props.filters.perPage));
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Cheques', href: '/cheques' }];

const reload = (pageNumber = 1) =>
    router.get(
        '/cheques',
        {
            search: search.value.trim() || undefined,
            format_id: formatId.value || undefined,
            party_id: partyId.value || undefined,
            per_page: perPage.value,
            page: pageNumber,
        },
        { preserveState: true, replace: true },
    );
const clearFilters = () => {
    search.value = '';
    formatId.value = '';
    partyId.value = '';
    perPage.value = '10';
    reload();
};
const deleteCheque = (cheque: ChequeRow) => {
    if (window.confirm(`Delete cheque ${cheque.chequeNumber || `#${cheque.id}`}?`)) router.delete(`/cheques/${cheque.id}`);
};
</script>

<template>
    <Head title="Cheques" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="cheque-transaction-module flex h-full min-w-0 flex-1 flex-col gap-4 p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-normal">Cheque Preparation</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Prepare, review and print text onto physical pre-printed cheques.</p>
                </div>
                <Button as-child
                    ><Link href="/cheques/create"><FilePlus2 class="size-4" />Prepare New Cheque</Link></Button
                >
            </div>
            <div v-if="page.props.flash?.success" class="rounded-md border border-green-600/30 bg-green-600/10 px-4 py-3 text-sm text-green-700">
                {{ page.props.flash.success }}
            </div>
            <section class="rounded-lg border bg-card shadow-sm">
                <form class="grid gap-3 border-b p-4 md:grid-cols-[1fr_180px_180px_100px_auto_auto] md:items-end" @submit.prevent="reload()">
                    <div class="grid gap-1.5">
                        <Label>Search</Label>
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" /><Input
                                v-model="search"
                                class="pl-9"
                                placeholder="Cheque, voucher or payee"
                            />
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Format</Label
                        ><select v-model="formatId" class="h-10 rounded-md border border-input bg-background px-2 text-sm">
                            <option value="">All formats</option>
                            <option v-for="format in formats" :key="format.id" :value="String(format.id)">{{ format.name }}</option>
                        </select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Party</Label
                        ><select v-model="partyId" class="h-10 rounded-md border border-input bg-background px-2 text-sm">
                            <option value="">All parties</option>
                            <option v-for="party in parties" :key="party.id" :value="String(party.id)">{{ party.name }}</option>
                        </select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Rows</Label
                        ><select v-model="perPage" class="h-10 rounded-md border border-input bg-background px-2 text-sm">
                            <option v-for="size in [10, 15, 25, 50]" :key="size" :value="String(size)">{{ size }}</option>
                        </select>
                    </div>
                    <Button type="submit">Apply</Button><Button type="button" variant="outline" @click="clearFilters">Clear</Button>
                </form>
                <div v-if="!pagination.total" class="flex min-h-52 items-center justify-center p-6 text-sm text-muted-foreground">
                    No prepared cheques found.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[1050px] text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">Date</th>
                                <th class="px-4 py-3 font-medium">Cheque #</th>
                                <th class="px-4 py-3 font-medium">Party / Payee</th>
                                <th class="px-4 py-3 font-medium">Format</th>
                                <th class="px-4 py-3 text-right font-medium">Amount</th>
                                <th class="px-4 py-3 font-medium">Voucher</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="cheque in cheques" :key="cheque.id" class="hover:bg-muted/30">
                                <td class="px-4 py-3">{{ cheque.date }}</td>
                                <td class="px-4 py-3 font-medium">{{ cheque.chequeNumber || '-' }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ cheque.payeeName }}</div>
                                    <div class="text-xs text-muted-foreground">{{ cheque.partyName }}</div>
                                </td>
                                <td class="px-4 py-3">{{ cheque.formatName }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ cheque.amount.toFixed(2) }}</td>
                                <td class="px-4 py-3">{{ cheque.voucherNumber || '-' }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-full border px-2 py-1 text-xs"
                                        :class="
                                            cheque.status === 'printed'
                                                ? 'border-green-600/30 bg-green-600/10 text-green-700'
                                                : 'text-muted-foreground'
                                        "
                                        >{{ cheque.statusLabel }}</span
                                    >
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1">
                                        <Button as-child size="icon" variant="ghost" title="Edit"
                                            ><Link :href="`/cheques/${cheque.id}/edit`"><Pencil class="size-4" /></Link></Button
                                        ><Button as-child size="icon" variant="ghost" title="Cheque Print / PDF"
                                            ><a :href="`/cheques/${cheque.id}/print`" target="_blank"><Printer class="size-4" /></a></Button
                                        ><Button as-child size="icon" variant="ghost" title="Voucher Print / PDF"
                                            ><a :href="`/cheques/${cheque.id}/voucher`" target="_blank"><FileText class="size-4" /></a></Button
                                        ><Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                            title="Delete"
                                            @click="deleteCheque(cheque)"
                                            ><Trash2 class="size-4"
                                        /></Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="pagination.total" class="flex flex-col gap-3 border-t p-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-muted-foreground">Showing {{ pagination.from }}-{{ pagination.to }} of {{ pagination.total }} cheques</p>
                    <div v-if="pagination.lastPage > 1" class="flex gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            :disabled="pagination.currentPage === 1"
                            @click="reload(pagination.currentPage - 1)"
                            >Previous</Button
                        ><Button
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
