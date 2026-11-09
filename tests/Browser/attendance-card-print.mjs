// Run with: node tests/Browser/attendance-card-print.mjs
// Uses an isolated headless Chromium profile and synthetic data; never signs in to the app.
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
import { inflateSync } from 'node:zlib';
import { build } from 'esbuild';
import { createSSRApp } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { compileScript, parse } from '@vue/compiler-sfc';

const root = process.cwd();
const output = path.join(root, 'storage/framework/testing/card-print-qa');
fs.mkdirSync(output, { recursive: true });
const css = [];
const compiled = await build({
    entryPoints: [path.join(root, 'resources/js/pages/AttendanceCards/Print.vue')],
    bundle: true, write: false, format: 'esm', platform: 'node', external: ['vue'],
    plugins: [{ name: 'card-ssr', setup(bundler) {
        bundler.onResolve({ filter: /^@inertiajs\/vue3$/ }, () => ({ path: 'head', namespace: 'mock' }));
        bundler.onLoad({ filter: /.*/, namespace: 'mock' }, () => ({ contents: 'export const Head = { render: () => null };', loader: 'js' }));
        bundler.onLoad({ filter: /\.vue$/ }, (args) => {
            const { descriptor } = parse(fs.readFileSync(args.path, 'utf8'), { filename: args.path });
            css.push(...descriptor.styles.map((style) => style.content));
            const script = compileScript(descriptor, { id: args.path, inlineTemplate: true });
            return { contents: script.content, loader: 'ts', resolveDir: path.dirname(args.path) };
        });
    } }],
});
const modulePath = path.join(output, 'print-fixture.mjs');
fs.writeFileSync(modulePath, compiled.outputFiles[0].text);
const Print = (await import(pathToFileURL(modulePath).href)).default;
const template = {
    id: null, name: 'Test Card', version: 1, signatureUrl: null,
    logoUrl: `data:image/png;base64,${fs.readFileSync(path.join(root, 'public/al-mohafiz-logo.png')).toString('base64')}`,
    layout: {
        width: 190, height: 132, font: 'Arial', font_size: 7, title: 'ATTENDANCE CARD - {year}',
        text_color: '#000000', border_color: '#000000', header_color: '#DDDDDD', rest_color: '#E5484D',
        rest_day: 5, show_ot_site: false, show_signature: true, attendance_label: 'Attendance', site_label: 'Site No.',
        overtime_label: 'Over Time', admin_label: 'ADMIN FOOTER', employee_label: 'EMPLOYEE FOOTER',
        sections: {
            title: { x: 5, y: 4, width: 135, height: 11, font_size: 15, visible: true },
            logo: { x: 148, y: 3, width: 36, height: 13, font_size: 7, visible: true },
            identity: { x: 3, y: 19, width: 184, height: 15, font_size: 8, visible: true },
            grid: { x: 3, y: 37, width: 184, height: 75, font_size: 7, visible: true },
            footer: { x: 3, y: 115, width: 184, height: 13, font_size: 7, visible: true },
            signature: { x: 5, y: 115, width: 45, height: 8, font_size: 7, visible: true },
        },
    },
};
const days = Array.from({ length: 30 }, (_, i) => ({ day: i + 1, date: `2026-11-${String(i + 1).padStart(2, '0')}`, mark: 'P',
    dayValue: 1, leave: 0, site: 'PR 267', projectName: 'Sample Project', otSite: '', otProjectName: null,
    overtimeMinutes: 90, overtime: '1:30', restLabel: null, note: null }));
const cards = Array.from({ length: 13 }, (_, i) => ({ employeeId: i + 1, code: String(i + 1), name: `WORKER${String(i + 1).padStart(2, '0')}`,
    profession: 'FOREMAN', month: 'Nov', monthLabel: 'November 2026', year: 2026, days,
    presentDays: 30, absentDays: 0, leaveDays: 0, overtimeMinutes: 2700, overtimeLabel: '45 hr' }));
