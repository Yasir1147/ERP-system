<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogScrollContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { FilePlus2, FileText, Pencil, Plus, Printer, Search, ShieldX } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface ChequeRow {
    id: number | null;
    chequeNumber: string;
    issueDate: string | null;
    chequeDate: string | null;
    partyName: string | null;
    payeeName: string | null;
    formatName: string | null;
    amount: number | null;
    voucherNumber: string | null;
    remarks: string | null;
    purpose: string | null;
    status: string | null;
    statusLabel: string | null;
    leafStatus: string;
    createdBy: string | null;
}

interface ChequeBookRow {
    id: number;
    reference: string;
    bankName: string | null;
    formatName: string | null;
    startNumber: string;
    endNumber: string;
    nextNumber: string | null;
    status: string;
    statusLabel: string;
    totalCount: number;
    availableCount: number;
    issuedCount: number;
    voidCount: number;
    remarks: string | null;
}

interface FormatOption {
    id: number;
    name: string;
    bankName: string | null;
}

const props = defineProps<{
    cheques: ChequeRow[];
    pagination: { currentPage: number; lastPage: number; perPage: number; total: number; from: number | null; to: number | null };
    filters: { search: string; bookId: string; status: string; perPage: number };
    books: ChequeBookRow[];
    bookFormats: FormatOption[];
}>();

const page = usePage();
const search = ref(props.filters.search);
const bookId = ref(props.filters.bookId);
const status = ref(props.filters.status);
const perPage = ref(String(props.filters.perPage));
const showBookForm = ref(false);
const printCheque = ref<ChequeRow | null>(null);
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Cheque Books & Cheques', href: '/cheques' }];
const selectedBook = computed(() => props.books.find((book) => String(book.id) === bookId.value) ?? null);
const activeBookExists = computed(() => props.books.some((book) => book.status === 'active'));

const bookForm = useForm({
    cheque_format_id: '',
    reference: '',
    start_number: '',
    end_number: '',
    received_date: '',
    remarks: '',
});

const reload = (pageNumber = 1) =>
    router.get(
        '/cheques',
        {
            search: search.value.trim() || undefined,
            book_id: bookId.value || undefined,
            status: status.value || undefined,
            per_page: perPage.value,
            page: pageNumber,
        },
        { preserveState: true, replace: true },
    );

const selectBook = (value: string) => {
    bookId.value = value;
    search.value = '';
    status.value = '';
    reload();
};

const clearFilters = () => {
    search.value = '';
    status.value = '';
    perPage.value = '10';
    reload();
};

const createBook = () => {
    bookForm.post('/cheque-books', {
        preserveScroll: true,
        onSuccess: () => {
            bookForm.reset();
            showBookForm.value = false;
        },
    });
};

const closeBook = () => {
    if (!selectedBook.value || !window.confirm(`Close cheque book ${selectedBook.value.reference}? Unused cheque numbers will not be issued.`)) return;
    router.post(`/cheque-books/${selectedBook.value.id}/close`);
};

const voidCheque = (cheque: ChequeRow) => {
    if (!cheque.id) return;
    if (window.confirm(`Mark cheque ${cheque.chequeNumber} void? This number will never be reused.`)) {
        router.delete(`/cheques/${cheque.id}`);
    }
};

const leafStatusLabel = (cheque: ChequeRow) => {
    if (cheque.leafStatus === 'available') return 'Available';
    if (cheque.leafStatus === 'void') return 'Void';
    return cheque.statusLabel || 'Issued';
};

const leafStatusClass = (cheque: ChequeRow) => {
    if (cheque.leafStatus === 'available') return 'border-blue-600/30 bg-blue-600/10 text-blue-700';
    if (cheque.leafStatus === 'void' || cheque.status === 'void') return 'border-red-600/30 bg-red-600/10 text-red-700';
    if (cheque.status === 'printed') return 'border-green-600/30 bg-green-600/10 text-green-700';
    return 'text-muted-foreground';
};
</script>

