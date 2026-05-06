<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ProBadge from '@/Components/ProBadge.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Channel {
    id: number;
    type: 'webhook' | 'slack' | 'email';
    label: string;
    is_active: boolean;
    summary: string;
}

const props = defineProps<{ channels: Channel[]; canManageChannels: boolean }>();

const typeLabel: Record<string, string> = {
    webhook: 'Webhook',
    slack:   'Slack',
    email:   'Email',
};

const typeIcon: Record<string, string> = {
    webhook: '🔗',
    slack:   '💬',
    email:   '✉️',
};

// ── Test button ─────────────────────────────────────────────────────────────
const testingId   = ref<number | null>(null);
const testResults = ref<Record<number, { success: boolean; message: string }>>({});

async function testChannel(id: number) {
    testingId.value = id;
    testResults.value[id] = { success: false, message: '' };

    try {
        const response = await fetch(route('notification-channels.test', id), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'Accept': 'application/json',
            },
        });
        const data = await response.json();
        testResults.value[id] = data;
    } catch {
        testResults.value[id] = { success: false, message: 'Errore di rete.' };
    } finally {
        testingId.value = null;
    }
}

function deleteChannel(id: number) {
    if (confirm('Eliminare questo canale?')) {
        router.delete(route('notification-channels.destroy', id));
    }
}
</script>

<template>
    <Head title="Canali di Notifica" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Canali di Notifica
                </h2>
                <Link
                    v-if="props.canManageChannels"
                    :href="route('notification-channels.create')"
                    class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    + Nuovo canale
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8 space-y-4">

                <!-- Upgrade prompt (free plan) -->
                <div
                    v-if="!props.canManageChannels"
                    class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800 p-10 text-center"
                >
                    <p class="text-5xl mb-4">🔔</p>
                    <div class="flex items-center justify-center gap-2 mb-3">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Canali di notifica aggiuntivi</h3>
                        <ProBadge />
                    </div>
                    <p class="text-gray-600 dark:text-gray-400 max-w-md mx-auto">
                        Con il piano Pro puoi ricevere notifiche via <strong>webhook HTTP</strong>, <strong>Slack</strong>
                        ed <strong>email personalizzate</strong>, in aggiunta all'email del tuo account.
                    </p>
                    <ul class="mt-4 space-y-1 text-sm text-gray-500 dark:text-gray-400">
                        <li>🔗 Webhook HTTP con firma HMAC opzionale</li>
                        <li>💬 Slack Incoming Webhooks</li>
                        <li>✉️ Email su indirizzi custom (es. ops@tuaazienda.com)</li>
                        <li>🔁 Canali illimitati</li>
                    </ul>
                </div>

                <!-- Empty state -->
                <div
                    v-if="props.canManageChannels && channels.length === 0"
                    class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800 p-10 text-center"
                >
                    <p class="text-4xl mb-3">🔔</p>
                    <p class="text-gray-600 dark:text-gray-400">
                        Nessun canale configurato. Aggiungine uno per ricevere notifiche via webhook, Slack o email personalizzata.
                    </p>
                    <Link
                        :href="route('notification-channels.create')"
                        class="mt-4 inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                    >
                        + Aggiungi il primo canale
                    </Link>
                </div>

                <!-- Channel cards -->
                <div
                    v-for="ch in props.canManageChannels ? channels : []"
                    :key="ch.id"
                    class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800"
                >
                    <div class="p-5">
                        <div class="flex items-start justify-between gap-4">
                            <!-- Left: info -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-lg">{{ typeIcon[ch.type] }}</span>
                                    <span class="font-semibold text-gray-900 dark:text-gray-100">{{ ch.label }}</span>
                                    <span
                                        class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium"
                                        :class="ch.is_active
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                            : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'"
                                    >
                                        {{ ch.is_active ? 'Attivo' : 'Inattivo' }}
                                    </span>
                                    <span class="inline-flex items-center rounded bg-indigo-50 px-1.5 py-0.5 text-xs text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300">
                                        {{ typeLabel[ch.type] }}
                                    </span>
                                </div>
                                <p class="mt-1 truncate text-sm text-gray-500 dark:text-gray-400">{{ ch.summary }}</p>

                                <!-- Test result -->
                                <p
                                    v-if="testResults[ch.id]?.message"
                                    class="mt-2 text-sm"
                                    :class="testResults[ch.id].success
                                        ? 'text-green-600 dark:text-green-400'
                                        : 'text-red-600 dark:text-red-400'"
                                >
                                    {{ testResults[ch.id].success ? '✓' : '✗' }}
                                    {{ testResults[ch.id].message }}
                                </p>
                            </div>

                            <!-- Right: actions -->
                            <div class="flex shrink-0 items-center gap-2">
                                <button
                                    type="button"
                                    @click="testChannel(ch.id)"
                                    :disabled="testingId === ch.id"
                                    class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                                >
                                    {{ testingId === ch.id ? '...' : 'Test' }}
                                </button>
                                <Link
                                    :href="route('notification-channels.edit', ch.id)"
                                    class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                                >
                                    Modifica
                                </Link>
                                <button
                                    type="button"
                                    @click="deleteChannel(ch.id)"
                                    class="rounded-md border border-red-300 bg-white px-3 py-1.5 text-sm text-red-600 shadow-sm hover:bg-red-50 dark:border-red-700 dark:bg-gray-700 dark:text-red-400 dark:hover:bg-gray-600"
                                >
                                    Elimina
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