const report = { filters: { month: '2026-11', perPage: 4 }, template, cards };
const scale = 1;
const html = await renderToString(createSSRApp(Print, { report, printLayout: { pageWidth: 420, pageHeight: 297, columns: 2, scale } }));
assert.equal((html.match(/class="attendance-card-canvas"/g) ?? []).length, 13);
assert.ok(!html.includes('transform:scale('), 'Printing must scale actual card geometry, not use CSS transform');
const htmlPath = path.join(output, 'four-cards.html');
const pdfPath = path.join(output, 'four-cards.pdf');
fs.writeFileSync(htmlPath, `<!doctype html><html><head><meta charset="utf-8"><style>${css.join('\n')}</style></head><body><div id="app">${html}</div></body></html>`);
const browser = process.env.CARD_PRINT_BROWSER ?? ['C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(fs.existsSync);
assert.ok(browser, 'Set CARD_PRINT_BROWSER to a Chromium executable');
execFileSync(browser, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
    `--user-data-dir=${path.join(output, 'chromium-profile')}`, '--no-pdf-header-footer', '--allow-file-access-from-files',
    `--print-to-pdf=${pdfPath}`, pathToFileURL(htmlPath).href], { windowsHide: true, timeout: 60000, stdio: 'pipe' });

// Chromium writes ordinary indirect objects. Decode page text through its ToUnicode maps.
const pdf = fs.readFileSync(pdfPath).toString('latin1');
const objects = new Map([...pdf.matchAll(/(\d+) 0 obj\b([\s\S]*?)endobj/g)].map((match) => [match[1], match[2]]));
function stream(id) {
    const object = objects.get(String(id)) ?? '';
    const match = object.match(/stream\r?\n([\s\S]*?)\r?\nendstream/);
    if (!match) return '';
    const bytes = Buffer.from(match[1], 'latin1');
    return (/\/FlateDecode/.test(object) ? inflateSync(bytes) : bytes).toString('latin1');
}
const fonts = new Map();
for (const [id, object] of objects) {
    const unicode = object.match(/\/ToUnicode (\d+) 0 R/);
    if (!unicode) continue;
    const mapping = new Map();
    const cmap = stream(unicode[1]);
    for (const block of cmap.matchAll(/beginbfchar([\s\S]*?)endbfchar/g)) {
        for (const pair of block[1].matchAll(/<([0-9a-f]+)>\s*<([0-9a-f]+)>/gi)) mapping.set(parseInt(pair[1], 16), String.fromCodePoint(parseInt(pair[2], 16)));
    }
    for (const block of cmap.matchAll(/beginbfrange([\s\S]*?)endbfrange/g)) {
        for (const range of block[1].matchAll(/<([0-9a-f]+)>\s*<([0-9a-f]+)>\s*<([0-9a-f]+)>/gi)) {
            const first = parseInt(range[1], 16), last = parseInt(range[2], 16), start = parseInt(range[3], 16);
            for (let code = first; code <= last; code++) mapping.set(code, String.fromCodePoint(start + code - first));
        }
    }
    fonts.set(id, mapping);
}
const pages = [...objects.values()].filter((object) => /\/Type\s*\/Page\b/.test(object));
assert.equal(pages.length, 4, '13 cards must produce exactly four pages at four per page');
for (const [index, page] of pages.entries()) {
    const box = page.match(/\/MediaBox\s*\[([^\]]+)\]/)?.[1].trim().split(/\s+/).map(Number);
    assert.ok(box && box[2] > box[3], `Page ${index + 1} must be landscape`);
    assert.ok(Math.abs(box[2] - 420 * 72 / 25.4) < 1 && Math.abs(box[3] - 297 * 72 / 25.4) < 1, `Page ${index + 1} must be A3, not A4`);
    const fontRefs = new Map([...page.matchAll(/\/(F\d+) (\d+) 0 R/g)].map((match) => [match[1], match[2]]));
    const contentRef = page.match(/\/Contents (\d+) 0 R/);
    assert.ok(contentRef, 'Expected a page content stream');
    const content = stream(contentRef[1]);
    let font = '', text = '';
    for (const token of content.matchAll(/\/(F\d+) [\d.]+ Tf|<([0-9a-f]+)>\s*Tj/gi)) {
        if (token[1]) { font = fontRefs.get(token[1]); continue; }
        const chars = token[2].match(/.{4}/g) ?? [];
        text += chars.map((code) => fonts.get(font)?.get(parseInt(code, 16)) ?? '').join('');
    }
    const names = [...text.matchAll(/WORKER\d{2}/g)].map((match) => match[0]);
    const expected = cards.slice(index * 4, index * 4 + 4).map((card) => card.name);
    assert.deepEqual(names, expected, `Page ${index + 1}: cards must not spill across pages`);
    assert.equal((text.match(/ADMIN FOOTER/g) ?? []).length, expected.length, `Page ${index + 1}: every card footer must remain on its own page`);
}
console.log('PASS: 13 cards, 4 A3 landscape PDF pages, each card name and footer on the expected page.');
console.log(`QA PDF: ${pdfPath}`);
