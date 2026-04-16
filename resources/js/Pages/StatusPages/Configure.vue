<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface UserMonitor {
    id: number;
    name: string | null;
    url: string;
}

interface AttachedEntry {
    monitor_id: number;
    display_name: string | null;
    sort_order: number;
}

const props = defineProps<{
    statusPage: { id: number; title: string };
    userMonitors: UserMonitor[];
    attached: AttachedEntry[];
}>();

const flash = computed(() => usePage().props.flash as { message?: string });

// Build initial selected state from attached
const selected = ref<Map<number, { display_name: string; sort_order: number }>>(
    new Map(props.attached.map(a => [a.monitor_id, {
        display_name: a.display_name ?? '',
        sort_order: a.sort_order,
    }]))
);

function isChecked(monitorId: number): boolean {
    return selected.value.has(monitorId);
}

function toggleMonitor(monitorId: number) {
    if (selected.value.has(monitorId)) {
        selected.value.delete(monitorId);
    } else {
        selected.value.set(monitorId, {
            display_name: '',
            sort_order: selected.value.size,
        });
    }
}

function getEntry(monitorId: number) {
    return selected.value.get(monitorId)!;
}

function moveUp(monitorId: number) {
    const entry = selected.value.get(monitorId);
    if (!entry || entry.sort_order <= 0) return;

    for (const [id, e] of selected.value) {
        if (e.sort_order === entry.sort_order - 1) {
            e.sort_order++;
            break;
        }
    }
    entry.sort_order--;
}

function moveDown(monitorId: number) {
    const entry = selected.value.get(monitorId);
    if (!entry || entry.sort_order >= selected.value.size - 1) return;

    for (const [id, e] of selected.value) {
        if (e.sort_order === entry.sort_order + 1) {
            e.sort_order--;
            break;
        }
    }
    entry.sort_order++;
}

const sortedSelected = computed(() => {
    return props.userMonitors
        .filter(m => selected.value.has(m.id))
        .sort((a, b) => (selected.value.get(a.id)!.sort_order) - (selected.value.get(b.id)!.sort_order));
});

const form = useForm<{ monitors: AttachedEntry[] }>({ monitors: [] });

function submit() {
    form.monitors = Array.from(selected.value.entries()).map(([id, entry]) => ({
        monitor_id: id,
        display_name: entry.display_name || null,
        sort_order: entry.sort_order,
    }));
    form.put(route('status-pages.update-monitors', props.statusPage.id));
}
</script>

<template>
    <Head :title="`Configura ${statusPage.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <a
                    :href="route('status-pages.index')"
                    class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                >
                    ← Status Pages
                </a>
                <span class="text-gray-300 dark:text-gray-600">/</span>
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Configura "{{ statusPage.title }}"
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8 space-y-6">

                <!-- Flash -->
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

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Monitor selection -->
                    <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800 p-6">
                        <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200 mb-4">
                            Seleziona monitor
                        </h3>

                        <div v-if="userMonitors.length === 0" class="text-sm text-gray-500 dark:text-gray-400">
                            Nessun monitor disponibile. Crea prima un monitor.
                        </div>

                        <div v-else class="space-y-2">
                            <label
                                v-for="monitor in userMonitors"
                                :key="monitor.id"
                                class="flex items-center gap-3 rounded-md px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-700/40 cursor-pointer"
                            >
                                <input
                                    type="checkbox"
                                    :checked="isChecked(monitor.id)"
                                    @change="toggleMonitor(monitor.id)"
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900"
                                />
                                <div class="min-w-0">
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ monitor.name ?? '—' }}
                                    </span>
                                    <span class="ml-2 text-xs text-gray-400 dark:text-gray-500 truncate">
                                        {{ monitor.url }}
                                    </span>
                                </div>
                            </label>
                        </div>

                        <InputError class="mt-2" :message="form.errors.monitors" />
                    </div>

                    <!-- Order & display name -->
                    <div
                        v-if="sortedSelected.length > 0"
                        class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800 p-6"
                    >
                        <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200 mb-4">
                            Ordine e nome visualizzato
                        </h3>

                        <div class="space-y-3">
                            <div
                                v-for="(monitor, idx) in sortedSelected"
                                :key="monitor.id"
                                class="flex items-center gap-3 rounded-md border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/60"
                            >
                                <!-- Order controls -->
                                <div class="flex flex-col gap-0.5">
                                    <button
                                        type="button"
                                        @click="moveUp(monitor.id)"
                                        :disabled="idx === 0"
                                        class="text-gray-400 hover:text-gray-600 disabled:opacity-25 dark:hover:text-gray-300"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                        </svg>
                                    </button>
                                    <button
                                        type="button"
                                        @click="moveDown(monitor.id)"
                                        :disabled="idx === sortedSelected.length - 1"
                                        class="text-gray-400 hover:text-gray-600 disabled:opacity-25 dark:hover:text-gray-300"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                </div>

                                <!-- Monitor info -->
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-500 dark:text-gray-400 truncate">
                                        {{ monitor.name ?? monitor.url }}
                                    </p>
                                    <input
                                        type="text"
                                        v-model="getEntry(monitor.id).display_name"
                                        :placeholder="monitor.name ?? monitor.url"
                                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="flex justify-end">
                        <PrimaryButton
                            :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing"
                        >
                            Salva configurazione
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
