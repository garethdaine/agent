<script setup>
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Banner from '@/Components/Banner.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import NotificationDrawer from '@/Components/NotificationDrawer.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import {
    applyThemePreference,
    getStoredThemePreference,
    setStoredThemePreference,
} from '@/Support/theme';
import {
    Clock,
    LayoutDashboard,
    Briefcase,
    Activity,
    MessageSquare,
    Wrench,
    GitBranch,
    Building2,
    ChevronDown,
    ChevronsUpDown,
    Menu,
    X,
    Check,
    Moon,
    Sun,
    Bell,
} from 'lucide-vue-next';

defineProps({
    title: String,
});

const showingNavigationDropdown = ref(false);
const themePreference = ref(getStoredThemePreference());
const resolvedTheme = ref('light');
const page = usePage();

const notificationDrawerOpen = ref(false);
const notifications = ref([]);
const unreadNotificationCount = ref(0);
const notificationsLoading = ref(false);
const notificationsMutating = ref(false);

let mediaQueryList = null;
let notificationPollInterval = null;

const isDarkTheme = computed(() => resolvedTheme.value === 'dark');
const isSystemTheme = computed(() => themePreference.value === 'system');
const hasUnreadNotifications = computed(() => unreadNotificationCount.value > 0);

const syncTheme = () => {
    resolvedTheme.value = applyThemePreference(themePreference.value);
};

const toggleTheme = () => {
    themePreference.value = isDarkTheme.value ? 'light' : 'dark';
    setStoredThemePreference(themePreference.value);
    syncTheme();
};

const useSystemTheme = () => {
    themePreference.value = 'system';
    setStoredThemePreference('system');
    syncTheme();
};

const handleSystemThemeChange = () => {
    if (themePreference.value === 'system') {
        syncTheme();
    }
};

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

const applyNotificationPayload = (payload) => {
    const nextNotifications = Array.isArray(payload?.notifications) ? payload.notifications : [];
    const nextUnreadCount = Number.isFinite(payload?.unread_count) ? payload.unread_count : 0;

    notifications.value = nextNotifications;
    unreadNotificationCount.value = nextUnreadCount;
};

const fetchNotifications = async ({ silent = false } = {}) => {
    if (!silent) {
        notificationsLoading.value = true;
    } else if (notificationsLoading.value) {
        return;
    }

    try {
        const response = await axios.get('/agent/api/v1/notifications', {
            params: { limit: 20 },
        });

        applyNotificationPayload(response.data?.data ?? {});
    } catch (error) {
        console.error('Failed to load notifications', error);
    } finally {
        if (!silent) {
            notificationsLoading.value = false;
        }
    }
};

const toggleNotificationDrawer = async () => {
    notificationDrawerOpen.value = !notificationDrawerOpen.value;

    if (notificationDrawerOpen.value) {
        await fetchNotifications();
    }
};

const closeNotificationDrawer = () => {
    notificationDrawerOpen.value = false;
};

const markNotificationAsRead = async (id) => {
    if (notificationsMutating.value) {
        return;
    }

    notificationsMutating.value = true;

    try {
        await axios.post(`/agent/api/v1/notifications/${id}/read`);
        await fetchNotifications({ silent: true });
    } catch (error) {
        console.error('Failed to mark notification as read', error);
    } finally {
        notificationsMutating.value = false;
    }
};

const markAllNotificationsRead = async () => {
    if (notificationsMutating.value || unreadNotificationCount.value === 0) {
        return;
    }

    notificationsMutating.value = true;

    try {
        await axios.post('/agent/api/v1/notifications/read-all');
        await fetchNotifications({ silent: true });
    } catch (error) {
        console.error('Failed to mark all notifications as read', error);
    } finally {
        notificationsMutating.value = false;
    }
};

const clearAllNotifications = async () => {
    if (notificationsMutating.value || notifications.value.length === 0) {
        return;
    }

    notificationsMutating.value = true;

    try {
        await axios.delete('/agent/api/v1/notifications');
        applyNotificationPayload({
            notifications: [],
            unread_count: 0,
        });
    } catch (error) {
        console.error('Failed to clear notifications', error);
    } finally {
        notificationsMutating.value = false;
    }
};

const openNotificationAction = async (notification) => {
    const targetUrl = notification?.action?.url;
    if (!targetUrl) {
        return;
    }

    if (!notification.read_at) {
        await markNotificationAsRead(notification.id);
    }

    closeNotificationDrawer();
    router.visit(targetUrl);
};

watch(
    () => page.props.notifications,
    (payload) => {
        applyNotificationPayload(payload ?? {});
    },
    { deep: true, immediate: true },
);

onMounted(() => {
    syncTheme();

    if (typeof window.matchMedia === 'function') {
        mediaQueryList = window.matchMedia('(prefers-color-scheme: dark)');
        if (typeof mediaQueryList.addEventListener === 'function') {
            mediaQueryList.addEventListener('change', handleSystemThemeChange);
        } else if (typeof mediaQueryList.addListener === 'function') {
            mediaQueryList.addListener(handleSystemThemeChange);
        }
    }

    notificationPollInterval = window.setInterval(() => {
        fetchNotifications({ silent: true });
    }, 30000);

    fetchNotifications({ silent: true });
});

