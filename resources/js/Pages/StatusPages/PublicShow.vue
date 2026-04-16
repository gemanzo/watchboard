<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface DailyUptime {
    [date: string]: number | null;
}

interface Monitor {
    name: string;
    current_status: 'unknown' | 'up' | 'down';
    response_time: number | null;
    daily_uptime: DailyUptime;
}

const props = defineProps<{
    statusPage: {
        title: string;
        description: string | null;
    };
    monitors: Monitor[];
}>();

// ─── Status helpers ──────────────────────────────────────────────────────────

function statusLabel(status: string): string {
    return { up: 'Operational', down: 'Down', unknown: 'Unknown' }[status] ?? 'Unknown';
}

function statusDotClass(status: string): string {
    return {
        up: 'bg-green-500',
        down: 'bg-red-500',
        unknown: 'bg-gray-400',
    }[status] ?? 'bg-gray-400';
}

function statusTextClass(status: string): string {
    return {
        up: 'text-green-600 dark:text-green-400',
        down: 'text-red-600 dark:text-red-400',
        unknown: 'text-gray-400 dark:text-gray-500',
    }[status] ?? 'text-gray-400';
}

// ─── Overall status ──────────────────────────────────────────────────────────

const overallStatus = computed(() => {
    if (props.monitors.length === 0) return 'empty';
    const hasDown = props.monitors.some(m => m.current_status === 'down');
    const allUp = props.monitors.every(m => m.current_status === 'up');
    if (allUp) return 'operational';
    if (hasDown) return 'major';
    return 'partial';
});

const overallConfig = {
    operational: {
        label: 'All Systems Operational',
        icon: '✓',
        bg: 'bg-green-50 border-green-200 dark:bg-green-900/20 dark:border-green-800',
        text: 'text-green-800 dark:text-green-300',
        iconBg: 'bg-green-500',
    },
    partial: {
        label: 'Partial Outage',
        icon: '!',
        bg: 'bg-yellow-50 border-yellow-200 dark:bg-yellow-900/20 dark:border-yellow-800',
        text: 'text-yellow-800 dark:text-yellow-300',
        iconBg: 'bg-yellow-500',
    },
    major: {
        label: 'Major Outage',
        icon: '!',
        bg: 'bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-800',
        text: 'text-red-800 dark:text-red-300',
        iconBg: 'bg-red-500',
    },
    empty: {
        label: 'No services monitored',
        icon: '?',
        bg: 'bg-gray-50 border-gray-200 dark:bg-gray-800 dark:border-gray-700',
        text: 'text-gray-600 dark:text-gray-400',
        iconBg: 'bg-gray-400',
    },
};

// ─── Uptime bar helpers ──────────────────────────────────────────────────────

function dayColor(uptime: number | null): string {
    if (uptime === null) return 'bg-gray-200 dark:bg-gray-700';
    if (uptime === 100) return 'bg-green-500';
    if (uptime >= 95) return 'bg-yellow-400';
    return 'bg-red-500';
}

function formatDate(dateStr: string): string {
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
}

function uptimeLabel(uptime: number | null): string {
    if (uptime === null) return 'No data';
    return `${uptime}% uptime`;
}

function overallUptimePercent(dailyUptime: DailyUptime): string | null {
    const values = Object.values(dailyUptime).filter((v): v is number => v !== null);
    if (values.length === 0) return null;
    const avg = values.reduce((a, b) => a + b, 0) / values.length;
    return avg.toFixed(2);
}

// ─── Tooltip state ───────────────────────────────────────────────────────────

const tooltip = ref<{ show: boolean; x: number; y: number; date: string; uptime: number | null }>({
    show: false, x: 0, y: 0, date: '', uptime: null,
});

function showTooltip(event: MouseEvent, date: string, uptime: number | null) {
    const rect = (event.target as HTMLElement).getBoundingClientRect();
    tooltip.value = {
        show: true,
        x: rect.left + rect.width / 2,
        y: rect.top - 8,
        date,
        uptime,
    };
}

