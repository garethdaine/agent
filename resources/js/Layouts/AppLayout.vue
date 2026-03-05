<script setup>
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppSidebar from '@/Components/AppSidebar.vue';
import Banner from '@/Components/Banner.vue';
import AppConfirmDialog from '@/Components/AppConfirmDialog.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NotificationDrawer from '@/Components/NotificationDrawer.vue';
import {
    applyThemePreference,
    getStoredThemePreference,
    setStoredThemePreference,
} from '@/Support/theme';
import {
    ChevronDown,
    Menu,
    X,
    Moon,
    Sun,
    Bell,
    PanelLeftClose,
    PanelLeftOpen,
} from 'lucide-vue-next';

defineProps({
    title: String,
});

const SIDEBAR_STORAGE_KEY = 'agent-ops-sidebar-collapsed';

const sidebarCollapsed = ref(localStorage.getItem(SIDEBAR_STORAGE_KEY) === 'true');
const mobileMenuOpen = ref(false);
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

const toggleSidebar = () => {
    sidebarCollapsed.value = !sidebarCollapsed.value;
    localStorage.setItem(SIDEBAR_STORAGE_KEY, sidebarCollapsed.value);
};

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

    const userId = page.props.auth?.user?.id;

    if (window.Echo && userId) {
        window.Echo.private(`user.${userId}`)
            .listen('.notification.created', () => {
                fetchNotifications({ silent: true });
            });

        notificationPollInterval = window.setInterval(() => {
            fetchNotifications({ silent: true });
        }, 60000);
    } else {
        notificationPollInterval = window.setInterval(() => {
            fetchNotifications({ silent: true });
        }, 30000);
    }

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

    const userId = page.props.auth?.user?.id;
    if (window.Echo && userId) {
        window.Echo.leave(`user.${userId}`);
    }
});
</script>

