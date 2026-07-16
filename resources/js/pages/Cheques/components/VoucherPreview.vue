<script setup lang="ts">
defineProps<{
    chequeNumber: string;
    issuedDate: string;
    chequeDate: string;
    amount: number;
    amountWords: string;
    beneficiary: string;
    purpose: string;
    receivedBy: string;
    receiverId: string;
    receiverMobile: string;
    preparedBy: string;
    checkedBy: string;
    approvedBy: string;
}>();

const displayDate = (date: string) => {
    const [year, month, day] = date.split('-');
    return year && month && day ? `${day}/${month}/${year}` : '';
};
</script>

<template>
    <section class="rounded-lg border bg-card shadow-sm">
        <div class="border-b p-4">
            <h2 class="font-medium">Payment Voucher Preview</h2>
            <p class="mt-1 text-xs text-muted-foreground">This voucher can be printed or saved as a separate PDF after saving the cheque.</p>
        </div>
        <div class="overflow-auto bg-muted/20 p-4 sm:p-6">
            <div class="mx-auto min-w-[720px] max-w-4xl border-2 border-slate-900 bg-white p-6 text-sm text-slate-950 shadow-sm">
                <div class="mb-5 text-center text-xl font-bold uppercase">Cheque Payment Voucher</div>
                <div class="grid grid-cols-3 border border-slate-900">
                    <div class="border-r border-slate-900 p-2"><strong>Cheque #:</strong> {{ chequeNumber || '—' }}</div>
                    <div class="border-r border-slate-900 p-2"><strong>Issued Date:</strong> {{ displayDate(issuedDate) || '—' }}</div>
                    <div class="p-2"><strong>Cheque Date:</strong> {{ displayDate(chequeDate) || '—' }}</div>
                    <div class="border-r border-t border-slate-900 p-2">
                        <strong>Amount:</strong> {{ amount.toLocaleString('en-AE', { minimumFractionDigits: 2 }) }}
                    </div>
                    <div class="col-span-2 border-t border-slate-900 p-2"><strong>Amount in Words:</strong> {{ amountWords || '—' }}</div>
                </div>
                <div class="mt-3 grid grid-cols-[130px_1fr] border border-slate-900">
                    <div class="border-r border-slate-900 p-2 font-bold">Beneficiary</div>
                    <div class="p-2">{{ beneficiary || '—' }}</div>
                    <div class="border-r border-t border-slate-900 p-2 font-bold">Purpose</div>
                    <div class="border-t border-slate-900 p-2">{{ purpose || '—' }}</div>
                </div>
                <div class="mt-3 grid grid-cols-2 border border-slate-900">
                    <div class="border-r border-slate-900 p-2"><strong>Received By:</strong> {{ receivedBy || '' }}</div>
                    <div class="p-2"><strong>ID:</strong> {{ receiverId || '' }}</div>
                    <div class="min-h-16 border-r border-t border-slate-900 p-2">
                        <strong>Receiver Signature & Mobile:</strong> {{ receiverMobile || '' }}
                    </div>
                    <div class="min-h-16 border-t border-slate-900 p-2"><strong>Company Stamp:</strong></div>
                </div>
                <div class="mt-4 grid grid-cols-3 border border-slate-900 text-center">
                    <div class="border-r border-slate-900 p-3"><strong>Prepared By:</strong> {{ preparedBy || '—' }}</div>
                    <div class="border-r border-slate-900 p-3"><strong>Checked By:</strong> {{ checkedBy || '—' }}</div>
                    <div class="p-3"><strong>Approved By:</strong> {{ approvedBy || '—' }}</div>
                </div>
            </div>
        </div>
    </section>
</template>
