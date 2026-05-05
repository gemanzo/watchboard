<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ProBadge from '@/Components/ProBadge.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const ALL_INTERVALS = [1, 2, 3, 5];

const props = defineProps<{
    availableIntervals: number[];
    maxThreshold: number;
    responseTimeAlertsEnabled: boolean;
}>();

const isPro = props.maxThreshold > 1;

const form = useForm({
    name:                        '',
    url:                         '',
    method:                      'GET',
    interval_minutes:            props.availableIntervals[0],
    confirmation_threshold:      1,
    response_time_threshold_ms:  null as number | null,
});

const submit = () => {
    form.post(route('monitors.store'));
};

function intervalLabel(minutes: number): string {
    return minutes === 1 ? '1 minuto' : `${minutes} minuti`;
}

function isIntervalLocked(minutes: number): boolean {
    return !props.availableIntervals.includes(minutes);
}
</script>

<template>
    <Head title="Nuovo Monitor" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Nuovo Monitor
            </h2>
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
                                    autofocus
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
                                    <TextInput
                                        id="response_time_threshold_ms"
                                        type="number"
                                        min="100"
                                        step="100"
                                        class="block w-full pr-12"
                                        :class="{ 'cursor-not-allowed opacity-50': !responseTimeAlertsEnabled }"
                                        v-model.number="form.response_time_threshold_ms"
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
                            <div class="flex items-center justify-end gap-4">
                                <a
                                    :href="route('dashboard')"
                                    class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-gray-400 dark:hover:text-gray-100"
                                >
                                    Annulla
                                </a>
                                <PrimaryButton
                                    :class="{ 'opacity-25': form.processing }"
                                    :disabled="form.processing"
                                >
                                    Crea Monitor
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
