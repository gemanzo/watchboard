<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

interface Monitor {
    id: number;
    name: string | null;
    url: string;
    method: string;
    interval_minutes: number;
    current_status: 'unknown' | 'up' | 'down';
    is_paused: boolean;
}

defineProps<{
    monitor: Monitor;
}>();
</script>

<template>
    <Head :title="monitor.name ?? monitor.url" />

    <AuthenticatedLayout>
        <template #header>
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
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <div class="flex flex-col items-center justify-center py-20 text-center">
                        <svg
                            class="mb-4 h-12 w-12 text-gray-300 dark:text-gray-600"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.5"
                                d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"
                            />
                        </svg>
                        <p class="text-base font-medium text-gray-500 dark:text-gray-400">
                            Dettaglio monitor
                        </p>
                        <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">
                            Grafici e storico check disponibili nei prossimi sprint.
                        </p>

                        <!-- Monitor summary -->
                        <dl class="mt-8 grid grid-cols-2 gap-x-8 gap-y-4 text-sm sm:grid-cols-4">
                            <div class="text-left">
                                <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">URL</dt>
                                <dd class="mt-1 truncate max-w-[180px] text-gray-700 dark:text-gray-200">{{ monitor.url }}</dd>
                            </div>
                            <div class="text-left">
                                <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Metodo</dt>
                                <dd class="mt-1 text-gray-700 dark:text-gray-200">{{ monitor.method }}</dd>
                            </div>
                            <div class="text-left">
                                <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Intervallo</dt>
                                <dd class="mt-1 text-gray-700 dark:text-gray-200">
                                    {{ monitor.interval_minutes === 1 ? '1 minuto' : `${monitor.interval_minutes} minuti` }}
                                </dd>
                            </div>
                            <div class="text-left">
                                <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Stato</dt>
                                <dd class="mt-1 capitalize text-gray-700 dark:text-gray-200">{{ monitor.current_status }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
