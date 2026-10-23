import { registerWallPanel } from './wall-panel';
// Dentro del bundle y antes que Alpine: el hosting bloquea las URL bajo /vendor/.
import '../../vendor/edwin-ortizp/laravel-markdown/resources/assets/editor.js';
import { registerMarkdownAdapter } from './markdown-adapter';
import { registerFormEditor } from './form-editor';
import { registerEnergyPricing } from './energy-pricing';
import { registerDialogViewport } from './dialog-viewport';
import { Alpine, Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';
import { registerPomodoroTimer } from './pomodoro-timer';
import { registerUiSurfaces } from './ui-surfaces';
import { markPlatform, registerAppFrame, registerPageTransitions } from './app-frame';
import { registerInstallPrompt, registerServiceWorker } from './pwa';
import { registerFeedback } from './feedback';
import { registerHealthBodyMap } from './health-body-map';
import { registerTaskCalendar } from './task-calendar';
import { registerTaskGantt } from './task-gantt';

registerPomodoroTimer(Alpine);
registerWallPanel(Alpine);
registerUiSurfaces(Alpine);
registerEnergyPricing(Alpine);
registerDialogViewport(Alpine);
registerFormEditor(Alpine);
registerMarkdownAdapter(Alpine);
registerAppFrame(Alpine);
registerFeedback(Alpine);
registerHealthBodyMap(Alpine);
registerTaskCalendar(Alpine);
registerTaskGantt(Alpine);

registerInstallPrompt(Alpine);

markPlatform();
registerPageTransitions();
registerServiceWorker();

const charts = new WeakMap();
const pendingCharts = new WeakSet();
let apexChartsPromise;

function color(name) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
}

// Lee un token resuelto en el propio elemento (p. ej. el acento del módulo).
function localColor(element, name) {
    return getComputedStyle(element).getPropertyValue(name).trim();
}

async function renderHealthChart(element) {
    if (charts.has(element) || pendingCharts.has(element)) {
        return;
    }

    pendingCharts.add(element);

    const points = JSON.parse(element.dataset.healthChart ?? '[]');
    if (points.length === 0) {
        pendingCharts.delete(element);
        return;
    }

    apexChartsPromise ??= import('apexcharts').then((module) => module.default);
    const ApexCharts = await apexChartsPromise;
    if (!element.isConnected) { pendingCharts.delete(element); return; }
    // Barras redondeadas con el acento del módulo, como en el mockup de Salud.
    const chart = new ApexCharts(element, {
        chart: {
            type: 'bar',
            height: 190,
            toolbar: { show: false },
            fontFamily: 'inherit',
            animations: { easing: 'easeinout', speed: 350 },
            parentHeightOffset: 0,
        },
        series: [{ name: 'Intensidad', data: points.map((point) => point.intensity) }],
        colors: [localColor(element, '--md-module-accent') || color('--md-sys-color-primary')],
        plotOptions: { bar: { borderRadius: 6, borderRadiusApplication: 'end', columnWidth: '70%' } },
        dataLabels: { enabled: false },
        grid: { show: false, padding: { left: 0, right: 0, top: -12, bottom: -4 } },
        states: { hover: { filter: { type: 'darken', value: 0.9 } } },
        xaxis: {
            categories: points.map((point) => point.date),
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: { style: { colors: color('--md-sys-color-on-surface-variant'), fontSize: '12px', fontWeight: 600 } },
            tooltip: { enabled: false },
        },
        yaxis: { min: 0, max: 10, show: false },
        tooltip: {
            theme: document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light',
            y: { formatter: (value) => `${value}/10` },
        },
        legend: { show: false },
    });

    charts.set(element, chart);
    pendingCharts.delete(element);
    chart.render();
}

async function renderDashboardChart(element) {
    if (charts.has(element) || pendingCharts.has(element)) {
        return;
    }

    pendingCharts.add(element);

    const points = JSON.parse(element.dataset.dashboardChart ?? '[]');
    if (points.length === 0) {
        pendingCharts.delete(element);
        return;
    }

    apexChartsPromise ??= import('apexcharts').then((module) => module.default);
    const ApexCharts = await apexChartsPromise;
    if (!element.isConnected) { pendingCharts.delete(element); return; }
    const chart = new ApexCharts(element, {
        chart: {
            type: 'area',
            height: 200,
            toolbar: { show: false },
            fontFamily: 'inherit',
            animations: { easing: 'easeinout', speed: 450 },
        },
        series: [
            { name: 'Hidratación', data: points.map((point) => point.water) },
            { name: 'Hábitos', data: points.map((point) => point.habits) },
        ],
        colors: [color('--md-sys-color-primary'), color('--md-sys-color-tertiary')],
        stroke: { curve: 'smooth', width: 3 },
        fill: {
            type: 'gradient',
            gradient: { shadeIntensity: 0, opacityFrom: 0.28, opacityTo: 0.02, stops: [0, 92, 100] },
        },
        markers: { size: 3, strokeWidth: 2, strokeColors: color('--md-sys-color-surface') },
        dataLabels: { enabled: false },
        grid: { borderColor: color('--md-sys-color-outline-variant'), strokeDashArray: 4, padding: { left: 2, right: 8 } },
        xaxis: {
            categories: points.map((point) => point.date),
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: { style: { colors: color('--md-sys-color-on-surface-variant'), fontSize: '11px' } },
        },
        yaxis: {
            min: 0,
            max: 100,
            tickAmount: 4,
            labels: {
                formatter: (value) => `${Math.round(value)}%`,
                style: { colors: color('--md-sys-color-on-surface-variant'), fontSize: '11px' },
            },
        },
        tooltip: {
            theme: document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light',
            y: { formatter: (value) => `${value}%` },
        },
        legend: {
            show: true,
            position: 'top',
            horizontalAlign: 'left',
            fontSize: '12px',
            labels: { colors: color('--md-sys-color-on-surface-variant') },
            markers: { size: 5 },
        },
    });

    charts.set(element, chart);
    pendingCharts.delete(element);
    chart.render();
}

