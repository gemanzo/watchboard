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
    sslCheckAvailable: boolean;
    keywordCheckAvailable: boolean;
    allowedCheckTypes: string[];
    cooldownOptions: number[];
    notificationsConfigurable: boolean;
}>();

const isPro = props.maxThreshold > 1;

const form = useForm({
    name:                        '',
    url:                         '',
    method:                      'GET',
    check_type:                  'http' as 'http' | 'tcp' | 'ping',
    port:                        null as number | null,
    interval_minutes:            props.availableIntervals[0],
    confirmation_threshold:      1,
    response_time_threshold_ms:  null as number | null,
    keyword_check:               '',
    keyword_check_type:          'contains' as 'contains' | 'not_contains',
    ssl_check_enabled:              false,
    ssl_expiry_alert_days:          14,
    notification_cooldown_minutes:  props.notificationsConfigurable ? 15 : null as number | null,
    recovery_bypass_cooldown:       props.notificationsConfigurable ? true : null as boolean | null,
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
                                <InputLabel for="url" :value="form.check_type === 'http' ? 'URL' : 'Host'" />
                                <TextInput
                                    id="url"
                                    :type="form.check_type === 'http' ? 'url' : 'text'"
                                    class="mt-1 block w-full"
                                    v-model="form.url"
                                    required
                                    autofocus
                                    :placeholder="form.check_type === 'http' ? 'https://example.com' : 'db.internal.local'"
                                />
                                <InputError class="mt-2" :message="form.errors.url" />
                            </div>

                            <!-- Check type -->
                            <div>
                                <div class="flex items-center gap-2">
                                    <InputLabel for="check_type" value="Tipo check" />
                                    <ProBadge v-if="!allowedCheckTypes.includes('tcp')" />
                                </div>
                                <select
                                    id="check_type"
                                    v-model="form.check_type"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                >
                                    <option value="http">🌐 HTTP</option>
                                    <option value="ping">📡 Ping</option>
                                    <option value="tcp" :disabled="!allowedCheckTypes.includes('tcp')">
                                        🔌 TCP{{ !allowedCheckTypes.includes('tcp') ? ' — Solo Piano Pro' : '' }}
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.check_type" />
                            </div>

                            <!-- Method -->
                            <div v-if="form.check_type === 'http'">
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

                            <!-- TCP port -->
                            <div v-if="form.check_type === 'tcp'">
                                <InputLabel for="port" value="Porta TCP" />
                                <input
                                    id="port"
                                    type="number"
                                    min="1"
                                    max="65535"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    :value="form.port ?? ''"
                                    @input="form.port = ($event.target as HTMLInputElement).value === '' ? null : Number(($event.target as HTMLInputElement).value)"
                                    placeholder="3306"
                                />
                                <InputError class="mt-2" :message="form.errors.port" />
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

                            <!-- Keyword check -->
                            <div v-if="form.check_type === 'http'" class="space-y-3">
                                <div class="flex items-center gap-2">
                                    <InputLabel for="keyword_check" value="Keyword check (opzionale)" />
                                    <ProBadge v-if="!keywordCheckAvailable" />
                                </div>
                                <TextInput
                                    id="keyword_check"
                                    type="text"
                                    class="mt-1 block w-full"
                                    v-model="form.keyword_check"
                                    autocomplete="off"
                                    placeholder="Es. Application Error"
                                    :disabled="!keywordCheckAvailable"
                                    :class="{ 'cursor-not-allowed opacity-50': !keywordCheckAvailable }"
                                />

                                <div v-if="keywordCheckAvailable && form.keyword_check.trim() !== ''">
                                    <InputLabel for="keyword_check_type" value="Regola keyword" />
                                    <select
                                        id="keyword_check_type"
                                        v-model="form.keyword_check_type"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    >
                                        <option value="contains">La risposta deve contenere la keyword</option>
                                        <option value="not_contains">La risposta NON deve contenere la keyword</option>
                                    </select>
                                    <InputError class="mt-2" :message="form.errors.keyword_check_type" />
                                </div>

                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Se valorizzato, un check HTTP 2xx viene considerato fallito quando la regola keyword non viene rispettata. Lascia vuoto per disabilitare.
                                </p>
                                <p v-if="!keywordCheckAvailable" class="text-sm text-amber-600 dark:text-amber-400">
                                    Hai raggiunto il limite di monitor con keyword check del tuo piano.
                                </p>
                                <InputError class="mt-2" :message="form.errors.keyword_check" />
                            </div>

                            <!-- SSL monitoring -->
                            <div class="space-y-3">
                                <div class="flex items-center gap-3">
                                    <button
                                        type="button"
                                        role="switch"
                                        :aria-checked="form.ssl_check_enabled"
                                        :disabled="!sslCheckAvailable"
                                        @click="sslCheckAvailable && (form.ssl_check_enabled = !form.ssl_check_enabled)"
                                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                        :class="[form.ssl_check_enabled ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-700', !sslCheckAvailable ? 'cursor-not-allowed opacity-50' : '']"
                                    >
                                        <span
                                            class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                                            :class="form.ssl_check_enabled ? 'translate-x-6' : 'translate-x-1'"
                                        />
                                    </button>
                                    <InputLabel value="Monitoraggio SSL" class="!mb-0 cursor-pointer" @click="sslCheckAvailable && (form.ssl_check_enabled = !form.ssl_check_enabled)" />
                                    <ProBadge v-if="!sslCheckAvailable" />
                                </div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Controlla la scadenza del certificato SSL ogni giorno e invia un alert prima che scada.
                                </p>

                                <div v-if="form.ssl_check_enabled">
                                    <InputLabel for="ssl_expiry_alert_days" value="Giorni di preavviso scadenza" />
                                    <div class="relative mt-1">
                                        <input
                                            id="ssl_expiry_alert_days"
                                            type="number"
                                            min="1"
                                            max="90"
                                            v-model.number="form.ssl_expiry_alert_days"
                                            class="block w-full rounded-md border-gray-300 pr-14 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        />
                                        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-gray-400">giorni</span>
                                    </div>
                                    <InputError class="mt-2" :message="form.errors.ssl_expiry_alert_days" />
                                </div>
                            </div>

                            <!-- Notification cooldown -->
                            <div class="space-y-3">
                                <div class="flex items-center gap-2">
                                    <InputLabel value="Cooldown notifiche" />
                                    <ProBadge v-if="!notificationsConfigurable" />
                                </div>

                                <!-- Pro: fully configurable -->
                                <template v-if="notificationsConfigurable">
                                    <select
                                        v-model="form.notification_cooldown_minutes"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    >
                                        <option v-for="opt in cooldownOptions" :key="opt" :value="opt">
                                            {{ opt }} minuti
                                        </option>
                                    </select>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Attendi almeno questo tempo prima di inviare una nuova notifica per lo stesso monitor.
                                    </p>
                                    <InputError class="mt-2" :message="form.errors.notification_cooldown_minutes" />

                                    <div class="flex items-center gap-3">
                                        <button
                                            type="button"
                                            role="switch"
                                            :aria-checked="form.recovery_bypass_cooldown ?? false"
                                            @click="form.recovery_bypass_cooldown = !form.recovery_bypass_cooldown"
                                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                            :class="form.recovery_bypass_cooldown ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-700'"
                                        >
                                            <span
                                                class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                                                :class="form.recovery_bypass_cooldown ? 'translate-x-6' : 'translate-x-1'"
                                            />
                                        </button>
                                        <InputLabel value="Recovery bypassa il cooldown" class="!mb-0 cursor-pointer" @click="form.recovery_bypass_cooldown = !form.recovery_bypass_cooldown" />
                                    </div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Se abilitato, la notifica di ripristino viene sempre inviata anche durante il cooldown.
                                    </p>
                                </template>

                                <!-- Free: fixed values, locked -->
                                <template v-else>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Cooldown fisso a <strong>15 minuti</strong>. La notifica di ripristino bypassa sempre il cooldown.
                                        Passa al piano Pro per personalizzare questi valori.
                                    </p>
                                </template>
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
