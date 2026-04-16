<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

interface StatusPage {
    id: number;
    title: string;
    slug: string;
    description: string | null;
    is_active: boolean;
}

const props = defineProps<{
    statusPage: StatusPage;
}>();

const form = useForm({
    title: props.statusPage.title,
    slug: props.statusPage.slug,
    description: props.statusPage.description ?? '',
    is_active: props.statusPage.is_active,
});

const submit = () => {
    form.put(route('status-pages.update', props.statusPage.id));
};
</script>

<template>
    <Head :title="`Modifica ${statusPage.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Modifica Status Page
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <div class="p-6">
                        <form @submit.prevent="submit" class="space-y-6">
                            <!-- Title -->
                            <div>
                                <InputLabel for="title" value="Titolo" />
                                <TextInput
                                    id="title"
                                    type="text"
                                    class="mt-1 block w-full"
                                    v-model="form.title"
                                    required
                                    autofocus
                                />
                                <InputError class="mt-2" :message="form.errors.title" />
                            </div>

                            <!-- Slug -->
                            <div>
                                <InputLabel for="slug" value="Slug" />
                                <div class="mt-1 flex rounded-md shadow-sm">
                                    <span class="inline-flex items-center rounded-l-md border border-r-0 border-gray-300 bg-gray-50 px-3 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
                                        /status/
                                    </span>
                                    <input
                                        id="slug"
                                        type="text"
                                        class="block w-full rounded-r-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        v-model="form.slug"
                                        required
                                    />
                                </div>
                                <InputError class="mt-2" :message="form.errors.slug" />
                            </div>

                            <!-- Description -->
                            <div>
                                <InputLabel for="description" value="Descrizione (opzionale)" />
                                <textarea
                                    id="description"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    v-model="form.description"
                                    rows="3"
                                />
                                <InputError class="mt-2" :message="form.errors.description" />
                            </div>

                            <!-- Active toggle -->
                            <div class="flex items-center gap-3">
                                <label class="relative inline-flex cursor-pointer items-center">
                                    <input
                                        type="checkbox"
                                        v-model="form.is_active"
                                        class="peer sr-only"
                                    />
                                    <div class="h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all peer-checked:bg-indigo-600 peer-checked:after:translate-x-full peer-checked:after:border-white dark:bg-gray-700 dark:peer-checked:bg-indigo-500" />
                                </label>
                                <span class="text-sm text-gray-700 dark:text-gray-300">
                                    {{ form.is_active ? 'Attiva' : 'Disattivata' }}
                                </span>
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center justify-end gap-4">
                                <a
                                    :href="route('status-pages.index')"
                                    class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-gray-400 dark:hover:text-gray-100"
                                >
                                    Annulla
                                </a>
                                <PrimaryButton
                                    :class="{ 'opacity-25': form.processing }"
                                    :disabled="form.processing"
                                >
                                    Salva
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
