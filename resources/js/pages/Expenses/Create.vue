<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { CheckCircle2, ImagePlus } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    employeeType: string;
    employeeTypeLabel: string;
    submitUrl: string;
}>();

const page = usePage();
const receiptName = ref('');
const receiptPreview = ref<string | null>(null);
const ocrProgress = ref(0);
const ocrStatus = ref('');
const ocrError = ref('');
const isReadingReceipt = ref(false);
const today = new Date().toISOString().slice(0, 10);

const successMessage = computed(() => page.props.flash?.success as string | undefined);
const attendanceHomeUrl = computed(() => (props.employeeType === 'rope_access' ? '/mark-attendance/rope-access' : '/mark-attendance'));

const form = useForm({
    type: props.employeeType,
    expense_date: today,
    purpose: '',
    amount: '',
    receipt: null as File | null,
    note: '',
});

const receiptUploadLimitKb = 10240;
const compressedReceiptMaxBytes = 4500 * 1024;
const amountPattern = /(?:AED|DHS|DIRHAM|TOTAL|AMOUNT|NET|CASH|WITHDRAWAL)?\s*([A-Z]?[0-9]{1,3}(?:,[0-9]{3})*(?:\.\d{1,2})|[A-Z]?[0-9]+(?:\.\d{1,2}))/gi;

const parseAmount = (value: string) => Number(value.replace(/,/g, '').replace(/^[^\d]+/, ''));

const shouldApplyDetectedAmount = () => {
    const currentAmount = Number(String(form.amount || '').replace(/,/g, ''));

    return !Number.isFinite(currentAmount) || currentAmount <= 0;
};

const prepareReceiptImage = (file: File, rotation: number) =>
    new Promise<string>((resolve, reject) => {
        const image = new Image();
        const imageUrl = URL.createObjectURL(file);

        image.onload = () => {
            const maxSide = 1800;
            const sourceWidth = image.naturalWidth || image.width;
            const sourceHeight = image.naturalHeight || image.height;
            const scale = Math.min(1, maxSide / Math.max(sourceWidth, sourceHeight));
            const width = Math.round(sourceWidth * scale);
            const height = Math.round(sourceHeight * scale);
            const normalizedRotation = ((rotation % 360) + 360) % 360;
            const isSideways = normalizedRotation === 90 || normalizedRotation === 270;
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');

            canvas.width = isSideways ? height : width;
            canvas.height = isSideways ? width : height;

            if (!context) {
                URL.revokeObjectURL(imageUrl);
                reject(new Error('Canvas is not available.'));
                return;
            }

            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, canvas.width, canvas.height);
            context.translate(canvas.width / 2, canvas.height / 2);
            context.rotate((normalizedRotation * Math.PI) / 180);
            context.filter = 'grayscale(1) contrast(1.35)';
            context.drawImage(image, -width / 2, -height / 2, width, height);
            URL.revokeObjectURL(imageUrl);

            resolve(canvas.toDataURL('image/jpeg', 0.92));
        };

        image.onerror = () => {
            URL.revokeObjectURL(imageUrl);
            reject(new Error('Receipt image could not be loaded.'));
        };

        image.src = imageUrl;
    });

const canvasToBlob = (canvas: HTMLCanvasElement, quality: number) =>
    new Promise<Blob>((resolve, reject) => {
        canvas.toBlob(
            (blob) => {
                if (blob) {
                    resolve(blob);
                    return;
                }

                reject(new Error('Receipt image could not be compressed.'));
            },
            'image/jpeg',
            quality,
        );
    });

