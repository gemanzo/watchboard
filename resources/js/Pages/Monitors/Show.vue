<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
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
}

interface MetricPoint {
    timestamp: string;
    avg_response_time_ms: number;
    check_count: number;
}

type Range = '24h' | '7d' | '30d';

const props = defineProps<{
    monitor: Monitor;
}>();

const showDeleteModal = ref(false);
const deleteForm = useForm({});

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
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                    :class="{
                                        'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400': monitor.current_status === 'up',
                                        'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400': monitor.current_status === 'down',
                                        'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400': monitor.current_status === 'unknown',
                                    }"
                                >
                                    {{ monitor.current_status }}
                                </span>
                            </dd>
                        </div>
                    </dl>
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
