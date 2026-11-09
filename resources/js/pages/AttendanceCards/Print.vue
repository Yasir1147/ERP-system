<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CardCanvas from './components/CardCanvas.vue';
import type { CardReport } from './types';
const props = defineProps<{ report: CardReport }>();
const pages = computed(() => {
    const result = [];
    for (let i = 0; i < props.report.cards.length; i += props.report.filters.perPage)
        result.push(props.report.cards.slice(i, i + props.report.filters.perPage));
    return result;
});
const printing = ref(false);
const error = ref('');
async function printCards() {
    printing.value = true;
    error.value = '';
    try {
        await document.fonts.ready;
        await Promise.all(Array.from(document.images).map((img) => img.decode()));
        window.print();
    } catch {
        error.value = 'An image could not be loaded. Reload this page before printing.';
    } finally {
        printing.value = false;
    }
}
</script>

<template>
    <Head :title="`Attendance Cards - ${report.filters.month}`" />
    <main class="card-print-main">
        <div class="card-print-controls">
            <div>
                <h1>Monthly Attendance Cards · {{ report.filters.month }}</h1>
                <p>
                    {{ report.cards.length }} cards · {{ report.filters.perPage }} per A4 page. Print at 100% / Actual Size, with browser headers and
                    footers off.
                </p>
                <p v-if="error" role="alert">{{ error }}</p>
            </div>
            <button type="button" :disabled="printing || !report.cards.length" @click="printCards">
                {{ printing ? 'Preparing...' : 'Print / Save as PDF' }}
            </button>
        </div>
        <div v-for="(page, index) in pages" :key="index" class="card-print-sheet">
            <CardCanvas v-for="card in page" :key="card.employeeId" :template="report.template" :card="card" />
        </div>
        <p v-if="!pages.length" class="card-print-controls">No employees selected. Return to Attendance Cards and generate cards.</p>
    </main>
</template>

<style>
.card-print-main {
    min-height: 100vh;
    background: #e2e8f0;
    padding: 16px;
    color: #0f172a;
}
.card-print-controls {
    max-width: 1000px;
    margin: 0 auto 16px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    background: white;
    padding: 16px;
    border-radius: 8px;
}
.card-print-controls h1 {
    font-weight: 700;
}
.card-print-controls p {
    margin-top: 5px;
    font-size: 12px;
}
.card-print-controls button {
    background: #171717;
    color: white;
    border-radius: 6px;
    padding: 10px 16px;
}
.card-print-controls button:disabled {
    opacity: 0.5;
}
.card-print-sheet {
    box-sizing: border-box;
    width: 210mm;
    height: 297mm;
    padding: 10mm;
    margin: 0 auto 16px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4mm;
    background: white;
    box-shadow: 0 2px 8px #0002;
}
@media print {
    @page {
        size: A4 portrait;
        margin: 0;
    }
    html,
    body,
    #app {
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
    }
    .card-print-main {
        padding: 0;
        background: white;
        min-height: 0;
    }
    .card-print-controls {
        display: none;
    }
    .card-print-sheet {
        margin: 0;
        box-shadow: none;
        break-after: page;
        break-inside: avoid;
    }
    .card-print-sheet:last-child {
        break-after: auto;
    }
}
</style>
