<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2, Users, X } from 'lucide-vue-next';
import { ref } from 'vue';

interface PartyRow {
    id: number;
    name: string;
    contactPerson: string | null;
    email: string | null;
    mobile: string | null;
    phone: string | null;
    fax: string | null;
    address: string | null;
    remarks: string | null;
    isActive: boolean;
    chequeCount: number;
}

const props = defineProps<{
    parties: PartyRow[];
    pagination: { currentPage: number; lastPage: number; perPage: number; total: number; from: number | null; to: number | null };
    filters: { search: string; perPage: number };
}>();

const page = usePage();
const search = ref(props.filters.search);
const perPage = ref(String(props.filters.perPage));
const editingId = ref<number | null>(null);
const showForm = ref(false);

const partyForm = useForm({
    name: '',
    contact_person: '',
    email: '',
    mobile: '',
    phone: '',
    fax: '',
    address: '',
    remarks: '',
    is_active: true,
});

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Cheque Parties', href: '/cheque-parties' }];

const resetForm = () => {
    editingId.value = null;
    showForm.value = false;
    partyForm.reset();
    partyForm.clearErrors();
};

const editParty = (party: PartyRow) => {
    editingId.value = party.id;
    showForm.value = true;
    partyForm.name = party.name;
    partyForm.contact_person = party.contactPerson ?? '';
    partyForm.email = party.email ?? '';
    partyForm.mobile = party.mobile ?? '';
    partyForm.phone = party.phone ?? '';
    partyForm.fax = party.fax ?? '';
    partyForm.address = party.address ?? '';
    partyForm.remarks = party.remarks ?? '';
    partyForm.is_active = party.isActive;
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const saveParty = () => {
    const options = { preserveScroll: true, errorBag: 'party', onSuccess: resetForm };
    if (editingId.value) partyForm.put(`/cheque-parties/${editingId.value}`, options);
    else partyForm.post('/cheque-parties', options);
};

const deleteParty = (party: PartyRow) => {
    if (!window.confirm(`Delete ${party.name}?`)) return;
    router.delete(`/cheque-parties/${party.id}`, { preserveScroll: true });
};

const reload = (pageNumber = 1) => {
    router.get(
        '/cheque-parties',
        {
            search: search.value.trim() || undefined,
            per_page: perPage.value,
            page: pageNumber,
        },
        { preserveState: true, replace: true },
    );
};
</script>

<template>
    <Head title="Cheque Parties" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="cheque-party-module flex h-full min-w-0 flex-1 flex-col gap-4 p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-normal">Party Master</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Maintain payees that can be selected while preparing cheques.</p>
                </div>
                <Button
                    type="button"
                    @click="
                        showForm = true;
                        editingId = null;
                        partyForm.reset();
                    "
                    ><Plus class="size-4" />Add Party</Button
                >
            </div>

            <div v-if="page.props.flash?.success" class="rounded-md border border-green-600/30 bg-green-600/10 px-4 py-3 text-sm text-green-700">
                {{ page.props.flash.success }}
            </div>
            <div v-if="page.props.errors?.party" class="rounded-md border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                {{ page.props.errors.party }}
            </div>

            <section v-if="showForm" class="rounded-lg border bg-card shadow-sm">
                <div class="flex items-center justify-between border-b p-4">
                    <div>
                        <h2 class="font-medium">{{ editingId ? 'Edit Party' : 'Add Party' }}</h2>
                        <p class="mt-1 text-xs text-muted-foreground">Only Party Name is required.</p>
                    </div>
                    <Button type="button" size="icon" variant="ghost" @click="resetForm"><X class="size-4" /></Button>
                </div>
                <form class="grid gap-4 p-4 md:grid-cols-2" @submit.prevent="saveParty">
                    <div class="grid gap-1.5">
                        <Label for="party-name">Party Name *</Label><Input id="party-name" v-model="partyForm.name" maxlength="255" /><InputError
                            :message="partyForm.errors.name"
                        />
                    </div>
                    <div class="grid gap-1.5"><Label>Contact Person</Label><Input v-model="partyForm.contact_person" maxlength="255" /></div>
                    <div class="grid gap-1.5">
                        <Label>Email</Label><Input v-model="partyForm.email" type="email" maxlength="255" /><InputError
                            :message="partyForm.errors.email"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-1.5"><Label>Mobile</Label><Input v-model="partyForm.mobile" maxlength="50" /></div>
                        <div class="grid gap-1.5"><Label>Phone</Label><Input v-model="partyForm.phone" maxlength="50" /></div>
                    </div>
                    <div class="grid gap-1.5"><Label>Fax</Label><Input v-model="partyForm.fax" maxlength="50" /></div>
                    <label class="flex items-center gap-2 self-end rounded-md border p-3 text-sm"
                        ><input v-model="partyForm.is_active" type="checkbox" class="size-4 rounded border-input" />Active and selectable</label
                    >
                    <div class="grid gap-1.5">
                        <Label>Address</Label
                        ><textarea v-model="partyForm.address" rows="3" class="rounded-md border border-input bg-background px-3 py-2 text-sm" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Remarks</Label
                        ><textarea v-model="partyForm.remarks" rows="3" class="rounded-md border border-input bg-background px-3 py-2 text-sm" />
                    </div>
                    <div class="flex justify-end gap-2 md:col-span-2">
                        <Button type="button" variant="outline" @click="resetForm">Cancel</Button
                        ><Button type="submit" :disabled="partyForm.processing">{{ partyForm.processing ? 'Saving...' : 'Save Party' }}</Button>
                    </div>
                </form>
            </section>

            <section class="rounded-lg border bg-card shadow-sm">
                <form class="grid gap-3 border-b p-4 sm:grid-cols-[1fr_120px_auto] sm:items-end" @submit.prevent="reload()">
                    <div class="grid gap-1.5">
                        <Label for="party-search">Search</Label>
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" /><Input
                                id="party-search"
                                v-model="search"
                                class="pl-9"
                                placeholder="Name, contact, email or phone"
                            />
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Rows</Label
                        ><select v-model="perPage" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                            <option v-for="size in [10, 15, 25, 50]" :key="size" :value="String(size)">{{ size }}</option>
                        </select>
                    </div>
                    <Button type="submit">Search</Button>
                </form>

                <div v-if="!pagination.total" class="flex min-h-52 flex-col items-center justify-center gap-2 p-6 text-center">
                    <Users class="size-9 text-muted-foreground" />
                    <p class="font-medium">No parties found</p>
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">Party Name</th>
                                <th class="px-4 py-3 font-medium">Contact</th>
                                <th class="px-4 py-3 font-medium">Email</th>
                                <th class="px-4 py-3 font-medium">Phone</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 font-medium">Cheques</th>
                                <th class="px-4 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="party in parties" :key="party.id" class="hover:bg-muted/30">
                                <td class="px-4 py-3 font-medium">{{ party.name }}</td>
                                <td class="px-4 py-3">{{ party.contactPerson || '-' }}</td>
                                <td class="px-4 py-3">{{ party.email || '-' }}</td>
                                <td class="px-4 py-3">{{ party.mobile || party.phone || '-' }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-full border px-2 py-1 text-xs"
                                        :class="party.isActive ? 'border-green-600/30 bg-green-600/10 text-green-700' : 'text-muted-foreground'"
                                        >{{ party.isActive ? 'Active' : 'Inactive' }}</span
                                    >
                                </td>
                                <td class="px-4 py-3">{{ party.chequeCount }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1">
                                        <Button type="button" size="icon" variant="ghost" title="Edit party" @click="editParty(party)"
                                            ><Pencil class="size-4" /></Button
                                        ><Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                            title="Delete party"
                                            :disabled="party.chequeCount > 0"
                                            @click="deleteParty(party)"
                                            ><Trash2 class="size-4"
                                        /></Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="pagination.total" class="flex flex-col gap-3 border-t p-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-muted-foreground">Showing {{ pagination.from }}-{{ pagination.to }} of {{ pagination.total }} parties</p>
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
