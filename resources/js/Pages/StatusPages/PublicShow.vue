<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

interface Monitor {
    name: string;
    current_status: 'unknown' | 'up' | 'down';
    response_time: number | null;
}

const props = defineProps<{
    statusPage: {
        title: string;
        description: string | null;
    };
    monitors: Monitor[];
}>();

const statusConfig: Record<string, { label: string; dot: string; bg: string }> = {
    up:      { label: 'Operativo',    dot: 'bg-green-500', bg: 'bg-green-50 dark:bg-green-900/20' },
    down:    { label: 'Non operativo', dot: 'bg-red-500',   bg: 'bg-red-50 dark:bg-red-900/20' },
    unknown: { label: 'Sconosciuto',  dot: 'bg-gray-400',  bg: 'bg-gray-50 dark:bg-gray-800' },
};

const allUp = props.monitors.length > 0 && props.monitors.every(m => m.current_status === 'up');
const someDown = props.monitors.some(m => m.current_status === 'down');
</script>

<template>
    <Head :title="statusPage.title" />

    <div class="min-h-screen bg-gray-50 dark:bg-gray-950">
        <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
            <!-- Header -->
            <div class="text-center mb-10">
                <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                    {{ statusPage.title }}
                </h1>
                <p v-if="statusPage.description" class="mt-2 text-gray-500 dark:text-gray-400">
                    {{ statusPage.description }}
                </p>
            </div>

            <!-- Global status banner -->
            <div
                class="mb-8 rounded-lg px-6 py-4 text-center text-sm font-medium"
                :class="allUp
                    ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
                    : someDown
                        ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300'
                        : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'"
            >
                <template v-if="monitors.length === 0">
                    Nessun servizio monitorato.
                </template>
                <template v-else-if="allUp">
                    Tutti i sistemi sono operativi.
                </template>
                <template v-else-if="someDown">
                    Alcuni sistemi presentano problemi.
                </template>
                <template v-else>
                    Stato dei servizi in aggiornamento.
                </template>
            </div>

            <!-- Monitor list -->
            <div class="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-900">
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    <div
                        v-for="(monitor, i) in monitors"
                        :key="i"
                        class="flex items-center justify-between px-6 py-4"
                    >
                        <div class="flex items-center gap-3">
                            <span :class="['h-2.5 w-2.5 rounded-full', statusConfig[monitor.current_status].dot]" />
                            <span class="font-medium text-gray-900 dark:text-gray-100">
                                {{ monitor.name }}
                            </span>
                        </div>

                        <div class="flex items-center gap-4">
                            <span
                                v-if="monitor.response_time !== null"
                                class="text-xs text-gray-400 dark:text-gray-500"
                            >
                                {{ monitor.response_time }} ms
                            </span>
                            <span class="text-xs font-medium" :class="{
                                'text-green-600 dark:text-green-400': monitor.current_status === 'up',
                                'text-red-600 dark:text-red-400': monitor.current_status === 'down',
                                'text-gray-400': monitor.current_status === 'unknown',
                            }">
                                {{ statusConfig[monitor.current_status].label }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <p class="mt-8 text-center text-xs text-gray-400 dark:text-gray-600">
                Powered by WatchBoard
            </p>
        </div>
    </div>
</template>