// Distribución mensual de síntomas y enfermedades (tarjeta "Momentos de enfermedad").
async function renderHealthMonthsChart(element) {
    if (charts.has(element) || pendingCharts.has(element)) {
        return;
    }

    pendingCharts.add(element);

    const months = JSON.parse(element.dataset.healthMonthsChart ?? '[]');
    if (months.length === 0) {
        pendingCharts.delete(element);
        return;
    }

    apexChartsPromise ??= import('apexcharts').then((module) => module.default);
    const ApexCharts = await apexChartsPromise;
    if (!element.isConnected) { pendingCharts.delete(element); return; }
    const accent = localColor(element, '--md-module-accent') || color('--md-sys-color-primary');
    const muted = color('--md-sys-color-surface-container-highest');
    const chart = new ApexCharts(element, {
        chart: {
            type: 'bar',
            height: 150,
            toolbar: { show: false },
            fontFamily: 'inherit',
            animations: { easing: 'easeinout', speed: 350 },
            parentHeightOffset: 0,
        },
        series: [{ name: 'Eventos', data: months.map((month) => Math.max(month.count, 0.15)) }],
        colors: [({ dataPointIndex }) => (months[dataPointIndex]?.count ? accent : muted)],
        plotOptions: { bar: { borderRadius: 4, borderRadiusApplication: 'end', columnWidth: '62%', distributed: true } },
        dataLabels: { enabled: false },
        grid: { show: false, padding: { left: 0, right: 0, top: -14, bottom: -6 } },
        xaxis: {
            categories: months.map((month) => month.month),
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: { style: { colors: color('--md-sys-color-on-surface-variant'), fontSize: '10px' } },
            tooltip: { enabled: false },
        },
        yaxis: { show: false, min: 0 },
        tooltip: {
            theme: document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light',
            y: {
                formatter: (value, { dataPointIndex }) => {
                    const count = months[dataPointIndex]?.count ?? 0;
                    return `${count} ${count === 1 ? 'evento' : 'eventos'}`;
                },
            },
        },
        legend: { show: false },
    });

    charts.set(element, chart);
    pendingCharts.delete(element);
    chart.render();
}

const chartRenderers = {
    '[data-health-chart]': renderHealthChart,
    '[data-health-months-chart]': renderHealthMonthsChart,
    '[data-dashboard-chart]': renderDashboardChart,
};

function discoverCharts(root = document) {
    Object.entries(chartRenderers).forEach(([selector, render]) => {
        if (root.nodeType === Node.ELEMENT_NODE && root.matches?.(selector)) {
            render(root);
        }
        root.querySelectorAll?.(selector).forEach(render);
    });
}

// `wire:navigate` reemplaza el contenido del body sin volver a disparar
// `DOMContentLoaded`, asi que el arranque se registra una sola vez y el
// descubrimiento de graficos se repite en cada navegacion.
let uiBootstrapped = false;

function bootstrapUi() {
    if (uiBootstrapped) {
        return;
    }

    uiBootstrapped = true;

    new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.target instanceof Element && mutation.target.closest('.apexcharts-canvas')) return;
            mutation.addedNodes.forEach(node => { if (node.isConnected) discoverCharts(node); });
            mutation.removedNodes.forEach((node) => {
                if (node.nodeType !== Node.ELEMENT_NODE || node.isConnected) return;
                Object.keys(chartRenderers).forEach((selector) => {
                    const targets = node.matches?.(selector)
                        ? [node]
                        : [...(node.querySelectorAll?.(selector) ?? [])];
                    targets.forEach((el) => {
                        const chart = charts.get(el);
                        if (chart) { chart.destroy(); charts.delete(el); }
                    });
                });
            });
        });
    }).observe(document.documentElement, { childList: true, subtree: true });

    // Respaldo táctil: abre el selector nativo aunque el navegador no lo haga solo al tocar.
    // En escritorio rompería la escritura por segmentos y el botón nativo del selector.
    const coarsePointer = window.matchMedia('(pointer: coarse)');
    document.addEventListener('click', (e) => {
        if (!coarsePointer.matches) return;
        const input = e.target;
        if (!(input instanceof HTMLInputElement) || input.disabled || input.readOnly) return;
        if (!['date', 'time', 'datetime-local', 'month', 'week'].includes(input.type)) return;
        try { input.showPicker?.(); } catch {}
    });

    // Ctrl+L (checklist) y los demás atajos del editor Markdown viven en el paquete laravel-markdown.
}

document.addEventListener('DOMContentLoaded', () => {
    bootstrapUi();
    discoverCharts();
});

document.addEventListener('livewire:navigated', () => {
    bootstrapUi();
    discoverCharts();
});

Livewire.start();