<template>
    <div>
        <Head :title="title" />

        <Banner />

        <div class="flex h-screen overflow-hidden bg-background">
            <!-- Desktop Sidebar -->
            <div class="hidden sm:flex shrink-0">
                <AppSidebar :collapsed="sidebarCollapsed">
                    <template #footer>
                        <div :class="sidebarCollapsed ? 'flex flex-col items-center' : ''">
                            <!-- User Dropdown -->
                            <Dropdown align="left" width="48" position="top">
                                <template #trigger>
                                    <button
                                        type="button"
                                        class="group relative flex items-center rounded-lg text-[13px] font-medium text-muted-foreground transition-colors duration-150 hover:bg-muted hover:text-foreground"
                                        :class="sidebarCollapsed ? 'justify-center w-10 h-10 mx-auto' : 'w-full gap-3 px-3 py-2'"
                                    >
                                        <div v-if="$page.props.jetstream.managesProfilePhotos" class="shrink-0">
                                            <img class="w-6 h-6 rounded-full object-cover" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name">
                                        </div>
                                        <div v-else class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-[11px] font-semibold text-primary">
                                            {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <span v-if="!sidebarCollapsed" class="truncate">{{ $page.props.auth.user.name }}</span>
                                        <ChevronDown v-if="!sidebarCollapsed" class="ml-auto h-3.5 w-3.5 shrink-0 opacity-50" />
                                        <div
                                            v-if="sidebarCollapsed"
                                            class="pointer-events-none absolute left-full top-1/2 -translate-y-1/2 ml-2 rounded-md bg-popover px-2 py-1 text-xs font-medium text-popover-foreground shadow-md border border-border opacity-0 group-hover:opacity-100 transition-opacity duration-150 whitespace-nowrap z-50"
                                        >
                                            {{ $page.props.auth.user.name }}
                                        </div>
                                    </button>
                                </template>

                                <template #content>
                                    <div class="block px-4 py-2 text-xs text-muted-foreground font-medium uppercase tracking-wide">
                                        Manage Account
                                    </div>
                                    <DropdownLink :href="route('profile.show')">
                                        Profile
                                    </DropdownLink>
                                    <DropdownLink v-if="$page.props.jetstream.hasApiFeatures" :href="route('api-tokens.index')">
                                        API Tokens
                                    </DropdownLink>
                                    <DropdownLink :href="route('billing.portal')">
                                        Billing
                                    </DropdownLink>

                                    <template v-if="$page.props.jetstream.hasTeamFeatures && $page.props.auth.user.current_team">
                                        <div class="border-t border-border my-1" />
                                        <div class="block px-4 py-2 text-xs text-muted-foreground font-medium uppercase tracking-wide">
                                            Team
                                        </div>
                                        <DropdownLink :href="route('teams.show', $page.props.auth.user.current_team)">
                                            {{ $page.props.auth.user.current_team.name }}
                                        </DropdownLink>
                                    </template>

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

                                    <form @submit.prevent="logout">
                                        <DropdownLink as="button">
                                            Log Out
                                        </DropdownLink>
                                    </form>
                                </template>
                            </Dropdown>
                        </div>
                    </template>
                </AppSidebar>
            </div>

            <!-- Mobile Sidebar Overlay -->
            <Teleport to="body">
                <Transition
                    enter-active-class="transition-opacity duration-300 ease-out"
                    enter-from-class="opacity-0"
                    enter-to-class="opacity-100"
                    leave-active-class="transition-opacity duration-200 ease-in"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                >
                    <div
                        v-if="mobileMenuOpen"
                        class="fixed inset-0 z-40 bg-black/50 backdrop-blur-sm sm:hidden"
                        @click="mobileMenuOpen = false"
                    />
                </Transition>

                <Transition
                    enter-active-class="transition-transform duration-300 ease-out"
                    enter-from-class="-translate-x-full"
                    enter-to-class="translate-x-0"
                    leave-active-class="transition-transform duration-200 ease-in"
                    leave-from-class="translate-x-0"
                    leave-to-class="-translate-x-full"
                >
                    <div
                        v-if="mobileMenuOpen"
                        class="fixed inset-y-0 left-0 z-50 w-[240px] sm:hidden"
                    >
                        <AppSidebar :collapsed="false">
                            <template #footer>
                                <div class="space-y-0.5">
                                    <button
                                        type="button"
                                        class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-[13px] font-medium text-muted-foreground transition-colors duration-150 hover:bg-muted hover:text-foreground"
                                        @click="toggleTheme"
                                    >
                                        <Sun v-if="isDarkTheme" class="h-4 w-4 shrink-0" />
                                        <Moon v-else class="h-4 w-4 shrink-0" />
                                        {{ isDarkTheme ? 'Light Mode' : 'Dark Mode' }}
                                    </button>
                                    <button
                                        type="button"
                                        class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-[13px] font-medium text-muted-foreground transition-colors duration-150 hover:bg-muted hover:text-foreground"
                                        @click="mobileMenuOpen = false; toggleNotificationDrawer()"
                                    >
                                        <div class="relative shrink-0">
                                            <Bell class="h-4 w-4" />
                                        </div>
                                        Notifications
                                        <span
                                            v-if="hasUnreadNotifications"
                                            class="ml-auto inline-flex min-w-5 items-center justify-center rounded-full bg-primary/15 px-1.5 text-[11px] font-semibold text-primary"
                                        >
                                            {{ unreadNotificationCount > 99 ? '99+' : unreadNotificationCount }}
                                        </span>
                                    </button>
                                    <Link
                                        :href="route('profile.show')"
                                        class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-[13px] font-medium text-muted-foreground transition-colors duration-150 hover:bg-muted hover:text-foreground"
                                    >
                                        <div v-if="$page.props.jetstream.managesProfilePhotos" class="shrink-0">
                                            <img class="w-6 h-6 rounded-full object-cover" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name">
                                        </div>
                                        <div v-else class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-[11px] font-semibold text-primary">
                                            {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                                        </div>
                                        {{ $page.props.auth.user.name }}
                                    </Link>
                                </div>
                            </template>
                        </AppSidebar>
                    </div>
                </Transition>
            </Teleport>

            <!-- Main Content Area -->
            <div class="flex flex-1 flex-col overflow-hidden">
                <!-- Top Header Bar -->
                <header class="flex h-14 shrink-0 items-center border-b border-border bg-card/95 backdrop-blur-sm px-4 sm:px-6">
                    <div class="flex flex-1 items-center min-w-0">
                        <!-- Mobile hamburger -->
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground transition sm:hidden"
                            @click="mobileMenuOpen = !mobileMenuOpen"
                        >
                            <Menu v-if="!mobileMenuOpen" class="h-5 w-5" />
                            <X v-else class="h-5 w-5" />
                        </button>

                        <!-- Sidebar collapse toggle (desktop) -->
                        <button
                            type="button"
                            class="hidden sm:inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground focus:outline-none mr-3"
                            aria-label="Toggle sidebar"
                            @click="toggleSidebar"
                        >
                            <PanelLeftClose v-if="!sidebarCollapsed" class="h-4 w-4" />
                            <PanelLeftOpen v-else class="h-4 w-4" />
                        </button>

                        <!-- Page heading -->
                        <div v-if="$slots.header" class="min-w-0 flex-1 text-sm font-medium text-foreground">
                            <slot name="header" />
                        </div>
                    </div>

                    <!-- Vertical divider + utility icons -->
                    <div class="hidden sm:flex items-center ml-4">
                        <div class="h-5 w-px bg-border" />

                        <button
                            type="button"
                            class="ml-3 inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground focus:outline-none"
                            :aria-label="isDarkTheme ? 'Switch to light mode' : 'Switch to dark mode'"
                            @click="toggleTheme"
                        >
                            <Sun v-if="isDarkTheme" class="h-4 w-4" />
                            <Moon v-else class="h-4 w-4" />
                        </button>

                        <button
                            type="button"
                            class="relative ml-1 inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground focus:outline-none"
                            aria-label="Open notifications"
                            @click="toggleNotificationDrawer"
                        >
                            <Bell class="h-4 w-4" />
                            <span
                                v-if="hasUnreadNotifications"
                                class="absolute right-1 top-1 inline-flex h-3.5 min-w-3.5 items-center justify-center rounded-full bg-primary px-0.5 text-[9px] font-semibold leading-none text-primary-foreground"
                            >
                                {{ unreadNotificationCount > 9 ? '9+' : unreadNotificationCount }}
                            </span>
                        </button>
                    </div>
                </header>

                <!-- Page Content -->
                <main class="flex-1 overflow-y-auto px-6 py-6">
                    <slot />
                </main>
            </div>
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

        <AppConfirmDialog />
    </div>
</template>