<template>
    <Head title="Cheque Books & Cheques" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="cheque-transaction-module flex h-full min-w-0 flex-1 flex-col gap-4 p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-normal">Cheque Books</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Manage physical cheque books, available leaves, issued cheques and print records.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button type="button" variant="outline" @click="showBookForm = true"><Plus class="size-4" />New Cheque Book</Button>
                    <Button as-child :disabled="!activeBookExists">
                        <Link v-if="activeBookExists" href="/cheques/create"><FilePlus2 class="size-4" />Prepare New Cheque</Link>
                        <span v-else><FilePlus2 class="size-4" />Prepare New Cheque</span>
                    </Button>
                </div>
            </div>

            <div v-if="page.props.flash?.success" class="rounded-md border border-green-600/30 bg-green-600/10 px-4 py-3 text-sm text-green-700">
                {{ page.props.flash.success }}
            </div>
            <div v-if="page.props.errors?.book" class="rounded-md border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                {{ page.props.errors.book }}
            </div>

            <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <button
                    v-for="book in books"
                    :key="book.id"
                    type="button"
                    class="rounded-lg border bg-card p-4 text-left shadow-sm transition hover:border-primary/50"
                    :class="bookId === String(book.id) ? 'border-primary ring-1 ring-primary/20' : ''"
                    @click="selectBook(String(book.id))"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="truncate font-semibold">{{ book.bankName || 'Bank' }} — {{ book.reference }}</div>
                            <div class="mt-1 truncate text-xs text-muted-foreground">{{ book.formatName }} · {{ book.startNumber }}–{{ book.endNumber }}</div>
                        </div>
                        <span class="rounded-full border px-2 py-1 text-xs capitalize" :class="book.status === 'active' ? 'border-green-600/30 bg-green-600/10 text-green-700' : 'text-muted-foreground'">
                            {{ book.statusLabel }}
                        </span>
                    </div>
                    <div class="mt-4 h-2 overflow-hidden rounded-full bg-muted">
                        <div class="h-full bg-primary" :style="{ width: `${book.totalCount ? ((book.issuedCount + book.voidCount) / book.totalCount) * 100 : 0}%` }" />
                    </div>
                    <div class="mt-2 grid grid-cols-3 gap-2 text-xs">
                        <span>Issued <strong>{{ book.issuedCount }}</strong></span>
                        <span>Available <strong>{{ book.availableCount }}</strong></span>
                        <span>Void <strong>{{ book.voidCount }}</strong></span>
                    </div>
                    <div v-if="book.status === 'active'" class="mt-2 text-xs text-muted-foreground">Next cheque: {{ book.nextNumber ?? 'None' }}</div>
                </button>

                <div v-if="!books.length" class="rounded-lg border border-dashed bg-card p-6 text-sm text-muted-foreground">
                    No cheque book exists yet. Create the first cheque book to begin preparing cheques.
                </div>
            </section>

            <section class="rounded-lg border bg-card shadow-sm">
                <div class="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="font-semibold">{{ selectedBook ? `${selectedBook.reference} Cheque Leaves` : 'Cheque Leaves' }}</h2>
                        <p v-if="selectedBook" class="mt-1 text-xs text-muted-foreground">
                            {{ selectedBook.bankName }} · {{ selectedBook.formatName }} · {{ selectedBook.startNumber }}–{{ selectedBook.endNumber }}
                        </p>
                    </div>
                    <Button v-if="selectedBook?.status === 'active'" type="button" size="sm" variant="outline" @click="closeBook">Close Book</Button>
                </div>

                <form class="grid gap-3 border-b p-4 md:grid-cols-[1fr_180px_100px_auto_auto] md:items-end" @submit.prevent="reload()">
                    <div class="grid gap-1.5">
                        <Label>Search</Label>
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input v-model="search" class="pl-9" placeholder="Cheque number, payee or remark" />
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Status</Label>
                        <select v-model="status" class="h-10 rounded-md border border-input bg-background px-2 text-sm">
                            <option value="">All statuses</option>
                            <option v-if="selectedBook" value="available">Available</option>
                            <option value="prepared">Prepared</option>
                            <option value="printed">Printed</option>
                            <option value="void">Void</option>
                        </select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Rows</Label>
                        <select v-model="perPage" class="h-10 rounded-md border border-input bg-background px-2 text-sm">
                            <option v-for="size in [10, 15, 25, 50]" :key="size" :value="String(size)">{{ size }}</option>
                        </select>
                    </div>
                    <Button type="submit">Apply</Button>
                    <Button type="button" variant="outline" @click="clearFilters">Clear</Button>
                </form>

                <div v-if="!pagination.total" class="flex min-h-52 items-center justify-center p-6 text-sm text-muted-foreground">No cheque leaves found.</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[1180px] text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">Cheque #</th>
                                <th class="px-4 py-3 font-medium">Issue Date</th>
                                <th class="px-4 py-3 font-medium">Cheque Date</th>
                                <th class="px-4 py-3 font-medium">Party / Payee</th>
                                <th class="px-4 py-3 text-right font-medium">Amount</th>
                                <th class="px-4 py-3 font-medium">Remark / Purpose</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="cheque in cheques" :key="`${cheque.chequeNumber}-${cheque.id ?? 'available'}`" class="hover:bg-muted/30">
                                <td class="px-4 py-3 font-medium tabular-nums">{{ cheque.chequeNumber }}</td>
                                <td class="px-4 py-3">{{ cheque.issueDate || '—' }}</td>
                                <td class="px-4 py-3">{{ cheque.chequeDate || '—' }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ cheque.payeeName || 'Unused cheque leaf' }}</div>
                                    <div class="text-xs text-muted-foreground">{{ cheque.partyName || '' }}</div>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ cheque.amount === null ? '—' : cheque.amount.toFixed(2) }}</td>
                                <td class="max-w-64 px-4 py-3">
                                    <div class="truncate">{{ cheque.remarks || cheque.purpose || '—' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full border px-2 py-1 text-xs" :class="leafStatusClass(cheque)">{{ leafStatusLabel(cheque) }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div v-if="cheque.id" class="flex justify-end gap-1">
                                        <Button v-if="cheque.status !== 'void'" as-child size="icon" variant="ghost" title="Edit">
                                            <Link :href="`/cheques/${cheque.id}/edit`"><Pencil class="size-4" /></Link>
                                        </Button>
                                        <Button v-if="cheque.status !== 'void'" type="button" size="icon" variant="ghost" title="Print options" @click="printCheque = cheque">
                                            <Printer class="size-4" />
                                        </Button>
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                            title="Mark Void"
                                            :disabled="cheque.status === 'void'"
                                            @click="voidCheque(cheque)"
                                        >
                                            <ShieldX class="size-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="pagination.total" class="flex flex-col gap-3 border-t p-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-muted-foreground">Showing {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total }} cheque leaves</p>
                    <div v-if="pagination.lastPage > 1" class="flex gap-2">
                        <Button type="button" size="sm" variant="outline" :disabled="pagination.currentPage === 1" @click="reload(pagination.currentPage - 1)">Previous</Button>
                        <Button type="button" size="sm" variant="outline" :disabled="pagination.currentPage === pagination.lastPage" @click="reload(pagination.currentPage + 1)">Next</Button>
                    </div>
                </div>
            </section>
        </div>

        <Dialog v-model:open="showBookForm">
            <DialogScrollContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Create Cheque Book</DialogTitle>
                    <DialogDescription>Create the physical cheque-number range. Numbers cannot overlap or be reused.</DialogDescription>
                </DialogHeader>
                <form class="grid gap-4 py-2 sm:grid-cols-2" @submit.prevent="createBook">
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label>Bank / Cheque Format *</Label>
                        <select v-model="bookForm.cheque_format_id" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                            <option value="">Select format</option>
                            <option v-for="format in bookFormats" :key="format.id" :value="String(format.id)">{{ format.bankName }} — {{ format.name }}</option>
                        </select>
                        <InputError :message="bookForm.errors.cheque_format_id" />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label>Book Reference *</Label>
                        <Input v-model="bookForm.reference" maxlength="100" placeholder="e.g. ADCB-2026-01" />
                        <InputError :message="bookForm.errors.reference" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Starting Cheque Number *</Label>
                        <Input v-model="bookForm.start_number" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="18" placeholder="e.g. 00100" @input="bookForm.start_number = bookForm.start_number.replace(/\D/g, '')" />
                        <InputError :message="bookForm.errors.start_number" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Ending Cheque Number *</Label>
                        <Input v-model="bookForm.end_number" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="18" placeholder="e.g. 00300" @input="bookForm.end_number = bookForm.end_number.replace(/\D/g, '')" />
                        <InputError :message="bookForm.errors.end_number" />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label>Received Date</Label>
                        <Input v-model="bookForm.received_date" type="date" />
                        <InputError :message="bookForm.errors.received_date" />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label>Remarks</Label>
                        <textarea v-model="bookForm.remarks" rows="3" class="rounded-md border border-input bg-background px-3 py-2 text-sm" />
                        <InputError :message="bookForm.errors.remarks" />
                    </div>
                </form>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="showBookForm = false">Cancel</Button>
                    <Button type="button" :disabled="bookForm.processing" @click="createBook">Create Cheque Book</Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>

        <Dialog :open="Boolean(printCheque)" @update:open="(open) => !open && (printCheque = null)">
            <DialogScrollContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Print Cheque {{ printCheque?.chequeNumber }}</DialogTitle>
                    <DialogDescription>Select the exact print output required.</DialogDescription>
                </DialogHeader>
                <div v-if="printCheque?.id" class="grid gap-2 py-2">
                    <Button as-child variant="outline" class="h-auto justify-start gap-3 p-3">
                        <a :href="`/cheques/${printCheque.id}/print`" target="_blank"><Printer class="size-4" /><span class="text-left"><strong class="block">Cheque Only</strong><small class="text-muted-foreground">Print positioned data on the physical cheque leaf.</small></span></a>
                    </Button>
                    <Button as-child variant="outline" class="h-auto justify-start gap-3 p-3">
                        <a :href="`/cheques/${printCheque.id}/voucher?include_cheque=0`" target="_blank"><FileText class="size-4" /><span class="text-left"><strong class="block">Voucher Only</strong><small class="text-muted-foreground">A4 voucher with data, without the cheque copy.</small></span></a>
                    </Button>
                    <Button as-child variant="outline" class="h-auto justify-start gap-3 p-3">
                        <a :href="`/cheques/${printCheque.id}/voucher?include_cheque=1`" target="_blank"><BookOpen class="size-4" /><span class="text-left"><strong class="block">Voucher with Cheque Copy</strong><small class="text-muted-foreground">A4 voucher including the cheque copy at the top.</small></span></a>
                    </Button>
                </div>
            </DialogScrollContent>
        </Dialog>
    </AppLayout>
</template>