const compressReceiptFile = (file: File) =>
    new Promise<File>((resolve, reject) => {
        const image = new Image();
        const imageUrl = URL.createObjectURL(file);

        image.onload = async () => {
            try {
                const sourceWidth = image.naturalWidth || image.width;
                const sourceHeight = image.naturalHeight || image.height;
                const maxSide = 2200;
                const scale = Math.min(1, maxSide / Math.max(sourceWidth, sourceHeight));
                const width = Math.max(1, Math.round(sourceWidth * scale));
                const height = Math.max(1, Math.round(sourceHeight * scale));
                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d');

                canvas.width = width;
                canvas.height = height;

                if (!context) {
                    reject(new Error('Canvas is not available.'));
                    return;
                }

                context.fillStyle = '#ffffff';
                context.fillRect(0, 0, width, height);
                context.drawImage(image, 0, 0, width, height);

                let blob = await canvasToBlob(canvas, 0.82);

                if (blob.size > compressedReceiptMaxBytes) {
                    blob = await canvasToBlob(canvas, 0.68);
                }

                if (blob.size > compressedReceiptMaxBytes) {
                    const secondScale = Math.min(1, 1800 / Math.max(width, height));
                    const resizedCanvas = document.createElement('canvas');
                    const resizedContext = resizedCanvas.getContext('2d');

                    resizedCanvas.width = Math.max(1, Math.round(width * secondScale));
                    resizedCanvas.height = Math.max(1, Math.round(height * secondScale));

                    if (!resizedContext) {
                        reject(new Error('Canvas is not available.'));
                        return;
                    }

                    resizedContext.fillStyle = '#ffffff';
                    resizedContext.fillRect(0, 0, resizedCanvas.width, resizedCanvas.height);
                    resizedContext.drawImage(canvas, 0, 0, resizedCanvas.width, resizedCanvas.height);
                    blob = await canvasToBlob(resizedCanvas, 0.72);
                }

                const compressedName = file.name.replace(/\.[^.]+$/, '') + '.jpg';

                resolve(new File([blob], compressedName, { type: 'image/jpeg', lastModified: Date.now() }));
            } catch (error) {
                reject(error);
            } finally {
                URL.revokeObjectURL(imageUrl);
            }
        };

        image.onerror = () => {
            URL.revokeObjectURL(imageUrl);
            reject(new Error('Receipt image could not be loaded.'));
        };

        image.src = imageUrl;
    });

const detectAmount = (text: string) => {
    const lines = text
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter(Boolean);

    const candidates: { amount: number; score: number }[] = [];
    const totalWindowPattern =
        /(grand\s+total|net\s+total|total\s+amount|invoice\s+total|amount\s+due|balance\s+due|total|cash\s+withdrawal)[\s\S]{0,90}?([0-9]{1,3}(?:,[0-9]{3})*(?:\.\d{1,2})|[0-9]+(?:\.\d{1,2}))/gi;
    const joinedText = lines.join('\n');

    for (const match of joinedText.matchAll(totalWindowPattern)) {
        const amount = parseAmount(match[2]);

        if (!Number.isFinite(amount) || amount <= 0 || amount > 99999999) {
            continue;
        }

        const label = match[1].toLowerCase();
        let score = 130;

        if (label.includes('grand') || label.includes('net') || label.includes('due')) {
            score += 45;
        }

        candidates.push({ amount, score });
    }

    lines.forEach((line, index) => {
        const lower = line.toLowerCase();

        for (const match of line.matchAll(amountPattern)) {
            const amount = parseAmount(match[1]);

            if (!Number.isFinite(amount) || amount <= 0 || amount > 99999999) {
                continue;
            }

            let score = index;
            const previousLine = index > 0 ? lines[index - 1].toLowerCase() : '';
            const nextLine = index + 1 < lines.length ? lines[index + 1].toLowerCase() : '';
            const context = `${previousLine} ${lower} ${nextLine}`;

            if (context.includes('grand total') || context.includes('net total') || context.includes('total amount') || context.includes('invoice total')) {
                score += 120;
            } else if (context.includes('total')) {
                score += 90;
            } else if (context.includes('amount') || context.includes('cash withdrawal') || context.includes('cash')) {
                score += 60;
            }

            if (context.includes('aed') || context.includes('dhs') || context.includes('dirham')) {
                score += 35;
            }

            if (context.includes('cash withdrawal')) {
                score += 75;
            }

            candidates.push({ amount, score });
        }
    });

    const best = candidates.sort((a, b) => b.score - a.score || b.amount - a.amount)[0];

    return best ? best.amount.toFixed(2) : '';
};

