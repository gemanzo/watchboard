<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ProBadge from '@/Components/ProBadge.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const ALL_INTERVALS = [1, 2, 3, 5];

interface Monitor {
    id: number;
    name: string | null;
    url: string;
    method: string;
    interval_minutes: number;
    current_status: string;
    is_paused: boolean;
    confirmation_threshold: number;
    response_time_threshold_ms: number | null;
}

const props = defineProps<{
    monitor: Monitor;
    availableIntervals: number[];
    maxThreshold: number;
    responseTimeAlertsEnabled: boolean;
}>();

const isPro = props.maxThreshold > 1;

const form = useForm({
    name:                       props.monitor.name ?? '',
    url:                        props.monitor.url,
    method:                     props.monitor.method,
    interval_minutes:           props.monitor.interval_minutes,
    confirmation_threshold:     props.monitor.confirmation_threshold,
    response_time_threshold_ms: props.monitor.response_time_threshold_ms,
});

const submit = () => {
    form.put(route('monitors.update', props.monitor.id));
};

// Delete dialog
const showDeleteModal = ref(false);
const deleteForm = useForm({});

const confirmDelete = () => {
    deleteForm.delete(route('monitors.destroy', props.monitor.id), {
        onSuccess: () => { showDeleteModal.value = false; },
    });
};

function intervalLabel(minutes: number): string {
    return minutes === 1 ? '1 minuto' : `${minutes} minuti`;
}

function isIntervalLocked(minutes: number): boolean {
    return !props.availableIntervals.includes(minutes);
}
</script>

<template>
    <Head :title="`Modifica – ${monitor.name ?? monitor.url}`" />

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
                    Modifica Monitor
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <div class="p-6">
                        <form @submit.prevent="submit" class="space-y-6">

                            <!-- Name -->
                            <div>
                                <InputLabel for="name" value="Nome (opzionale)" />
                                <TextInput
                                    id="name"
                                    type="text"
                                    class="mt-1 block w-full"
                                    v-model="form.name"
                                    autocomplete="off"
                                    placeholder="Es. API Produzione"
                                />
                                <InputError class="mt-2" :message="form.errors.name" />
                            </div>

                            <!-- URL -->
                            <div>
                                <InputLabel for="url" value="URL" />
                                <TextInput
                                    id="url"
                                    type="url"
                                    class="mt-1 block w-full"
                                    v-model="form.url"
                                    required
                                    placeholder="https://example.com"
                                />
                                <InputError class="mt-2" :message="form.errors.url" />
                            </div>

                            <!-- Method -->
                            <div>
                                <InputLabel for="method" value="Metodo HTTP" />
                                <select
                                    id="method"
                                    v-model="form.method"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                >
                                    <option value="GET">GET</option>
                                    <option value="HEAD">HEAD</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.method" />
                            </div>

                            <!-- Interval -->
                            <div>
                                <div class="flex items-center">
                                    <InputLabel for="interval_minutes" value="Intervallo" />
                                </div>
                                <select
                                    id="interval_minutes"
                                    v-model="form.interval_minutes"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                >
                                    <option
                                        v-for="interval in ALL_INTERVALS"
                                        :key="interval"
                                        :value="interval"
                                        :disabled="isIntervalLocked(interval)"
                                    >
                                        {{ intervalLabel(interval) }}{{ isIntervalLocked(interval) ? ' — Solo Piano Pro' : '' }}
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.interval_minutes" />
                            </div>

                            <!-- Confirmation threshold -->
                            <div>
                                <div class="flex items-center">
                                    <InputLabel for="confirmation_threshold" value="Soglia di conferma" />
                                    <ProBadge v-if="!isPro" />
                                </div>
                                <select
                                    id="confirmation_threshold"
                                    v-model="form.confirmation_threshold"
                                    :disabled="!isPro"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                >
                                    <option :value="1">1 check (immediato)</option>
                                    <option :value="2" :disabled="maxThreshold < 2">2 check consecutivi</option>
                                    <option :value="3" :disabled="maxThreshold < 3">3 check consecutivi</option>
                                </select>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Quanti check falliti consecutivi prima di ricevere un alert.
                                </p>
                                <InputError class="mt-2" :message="form.errors.confirmation_threshold" />
                            </div>

                            <!-- Response time threshold -->
                            <div>
                                <div class="flex items-center">
                                    <InputLabel for="response_time_threshold_ms" value="Soglia response time (opzionale)" />
                                    <ProBadge v-if="!responseTimeAlertsEnabled" />
                                </div>
                                <div class="relative mt-1">
                                    <input
                                        id="response_time_threshold_ms"
                                        type="number"
                                        min="100"
                                        step="100"
                                        class="block w-full rounded-md border-gray-300 pr-12 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600"
                                        :class="{ 'cursor-not-allowed opacity-50': !responseTimeAlertsEnabled }"
                                        :value="form.response_time_threshold_ms ?? ''"
                                        @input="form.response_time_threshold_ms = ($event.target as HTMLInputElement).value === '' ? null : Number(($event.target as HTMLInputElement).value)"
                                        :disabled="!responseTimeAlertsEnabled"
                                        placeholder="Es. 2000"
                                    />
                                    <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-gray-400">
                                        ms
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Ricevi un alert quando la risposta supera questa soglia. Lascia vuoto per disabilitare.
                                </p>
                                <InputError class="mt-2" :message="form.errors.response_time_threshold_ms" />
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center justify-between pt-2">
                                <!-- Delete -->
                                <DangerButton
                                    type="button"
                                    @click="showDeleteModal = true"
                                >
                                    Elimina monitor
                                </DangerButton>

                                <!-- Save -->
                                <div class="flex items-center gap-4">
                                    <Link
                                        :href="route('dashboard')"
                                        class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-gray-400 dark:hover:text-gray-100"
                                    >
                                        Annulla
                                    </Link>
                                    <PrimaryButton
                                        :class="{ 'opacity-25': form.processing }"
                                        :disabled="form.processing"
                                    >
                                        Salva modifiche
                                    </PrimaryButton>
                                </div>
                            </div>
                        </form>
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
