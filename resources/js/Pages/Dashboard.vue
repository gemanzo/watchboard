<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Monitor {
    id: number;
    name: string | null;
    url: string;
    method: string;
    interval_minutes: number;
    current_status: 'unknown' | 'up' | 'down';
    is_paused: boolean;
    last_status_code: number | null;
    last_response_time_ms: number | null;
    last_checked_at_human: string | null;
}

defineProps<{
    monitors: Monitor[];
}>();

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
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    I miei monitor
                </h2>
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
                        v-if="monitors.length === 0"
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
                                    <th class="px-6 py-3">Status code</th>
                                    <th class="px-6 py-3">Resp. time</th>
                                    <th class="px-6 py-3">Ultimo check</th>
                                    <th class="px-6 py-3">Intervallo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr
                                    v-for="monitor in monitors"
                                    :key="monitor.id"
                                    class="group cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/40 transition-colors"
                                    @click="$inertia.visit(route('monitors.show', monitor.id))"
                                >
                                    <!-- Nome / URL -->
                                    <td class="px-6 py-4 max-w-xs">
                                        <div class="font-medium text-gray-900 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 truncate">
                                            {{ monitor.name ?? '—' }}
                                        </div>
                                        <div class="text-xs text-gray-400 dark:text-gray-500 truncate mt-0.5">
                                            {{ monitor.url }}
                                        </div>
                                    </td>

                                    <!-- Stato -->
                                    <td class="px-6 py-4">
                                        <span :class="['inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium', getStatus(monitor).badge]">
                                            <span :class="['h-1.5 w-1.5 rounded-full', getStatus(monitor).dot]" />
                                            {{ getStatus(monitor).label }}
                                        </span>
                                    </td>

                                    <!-- Status code -->
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-300 font-mono">
                                        <span v-if="monitor.last_status_code !== null">
                                            {{ monitor.last_status_code }}
                                        </span>
                                        <span v-else class="text-gray-300 dark:text-gray-600">—</span>
                                    </td>

                                    <!-- Response time -->
                                    <td class="px-6 py-4 text-gray-600 dark:text-gray-300">
                                        <span v-if="monitor.last_response_time_ms !== null">
                                            {{ monitor.last_response_time_ms }} ms
                                        </span>
                                        <span v-else class="text-gray-300 dark:text-gray-600">—</span>
                                    </td>

                                    <!-- Ultimo check -->
                                    <td class="px-6 py-4 text-gray-500 dark:text-gray-400 text-xs">
                                        <span v-if="monitor.last_checked_at_human">
                                            {{ monitor.last_checked_at_human }}
                                        </span>
                                        <span v-else class="text-gray-300 dark:text-gray-600">Mai</span>
                                    </td>

                                    <!-- Intervallo -->
                                    <td class="px-6 py-4 text-gray-500 dark:text-gray-400">
                                        {{ monitor.interval_minutes === 1 ? '1 min' : `${monitor.interval_minutes} min` }}
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
