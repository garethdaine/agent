<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Banner from '@/Components/Banner.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import {
    Clock,
    LayoutDashboard,
    Briefcase,
    Activity,
    MessageSquare,
    Wrench,
    GitBranch,
    ChevronDown,
    ChevronsUpDown,
    Menu,
    X,
    Check,
} from 'lucide-vue-next';

defineProps({
    title: String,
});

const showingNavigationDropdown = ref(false);

const switchToTeam = (team) => {
    router.put(route('current-team.update'), {
        team_id: team.id,
    }, {
        preserveState: false,
    });
};

const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <div>
        <Head :title="title" />

        <Banner />

        <div class="min-h-screen bg-background">
            <nav class="sticky top-0 z-50 bg-card/95 backdrop-blur-sm border-b border-border">
                <!-- Primary Navigation Menu -->
                <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between h-14">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="shrink-0 flex items-center">
                                <Link :href="route('dashboard')" class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-lg bg-primary flex items-center justify-center">
                                        <Clock class="w-4 h-4 text-primary-foreground" />
                                    </div>
                                    <span class="text-foreground font-semibold text-[15px]">Agent Scheduler</span>
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div class="hidden space-x-1 sm:-my-px sm:ms-8 sm:flex sm:items-center">
                                <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
                                    <LayoutDashboard class="w-4 h-4 mr-1.5" />
                                    Dashboard
                                </NavLink>
                                <NavLink :href="route('agent.jobs.index')" :active="route().current('agent.jobs.*')">
                                    <Briefcase class="w-4 h-4 mr-1.5" />
                                    Jobs
                                </NavLink>
                                <NavLink :href="route('agent.monitor.index')" :active="route().current('agent.monitor.*')">
                                    <Activity class="w-4 h-4 mr-1.5" />
                                    Monitor
                                </NavLink>
                                <NavLink :href="route('tools.messenger.index')" :active="route().current('tools.messenger.*')">
                                    <MessageSquare class="w-4 h-4 mr-1.5" />
                                    Messenger
                                </NavLink>
                                <NavLink :href="route('tools.index')" :active="route().current('tools.index') || route().current('tools.discovery.*') || route().current('tools.backups.*') || route().current('tools.features.*')">
                                    <Wrench class="w-4 h-4 mr-1.5" />
                                    Tools
                                </NavLink>
                                <NavLink
                                    v-if="$page.props.delegationEnabled"
                                    :href="route('agent.delegation.index')"
                                    :active="route().current('agent.delegation.*')"
                                >
                                    <GitBranch class="w-4 h-4 mr-1.5" />
                                    Delegation
                                </NavLink>
                            </div>
                        </div>

                        <div class="hidden sm:flex sm:items-center sm:ms-6">
                            <div class="ms-3 relative">
                                <!-- Teams Dropdown -->
                                <Dropdown v-if="$page.props.jetstream.hasTeamFeatures" align="right" width="60">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button type="button" class="inline-flex items-center px-3 py-2 border border-border text-sm leading-4 font-medium rounded-lg text-muted-foreground bg-card hover:text-foreground hover:bg-muted focus:outline-none focus:ring-2 focus:ring-ring/50 transition ease-in-out duration-150">
                                                {{ $page.props.auth.user.current_team.name }}

                                                <ChevronsUpDown class="ms-2 -me-0.5 w-4 h-4" />
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <div class="w-60">
                                            <!-- Team Management -->
                                            <div class="block px-4 py-2 text-xs text-muted-foreground font-medium uppercase tracking-wide">
                                                Manage Team
                                            </div>

                                            <!-- Team Settings -->
                                            <DropdownLink :href="route('teams.show', $page.props.auth.user.current_team)">
                                                Team Settings
                                            </DropdownLink>

                                            <DropdownLink v-if="$page.props.jetstream.canCreateTeams" :href="route('teams.create')">
                                                Create New Team
                                            </DropdownLink>

                                            <!-- Team Switcher -->
                                            <template v-if="$page.props.auth.user.all_teams.length > 1">
                                                <div class="border-t border-border my-1" />

                                                <div class="block px-4 py-2 text-xs text-muted-foreground font-medium uppercase tracking-wide">
                                                    Switch Teams
                                                </div>

                                                <template v-for="team in $page.props.auth.user.all_teams" :key="team.id">
                                                    <form @submit.prevent="switchToTeam(team)">
                                                        <DropdownLink as="button">
                                                            <div class="flex items-center">
                                                                <Check v-if="team.id == $page.props.auth.user.current_team_id" class="me-2 w-5 h-5 text-success" />
                                                                <div>{{ team.name }}</div>
                                                            </div>
                                                        </DropdownLink>
                                                    </form>
                                                </template>
                                            </template>
                                        </div>
                                    </template>
                                </Dropdown>
                            </div>

                            <!-- Settings Dropdown -->
                            <div class="ms-3 relative">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <button v-if="$page.props.jetstream.managesProfilePhotos" type="button" class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:ring-2 focus:ring-ring/50 transition">
                                            <img class="w-8 h-8 rounded-full object-cover" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name">
                                        </button>

                                        <span v-else class="inline-flex rounded-md">
                                            <button type="button" class="inline-flex items-center px-3 py-2 border border-border text-sm leading-4 font-medium rounded-lg text-muted-foreground bg-card hover:text-foreground hover:bg-muted focus:outline-none focus:ring-2 focus:ring-ring/50 transition ease-in-out duration-150">
                                                {{ $page.props.auth.user.name }}

                                                <ChevronDown class="ms-2 -me-0.5 w-4 h-4" />
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <!-- Account Management -->
                                        <div class="block px-4 py-2 text-xs text-muted-foreground font-medium uppercase tracking-wide">
                                            Manage Account
                                        </div>

                                        <DropdownLink :href="route('profile.show')">
                                            Profile
                                        </DropdownLink>

                                        <DropdownLink v-if="$page.props.jetstream.hasApiFeatures" :href="route('api-tokens.index')">
                                            API Tokens
                                        </DropdownLink>

                                        <div class="border-t border-border my-1" />

                                        <!-- Authentication -->
                                        <form @submit.prevent="logout">
                                            <DropdownLink as="button">
                                                Log Out
                                            </DropdownLink>
                                        </form>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button class="inline-flex items-center justify-center p-2 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted focus:outline-none focus:ring-2 focus:ring-ring/50 transition duration-150 ease-in-out" @click="showingNavigationDropdown = ! showingNavigationDropdown">
                                <Menu v-if="!showingNavigationDropdown" class="w-6 h-6" />
                                <X v-else class="w-6 h-6" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div :class="{'block': showingNavigationDropdown, 'hidden': ! showingNavigationDropdown}" class="sm:hidden">
                    <div class="pt-2 pb-3 space-y-1 px-2">
                        <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
                            <LayoutDashboard class="w-5 h-5" />
                            Dashboard
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('agent.jobs.index')" :active="route().current('agent.jobs.*')">
                            <Briefcase class="w-5 h-5" />
                            Jobs
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('agent.monitor.index')" :active="route().current('agent.monitor.*')">
                            <Activity class="w-5 h-5" />
                            Monitor
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('tools.messenger.index')" :active="route().current('tools.messenger.*')">
                            <MessageSquare class="w-5 h-5" />
                            Messenger
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('tools.index')" :active="route().current('tools.index') || route().current('tools.discovery.*') || route().current('tools.backups.*') || route().current('tools.features.*')">
                            <Wrench class="w-5 h-5" />
                            Tools
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="$page.props.delegationEnabled"
                            :href="route('agent.delegation.index')"
                            :active="route().current('agent.delegation.*')"
                        >
                            <GitBranch class="w-5 h-5" />
                            Delegation
                        </ResponsiveNavLink>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div class="pt-4 pb-1 border-t border-border">
                        <div class="flex items-center px-4">
                            <div v-if="$page.props.jetstream.managesProfilePhotos" class="shrink-0 me-3">
                                <img class="w-10 h-10 rounded-full object-cover" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name">
                            </div>
                            <div v-else class="shrink-0 me-3">
                                <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary font-semibold text-sm">
                                    {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                                </div>
                            </div>

                            <div>
                                <div class="font-medium text-base text-foreground">
                                    {{ $page.props.auth.user.name }}
                                </div>
                                <div class="font-medium text-sm text-muted-foreground">
                                    {{ $page.props.auth.user.email }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 space-y-1 px-2">
                            <ResponsiveNavLink :href="route('profile.show')" :active="route().current('profile.show')">
                                Profile
                            </ResponsiveNavLink>

                            <ResponsiveNavLink v-if="$page.props.jetstream.hasApiFeatures" :href="route('api-tokens.index')" :active="route().current('api-tokens.index')">
                                API Tokens
                            </ResponsiveNavLink>

                            <!-- Authentication -->
                            <form method="POST" @submit.prevent="logout">
                                <ResponsiveNavLink as="button">
                                    Log Out
                                </ResponsiveNavLink>
                            </form>

                            <!-- Team Management -->
                            <template v-if="$page.props.jetstream.hasTeamFeatures">
                                <div class="border-t border-border my-2" />

                                <div class="block px-3 py-2 text-xs text-muted-foreground font-medium uppercase tracking-wide">
                                    Manage Team
                                </div>

                                <!-- Team Settings -->
                                <ResponsiveNavLink :href="route('teams.show', $page.props.auth.user.current_team)" :active="route().current('teams.show')">
                                    Team Settings
                                </ResponsiveNavLink>

                                <ResponsiveNavLink v-if="$page.props.jetstream.canCreateTeams" :href="route('teams.create')" :active="route().current('teams.create')">
                                    Create New Team
                                </ResponsiveNavLink>

                                <!-- Team Switcher -->
                                <template v-if="$page.props.auth.user.all_teams.length > 1">
                                    <div class="border-t border-border my-2" />

                                    <div class="block px-3 py-2 text-xs text-muted-foreground font-medium uppercase tracking-wide">
                                        Switch Teams
                                    </div>

                                    <template v-for="team in $page.props.auth.user.all_teams" :key="team.id">
                                        <form @submit.prevent="switchToTeam(team)">
                                            <ResponsiveNavLink as="button">
                                                <div class="flex items-center">
                                                    <Check v-if="team.id == $page.props.auth.user.current_team_id" class="me-2 w-5 h-5 text-success" />
                                                    <div>{{ team.name }}</div>
                                                </div>
                                            </ResponsiveNavLink>
                                        </form>
                                    </template>
                                </template>
                            </template>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header v-if="$slots.header" class="bg-card border-b border-border">
                <div class="max-w-[1440px] mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <slot />
            </main>
        </div>
    </div>
</template>
