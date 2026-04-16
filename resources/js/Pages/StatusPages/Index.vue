<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

interface StatusPage {
    id: number;
    title: string;
    slug: string;
    description: string | null;
    is_active: boolean;
    public_url: string;
}

defineProps<{
    statusPages: StatusPage[];
}>();

const flash = computed(() => usePage().props.flash as { message?: string });

const showDeleteModal = ref(false);
const deleteTarget = ref<StatusPage | null>(null);
const deleteForm = useForm({});

function confirmDelete(sp: StatusPage) {
    deleteTarget.value = sp;
    showDeleteModal.value = true;
}

function performDelete() {
    if (!deleteTarget.value) return;
    deleteForm.delete(route('status-pages.destroy', deleteTarget.value.id), {
        onSuccess: () => { showDeleteModal.value = false; },
    });
}

function toggleActive(sp: StatusPage) {
    router.patch(route('status-pages.toggle', sp.id), {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Status Pages" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Status Pages
                </h2>
                <Link
                    :href="route('status-pages.create')"
                    class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:bg-gray-200 dark:text-gray-800 dark:hover:bg-white"
                >
                    + Nuova Status Page
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-4">

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

                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">

                    <!-- Empty state -->
                    <div
                        v-if="statusPages.length === 0"
                        class="flex flex-col items-center justify-center py-20 text-center"
                    >
                        <svg class="mb-4 h-14 w-14 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" />
                        </svg>
                        <p class="text-base font-medium text-gray-500 dark:text-gray-400">
                            Nessuna status page
                        </p>
                        <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">
                            Crea una pagina pubblica per condividere lo stato dei tuoi servizi.
                        </p>
                        <Link
                            :href="route('status-pages.create')"
                            class="mt-5 rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 dark:bg-indigo-600 dark:hover:bg-indigo-500"
                        >
                            Crea la tua prima status page
                        </Link>
                    </div>

                    <!-- List -->
                    <div v-else class="divide-y divide-gray-100 dark:divide-gray-700">
                        <div
                            v-for="sp in statusPages"
                            :key="sp.id"
                            class="flex items-center justify-between px-6 py-4"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <p class="truncate font-medium text-gray-900 dark:text-gray-100">
                                        {{ sp.title }}
                                    </p>
                                    <span
                                        :class="sp.is_active
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                                            : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'"
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    >
                                        {{ sp.is_active ? 'Attiva' : 'Disattivata' }}
                                    </span>
                                </div>
                                <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                                    /status/{{ sp.slug }}
                                </p>
                            </div>

                            <div class="flex items-center gap-2 ml-4">
                                <a
                                    :href="sp.public_url"
                                    target="_blank"
                                    class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                                >
                                    Apri
                                </a>
                                <button
                                    @click="toggleActive(sp)"
                                    class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                                >
                                    {{ sp.is_active ? 'Disattiva' : 'Attiva' }}
                                </button>
                                <Link
                                    :href="route('status-pages.edit', sp.id)"
                                    class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                                >
                                    Modifica
                                </Link>
                                <button
                                    @click="confirmDelete(sp)"
                                    class="rounded-md border border-red-300 bg-white px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:bg-gray-800 dark:text-red-400 dark:hover:bg-red-900/20"
                                >
                                    Elimina
                                </button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Delete modal -->
        <Modal :show="showDeleteModal" max-width="md" @close="showDeleteModal = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    Eliminare la status page?
                </h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    La status page <strong>{{ deleteTarget?.title }}</strong> verrà eliminata definitivamente
                    e non sarà più raggiungibile.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="showDeleteModal = false">Annulla</SecondaryButton>
                    <DangerButton
                        :class="{ 'opacity-25': deleteForm.processing }"
                        :disabled="deleteForm.processing"
                        @click="performDelete"
                    >
                        Sì, elimina
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
