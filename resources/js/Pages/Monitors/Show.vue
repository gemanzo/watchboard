<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, onMounted, watch } from 'vue';
import { Line } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Filler,
} from 'chart.js';
import axios from 'axios';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Filler);

interface Monitor {
    id: number;
    name: string | null;
    url: string;
    method: string;
    interval_minutes: number;
    current_status: 'unknown' | 'up' | 'down';
    is_paused: boolean;
    ssl_check_enabled: boolean;
}

interface SslCheck {
    issuer: string | null;
    valid_from: string | null;
    valid_to: string | null;
    days_until_expiry: number | null;
    is_valid: boolean;
    error: string | null;
    alert_level: 'ok' | 'warning' | 'critical' | 'expired';
    checked_at: string;
}

interface MetricPoint {
    timestamp: string;
    avg_response_time_ms: number;
    check_count: number;
}

type Range = '24h' | '7d' | '30d';

interface Uptime {
    '24h': number | null;
    '7d': number | null;
    '30d': number | null;
}

interface Incident {
    id: number;
    started_at: string;
    resolved_at: string | null;
    duration_seconds: number | null;
}

const props = defineProps<{
    monitor: Monitor;
    uptime: Uptime;
    incidents: Incident[];
    sslCheck: SslCheck | null;
}>();

