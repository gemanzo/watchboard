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
}

defineProps<{
    monitors: Monitor[];
}>();

const flash = computed(() => usePage().props.flash as { message?: string });

const statusLabel: Record<string, string> = {
    unknown: 'Sconosciuto',
    up: 'Attivo',
    down: 'Inattivo',
};

const statusClass: Record<string, string> = {
    unknown: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    up: 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
    down: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Dashboard
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
                    <div v-if="monitors.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
                        <svg class="mb-4 h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0H3" />
                        </svg>
                        <p class="text-gray-500 dark:text-gray-400">Nessun monitor ancora.</p>
                        <Link
                            :href="route('monitors.create')"
                            class="mt-4 text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                        >
                            Crea il tuo primo monitor →
                        </Link>
                    </div>

                    <!-- Table -->
                    <table v-else class="w-full text-sm text-left">
                        <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <tr>
                                <th class="px-6 py-3">Nome / URL</th>
                                <th class="px-6 py-3">Metodo</th>
                                <th class="px-6 py-3">Intervallo</th>
                                <th class="px-6 py-3">Stato</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <tr v-for="monitor in monitors" :key="monitor.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">
                                        {{ monitor.name ?? '—' }}
                                    </div>
                                    <div class="truncate max-w-xs text-gray-500 dark:text-gray-400">
                                        {{ monitor.url }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300">
                                    {{ monitor.method }}
                                </td>
                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300">
                                    {{ monitor.interval_minutes === 1 ? '1 min' : `${monitor.interval_minutes} min` }}
                                </td>
                                <td class="px-6 py-4">
                                    <span :class="['rounded-full px-2.5 py-0.5 text-xs font-medium', statusClass[monitor.current_status]]">
                                        {{ statusLabel[monitor.current_status] }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
