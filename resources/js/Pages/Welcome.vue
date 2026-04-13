<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    canLogin?: boolean;
    canRegister?: boolean;
}>();
</script>

<template>
    <Head title="WatchBoard — Uptime Monitoring" />

    <div class="min-h-screen bg-slate-950 text-white antialiased">
        <!-- Nav -->
        <nav class="flex items-center justify-between px-6 py-5 max-w-6xl mx-auto">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-emerald-500 flex items-center justify-center">
                    <svg class="w-4.5 h-4.5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                    </svg>
                </div>
                <span class="text-lg font-semibold tracking-tight">WatchBoard</span>
            </div>

            <div v-if="canLogin" class="flex items-center gap-3">
                <Link
                    v-if="$page.props.auth.user"
                    :href="route('dashboard')"
                    class="text-sm font-medium px-4 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-400 transition-colors"
                >
                    Dashboard
                </Link>
                <template v-else>
                    <Link
                        :href="route('login')"
                        class="text-sm font-medium text-slate-300 hover:text-white transition-colors px-3 py-2"
                    >
                        Log in
                    </Link>
                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="text-sm font-medium px-4 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-400 transition-colors"
                    >
                        Get Started
                    </Link>
                </template>
            </div>
        </nav>

        <!-- Hero -->
        <main class="max-w-6xl mx-auto px-6">
            <section class="pt-24 pb-20 text-center">
                <div class="inline-flex items-center gap-2 text-xs font-medium text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 rounded-full px-3.5 py-1.5 mb-8">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                    Monitoring made simple
                </div>

                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-tight max-w-3xl mx-auto">
                    Know when your services
                    <span class="text-emerald-400">go down</span>
                    before your users do
                </h1>

                <p class="mt-6 text-lg text-slate-400 max-w-xl mx-auto leading-relaxed">
                    Monitor uptime, get instant alerts, and share a public status page with your users. Free to start.
                </p>

                <div class="flex items-center justify-center gap-4 mt-10">
                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="text-sm font-semibold px-6 py-3 rounded-lg bg-emerald-500 hover:bg-emerald-400 transition-colors"
                    >
                        Start Monitoring — Free
                    </Link>
                    <a href="#features" class="text-sm font-medium text-slate-400 hover:text-white transition-colors px-4 py-3">
                        See how it works &darr;
                    </a>
                </div>
            </section>

            <!-- Status Preview -->
            <section class="pb-24">
                <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-6 max-w-2xl mx-auto">
                    <div class="flex items-center justify-between mb-5">
                        <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Live Status</span>
                        <span class="text-xs text-emerald-400 font-medium">All Systems Operational</span>
                    </div>
                    <div class="space-y-3">
                        <div v-for="service in [
                            { name: 'API Server', status: 'up', time: '45ms' },
                            { name: 'Web App', status: 'up', time: '120ms' },
                            { name: 'Database Cluster', status: 'up', time: '12ms' },
                            { name: 'CDN', status: 'down', time: '—' },
                        ]" :key="service.name"
                            class="flex items-center justify-between py-2.5 px-4 rounded-lg bg-slate-800/50"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    class="w-2 h-2 rounded-full"
                                    :class="service.status === 'up' ? 'bg-emerald-500' : 'bg-red-500'"
                                ></span>
                                <span class="text-sm text-slate-200">{{ service.name }}</span>
                            </div>
                            <span class="text-xs font-mono" :class="service.status === 'up' ? 'text-slate-500' : 'text-red-400'">
                                {{ service.time }}
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Features -->
            <section id="features" class="pb-24">
                <div class="grid md:grid-cols-3 gap-6">
                    <div v-for="feature in [
                        {
                            icon: 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0z',
                            title: 'Checks every minute',
                            desc: 'HTTP monitoring with configurable intervals. Know about issues before they become incidents.'
                        },
                        {
                            icon: 'M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0',
                            title: 'Instant alerts',
                            desc: 'Email notifications when your services go down and when they recover. Never miss a beat.'
                        },
                        {
                            icon: 'M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5',
                            title: 'Public status page',
                            desc: 'A branded status page your users can check. Uptime history, incident tracking, all built-in.'
                        },
                    ]" :key="feature.title"
                        class="rounded-xl border border-slate-800 bg-slate-900/40 p-6"
                    >
                        <div class="w-10 h-10 rounded-lg bg-slate-800 flex items-center justify-center mb-4">
                            <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="feature.icon" />
                            </svg>
                        </div>
                        <h3 class="text-sm font-semibold text-white mb-2">{{ feature.title }}</h3>
                        <p class="text-sm text-slate-400 leading-relaxed">{{ feature.desc }}</p>
                    </div>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer class="border-t border-slate-800/60 py-8">
            <p class="text-center text-xs text-slate-600">
                &copy; {{ new Date().getFullYear() }} WatchBoard
            </p>
        </footer>
    </div>
</template>