onBeforeUnmount(() => {
    if (mediaQueryList) {
        if (typeof mediaQueryList.removeEventListener === 'function') {
            mediaQueryList.removeEventListener('change', handleSystemThemeChange);
        } else if (typeof mediaQueryList.removeListener === 'function') {
            mediaQueryList.removeListener(handleSystemThemeChange);
        }
    }

    if (notificationPollInterval !== null) {
        window.clearInterval(notificationPollInterval);
        notificationPollInterval = null;
    }
});
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
                                <NavLink
                                    v-if="$page.props.orgLayerEnabled"
                                    :href="route('org.index')"
                                    :active="route().current('org.*')"
                                >
                                    <Building2 class="w-4 h-4 mr-1.5" />
                                    Agents
                                </NavLink>
                            </div>
                        </div>

                        <div class="hidden sm:flex sm:items-center sm:ms-6 gap-2">
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

                            <button
                                type="button"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-transparent text-muted-foreground transition hover:bg-muted hover:text-foreground focus:outline-none focus:ring-2 focus:ring-ring/50"
                                :aria-label="isDarkTheme ? 'Switch to light mode' : 'Switch to dark mode'"
                                @click="toggleTheme"
                            >
                                <Sun v-if="isDarkTheme" class="h-4 w-4" />
                                <Moon v-else class="h-4 w-4" />
                            </button>

                            <button
                                type="button"
                                class="relative inline-flex h-8 w-8 items-center justify-center rounded-md border border-transparent text-muted-foreground transition hover:bg-muted hover:text-foreground focus:outline-none focus:ring-2 focus:ring-ring/50"
                                aria-label="Open notifications"
                                @click="toggleNotificationDrawer"
                            >
                                <Bell class="h-4 w-4" />
                                <span
                                    v-if="hasUnreadNotifications"
                                    class="absolute -right-1 -top-1 inline-flex min-h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-semibold leading-none text-primary-foreground"
                                >
                                    {{ unreadNotificationCount > 99 ? '99+' : unreadNotificationCount }}
                                </span>
                            </button>

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

                                        <div class="block px-4 py-2 text-xs text-muted-foreground font-medium uppercase tracking-wide">
                                            Appearance
                                        </div>

                                        <button
                                            type="button"
                                            class="block w-full px-4 py-2 text-start text-sm leading-5 text-foreground hover:bg-muted focus:outline-none focus:bg-muted transition duration-150 ease-in-out"
                                            @click="toggleTheme"
                                        >
                                            {{ isDarkTheme ? 'Switch to Light Mode' : 'Switch to Dark Mode' }}
                                        </button>

                                        <button
                                            type="button"
                                            class="block w-full px-4 py-2 text-start text-sm leading-5 text-foreground hover:bg-muted focus:outline-none focus:bg-muted transition duration-150 ease-in-out"
                                            :disabled="isSystemTheme"
                                            @click="useSystemTheme"
                                        >
                                            Use System Theme
                                        </button>

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
                        <ResponsiveNavLink
                            v-if="$page.props.orgLayerEnabled"
                            :href="route('org.index')"
                            :active="route().current('org.*')"
                        >
                            <Building2 class="w-5 h-5" />
                            Agents
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

                        <div class="mt-3 px-4">
                            <button
                                type="button"
                                class="inline-flex h-8 items-center gap-2 rounded-md border border-border px-3 text-xs font-medium text-foreground transition hover:bg-muted focus:outline-none focus:ring-2 focus:ring-ring/50"
                                @click="showingNavigationDropdown = false; toggleNotificationDrawer()"
                            >
                                <Bell class="h-3.5 w-3.5" />
                                Notifications
                                <span
                                    v-if="hasUnreadNotifications"
                                    class="inline-flex min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-semibold leading-4 text-primary-foreground"
                                >
                                    {{ unreadNotificationCount > 99 ? '99+' : unreadNotificationCount }}
                                </span>
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-8 items-center gap-2 rounded-md border border-border px-3 text-xs font-medium text-foreground transition hover:bg-muted focus:outline-none focus:ring-2 focus:ring-ring/50"
                                @click="toggleTheme"
                            >
                                <Sun v-if="isDarkTheme" class="h-3.5 w-3.5" />
                                <Moon v-else class="h-3.5 w-3.5" />
                                {{ isDarkTheme ? 'Light mode' : 'Dark mode' }}
                            </button>
                            <button
                                type="button"
                                class="ms-2 inline-flex h-8 items-center rounded-md border border-border px-3 text-xs font-medium text-foreground transition hover:bg-muted focus:outline-none focus:ring-2 focus:ring-ring/50 disabled:opacity-50"
                                :disabled="isSystemTheme"
                                @click="useSystemTheme"
                            >
                                System
                            </button>
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

        <NotificationDrawer
            :open="notificationDrawerOpen"
            :notifications="notifications"
            :unread-count="unreadNotificationCount"
            :loading="notificationsLoading"
            :mutating="notificationsMutating"
            @close="closeNotificationDrawer"
            @refresh="fetchNotifications()"
            @mark-read="markNotificationAsRead"
            @mark-all-read="markAllNotificationsRead"
            @clear-all="clearAllNotifications"
            @open-action="openNotificationAction"
        />
    </div>
</template>
