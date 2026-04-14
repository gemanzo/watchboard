<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    availableIntervals: number[];
}>();

const form = useForm({
    name: '',
    url: '',
    method: 'GET',
    interval_minutes: props.availableIntervals[0],
});

const submit = () => {
    form.post(route('monitors.store'));
};
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
                                <InputLabel for="interval_minutes" value="Intervallo" />
                                <select
                                    id="interval_minutes"
                                    v-model="form.interval_minutes"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                >
                                    <option
                                        v-for="interval in availableIntervals"
                                        :key="interval"
                                        :value="interval"
                                    >
                                        {{ interval === 1 ? '1 minuto' : `${interval} minuti` }}
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.interval_minutes" />
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
