<script setup>
import { Search, RefreshCw, MessageSquare, Plus } from 'lucide-vue-next';
import Button from '@/Components/ui/Button.vue';
import ChatSessionListItem from './ChatSessionListItem.vue';

const props = defineProps({
    sessions: { type: Array, default: () => [] },
    meta: { type: Object, default: () => ({}) },
    loading: { type: Boolean, default: false },
    activeSessionId: { type: String, default: null },
    statusFilter: { type: String, default: '' },
    searchQuery: { type: String, default: '' },
    relativeTime: { type: Function, required: true },
});

const emit = defineEmits([
    'select',
    'archive',
    'refresh',
    'create',
    'update:statusFilter',
    'update:searchQuery',
    'load-page',
]);
</script>

<template>
    <div class="flex w-[280px] shrink-0 flex-col bg-card h-full">
        <!-- Header -->
        <div class="shrink-0 border-b border-border px-4 py-4 space-y-2.5">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <MessageSquare class="h-4 w-4 text-primary" />
                    <span class="text-sm font-semibold text-foreground">Sessions</span>
                </div>
                <div class="flex items-center gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        class="h-7 w-7"
                        :disabled="loading"
                        title="Refresh"
                        @click="emit('refresh')"
                    >
                        <RefreshCw class="h-3.5 w-3.5" :class="{ 'animate-spin': loading }" />
                    </Button>
                    <Button
                        size="icon"
                        class="h-7 w-7"
                        title="New session"
                        @click="emit('create')"
                    >
                        <Plus class="h-3.5 w-3.5" />
                    </Button>
                </div>
            </div>

            <!-- Search -->
            <div class="relative">
                <Search class="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                <input
                    type="text"
                    :value="searchQuery"
                    placeholder="Search sessions..."
                    class="w-full rounded-md border border-input bg-background pl-8 pr-3 py-1.5 text-xs ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                    @input="emit('update:searchQuery', $event.target.value)"
                />
            </div>

            <!-- Status filter -->
            <select
                :value="statusFilter"
                class="w-full rounded-md border border-input bg-background px-2.5 py-1.5 text-xs"
                @change="emit('update:statusFilter', $event.target.value)"
            >
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="archived">Archived</option>
            </select>
        </div>

        <!-- Session list -->
        <div class="flex-1 overflow-y-auto py-2">
            <div v-if="loading && sessions.length === 0" class="px-4 py-8 text-center text-xs text-muted-foreground">
                Loading sessions...
            </div>

            <div v-else-if="sessions.length === 0" class="px-4 py-8 text-center text-xs text-muted-foreground">
                No sessions found.
            </div>

            <div v-else class="space-y-0.5 px-2">
                <ChatSessionListItem
                    v-for="session in sessions"
                    :key="session.id"
                    :session="session"
                    :active="session.id === activeSessionId"
                    :relative-time="relativeTime"
                    @select="emit('select', $event)"
                    @archive="emit('archive', $event)"
                />
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="meta.last_page > 1" class="shrink-0 border-t border-border px-4 py-3 flex items-center justify-between">
            <Button
                variant="ghost"
                size="sm"
                class="h-7 text-xs"
                :disabled="(meta.current_page ?? 1) <= 1"
                @click="emit('load-page', (meta.current_page ?? 1) - 1)"
            >
                Prev
            </Button>
            <span class="text-[10px] text-muted-foreground">
                {{ meta.current_page }} / {{ meta.last_page }}
            </span>
            <Button
                variant="ghost"
                size="sm"
                class="h-7 text-xs"
                :disabled="(meta.current_page ?? 1) >= (meta.last_page ?? 1)"
                @click="emit('load-page', (meta.current_page ?? 1) + 1)"
            >
                Next
            </Button>
        </div>
    </div>
</template>
