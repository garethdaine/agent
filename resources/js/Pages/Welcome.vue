<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted } from 'vue';
import ApplicationMark from '@/Components/ApplicationMark.vue';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
    laravelVersion: {
        type: String,
        required: true,
    },
    phpVersion: {
        type: String,
        required: true,
    },
});

const coreFeatures = [
    {
        title: 'Scheduled Agent Jobs',
        does: 'Creates deterministic, cron-backed jobs for Codex, Claude, and custom runners.',
        canDo: 'Run markdown-defined tasks on queue workers with full lifecycle tracking, retries, and timeout handling.',
        benefit: 'Turns one-off terminal routines into repeatable, auditable automation.',
    },
    {
        title: 'Requirements Discovery',
        does: 'Runs AI-guided interrogation sessions to capture requirements and implementation context.',
        canDo: 'Collect structured answers, produce summaries/plans, and drive build-task generation from the same flow.',
        benefit: 'Reduces ambiguity before implementation and improves delivery quality.',
    },
    {
        title: 'Linear Delivery Sync',
        does: 'Connects Linear through OAuth and syncs approved tasks from discovery outputs.',
        canDo: 'Map sessions to teams/projects, create issues automatically, and keep execution tied to planning artifacts.',
        benefit: 'Bridges planning and execution without manual copy/paste handoffs.',
    },
    {
        title: 'Live Monitor + Run Events',
        does: 'Streams stdout, stderr, and lifecycle events from active and completed runs.',
        canDo: 'Inspect transitions, diagnose failures quickly, and verify runtime behavior per job run.',
        benefit: 'Gives operators confidence through immediate observability.',
    },
    {
        title: 'Operations + Tooling',
        does: 'Consolidates discovery settings, provider controls, and operational tooling in one interface.',
        canDo: 'Configure task providers, tune workflow behavior, and manage backup-related runtime settings.',
        benefit: 'Keeps platform operations centralized and team-friendly.',
    },
    {
        title: 'Security + Governance',
        does: 'Applies policy enforcement at command, path, env, and API boundaries.',
        canDo: 'Restrict executable resolution, block unsafe env injection, and enforce guarded mutation paths.',
        benefit: 'Maintains safe automation under real production pressure.',
    },
];

const advancedCapabilities = [
    {
        title: 'Agent MCP v5 Control Surface',
        copy: 'Scoped auth, canonical tool manifests, strict error mapping, and versioned contracts for reliable tool execution.',
    },
    {
        title: 'Messenger Control Plane',
        copy: 'Channel connectors, orchestrated chat semantics, and controlled runtime management for cross-channel operations.',
    },
    {
        title: 'Intelligent Delegation v3',
        copy: 'Delegatee ranking, verification stages, coordinator-driven orchestration, and queue topology designed for scale.',
    },
    {
        title: 'Four-Layer Memory Delegation v2',
        copy: 'Core, working, long-term, and consolidation memory layers with retrieval fusion and controlled context injection.',
    },
    {
        title: 'Natural-Language Scheduling',
        copy: 'Parse human scheduling intent into validated rules with fallback contracts and scheduler-safe compatibility behavior.',
    },
];

const advancedFeatureDetails = [
    {
        title: 'Agent MCP v5 Control Surface',
        does: 'Provides a canonical tool execution layer with scoped authorization, versioned manifests, and strict transport/error mapping.',
        canDo: 'Enforce per-tool scope policies, standardize transitions, and keep tool behavior predictable across environments.',
        benefit: 'Reduces integration drift and improves reliability when automations depend on external tool contracts.',
    },
    {
        title: 'Messenger Control Plane',
        does: 'Adds a unified control plane for channel connectors, orchestration semantics, and runtime lifecycle management.',
        canDo: 'Coordinate cross-channel routing, steering behavior, and controlled connector operations from one governance model.',
        benefit: 'Lets teams scale conversational operations without fragmented channel logic.',
    },
    {
        title: 'Intelligent Delegation v3',
        does: 'Implements ranked delegatee assignment with verification stages and coordinator-led execution routing.',
        canDo: 'Select best-fit delegatees, enforce validation gates, and orchestrate multi-queue delegation flows with audit depth.',
        benefit: 'Improves quality and throughput by matching work to the right execution path automatically.',
    },
    {
        title: 'Four-Layer Memory Delegation v2',
        does: 'Introduces layered memory architecture for core, working, long-term, and consolidation memory contexts.',
        canDo: 'Fuse retrieval, manage forgetting/consolidation policies, and inject contextual memory safely into active runs.',
        benefit: 'Keeps agents context-aware over time while maintaining controllable memory boundaries.',
    },
    {
        title: 'Natural-Language Scheduling',
        does: 'Transforms plain-language scheduling intent into validated scheduler-compatible rules.',
        canDo: 'Interpret human phrasing, apply parser safeguards and fallback behavior, and preserve backward-compatible run semantics.',
        benefit: 'Makes advanced scheduling accessible to non-operators without sacrificing runtime correctness.',
    },
];

