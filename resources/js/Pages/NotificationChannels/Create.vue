<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    type:      'webhook' as 'webhook' | 'slack' | 'email',
    label:     '',
    is_active: true,
    config: {
        // webhook
        url:             '',
        secret:          '',
        timeout_seconds: 10,
        // slack
        webhook_url: '',
        // email
        address: '',
    },
});

const submit = () => form.post(route('notification-channels.store'));
</script>

<template>
    <Head title="Nuovo Canale" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Nuovo Canale di Notifica
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
                                    placeholder="Es. Webhook produzione"
                                    autofocus
                                />
                                <InputError class="mt-2" :message="form.errors.label" />
                            </div>

                            <!-- Type -->
                            <div>
                                <InputLabel for="type" value="Tipo canale" />
                                <select
                                    id="type"
                                    v-model="form.type"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                >
                                    <option value="webhook">🔗 Webhook HTTP</option>
                                    <option value="slack">💬 Slack</option>
                                    <option value="email">✉️ Email personalizzata</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.type" />
                            </div>

                            <!-- ── Webhook config ─────────────────────────── -->
                            <template v-if="form.type === 'webhook'">
                                <div>
                                    <InputLabel for="config_url" value="URL endpoint" />
                                    <TextInput
                                        id="config_url"
                                        v-model="form.config.url"
                                        type="url"
                                        class="mt-1 block w-full"
                                        placeholder="https://example.com/webhooks/watchboard"
                                    />
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        WatchBoard invierà un POST JSON a questo URL ad ogni evento.
                                    </p>
                                    <InputError class="mt-2" :message="(form.errors as any)['config.url']" />
                                </div>

                                <div>
                                    <InputLabel for="config_secret" value="Segreto HMAC (opzionale)" />
                                    <TextInput
                                        id="config_secret"
                                        v-model="form.config.secret"
                                        type="text"
                                        class="mt-1 block w-full font-mono"
                                        placeholder="una-chiave-segreta"
                                    />
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        Se impostato, ogni richiesta includerà l'header
                                        <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">X-WatchBoard-Signature: sha256=&lt;hmac&gt;</code>
                                        per verificare l'autenticità.
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
                                    <InputError class="mt-2" :message="(form.errors as any)['config.timeout_seconds']" />
                                </div>
                            </template>

                            <!-- ── Slack config ───────────────────────────── -->
                            <template v-if="form.type === 'slack'">
                                <div>
                                    <InputLabel for="config_webhook_url" value="Slack Webhook URL" />
                                    <TextInput
                                        id="config_webhook_url"
                                        v-model="form.config.webhook_url"
                                        type="url"
                                        class="mt-1 block w-full"
                                        placeholder="https://hooks.slack.com/services/..."
                                    />
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        Crea un'app Slack con Incoming Webhooks e incolla qui l'URL.
                                    </p>
                                    <InputError class="mt-2" :message="(form.errors as any)['config.webhook_url']" />
                                </div>
                            </template>

                            <!-- ── Email config ───────────────────────────── -->
                            <template v-if="form.type === 'email'">
                                <div>
                                    <InputLabel for="config_address" value="Indirizzo email" />
                                    <TextInput
                                        id="config_address"
                                        v-model="form.config.address"
                                        type="email"
                                        class="mt-1 block w-full"
                                        placeholder="ops@example.com"
                                    />
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        Le notifiche verranno inviate anche a questo indirizzo, in aggiunta all'email del tuo account.
                                    </p>
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

                            <!-- Actions -->
                            <div class="flex items-center justify-end gap-4">
                                <a
                                    :href="route('notification-channels.index')"
                                    class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                                >
                                    Annulla
                                </a>
                                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                    Crea canale
                                </PrimaryButton>
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