function formatDate(iso: string): string {
    return new Date(iso).toLocaleString('it-IT', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

function formatDuration(seconds: number | null): string {
    if (seconds === null) return '—';
    if (seconds < 60) return `${seconds}s`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    return m > 0 ? `${h}h ${m}m` : `${h}h`;
}

function uptimeBadgeClass(value: number | null): string {
    if (value === null) return 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400';
    if (value >= 99) return 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300';
    if (value >= 95) return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300';
    return 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300';
}

const showDeleteModal = ref(false);
const deleteForm = useForm({});
const pauseForm = useForm({});

function togglePause() {
    pauseForm.patch(route('monitors.toggle-pause', props.monitor.id));
}

const confirmDelete = () => {
    deleteForm.delete(route('monitors.destroy', props.monitor.id), {
        onSuccess: () => { showDeleteModal.value = false; },
    });
};

// ─── Metrics ──────────────────────────────────────────────────────────────────

const selectedRange = ref<Range>('24h');
const metrics = ref<MetricPoint[]>([]);
const loading = ref(true);

const ranges: { value: Range; label: string }[] = [
    { value: '24h', label: '24h' },
    { value: '7d', label: '7 giorni' },
    { value: '30d', label: '30 giorni' },
];

async function fetchMetrics() {
    loading.value = true;
    try {
        const { data } = await axios.get(
            route('monitors.metrics', props.monitor.id),
            { params: { range: selectedRange.value } },
        );
        metrics.value = data.data;
    } finally {
        loading.value = false;
    }
}

onMounted(fetchMetrics);
watch(selectedRange, fetchMetrics);

function formatLabel(iso: string): string {
    const d = new Date(iso);
    if (selectedRange.value === '24h') {
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
    return d.toLocaleDateString([], { day: '2-digit', month: '2-digit' })
        + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

const chartData = ref<any>({ labels: [], datasets: [] });
const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { intersect: false, mode: 'index' as const },
    plugins: {
        tooltip: {
            callbacks: {
                label: (ctx: any) => `${ctx.parsed.y} ms`,
            },
        },
        legend: { display: false },
    },
    scales: {
        x: {
            ticks: { maxTicksLimit: 12, maxRotation: 0, color: '#9ca3af' },
            grid: { display: false },
        },
        y: {
            beginAtZero: true,
            ticks: {
                callback: (v: any) => `${v} ms`,
                color: '#9ca3af',
            },
            grid: { color: 'rgba(156,163,175,0.15)' },
        },
    },
};

watch(metrics, (pts) => {
    chartData.value = {
        labels: pts.map(p => formatLabel(p.timestamp)),
        datasets: [{
            data: pts.map(p => p.avg_response_time_ms),
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99,102,241,0.08)',
            borderWidth: 2,
            pointRadius: pts.length > 100 ? 0 : 2,
            pointHoverRadius: 4,
            tension: 0.3,
            fill: true,
        }],
    };
}, { immediate: true });
</script>

<template>
    <Head :title="monitor.name ?? monitor.url" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('dashboard')"
                        class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        ← Dashboard
                    </Link>
                    <span class="text-gray-300 dark:text-gray-600">/</span>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ monitor.name ?? monitor.url }}
                    </h2>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-3">
                    <button
                        :disabled="pauseForm.processing"
                        class="inline-flex items-center gap-1.5 rounded-md border px-4 py-2 text-xs font-semibold uppercase tracking-widest shadow-sm transition focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-40"
                        :class="monitor.is_paused
                            ? 'border-indigo-300 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 dark:border-indigo-500 dark:bg-indigo-900/20 dark:text-indigo-300 dark:hover:bg-indigo-900/40'
                            : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'"
                        @click="togglePause"
                    >
                        <!-- Play icon (resume) -->
                        <svg v-if="monitor.is_paused" class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M6.3 2.84A1.5 1.5 0 0 0 4 4.11v11.78a1.5 1.5 0 0 0 2.3 1.27l9.344-5.891a1.5 1.5 0 0 0 0-2.538L6.3 2.84Z" />
                        </svg>
                        <!-- Pause icon -->
                        <svg v-else class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M5.75 3a.75.75 0 0 0-.75.75v12.5c0 .414.336.75.75.75h1.5a.75.75 0 0 0 .75-.75V3.75A.75.75 0 0 0 7.25 3h-1.5ZM12.75 3a.75.75 0 0 0-.75.75v12.5c0 .414.336.75.75.75h1.5a.75.75 0 0 0 .75-.75V3.75a.75.75 0 0 0-.75-.75h-1.5Z" />
                        </svg>
                        {{ monitor.is_paused ? 'Riprendi' : 'Pausa' }}
                    </button>
                    <Link
                        :href="route('monitors.edit', monitor.id)"
                        class="rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        Modifica
                    </Link>
                    <DangerButton type="button" @click="showDeleteModal = true">
                        Elimina
                    </DangerButton>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">

                <!-- Monitor info -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <dl class="grid grid-cols-2 gap-x-8 gap-y-4 p-6 text-sm sm:grid-cols-4">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">URL</dt>
                            <dd class="mt-1 truncate max-w-[220px] text-gray-700 dark:text-gray-200">{{ monitor.url }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Metodo</dt>
                            <dd class="mt-1 text-gray-700 dark:text-gray-200">{{ monitor.method }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Intervallo</dt>
                            <dd class="mt-1 text-gray-700 dark:text-gray-200">
                                {{ monitor.interval_minutes === 1 ? '1 minuto' : `${monitor.interval_minutes} minuti` }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Stato</dt>
                            <dd class="mt-1">
                                <span
                                    v-if="monitor.is_paused"
                                    class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-yellow-400" />
                                    In pausa
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                    :class="{
                                        'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400': monitor.current_status === 'up',
                                        'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400': monitor.current_status === 'down',
                                        'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400': monitor.current_status === 'unknown',
                                    }"
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full"
                                        :class="{
                                            'bg-green-500': monitor.current_status === 'up',
                                            'bg-red-500': monitor.current_status === 'down',
                                            'bg-gray-400': monitor.current_status === 'unknown',
                                        }"
                                    />
                                    {{ monitor.current_status }}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- SSL Certificate -->
                <div v-if="monitor.ssl_check_enabled" class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700 flex items-center justify-between">
                        <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                            </svg>
                            Certificato SSL
                        </h3>
                        <span v-if="sslCheck" class="text-xs text-gray-400 dark:text-gray-500">
                            aggiornato {{ sslCheck.checked_at }}
                        </span>
                    </div>

                    <!-- No data yet -->
                    <div v-if="!sslCheck" class="flex flex-col items-center justify-center py-10 text-center">
                        <svg class="mb-3 h-8 w-8 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <p class="text-sm text-gray-500 dark:text-gray-400">In attesa del primo check SSL</p>
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Il check viene eseguito automaticamente ogni giorno.</p>
                    </div>

                    <!-- SSL data -->
                    <div v-else class="p-6">
                        <div class="flex flex-wrap items-start gap-6">
                            <!-- Badge -->
                            <div class="flex flex-col items-center gap-1.5 min-w-[80px]">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-semibold"
                                    :class="{
                                        'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300': sslCheck.alert_level === 'ok',
                                        'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300': sslCheck.alert_level === 'warning',
                                        'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300': sslCheck.alert_level === 'critical',
                                        'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300': sslCheck.alert_level === 'expired',
                                    }"
                                >
                                    <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
                                        <circle v-if="sslCheck.alert_level === 'ok'" cx="10" cy="10" r="7" fill="currentColor" />
                                        <path v-else fill-rule="evenodd" clip-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" />
                                    </svg>
                                    <span>
                                        {{ sslCheck.alert_level === 'ok' ? 'Valido' : sslCheck.alert_level === 'warning' ? 'In scadenza' : sslCheck.alert_level === 'critical' ? 'Critico' : 'Scaduto' }}
                                    </span>
                                </span>
                                <span v-if="sslCheck.days_until_expiry !== null && sslCheck.is_valid" class="text-xs text-gray-400">
                                    {{ sslCheck.days_until_expiry }}g rimasti
                                </span>
                            </div>

                            <!-- Details grid -->
                            <dl class="grid grid-cols-2 gap-x-8 gap-y-3 text-sm sm:grid-cols-3 flex-1">
                                <div v-if="sslCheck.issuer">
                                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Emesso da</dt>
                                    <dd class="mt-1 text-gray-700 dark:text-gray-200">{{ sslCheck.issuer }}</dd>
                                </div>
                                <div v-if="sslCheck.valid_from">
                                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Valido dal</dt>
                                    <dd class="mt-1 text-gray-700 dark:text-gray-200">{{ sslCheck.valid_from }}</dd>
                                </div>
                                <div v-if="sslCheck.valid_to">
                                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Scade il</dt>
                                    <dd
                                        class="mt-1 font-medium"
                                        :class="{
                                            'text-gray-700 dark:text-gray-200': sslCheck.alert_level === 'ok',
                                            'text-yellow-600 dark:text-yellow-400': sslCheck.alert_level === 'warning',
                                            'text-orange-600 dark:text-orange-400': sslCheck.alert_level === 'critical',
                                            'text-red-600 dark:text-red-400': sslCheck.alert_level === 'expired',
                                        }"
                                    >{{ sslCheck.valid_to }}</dd>
                                </div>
                                <div v-if="sslCheck.error" class="col-span-full">
                                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Errore</dt>
                                    <dd class="mt-1 text-red-600 dark:text-red-400 font-mono text-xs">{{ sslCheck.error }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>

                <!-- Uptime -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <div class="grid grid-cols-3 divide-x divide-gray-100 dark:divide-gray-700">
                        <div v-for="(label, key) in { '24h': '24 ore', '7d': '7 giorni', '30d': '30 giorni' }" :key="key" class="px-6 py-5 text-center">
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Uptime {{ label }}</dt>
                            <dd class="mt-2">
                                <span
                                    :class="['inline-flex rounded-full px-3 py-1 text-sm font-semibold', uptimeBadgeClass(uptime[key as keyof Uptime])]"
                                >
                                    {{ uptime[key as keyof Uptime] !== null ? `${uptime[key as keyof Uptime]}%` : '—' }}
                                </span>
                            </dd>
                        </div>
                    </div>
                </div>

                <!-- Response time chart -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200">
                            Response Time
                        </h3>
                        <div class="flex gap-1">
                            <button
                                v-for="r in ranges"
                                :key="r.value"
                                @click="selectedRange = r.value"
                                class="rounded-md px-3 py-1 text-xs font-medium transition"
                                :class="selectedRange === r.value
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'"
                            >
                                {{ r.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Loading -->
                    <div v-if="loading" class="flex items-center justify-center h-64">
                        <svg class="animate-spin h-6 w-6 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                    </div>

                    <!-- Empty state -->
                    <div v-else-if="metrics.length === 0" class="flex flex-col items-center justify-center h-64 text-center">
                        <svg class="mb-3 h-10 w-10 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                        </svg>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            Nessun dato disponibile
                        </p>
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                            I dati appariranno dopo i primi check.
                        </p>
                    </div>

                    <!-- Chart -->
                    <div v-else class="h-64">
                        <Line :data="chartData" :options="chartOptions" />
                    </div>
                </div>

                <!-- Incidents -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                        <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200">
                            Storico downtime
                        </h3>
                    </div>

                    <!-- Empty state -->
                    <div v-if="incidents.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
                        <svg class="mb-3 h-10 w-10 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Nessun downtime registrato</p>
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Il monitor non ha mai avuto incidenti.</p>
                    </div>

                    <!-- Table -->
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="border-b border-gray-200 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-400">
                                <tr>
                                    <th class="px-6 py-3">Inizio downtime</th>
                                    <th class="px-6 py-3">Fine downtime</th>
                                    <th class="px-6 py-3">Durata</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr
                                    v-for="incident in incidents"
                                    :key="incident.id"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-700/40 transition-colors"
                                >
                                    <td class="px-6 py-4 text-gray-700 dark:text-gray-200 font-mono text-xs">
                                        {{ formatDate(incident.started_at) }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span v-if="incident.resolved_at" class="font-mono text-xs text-gray-700 dark:text-gray-200">
                                            {{ formatDate(incident.resolved_at) }}
                                        </span>
                                        <span v-else class="inline-flex items-center gap-1.5 rounded-full bg-red-100 px-2.5 py-1 text-xs font-medium text-red-700 dark:bg-red-900/40 dark:text-red-300">
                                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-red-500" />
                                            In corso
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-gray-500 dark:text-gray-400 text-xs">
                                        {{ formatDuration(incident.duration_seconds) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <!-- Delete confirmation modal -->
        <Modal :show="showDeleteModal" max-width="md" @close="showDeleteModal = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    Eliminare il monitor?
                </h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Questa azione è irreversibile. Il monitor e tutti i check result associati
                    verranno eliminati definitivamente.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="showDeleteModal = false">
                        Annulla
                    </SecondaryButton>
                    <DangerButton
                        :class="{ 'opacity-25': deleteForm.processing }"
                        :disabled="deleteForm.processing"
                        @click="confirmDelete"
                    >
                        Sì, elimina
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