const executionChannels = [
    {
        title: 'Codex Local CLI Subscriptions',
        detail: 'Runs jobs through locally-installed Codex CLI environments tied to your team subscriptions and local runtime controls.',
    },
    {
        title: 'Claude Local CLI Subscriptions',
        detail: 'Supports local Claude CLI execution patterns for teams that rely on subscription-backed local agent workflows.',
    },
    {
        title: 'API Integrations',
        detail: 'Extends workflows with API-based providers for generation, orchestration, and memory/execution services where key-based integration is required.',
    },
];

const securityPillars = [
    'Command templates resolve executables through allowlists and placeholder rules.',
    'Path policies enforce absolute, boundary-safe directories and markdown constraints.',
    'Environment overrides block forbidden keys and sensitive variable injection.',
    'Sanctum-guarded APIs with throttling and explicit policy authorization on mutation paths.',
    'Queue isolation + audited lifecycle transitions for accountable automation at scale.',
];

let revealObserver = null;

onMounted(() => {
    const nodes = Array.from(document.querySelectorAll('[data-reveal]'));

    revealObserver = new IntersectionObserver((entries) => {
        for (const entry of entries) {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                revealObserver?.unobserve(entry.target);
            }
        }
    }, {
        threshold: 0.16,
        rootMargin: '0px 0px -56px 0px',
    });

    nodes.forEach((node, index) => {
        node.style.transitionDelay = `${Math.min(index * 45, 280)}ms`;
        revealObserver?.observe(node);
    });
});

onBeforeUnmount(() => {
    revealObserver?.disconnect();
    revealObserver = null;
});
</script>

