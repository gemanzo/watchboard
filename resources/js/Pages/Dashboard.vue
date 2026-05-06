<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import echo from '@/echo';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

interface Monitor {
    id: number;
    name: string | null;
    url: string;
    method: string | null;
    check_type: 'http' | 'tcp' | 'ping';
    port: number | null;
    interval_minutes: number;
    current_status: 'unknown' | 'up' | 'down';
    is_paused: boolean;
    last_status_code: number | null;
    last_response_time_ms: number | null;
    last_checked_at_human: string | null;
    uptime_24h: number | null;
}

const props = defineProps<{
    monitors: Monitor[];
}>();

// ─── Reactive local copy (updated via WebSocket) ──────────────────────────────

const monitors = ref<Monitor[]>([...props.monitors]);

// Resync when Inertia navigates (e.g. after pause/delete)
watch(() => props.monitors, (updated) => {
    monitors.value = [...updated];
});

// Keep monitors sorted: down first, then unknown, then up; paused last
const statusOrder: Record<string, number> = { down: 0, unknown: 1, up: 2 };

const sortedMonitors = computed(() =>
    [...monitors.value].sort((a, b) => {
        if (a.is_paused !== b.is_paused) return a.is_paused ? 1 : -1;
        const diff = (statusOrder[a.current_status] ?? 3) - (statusOrder[b.current_status] ?? 3);
        return diff !== 0 ? diff : (a.name ?? '').localeCompare(b.name ?? '');
    }),
);

// ─── WebSocket connection state ───────────────────────────────────────────────

type WsState = 'connecting' | 'connected' | 'disconnected';
const wsState = ref<WsState>('connecting');

function bindConnectionState() {
    const conn = echo.connector.pusher.connection;

    const update = (state: WsState) => () => { wsState.value = state; };

    conn.bind('connected',     update('connected'));
    conn.bind('disconnected',  update('disconnected'));
    conn.bind('unavailable',   update('disconnected'));
    conn.bind('failed',        update('disconnected'));
    conn.bind('connecting',    update('connecting'));

    // Set initial state
    wsState.value = conn.state === 'connected' ? 'connected'
        : conn.state === 'connecting'          ? 'connecting'
        : 'disconnected';
}

// ─── Channel subscription ─────────────────────────────────────────────────────

const userId = computed(() => (usePage().props.auth as any).user.id as number);
let channelName = '';

onMounted(() => {
    bindConnectionState();

    channelName = `user.${userId.value}`;

    echo.private(channelName).listen('.CheckCompleted', (e: { monitor: Partial<Monitor> }) => {
        const idx = monitors.value.findIndex(m => m.id === e.monitor.id);
        if (idx !== -1) {
            monitors.value[idx] = { ...monitors.value[idx], ...e.monitor };
        }
    });
});

onUnmounted(() => {
    if (channelName) echo.leave(channelName);
});

// ─── Flash / UI ───────────────────────────────────────────────────────────────

const flash = computed(() => usePage().props.flash as { message?: string });

const statusConfig: Record<string, { label: string; dot: string; badge: string }> = {
    up: {
        label: 'Up',
        dot: 'bg-green-500',
        badge: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    },
    down: {
        label: 'Down',
        dot: 'bg-red-500',
        badge: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    },
    unknown: {
        label: 'Unknown',
        dot: 'bg-gray-400',
        badge: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    },
};

function uptimeBadgeClass(uptime: number | null): string {
    if (uptime === null) return 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400';
    if (uptime >= 99) return 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300';
    if (uptime >= 95) return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300';
    return 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300';
}

function getStatus(monitor: Monitor) {
    if (monitor.is_paused) {
        return {
            label: 'In pausa',
            dot: 'bg-yellow-400',
            badge: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300',
        };
    }
    return statusConfig[monitor.current_status] ?? statusConfig.unknown;
}