const detectPurpose = (text: string) => {
    const lines = text
        .split(/\r?\n/)
        .map((line) => line.replace(/\s+/g, ' ').trim())
        .filter((line) => line.length >= 3);

    const joined = lines.join(' ').toLowerCase();

    if (joined.includes('cash withdrawal')) {
        return 'Cash withdrawal';
    }

    if (joined.includes('petrol') || joined.includes('fuel') || joined.includes('diesel')) {
        return 'Fuel expense';
    }

    if (joined.includes('parking')) {
        return 'Parking expense';
    }

    if (joined.includes('toll') || joined.includes('salik')) {
        return 'Toll expense';
    }

    const ignored = /(receipt|invoice|tax|vat|trn|date|time|total|amount|balance|card|cash|auth|approval|customer|copy|tel|phone|www|\.com|terminal|merchant|account|successful)/i;
    const merchant = lines.find((line) => !ignored.test(line) && /[a-zA-Z]{3,}/.test(line));

    return merchant ? merchant.slice(0, 80) : '';
};

const readReceipt = async (file: File) => {
    isReadingReceipt.value = true;
    ocrProgress.value = 0;
    ocrStatus.value = 'Reading receipt image...';
    ocrError.value = '';

    try {
        const { recognize } = await import('tesseract.js');
        const rotations = [0, 90, 270, 180];
        let bestResult = { amount: '', purpose: '', score: -1 };

        for (const [index, rotation] of rotations.entries()) {
            ocrStatus.value = `Reading receipt ${index + 1}/${rotations.length}...`;
            const preparedImage = await prepareReceiptImage(file, rotation);
            const result = await recognize(preparedImage, 'eng', {
                logger: (message) => {
                    if (message.status) {
                        ocrStatus.value = `${message.status} ${index + 1}/${rotations.length}`;
                    }

                    if (typeof message.progress === 'number') {
                        ocrProgress.value = Math.round(((index + message.progress) / rotations.length) * 100);
                    }
                },
            });

            const text = result.data.text ?? '';
            const amount = detectAmount(text);
            const purpose = detectPurpose(text);
            const score = (amount ? 100 : 0) + (purpose ? 25 : 0) + (text.toLowerCase().includes('cash withdrawal') ? 35 : 0);

            if (score > bestResult.score) {
                bestResult = { amount, purpose, score };
            }

            if (amount) {
                break;
            }
        }

        if (bestResult.amount && shouldApplyDetectedAmount()) {
            form.amount = bestResult.amount;
            form.clearErrors('amount');
        }

        if (bestResult.purpose && !form.purpose) {
            form.purpose = bestResult.purpose;
            form.clearErrors('purpose');
        }

        if (!bestResult.amount && !bestResult.purpose) {
            ocrError.value = 'Could not detect amount or purpose clearly. Please enter them manually.';
        } else if (!bestResult.amount) {
            ocrError.value = 'Purpose was detected, but amount was not clear. Please enter the total amount manually.';
        } else {
            ocrStatus.value = 'Receipt details detected. Please verify before submitting.';
        }
    } catch {
        ocrError.value = 'Could not read this receipt image. Please enter amount and purpose manually.';
    } finally {
        isReadingReceipt.value = false;
    }
};

const onReceiptChange = async (event: Event) => {
    const input = event.target as HTMLInputElement;
    const selectedFile = input.files?.[0] ?? null;
    const file = selectedFile ? await compressReceiptFile(selectedFile) : null;

    form.receipt = file;
    receiptName.value = file?.name ?? '';

    if (receiptPreview.value) {
        URL.revokeObjectURL(receiptPreview.value);
    }

    receiptPreview.value = file ? URL.createObjectURL(file) : null;

    if (file) {
        await readReceipt(file);
    } else {
        ocrProgress.value = 0;
        ocrStatus.value = '';
        ocrError.value = '';
    }
};

const submitExpense = () => {
    form.post(props.submitUrl, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('purpose', 'amount', 'receipt', 'note');
            form.expense_date = today;
            receiptName.value = '';
            ocrProgress.value = 0;
            ocrStatus.value = '';
            ocrError.value = '';

            if (receiptPreview.value) {
                URL.revokeObjectURL(receiptPreview.value);
                receiptPreview.value = null;
            }
        },
    });
};
</script>

