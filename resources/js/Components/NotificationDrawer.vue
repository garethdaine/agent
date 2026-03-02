<script setup>
import { computed } from 'vue';
import { CheckCheck, ExternalLink, Loader2, Trash2, X } from 'lucide-vue-next';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    notifications: {
        type: Array,
        default: () => [],
    },
    unreadCount: {
        type: Number,
        default: 0,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    mutating: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    'close',
    'refresh',
    'mark-read',
    'mark-all-read',
    'clear-all',
    'open-action',
]);

const hasNotifications = computed(() => props.notifications.length > 0);

const formatTimestamp = (value) => {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(date);
};
</script>

<template>
    <div>
        <transition
            enter-active-class="ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-40 bg-black/50"
                @click="emit('close')"
            />
        </transition>

        <transition
            enter-active-class="transform transition ease-out duration-250"
            enter-from-class="translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="transform transition ease-in duration-200"
            leave-from-class="translate-x-0"
            leave-to-class="translate-x-full"
        >
            <aside
                v-if="open"
                class="fixed inset-y-0 right-0 z-50 w-full max-w-md border-l border-border bg-card shadow-2xl"
                aria-label="Notifications"
            >
                <div class="flex h-full flex-col">
                    <header class="border-b border-border px-4 py-3">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <h2 class="text-base font-semibold text-foreground">Notifications</h2>
                                <span
                                    v-if="unreadCount > 0"
                                    class="inline-flex min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-[11px] font-semibold leading-5 text-primary-foreground"
                                >
                                    {{ unreadCount > 99 ? '99+' : unreadCount }}
                                </span>
                            </div>
                            <button
                                type="button"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground focus:outline-none focus:ring-2 focus:ring-ring/50"
                                @click="emit('close')"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                class="inline-flex h-8 items-center gap-1.5 rounded-md border border-border px-2.5 text-xs font-medium text-foreground transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="loading || mutating"
                                @click="emit('refresh')"
                            >
                                <Loader2 v-if="loading" class="h-3.5 w-3.5 animate-spin" />
                                <span>{{ loading ? 'Refreshing…' : 'Refresh' }}</span>
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-8 items-center gap-1.5 rounded-md border border-border px-2.5 text-xs font-medium text-foreground transition hover:bg-muted disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="unreadCount === 0 || mutating"
                                @click="emit('mark-all-read')"
                            >
                                <CheckCheck class="h-3.5 w-3.5" />
                                Mark all read
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-8 items-center gap-1.5 rounded-md border border-destructive/40 px-2.5 text-xs font-medium text-destructive transition hover:bg-destructive/10 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="!hasNotifications || mutating"
                                @click="emit('clear-all')"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                                Clear all
                            </button>
                        </div>
                    </header>

                    <div class="flex-1 overflow-y-auto p-4">
                        <div
                            v-if="!hasNotifications && !loading"
                            class="rounded-lg border border-dashed border-border p-6 text-center text-sm text-muted-foreground"
                        >
                            No notifications yet.
                        </div>

                        <div
                            v-else-if="loading && !hasNotifications"
                            class="flex items-center justify-center py-8 text-sm text-muted-foreground"
                        >
                            <Loader2 class="mr-2 h-4 w-4 animate-spin" />
                            Loading notifications…
                        </div>

                        <ul v-else class="space-y-3">
                            <li
                                v-for="notification in notifications"
                                :key="notification.id"
                                class="rounded-lg border border-border bg-background/40 p-3"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-foreground">
                                            {{ notification.title }}
                                        </p>
                                        <p v-if="notification.body" class="mt-1 text-sm text-muted-foreground">
                                            {{ notification.body }}
                                        </p>
                                    </div>
                                    <span
                                        v-if="!notification.read_at"
                                        class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-primary"
                                    />
                                </div>

                                <div class="mt-3 flex items-center justify-between gap-3">
                                    <span class="text-xs text-muted-foreground">
                                        {{ formatTimestamp(notification.created_at) }}
                                    </span>

                                    <div class="flex items-center gap-2">
                                        <button
                                            v-if="notification.action?.url"
                                            type="button"
                                            class="inline-flex items-center gap-1 text-xs font-medium text-primary transition hover:text-primary/80"
                                            @click="emit('open-action', notification)"
                                        >
                                            {{ notification.action.label || 'Open' }}
                                            <ExternalLink class="h-3.5 w-3.5" />
                                        </button>
                                        <button
                                            v-if="!notification.read_at"
                                            type="button"
                                            class="text-xs font-medium text-foreground transition hover:text-primary"
                                            :disabled="mutating"
                                            @click="emit('mark-read', notification.id)"
                                        >
                                            Mark as read
                                        </button>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </aside>
        </transition>
    </div>
</template>