function hideTooltip() {
    tooltip.value.show = false;
}
</script>

<template>
    <Head :title="statusPage.title" />

    <!-- Tooltip (portal to body-level) -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="opacity-0 translate-y-1"
            leave-active-class="transition duration-75 ease-in"
            leave-to-class="opacity-0 translate-y-1"
        >
            <div
                v-if="tooltip.show"
                class="pointer-events-none fixed z-50 -translate-x-1/2 -translate-y-full rounded-lg bg-gray-900 px-3 py-2 text-xs text-white shadow-lg dark:bg-gray-100 dark:text-gray-900"
                :style="{ left: `${tooltip.x}px`, top: `${tooltip.y}px` }"
            >
                <p class="font-medium">{{ formatDate(tooltip.date) }}</p>
                <p class="mt-0.5 opacity-80">{{ uptimeLabel(tooltip.uptime) }}</p>
                <div class="absolute left-1/2 top-full -translate-x-1/2 border-4 border-transparent border-t-gray-900 dark:border-t-gray-100" />
            </div>
        </Transition>
    </Teleport>

    <div class="min-h-screen bg-gray-50 dark:bg-gray-950">
        <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6">

            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-gray-100">
                    {{ statusPage.title }}
                </h1>
                <p v-if="statusPage.description" class="mt-2 text-base text-gray-500 dark:text-gray-400">
                    {{ statusPage.description }}
                </p>
            </div>

            <!-- Overall status banner -->
            <div
                class="mb-8 flex items-center justify-center gap-3 rounded-xl border px-6 py-4"
                :class="[overallConfig[overallStatus].bg]"
            >
                <span
                    class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold text-white"
                    :class="overallConfig[overallStatus].iconBg"
                >
                    {{ overallConfig[overallStatus].icon }}
                </span>
                <span class="text-sm font-semibold" :class="overallConfig[overallStatus].text">
                    {{ overallConfig[overallStatus].label }}
                </span>
            </div>

            <!-- Monitor list -->
            <div class="space-y-4">
                <div
                    v-for="(monitor, i) in monitors"
                    :key="i"
                    class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800"
                >
                    <!-- Monitor header row -->
                    <div class="flex items-center justify-between px-5 pt-4 pb-2">
                        <div class="flex items-center gap-2.5">
                            <span :class="['h-2.5 w-2.5 rounded-full', statusDotClass(monitor.current_status)]" />
                            <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                {{ monitor.name }}
                            </span>
                        </div>
                        <span class="text-xs font-medium" :class="statusTextClass(monitor.current_status)">
                            {{ statusLabel(monitor.current_status) }}
                        </span>
                    </div>

                    <!-- 90-day uptime bar -->
                    <div class="px-5 pb-4">
                        <div class="flex items-end gap-px">
                            <div
                                v-for="(uptime, date) in monitor.daily_uptime"
                                :key="date"
                                :class="['flex-1 rounded-sm cursor-pointer transition-all hover:opacity-80', dayColor(uptime)]"
                                style="min-width: 2px; height: 28px;"
                                @mouseenter="showTooltip($event, date as string, uptime)"
                                @mouseleave="hideTooltip"
                            />
                        </div>
                        <div class="mt-1.5 flex items-center justify-between text-[10px] text-gray-400 dark:text-gray-500">
                            <span>90 days ago</span>
                            <span v-if="overallUptimePercent(monitor.daily_uptime)" class="font-medium">
                                {{ overallUptimePercent(monitor.daily_uptime) }}% uptime
                            </span>
                            <span>Today</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div
                v-if="monitors.length === 0"
                class="mt-4 rounded-xl bg-white p-12 text-center shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800"
            >
                <p class="text-sm text-gray-400 dark:text-gray-500">No services monitored.</p>
            </div>

            <!-- Footer -->
            <p class="mt-10 text-center text-xs text-gray-400 dark:text-gray-600">
                Powered by WatchBoard
            </p>
        </div>
    </div>
</template>