<template>
    <Head title="Agent Scheduler" />

    <div class="landing-root relative min-h-screen overflow-x-hidden text-slate-900 dark:text-slate-100">
        <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
            <div class="ambient-orb orb-a" />
            <div class="ambient-orb orb-b" />
            <div class="ambient-orb orb-c" />
            <div class="ambient-grid" />
        </div>

        <div class="mx-auto w-full max-w-7xl px-6 py-8 sm:px-10 lg:px-16">
            <header class="reveal-card mb-12 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-sky-100/70 bg-white/80 p-4 shadow-[0_18px_48px_rgba(2,132,199,0.12)] backdrop-blur dark:border-slate-700 dark:bg-slate-900/70 dark:shadow-[0_22px_52px_rgba(2,6,23,0.45)]" data-reveal>
                <Link :href="'/'" class="flex items-center gap-3">
                    <ApplicationMark class="h-9 w-9" />
                    <div>
                        <p class="brand-title text-[1.05rem] font-extrabold leading-none text-slate-900 dark:text-slate-100">Agent Scheduler</p>
                        <p class="mono mt-1 text-[11px] tracking-[0.18em] text-slate-500 dark:text-slate-400">LOCAL-FIRST ORCHESTRATION</p>
                    </div>
                </Link>

                <nav v-if="canLogin" class="flex items-center gap-2">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 dark:bg-sky-500 dark:text-slate-950 dark:hover:bg-sky-400"
                    >
                        Open Dashboard
                    </Link>

                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-900 hover:text-slate-900 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-sky-400 dark:hover:text-sky-300"
                        >
                            Log in
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 dark:bg-sky-500 dark:text-slate-950 dark:hover:bg-sky-400"
                        >
                            Create account
                        </Link>
                    </template>
                </nav>
            </header>

            <main class="space-y-20 pb-16">
                <section class="grid items-center gap-10 lg:grid-cols-[1.12fr_0.88fr]">
                    <div class="reveal-card" data-reveal>
                        <p class="mono mb-5 inline-flex items-center rounded-full border border-sky-200/80 bg-white/80 px-4 py-2 text-xs font-medium tracking-[0.22em] text-sky-700 dark:border-sky-900/70 dark:bg-slate-900/70 dark:text-sky-300">
                            UNIFIED AGENT OPERATING SYSTEM
                        </p>
                        <h1 class="text-balance text-4xl font-extrabold leading-tight tracking-tight text-slate-950 dark:text-slate-100 sm:text-5xl lg:text-6xl">
                            Run jobs, discover requirements,
                            <span class="text-sky-600 dark:text-sky-400">delegate intelligently, and orchestrate every agent workflow in one place.</span>
                        </h1>
                        <p class="mt-6 max-w-2xl text-lg leading-relaxed text-slate-700 dark:text-slate-300">
                            Agent Scheduler gives your team an execution-safe control plane that spans scheduling, discovery, provider sync, memory-aware delegation, and secure runtime governance.
                        </p>

                        <div class="mt-8 flex flex-wrap gap-3">
                            <Link
                                v-if="$page.props.auth.user"
                                :href="route('agent.jobs.index')"
                                class="rounded-xl bg-sky-600 px-6 py-3 text-sm font-semibold text-white shadow-[0_14px_28px_rgba(2,132,199,0.28)] transition hover:bg-sky-500 dark:bg-sky-500 dark:text-slate-950 dark:hover:bg-sky-400"
                            >
                                Manage Jobs
                            </Link>
                            <template v-else>
                                <Link
                                    :href="route('login')"
                                    class="rounded-xl bg-sky-600 px-6 py-3 text-sm font-semibold text-white shadow-[0_14px_28px_rgba(2,132,199,0.28)] transition hover:bg-sky-500 dark:bg-sky-500 dark:text-slate-950 dark:hover:bg-sky-400"
                                >
                                    Start Scheduling
                                </Link>
                                <Link
                                    v-if="canRegister"
                                    :href="route('register')"
                                    class="rounded-xl border border-slate-300 bg-white px-6 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-900 hover:text-slate-900 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-sky-400 dark:hover:text-sky-300"
                                >
                                    Create Workspace
                                </Link>
                            </template>
                        </div>

                        <div class="mt-8 flex flex-wrap gap-2">
                            <Link :href="route('agent.monitor.index')" class="mono rounded-full border border-slate-300 bg-white px-3 py-2 text-[11px] tracking-[0.1em] text-slate-600 transition hover:border-sky-500 hover:text-sky-700 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-sky-400 dark:hover:text-sky-300">
                                MONITOR
                            </Link>
                            <Link :href="route('tools.discovery.index')" class="mono rounded-full border border-slate-300 bg-white px-3 py-2 text-[11px] tracking-[0.1em] text-slate-600 transition hover:border-sky-500 hover:text-sky-700 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-sky-400 dark:hover:text-sky-300">
                                DISCOVERY
                            </Link>
                            <Link :href="route('tools.index')" class="mono rounded-full border border-slate-300 bg-white px-3 py-2 text-[11px] tracking-[0.1em] text-slate-600 transition hover:border-sky-500 hover:text-sky-700 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-sky-400 dark:hover:text-sky-300">
                                TOOLS
                            </Link>
                        </div>
                    </div>

                    <div class="reveal-card rounded-3xl border border-slate-200 bg-white p-6 shadow-[0_26px_68px_rgba(15,23,42,0.14)] dark:border-slate-700 dark:bg-slate-900/85 dark:shadow-[0_26px_64px_rgba(2,6,23,0.5)]" data-reveal>
                        <div class="mb-4 flex items-center justify-between">
                            <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Platform Surface</h2>
                            <span class="mono rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">active</span>
                        </div>

                        <div class="space-y-3 text-sm text-slate-600 dark:text-slate-300">
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/70">
                                <p class="mono text-[11px] tracking-wider text-slate-500 dark:text-slate-400">CORE</p>
                                <p class="mt-1 font-semibold text-slate-900 dark:text-slate-100">jobs · monitor · audits · backups</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/70">
                                <p class="mono text-[11px] tracking-wider text-slate-500 dark:text-slate-400">DISCOVERY + DELIVERY</p>
                                <p class="mt-1 font-semibold text-slate-900 dark:text-slate-100">requirements wizard · linear sync · build task generation</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/70">
                                <p class="mono text-[11px] tracking-wider text-slate-500 dark:text-slate-400">INTELLIGENCE LAYER</p>
                                <p class="mt-1 font-semibold text-slate-900 dark:text-slate-100">mcp v5 · messenger control plane · delegation memory</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/70">
                                <p class="mono text-[11px] tracking-wider text-slate-500 dark:text-slate-400">SCHEDULING UX</p>
                                <p class="mt-1 font-semibold text-slate-900 dark:text-slate-100">cron precision + natural-language schedule builder</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section>
                    <div class="reveal-card mb-8" data-reveal>
                        <h2 class="text-2xl font-bold text-slate-950 dark:text-slate-100 sm:text-3xl">Execution channels: subscriptions and APIs</h2>
                        <p class="mt-2 max-w-3xl text-slate-600 dark:text-slate-300">
                            Run with local agent subscriptions where your team already works, and layer API integrations when you need provider-level automation or service-backed workflows.
                        </p>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-3">
                        <article
                            v-for="channel in executionChannels"
                            :key="channel.title"
                            class="reveal-card rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_10px_30px_rgba(15,23,42,0.08)] dark:border-slate-700 dark:bg-slate-900/85 dark:shadow-[0_18px_42px_rgba(2,6,23,0.4)]"
                            data-reveal
                        >
                            <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">{{ channel.title }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ channel.detail }}</p>
                        </article>
                    </div>
                </section>

                <section>
                    <div class="reveal-card mb-8 flex items-end justify-between gap-4" data-reveal>
                        <h2 class="text-2xl font-bold text-slate-950 dark:text-slate-100 sm:text-3xl">Core platform features in detail</h2>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <article
                            v-for="feature in coreFeatures"
                            :key="feature.title"
                            class="reveal-card rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_12px_34px_rgba(15,23,42,0.08)] dark:border-slate-700 dark:bg-slate-900/85 dark:shadow-[0_18px_42px_rgba(2,6,23,0.42)]"
                            data-reveal
                        >
                            <h3 class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ feature.title }}</h3>

                            <div class="mt-3 space-y-3 text-sm leading-relaxed">
                                <div>
                                    <p class="mono text-[11px] tracking-[0.16em] text-sky-700 dark:text-sky-300">WHAT IT DOES</p>
                                    <p class="mt-1 text-slate-700 dark:text-slate-300">{{ feature.does }}</p>
                                </div>
                                <div>
                                    <p class="mono text-[11px] tracking-[0.16em] text-sky-700 dark:text-sky-300">CAN DO</p>
                                    <p class="mt-1 text-slate-700 dark:text-slate-300">{{ feature.canDo }}</p>
                                </div>
                                <div>
                                    <p class="mono text-[11px] tracking-[0.16em] text-sky-700 dark:text-sky-300">BENEFIT</p>
                                    <p class="mt-1 text-slate-700 dark:text-slate-300">{{ feature.benefit }}</p>
                                </div>
                            </div>
                        </article>
                    </div>
                </section>

                <section>
                    <div class="reveal-card mb-8" data-reveal>
                        <h2 class="text-2xl font-bold text-slate-950 dark:text-slate-100 sm:text-3xl">Advanced automation capabilities</h2>
                        <p class="mt-2 max-w-3xl text-slate-600 dark:text-slate-300">
                            Deep orchestration primitives for tools, messaging, delegation, memory, and natural-language control.
                        </p>
                    </div>
                    <div class="grid gap-4 lg:grid-cols-5">
                        <article
                            v-for="capability in advancedCapabilities"
                            :key="capability.title"
                            class="reveal-card rounded-2xl border border-sky-100 bg-sky-50/50 p-5 shadow-[0_10px_30px_rgba(2,132,199,0.1)] dark:border-sky-900/45 dark:bg-sky-950/35 dark:shadow-[0_14px_34px_rgba(2,6,23,0.48)]"
                            data-reveal
                        >
                            <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">{{ capability.title }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-700 dark:text-slate-300">{{ capability.copy }}</p>
                        </article>
                    </div>
                </section>

                <section>
                    <div class="reveal-card mb-8" data-reveal>
                        <h2 class="text-2xl font-bold text-slate-950 dark:text-slate-100 sm:text-3xl">Advanced capability deep-dive</h2>
                        <p class="mt-2 max-w-3xl text-slate-600 dark:text-slate-300">
                            What each advanced feature does in practice and the operational advantage it gives your team.
                        </p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <article
                            v-for="feature in advancedFeatureDetails"
                            :key="feature.title"
                            class="reveal-card rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_12px_34px_rgba(15,23,42,0.08)] dark:border-slate-700 dark:bg-slate-900/85 dark:shadow-[0_18px_42px_rgba(2,6,23,0.42)]"
                            data-reveal
                        >
                            <h3 class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ feature.title }}</h3>

                            <div class="mt-3 space-y-3 text-sm leading-relaxed">
                                <div>
                                    <p class="mono text-[11px] tracking-[0.16em] text-sky-700 dark:text-sky-300">WHAT IT DOES</p>
                                    <p class="mt-1 text-slate-700 dark:text-slate-300">{{ feature.does }}</p>
                                </div>
                                <div>
                                    <p class="mono text-[11px] tracking-[0.16em] text-sky-700 dark:text-sky-300">CAN DO</p>
                                    <p class="mt-1 text-slate-700 dark:text-slate-300">{{ feature.canDo }}</p>
                                </div>
                                <div>
                                    <p class="mono text-[11px] tracking-[0.16em] text-sky-700 dark:text-sky-300">BENEFIT</p>
                                    <p class="mt-1 text-slate-700 dark:text-slate-300">{{ feature.benefit }}</p>
                                </div>
                            </div>
                        </article>
                    </div>
                </section>

                <section class="reveal-card rounded-3xl bg-slate-900 px-8 py-10 text-white dark:bg-slate-950 sm:px-10" data-reveal>
                    <div class="grid gap-8 lg:grid-cols-[1.1fr_0.9fr]">
                        <div>
                            <h2 class="text-2xl font-bold sm:text-3xl">Security is part of execution, not an afterthought.</h2>
                            <p class="mt-3 max-w-2xl text-slate-300">
                                Agent Scheduler enforces policy at command, path, environment, API, and queue boundaries so autonomous workflows stay controlled under pressure.
                            </p>
                        </div>
                        <ul class="space-y-3">
                            <li
                                v-for="pillar in securityPillars"
                                :key="pillar"
                                class="rounded-xl border border-slate-700 bg-slate-800/80 px-4 py-3 text-sm text-slate-200 dark:border-slate-800 dark:bg-slate-900"
                            >
                                {{ pillar }}
                            </li>
                        </ul>
                    </div>
                </section>
            </main>

            <footer class="mono border-t border-slate-200 py-6 text-center text-xs tracking-[0.16em] text-slate-500 dark:border-slate-700 dark:text-slate-400">
                AGENT SCHEDULER · LARAVEL {{ laravelVersion }} · PHP {{ phpVersion }}
            </footer>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.bunny.net/css?family=outfit:400,500,600,700,800|ibm-plex-mono:400,500&display=swap');

