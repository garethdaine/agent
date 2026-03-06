<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import SidebarNavLink from '@/Components/SidebarNavLink.vue';
import SidebarNavGroup from '@/Components/SidebarNavGroup.vue';
import {
    Clock,
    LayoutDashboard,
    BookOpen,
    Briefcase,
    Rocket,
    Monitor,
    MessageSquare,
    Play,
    Wrench,
    GitBranch,
    Bot,
    Building2,
    Database,
    Key,
    ToggleLeft,
    Brain,
    FileCode,
    ShieldCheck,
    Stethoscope,
    ScrollText,
    UserCheck,
    Settings,
    FileText,
} from 'lucide-vue-next';

defineProps({
    collapsed: Boolean,
});

const page = usePage();
</script>

<template>
    <aside
        class="flex h-full flex-col border-r border-border bg-card transition-[width] duration-300 ease-in-out"
        :class="collapsed ? 'w-[60px]' : 'w-[240px]'"
    >
        <!-- Logo -->
        <div
            class="flex h-14 shrink-0 items-center border-b border-border px-3"
            :class="collapsed ? 'justify-center' : 'gap-2.5'"
        >
            <Link :href="route('dashboard')" class="flex items-center" :class="collapsed ? '' : 'gap-2.5'">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary">
                    <Clock class="h-4 w-4 text-primary-foreground" />
                </div>
                <span
                    v-if="!collapsed"
                    class="text-[15px] font-semibold text-foreground whitespace-nowrap"
                >
                    Agent Ops
                </span>
            </Link>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto overflow-x-hidden py-3 px-2" :class="collapsed ? 'px-1.5' : 'px-2'">
            <SidebarNavGroup label="Overview" :collapsed="collapsed">
                <SidebarNavLink
                    :href="route('dashboard')"
                    :active="route().current('dashboard')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <LayoutDashboard class="h-4 w-4 shrink-0" />
                    </template>
                    Dashboard
                </SidebarNavLink>
                <SidebarNavLink
                    :href="route('docs.index')"
                    :active="route().current('docs.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <BookOpen class="h-4 w-4 shrink-0" />
                    </template>
                    Docs
                </SidebarNavLink>
                <SidebarNavLink
                    :href="'/docs/api'"
                    :active="$page.url.startsWith('/docs/api')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <FileCode class="h-4 w-4 shrink-0" />
                    </template>
                    API Docs
                </SidebarNavLink>
            </SidebarNavGroup>

            <SidebarNavGroup label="Operations" :collapsed="collapsed">
                <SidebarNavLink
                    :href="route('agent.jobs.index')"
                    :active="route().current('agent.jobs.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <Briefcase class="h-4 w-4 shrink-0" />
                    </template>
                    Jobs
                </SidebarNavLink>
                <SidebarNavLink
                    :href="$page.props.operatorNavigation.deployments"
                    :active="route().current('agent.deployments.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <Rocket class="h-4 w-4 shrink-0" />
                    </template>
                    Deployment
                </SidebarNavLink>
                <SidebarNavLink
                    :href="route('agent.monitor.index')"
                    :active="route().current('agent.monitor.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <Monitor class="h-4 w-4 shrink-0" />
                    </template>
                    Monitor
                </SidebarNavLink>
            </SidebarNavGroup>

            <SidebarNavGroup label="Communication" :collapsed="collapsed">
                <SidebarNavLink
                    :href="route('tools.messenger.index')"
                    :active="route().current('tools.messenger.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <MessageSquare class="h-4 w-4 shrink-0" />
                    </template>
                    Messenger
                </SidebarNavLink>
                <SidebarNavLink
                    :href="route('messenger.runtime.index')"
                    :active="route().current('messenger.runtime.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <Play class="h-4 w-4 shrink-0" />
                    </template>
                    Sessions
                </SidebarNavLink>
                <SidebarNavLink
                    :href="route('messenger.pairings.index')"
                    :active="route().current('messenger.pairings.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <UserCheck class="h-4 w-4 shrink-0" />
                    </template>
                    Pairings
                </SidebarNavLink>
                <SidebarNavLink
                    :href="route('messenger.sessions.index')"
                    :active="route().current('messenger.sessions.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <MessageSquare class="h-4 w-4 shrink-0" />
                    </template>
                    Chat History
                </SidebarNavLink>
            </SidebarNavGroup>

            <SidebarNavGroup label="Workspace" :collapsed="collapsed">
                <SidebarNavLink
                    :href="route('tools.index')"
                    :active="route().current('tools.index') || route().current('tools.discovery.index') || route().current('tools.discovery.create') || route().current('tools.discovery.wizard') || route().current('tools.memory.index') || route().current('tools.code-analysis.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <Wrench class="h-4 w-4 shrink-0" />
                    </template>
                    Tools
                </SidebarNavLink>
                <SidebarNavLink
                    v-if="page.props.delegationEnabled"
                    :href="route('agent.delegation.index')"
                    :active="route().current('agent.delegation.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <GitBranch class="h-4 w-4 shrink-0" />
                    </template>
                    Delegation
                </SidebarNavLink>
                <SidebarNavLink
                    v-if="page.props.orgLayerEnabled"
                    :href="route('org.index')"
                    :active="route().current('org.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <Bot class="h-4 w-4 shrink-0" />
                    </template>
                    Agents
                </SidebarNavLink>
                <SidebarNavLink
                    v-if="page.props.office3dEnabled"
                    :href="route('agent.office')"
                    :active="route().current('agent.office')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <Building2 class="h-4 w-4 shrink-0" />
                    </template>
                    Agent Office
                </SidebarNavLink>
            </SidebarNavGroup>

            <SidebarNavGroup label="Settings" :collapsed="collapsed" :default-open="false">
                <SidebarNavLink
                    :href="route('tools.security.index')"
                    :active="route().current('tools.security.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <ShieldCheck class="h-4 w-4 shrink-0" />
                    </template>
                    Security
                </SidebarNavLink>
                <SidebarNavLink
                    :href="route('tools.diagnostics.index')"
                    :active="route().current('tools.diagnostics.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <Stethoscope class="h-4 w-4 shrink-0" />
                    </template>
                    Diagnostics
                </SidebarNavLink>
                <SidebarNavLink
                    :href="route('tools.logs.index')"
                    :active="route().current('tools.logs.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <FileText class="h-4 w-4 shrink-0" />
                    </template>
                    Logs
                </SidebarNavLink>
                <SidebarNavLink
                    :href="route('tools.audit.index')"
                    :active="route().current('tools.audit.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <ScrollText class="h-4 w-4 shrink-0" />
                    </template>
                    Audit Log
                </SidebarNavLink>
                <SidebarNavLink
                    :href="route('tools.backups.settings')"
                    :active="route().current('tools.backups.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <Database class="h-4 w-4 shrink-0" />
                    </template>
                    Backups
                </SidebarNavLink>
                <SidebarNavLink
                    :href="route('tools.features.settings')"
                    :active="route().current('tools.features.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <ToggleLeft class="h-4 w-4 shrink-0" />
                    </template>
                    Features
                </SidebarNavLink>
                <SidebarNavLink
                    :href="route('tools.memory.settings')"
                    :active="route().current('tools.memory.settings')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <Brain class="h-4 w-4 shrink-0" />
                    </template>
                    Memory
                </SidebarNavLink>
                <SidebarNavLink
                    :href="route('settings.configuration.index')"
                    :active="route().current('settings.configuration.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <Settings class="h-4 w-4 shrink-0" />
                    </template>
                    Configuration
                </SidebarNavLink>
                <SidebarNavLink
                    :href="route('settings.credentials.index')"
                    :active="route().current('settings.credentials.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <Key class="h-4 w-4 shrink-0" />
                    </template>
                    Credentials
                </SidebarNavLink>
            </SidebarNavGroup>
        </nav>

        <!-- Footer (user controls injected by parent) -->
        <div class="shrink-0 border-t border-border p-2">
            <slot name="footer" />
        </div>
    </aside>
</template>
