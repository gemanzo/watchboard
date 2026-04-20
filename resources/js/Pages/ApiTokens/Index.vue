<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Token {
    id: number;
    name: string;
    last_used_at: string | null;
    created_at: string;
}

defineProps<{ tokens: Token[] }>();

const flash = computed(() => usePage().props.flash as { message?: string; new_token?: string });

// ─── Create token ─────────────────────────────────────────────────────────────
const createForm = useForm({ name: '' });

function createToken() {
    createForm.post(route('api-tokens.store'), {
        onSuccess: () => { createForm.reset(); },
    });
}

// ─── Copy token ───────────────────────────────────────────────────────────────
const copied = ref(false);

async function copyToken(token: string) {
    await navigator.clipboard.writeText(token);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2000);
}

// ─── Revoke token ─────────────────────────────────────────────────────────────
const revokeForm = useForm({});

function revokeToken(tokenId: number) {
    revokeForm.delete(route('api-tokens.destroy', tokenId));
}
</script>

<template>
    <Head title="API Tokens" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                API Tokens
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8 space-y-6">

                <!-- New token banner (shown once after creation) -->
                <Transition
                    enter-active-class="transition ease-in-out duration-300"
                    enter-from-class="opacity-0 -translate-y-1"
                    leave-active-class="transition ease-in-out duration-300"
                    leave-to-class="opacity-0 -translate-y-1"
                >
                    <div
                        v-if="flash.new_token"
                        class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/20"
                    >
                        <p class="mb-2 text-sm font-semibold text-green-800 dark:text-green-300">
                            Token creato — copialo ora, non verrà più mostrato.
                        </p>
                        <div class="flex items-center gap-3">
                            <code class="flex-1 overflow-x-auto rounded bg-white px-3 py-2 font-mono text-xs text-gray-800 shadow-inner dark:bg-gray-900 dark:text-gray-200">
                                {{ flash.new_token }}
                            </code>
                            <button
                                class="shrink-0 rounded-md border border-green-300 bg-white px-3 py-2 text-xs font-medium text-green-700 transition hover:bg-green-50 dark:border-green-700 dark:bg-gray-800 dark:text-green-400 dark:hover:bg-gray-700"
                                @click="copyToken(flash.new_token!)"
                            >
                                {{ copied ? '✓ Copiato' : 'Copia' }}
                            </button>
                        </div>
                    </div>
                </Transition>

                <!-- Flash message -->
                <Transition
                    enter-active-class="transition ease-in-out duration-300"
                    enter-from-class="opacity-0 -translate-y-1"
                    leave-active-class="transition ease-in-out duration-300"
                    leave-to-class="opacity-0 -translate-y-1"
                >
                    <div
                        v-if="flash.message && !flash.new_token"
                        class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 shadow-sm dark:bg-green-900/30 dark:text-green-300"
                    >
                        {{ flash.message }}
                    </div>
                </Transition>

                <!-- Create new token -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                            Crea nuovo token
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            I token permettono di autenticarti all'API con l'header
                            <code class="rounded bg-gray-100 px-1 py-0.5 text-xs dark:bg-gray-700">Authorization: Bearer &lt;token&gt;</code>
                        </p>
                    </div>
                    <form class="flex items-end gap-3 p-6" @submit.prevent="createToken">
                        <div class="flex-1">
                            <label class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300">
                                Nome del token
                            </label>
                            <input
                                v-model="createForm.name"
                                type="text"
                                placeholder="es. GitHub Actions, Local dev…"
                                class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:placeholder-gray-400"
                            />
                            <p v-if="createForm.errors.name" class="mt-1 text-xs text-red-600">
                                {{ createForm.errors.name }}
                            </p>
                        </div>
                        <button
                            type="submit"
                            :disabled="createForm.processing || !createForm.name.trim()"
                            class="shrink-0 rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-700 disabled:opacity-40 dark:bg-indigo-600 dark:hover:bg-indigo-500"
                        >
                            Genera token
                        </button>
                    </form>
                </div>

                <!-- Token list -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                            Token attivi
                        </h3>
                    </div>

                    <!-- Empty state -->
                    <div v-if="tokens.length === 0" class="px-6 py-10 text-center">
                        <p class="text-sm text-gray-400 dark:text-gray-500">Nessun token ancora. Creane uno sopra.</p>
                    </div>

                    <!-- List -->
                    <ul v-else class="divide-y divide-gray-100 dark:divide-gray-700">
                        <li
                            v-for="token in tokens"
                            :key="token.id"
                            class="flex items-center justify-between px-6 py-4"
                        >
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ token.name }}
                                </p>
                                <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                                    Creato il {{ token.created_at }}
                                    <span v-if="token.last_used_at"> · Usato {{ token.last_used_at }}</span>
                                    <span v-else> · Mai usato</span>
                                </p>
                            </div>
                            <button
                                :disabled="revokeForm.processing"
                                class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-50 disabled:opacity-40 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20"
                                @click="revokeToken(token.id)"
                            >
                                Revoca
                            </button>
                        </li>
                    </ul>
                </div>

                <!-- API reference quick guide -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                            Riferimento rapido API
                        </h3>
                    </div>
                    <div class="space-y-3 p-6 text-sm text-gray-600 dark:text-gray-300">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Base URL</p>
                        <code class="block rounded bg-gray-50 px-4 py-2 font-mono text-xs dark:bg-gray-900">
                            {{ $page.props.ziggy.url }}/api/v1
                        </code>

                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 pt-2">Autenticazione</p>
                        <code class="block rounded bg-gray-50 px-4 py-2 font-mono text-xs dark:bg-gray-900">
                            Authorization: Bearer &lt;token&gt;
                        </code>

                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 pt-2">Endpoints</p>
                        <table class="w-full text-xs">
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr v-for="ep in [
                                    { method: 'GET',    path: '/monitors',                    desc: 'Lista monitor (paginata)' },
                                    { method: 'POST',   path: '/monitors',                    desc: 'Crea monitor' },
                                    { method: 'GET',    path: '/monitors/{id}',               desc: 'Dettaglio monitor' },
                                    { method: 'PUT',    path: '/monitors/{id}',               desc: 'Aggiorna monitor' },
                                    { method: 'DELETE', path: '/monitors/{id}',               desc: 'Elimina monitor' },
                                    { method: 'GET',    path: '/monitors/{id}/checks',        desc: 'Check results (paginati, ?from=&to=)' },
                                ]" :key="ep.path + ep.method" class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                    <td class="py-2 pr-4 font-mono font-bold"
                                        :class="{
                                            'text-green-600 dark:text-green-400': ep.method === 'GET',
                                            'text-blue-600 dark:text-blue-400': ep.method === 'POST',
                                            'text-yellow-600 dark:text-yellow-400': ep.method === 'PUT',
                                            'text-red-600 dark:text-red-400': ep.method === 'DELETE',
                                        }"
                                    >{{ ep.method }}</td>
                                    <td class="py-2 pr-4 font-mono text-gray-700 dark:text-gray-300">{{ ep.path }}</td>
                                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ ep.desc }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