.landing-root {
    background: linear-gradient(165deg, #f8fcff 0%, #eaf4ff 52%, #f7fbff 100%);
    font-family: 'Outfit', 'Segoe UI', sans-serif;
}

.brand-title {
    letter-spacing: -0.02em;
}

.mono {
    font-family: 'IBM Plex Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
}

.text-balance {
    text-wrap: balance;
}

.ambient-grid {
    position: absolute;
    inset: 0;
    background-image: linear-gradient(to right, rgba(15, 23, 42, 0.05) 1px, transparent 1px), linear-gradient(to bottom, rgba(15, 23, 42, 0.05) 1px, transparent 1px);
    background-size: 32px 32px;
    mask-image: radial-gradient(ellipse at center, black 52%, transparent 90%);
    animation: gridDrift 28s linear infinite;
}

.ambient-orb {
    position: absolute;
    border-radius: 9999px;
    filter: blur(2px);
    animation: orbFloat 14s ease-in-out infinite;
}

.orb-a {
    left: -12rem;
    top: -10rem;
    height: 32rem;
    width: 32rem;
    background: radial-gradient(circle, rgba(56, 189, 248, 0.3) 0%, rgba(56, 189, 248, 0) 68%);
}

.orb-b {
    right: -12rem;
    top: 7rem;
    height: 28rem;
    width: 28rem;
    background: radial-gradient(circle, rgba(14, 165, 233, 0.24) 0%, rgba(14, 165, 233, 0) 70%);
    animation-delay: -4s;
}

.orb-c {
    bottom: -10rem;
    left: 26%;
    height: 22rem;
    width: 22rem;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.16) 0%, rgba(99, 102, 241, 0) 72%);
    animation-delay: -8s;
}