<template>
    <Head title="Create Expense Bill" />

    <main class="min-h-svh bg-background px-4 py-10">
        <div class="mx-auto max-w-xl">
            <div class="mb-8 text-center">
                <AppLogoIcon class="mx-auto size-24" />
                <h1 class="mt-3 text-2xl font-semibold tracking-normal">Daily Expense Bill</h1>
                <p class="mt-1 text-sm text-muted-foreground">Submit a {{ employeeTypeLabel }} daily expense for admin review.</p>
                <Link :href="attendanceHomeUrl" class="mt-3 inline-flex text-sm font-medium text-primary underline underline-offset-4">
                    Back to Mark Attendance
                </Link>
            </div>

            <form class="min-w-0 overflow-hidden rounded-lg border border-sidebar-border/70 bg-card p-5 shadow-sm" @submit.prevent="submitExpense">
                <div v-if="successMessage" class="mb-4 rounded-md border border-green-600/20 bg-green-600/10 px-3 py-2 text-sm font-medium text-green-700">
                    {{ successMessage }}
                </div>

                <div class="grid min-w-0 gap-5">
                    <div class="grid min-w-0 gap-2">
                        <Label for="expense-date">Expense Date</Label>
                        <Input id="expense-date" v-model="form.expense_date" type="date" :max="today" />
                        <InputError :message="form.errors.expense_date" />
                    </div>

                    <div class="grid min-w-0 gap-2">
                        <Label for="expense-purpose">Purpose</Label>
                        <Input
                            id="expense-purpose"
                            v-model="form.purpose"
                            type="text"
                            maxlength="255"
                            placeholder="Enter expense purpose"
                        />
                        <InputError :message="form.errors.purpose" />
                    </div>

                    <div class="grid min-w-0 gap-2">
                        <Label for="expense-amount">Total Amount</Label>
                        <Input id="expense-amount" v-model="form.amount" type="number" min="0.01" step="0.01" placeholder="0.00" />
                        <p class="text-xs text-muted-foreground">Enter the receipt total amount. OCR will try to read totals from receipts and invoices, but verify it before submitting.</p>
                        <InputError :message="form.errors.amount" />
                    </div>

                    <div class="grid min-w-0 gap-2">
                        <Label for="expense-receipt">Receipt Image</Label>
                        <label
                            for="expense-receipt"
                            class="flex min-h-28 cursor-pointer flex-col items-center justify-center gap-2 rounded-md border border-dashed bg-muted/20 px-4 py-5 text-center text-sm hover:bg-muted/40"
                        >
                            <ImagePlus class="size-6 text-muted-foreground" />
                            <span class="font-medium">{{ receiptName || 'Take photo or upload receipt image' }}</span>
                            <span class="text-xs text-muted-foreground">On mobile, this can open the camera. Images are compressed before upload, up to {{ receiptUploadLimitKb / 1024 }} MB.</span>
                        </label>
                        <input id="expense-receipt" type="file" accept="image/*" capture="environment" class="hidden" @change="onReceiptChange" />
                        <img v-if="receiptPreview" :src="receiptPreview" alt="Receipt preview" class="max-h-64 rounded-md border object-contain" />
                        <div v-if="isReadingReceipt || ocrStatus || ocrError" class="rounded-md border bg-muted/20 px-3 py-2 text-xs">
                            <p v-if="isReadingReceipt" class="font-medium">Reading receipt... {{ ocrProgress }}%</p>
                            <p v-else-if="ocrStatus" class="font-medium text-green-700">{{ ocrStatus }}</p>
                            <p v-if="ocrError" class="font-medium text-red-600">{{ ocrError }}</p>
                            <p class="mt-1 text-muted-foreground">Please verify the detected purpose and amount before submitting.</p>
                        </div>
                        <InputError :message="form.errors.receipt" />
                    </div>

                    <div class="grid min-w-0 gap-2">
                        <Label for="expense-note">Note</Label>
                        <textarea
                            id="expense-note"
                            v-model="form.note"
                            rows="4"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            placeholder="Optional details"
                        />
                        <InputError :message="form.errors.note" />
                    </div>

                    <Button type="submit" class="h-11 w-full" :disabled="form.processing">Submit Expense Bill</Button>

                    <div v-if="form.recentlySuccessful" class="flex items-center justify-center gap-2 rounded-md border border-green-600/30 bg-green-600/10 px-3 py-2 text-sm text-green-600">
                        <CheckCircle2 class="size-4" />
                        Expense submitted.
                    </div>
                </div>
            </form>
        </div>
    </main>
</template>
