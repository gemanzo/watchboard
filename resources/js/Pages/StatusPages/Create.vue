<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const form = useForm({
    title: '',
    slug: '',
    description: '',
});

let slugManuallyEdited = false;

function slugify(str: string): string {
    return str
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/[\s]+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
}

watch(() => form.title, (title) => {
    if (!slugManuallyEdited) {
        form.slug = slugify(title);
    }
});

function onSlugInput() {
    slugManuallyEdited = true;
}

const submit = () => {
    form.post(route('status-pages.store'));
};
</script>

<template>
    <Head title="Nuova Status Page" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Nuova Status Page
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
                                    placeholder="Es. Stato Servizi Acme"
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
                                        @input="onSlugInput"
                                        required
                                        placeholder="stato-servizi-acme"
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
                                    placeholder="Stato in tempo reale dei nostri servizi"
                                />
                                <InputError class="mt-2" :message="form.errors.description" />
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
                                    Crea Status Page
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