.reveal-card {
    opacity: 0;
    transform: translateY(18px);
    transition: opacity 560ms ease, transform 560ms ease;
}

.reveal-card.is-visible {
    opacity: 1;
    transform: translateY(0);
}

@keyframes orbFloat {
    0% {
        transform: translate3d(0, 0, 0) scale(1);
    }
    50% {
        transform: translate3d(0, -14px, 0) scale(1.02);
    }
    100% {
        transform: translate3d(0, 0, 0) scale(1);
    }
}

@keyframes gridDrift {
    0% {
        transform: translate3d(0, 0, 0);
    }
    50% {
        transform: translate3d(8px, -8px, 0);
    }
    100% {
        transform: translate3d(0, 0, 0);
    }
}

@media (prefers-color-scheme: dark) {
    .landing-root {
        background: linear-gradient(165deg, #020617 0%, #0b1224 55%, #0f172a 100%);
    }

    .ambient-grid {
        background-image: linear-gradient(to right, rgba(148, 163, 184, 0.12) 1px, transparent 1px), linear-gradient(to bottom, rgba(148, 163, 184, 0.12) 1px, transparent 1px);
    }

    .orb-a {
        background: radial-gradient(circle, rgba(14, 165, 233, 0.24) 0%, rgba(14, 165, 233, 0) 72%);
    }

    .orb-b {
        background: radial-gradient(circle, rgba(56, 189, 248, 0.18) 0%, rgba(56, 189, 248, 0) 72%);
    }

    .orb-c {
        background: radial-gradient(circle, rgba(99, 102, 241, 0.2) 0%, rgba(99, 102, 241, 0) 72%);
    }
}

:global(.dark) .landing-root {
    background: linear-gradient(165deg, #020617 0%, #0b1224 55%, #0f172a 100%);
}

:global(.dark) .ambient-grid {
    background-image: linear-gradient(to right, rgba(148, 163, 184, 0.12) 1px, transparent 1px), linear-gradient(to bottom, rgba(148, 163, 184, 0.12) 1px, transparent 1px);
}

:global(.dark) .orb-a {
    background: radial-gradient(circle, rgba(14, 165, 233, 0.24) 0%, rgba(14, 165, 233, 0) 72%);
}

:global(.dark) .orb-b {
    background: radial-gradient(circle, rgba(56, 189, 248, 0.18) 0%, rgba(56, 189, 248, 0) 72%);
}

:global(.dark) .orb-c {
    background: radial-gradient(circle, rgba(99, 102, 241, 0.2) 0%, rgba(99, 102, 241, 0) 72%);
}

@media (prefers-reduced-motion: reduce) {
    .ambient-orb,
    .ambient-grid,
    .reveal-card {
        animation: none !important;
        transition: none !important;
        opacity: 1 !important;
        transform: none !important;
    }
}
</style>