function typeIcon(type: Monitor['check_type']): string {
    return {
        http: '🌐',
        tcp: '🔌',
        ping: '📡',
    }[type] ?? '🌐';
}

// ─── Pause toggle ─────────────────────────────────────────────────────────────

const pauseForms = new Map<number, ReturnType<typeof useForm>>();

function getPauseForm(monitorId: number) {
    if (!pauseForms.has(monitorId)) {
        pauseForms.set(monitorId, useForm({}));
    }
    return pauseForms.get(monitorId)!;
}

function togglePause(event: Event, monitor: Monitor) {
    event.stopPropagation();
    getPauseForm(monitor.id).patch(route('monitors.toggle-pause', monitor.id));
}
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        I miei monitor
                    </h2>

                    <!-- WebSocket status indicator -->
                    <div class="flex items-center gap-1.5">
                        <span
                            class="h-1.5 w-1.5 rounded-full"
                            :class="{
                                'bg-green-500': wsState === 'connected',
                                'bg-yellow-400 animate-pulse': wsState === 'connecting',
                                'bg-gray-400': wsState === 'disconnected',
                            }"
                        />
                        <span
                            class="text-xs font-medium"
                            :class="{
                                'text-green-600 dark:text-green-400': wsState === 'connected',
                                'text-yellow-600 dark:text-yellow-400': wsState === 'connecting',
                                'text-gray-400': wsState === 'disconnected',
                            }"
                        >
                            <template v-if="wsState === 'connected'">Live</template>
                            <template v-else-if="wsState === 'connecting'">Connessione…</template>
                            <template v-else>Offline</template>
                        </span>
                        <!-- Manual refresh button shown only when disconnected -->
                        <button
                            v-if="wsState === 'disconnected'"
                            class="ml-1 rounded-md border border-gray-300 px-2 py-0.5 text-xs text-gray-500 transition hover:bg-gray-100 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700"
                            title="Aggiorna la pagina"
                            @click="router.reload()"
                        >
                            ↻ Aggiorna
                        </button>
                    </div>
                </div>

                <Link
                    :href="route('monitors.create')"
                    class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:bg-gray-200 dark:text-gray-800 dark:hover:bg-white"
                >
                    + Aggiungi Monitor
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-4">

                <!-- Flash message -->
                <Transition
                    enter-active-class="transition ease-in-out duration-300"
                    enter-from-class="opacity-0 -translate-y-1"
                    leave-active-class="transition ease-in-out duration-300"
                    leave-to-class="opacity-0 -translate-y-1"
                >
                    <div
                        v-if="flash.message"
                        class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 shadow-sm dark:bg-green-900/30 dark:text-green-300"
                    >
                        {{ flash.message }}
                    </div>
                </Transition>

                <!-- Monitor list -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">

                    <!-- Empty state -->
                    <div
                        v-if="sortedMonitors.length === 0"
                        class="flex flex-col items-center justify-center py-20 text-center"
                    >
                        <svg
                            class="mb-4 h-14 w-14 text-gray-300 dark:text-gray-600"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.5"
                                d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0H3"
                            />
                        </svg>
                        <p class="text-base font-medium text-gray-500 dark:text-gray-400">
                            Nessun monitor ancora
                        </p>
                        <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">
                            Inizia monitorando il tuo primo servizio.
                        </p>
                        <Link
                            :href="route('monitors.create')"
                            class="mt-5 rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 dark:bg-indigo-600 dark:hover:bg-indigo-500"
                        >
                            Aggiungi il tuo primo monitor
                        </Link>
                    </div>

                    <!-- Table -->
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="border-b border-gray-200 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-400">
                                <tr>
                                    <th class="px-6 py-3">Nome / URL</th>
                                    <th class="px-6 py-3">Stato</th>
                                    <th class="px-6 py-3">Uptime 24h</th>
                                    <th class="px-6 py-3">Status code</th>
                                    <th class="px-6 py-3">Resp. time</th>
                                    <th class="px-6 py-3">Ultimo check</th>
                                    <th class="px-6 py-3">Intervallo</th>
                                    <th class="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr
                                    v-for="monitor in sortedMonitors"
                                    :key="monitor.id"
                                    class="group cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/40 transition-colors"
                                    @click="$inertia.visit(route('monitors.show', monitor.id))"
                                >
                                    <!-- Nome / URL -->
                                    <td class="px-6 py-4 max-w-xs">
                                        <div class="font-medium text-gray-900 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 truncate">
                                            {{ typeIcon(monitor.check_type) }} {{ monitor.name ?? '—' }}
                                        </div>
                                        <div class="text-xs text-gray-400 dark:text-gray-500 truncate mt-0.5">
                                            {{ monitor.url }}<span v-if="monitor.check_type === 'tcp' && monitor.port">:{{ monitor.port }}</span>
                                        </div>
                                    </td>

                                    <!-- Stato -->
                                    <td class="px-6 py-4">
                                        <span :class="['inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium', getStatus(monitor).badge]">
                                            <span :class="['h-1.5 w-1.5 rounded-full', getStatus(monitor).dot]" />
                                            {{ getStatus(monitor).label }}
                                        </span>
                                    </td>

                                    <!-- Uptime 24h -->
                                    <td class="px-6 py-4">
                                        <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', uptimeBadgeClass(monitor.uptime_24h)]">
                                            {{ monitor.uptime_24h !== null ? `${monitor.uptime_24h}%` : '—' }}
                                        </span>
                                    </td>

                                    <!-- Status code -->
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-300 font-mono">
                                        <span v-if="monitor.last_status_code !== null">{{ monitor.last_status_code }}</span>
                                        <span v-else class="text-gray-300 dark:text-gray-600">—</span>
                                    </td>

                                    <!-- Response time -->
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-300">
                                        <span v-if="monitor.last_response_time_ms !== null">{{ monitor.last_response_time_ms }} ms</span>
                                        <span v-else class="text-gray-300 dark:text-gray-600">—</span>
                                    </td>

                                    <!-- Ultimo check -->
                                    <td class="px-6 py-4 text-gray-500 dark:text-gray-400 text-xs">
                                        <span v-if="monitor.last_checked_at_human">{{ monitor.last_checked_at_human }}</span>
                                        <span v-else class="text-gray-300 dark:text-gray-600">Mai</span>
                                    </td>

                                    <!-- Intervallo -->
                                    <td class="px-6 py-4 text-gray-500 dark:text-gray-400">
                                        {{ monitor.interval_minutes === 1 ? '1 min' : `${monitor.interval_minutes} min` }}
                                    </td>

                                    <!-- Azioni -->
                                    <td class="px-4 py-4 text-right" @click.stop>
                                        <button
                                            :title="monitor.is_paused ? 'Riprendi monitoraggio' : 'Metti in pausa'"
                                            :disabled="getPauseForm(monitor.id).processing"
                                            class="inline-flex items-center justify-center rounded-md p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 disabled:opacity-40 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                                            @click="togglePause($event, monitor)"
                                        >
                                            <svg v-if="monitor.is_paused" class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M6.3 2.84A1.5 1.5 0 0 0 4 4.11v11.78a1.5 1.5 0 0 0 2.3 1.27l9.344-5.891a1.5 1.5 0 0 0 0-2.538L6.3 2.84Z" />
                                            </svg>
                                            <svg v-else class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M5.75 3a.75.75 0 0 0-.75.75v12.5c0 .414.336.75.75.75h1.5a.75.75 0 0 0 .75-.75V3.75A.75.75 0 0 0 7.25 3h-1.5ZM12.75 3a.75.75 0 0 0-.75.75v12.5c0 .414.336.75.75.75h1.5a.75.75 0 0 0 .75-.75V3.75a.75.75 0 0 0-.75-.75h-1.5Z" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
