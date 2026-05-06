<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Channel {
    id: number;
    type: 'webhook' | 'slack' | 'email';
    label: string;
    is_active: boolean;
    config: Record<string, string | number | null>;
}

const props = defineProps<{ channel: Channel }>();

const form = useForm({
    type:      props.channel.type,
    label:     props.channel.label,
    is_active: props.channel.is_active,
    config: {
        url:             (props.channel.config.url as string) ?? '',
        secret:          (props.channel.config.secret as string) ?? '',
        timeout_seconds: (props.channel.config.timeout_seconds as number) ?? 10,
        webhook_url:     (props.channel.config.webhook_url as string) ?? '',
        address:         (props.channel.config.address as string) ?? '',
    },
});

const submit = () => form.put(route('notification-channels.update', props.channel.id));

// ── Test button ──────────────────────────────────────────────────────────────
const testing    = ref(false);
const testResult = ref<{ success: boolean; message: string } | null>(null);

async function testChannel() {
    testing.value    = true;
    testResult.value = null;

    try {
        const response = await fetch(route('notification-channels.test', props.channel.id), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'Accept': 'application/json',
            },
        });
        testResult.value = await response.json();
    } catch {
        testResult.value = { success: false, message: 'Errore di rete.' };
    } finally {
        testing.value = false;
    }
}
</script>

<template>
    <Head :title="`Modifica – ${channel.label}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Modifica Canale
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                    <div class="p-6">
                        <form @submit.prevent="submit" class="space-y-6">

                            <!-- Label -->
                            <div>
                                <InputLabel for="label" value="Nome canale" />
                                <TextInput
                                    id="label"
                                    v-model="form.label"
                                    type="text"
                                    class="mt-1 block w-full"
                                />
                                <InputError class="mt-2" :message="form.errors.label" />
                            </div>

                            <!-- Type (read-only) -->
                            <div>
                                <InputLabel value="Tipo canale" />
                                <p class="mt-1 text-sm text-gray-700 dark:text-gray-300 capitalize">
                                    {{ channel.type === 'webhook' ? '🔗 Webhook HTTP' : channel.type === 'slack' ? '💬 Slack' : '✉️ Email' }}
                                    <span class="text-gray-400 dark:text-gray-500 text-xs ml-1">(non modificabile)</span>
                                </p>
                            </div>

                            <!-- ── Webhook config ─────────────────────────── -->
                            <template v-if="channel.type === 'webhook'">
                                <div>
                                    <InputLabel for="config_url" value="URL endpoint" />
                                    <TextInput id="config_url" v-model="form.config.url" type="url" class="mt-1 block w-full" />
                                    <InputError class="mt-2" :message="(form.errors as any)['config.url']" />
                                </div>

                                <div>
                                    <InputLabel for="config_secret" value="Segreto HMAC (opzionale)" />
                                    <TextInput
                                        id="config_secret"
                                        v-model="form.config.secret"
                                        type="text"
                                        class="mt-1 block w-full font-mono"
                                        placeholder="lascia vuoto per non modificare"
                                    />
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        Header inviato: <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">X-WatchBoard-Signature: sha256=&lt;hmac&gt;</code>
                                    </p>
                                    <InputError class="mt-2" :message="(form.errors as any)['config.secret']" />
                                </div>

                                <div>
                                    <InputLabel for="config_timeout" value="Timeout (secondi)" />
                                    <select
                                        id="config_timeout"
                                        v-model.number="form.config.timeout_seconds"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    >
                                        <option :value="5">5 secondi</option>
                                        <option :value="10">10 secondi</option>
                                        <option :value="15">15 secondi</option>
                                        <option :value="30">30 secondi</option>
                                    </select>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        Tempo massimo di attesa per una risposta dall'endpoint. Se superato, la notifica viene considerata fallita senza bloccare gli altri canali.
                                    </p>
                                </div>
                            </template>

                            <!-- ── Slack config ───────────────────────────── -->
                            <template v-if="channel.type === 'slack'">
                                <div>
                                    <InputLabel for="config_webhook_url" value="Slack Webhook URL" />
                                    <TextInput id="config_webhook_url" v-model="form.config.webhook_url" type="url" class="mt-1 block w-full" />
                                    <InputError class="mt-2" :message="(form.errors as any)['config.webhook_url']" />
                                </div>
                            </template>

                            <!-- ── Email config ───────────────────────────── -->
                            <template v-if="channel.type === 'email'">
                                <div>
                                    <InputLabel for="config_address" value="Indirizzo email" />
                                    <TextInput id="config_address" v-model="form.config.address" type="email" class="mt-1 block w-full" />
                                    <InputError class="mt-2" :message="(form.errors as any)['config.address']" />
                                </div>
                            </template>

                            <!-- Active toggle -->
                            <div class="flex items-center gap-3">
                                <button
                                    type="button"
                                    role="switch"
                                    :aria-checked="form.is_active"
                                    @click="form.is_active = !form.is_active"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    :class="form.is_active ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-700'"
                                >
                                    <span
                                        class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                                        :class="form.is_active ? 'translate-x-6' : 'translate-x-1'"
                                    />
                                </button>
                                <InputLabel value="Attivo" class="!mb-0 cursor-pointer" @click="form.is_active = !form.is_active" />
                            </div>

                            <!-- Test result -->
                            <div
                                v-if="testResult"
                                class="rounded-md p-3 text-sm"
                                :class="testResult.success
                                    ? 'bg-green-50 text-green-800 dark:bg-green-900 dark:text-green-200'
                                    : 'bg-red-50 text-red-800 dark:bg-red-900 dark:text-red-200'"
                            >
                                {{ testResult.success ? '✓' : '✗' }} {{ testResult.message }}
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center justify-between">
                                <button
                                    type="button"
                                    @click="testChannel"
                                    :disabled="testing"
                                    class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                                >
                                    {{ testing ? 'Invio in corso...' : '▶ Invia test' }}
                                </button>

                                <div class="flex items-center gap-4">
                                    <a
                                        :href="route('notification-channels.index')"
                                        class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                                    >
                                        Annulla
                                    </a>
                                    <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                        Salva modifiche
                                    </PrimaryButton>
                                </div>
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
