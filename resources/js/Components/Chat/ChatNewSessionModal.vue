<script setup>
import { ref, watch } from 'vue';
import Button from '@/Components/ui/Button.vue';
import Badge from '@/Components/ui/Badge.vue';
import Spinner from '@/Components/ui/Spinner.vue';
import { X, Plus, MessageSquare } from 'lucide-vue-next';

const props = defineProps({
    open: { type: Boolean, default: false },
    connectors: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'create']);

const selectedConnectorId = ref(null);
const channelId = ref('');
const creating = ref(false);

watch(() => props.open, (val) => {
    if (val) {
        selectedConnectorId.value = null;
        channelId.value = '';
        creating.value = false;
    }
});

const providerLabels = {
    slack: 'Slack',
    telegram: 'Telegram',
    discord: 'Discord',
    whatsapp: 'WhatsApp',
};

const handleCreate = async () => {
    if (!selectedConnectorId.value) return;
    creating.value = true;
    emit('create', selectedConnectorId.value, channelId.value || null);
};

const handleBackdrop = (e) => {
    if (e.target === e.currentTarget) emit('close');
};
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 backdrop-blur-sm"
                @click="handleBackdrop"
                @keydown.esc="emit('close')"
                tabindex="0"
            >
                <div class="w-full max-w-md rounded-2xl border border-border bg-card shadow-xl">
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b border-border px-5 py-4">
                        <div class="flex items-center gap-2.5">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10">
                                <MessageSquare class="h-4 w-4 text-primary" />
                            </div>
                            <h3 class="text-sm font-semibold text-foreground">New Chat Session</h3>
                        </div>
                        <button
                            type="button"
                            class="flex h-7 w-7 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                            @click="emit('close')"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="px-5 py-4 space-y-4">
                        <!-- Connector selection -->
                        <div>
                            <label class="text-xs font-medium text-muted-foreground block mb-2">
                                Select Connector
                            </label>

                            <div v-if="loading" class="text-xs text-muted-foreground py-4 text-center">
                                <Spinner class="h-4 w-4 mx-auto mb-1" />
                                Loading connectors...
                            </div>

                            <div v-else-if="connectors.length === 0" class="text-xs text-muted-foreground py-4 text-center">
                                No connected accounts available. Set up a connector first.
                            </div>

                            <div v-else class="space-y-1.5">
                                <button
                                    v-for="connector in connectors"
                                    :key="connector.id"
                                    type="button"
                                    class="flex w-full items-center gap-3 rounded-xl border px-3.5 py-3 text-left transition-colors"
                                    :class="selectedConnectorId === connector.id
                                        ? 'border-primary bg-primary/5'
                                        : 'border-border hover:bg-muted/50'"
                                    @click="selectedConnectorId = connector.id"
                                >
                                    <Badge
                                        :variant="selectedConnectorId === connector.id ? 'default' : 'outline'"
                                        class="shrink-0 text-[10px]"
                                    >
                                        {{ providerLabels[connector.provider] ?? connector.provider }}
                                    </Badge>
                                    <span class="text-sm font-medium text-foreground truncate">
                                        {{ connector.name }}
                                    </span>
                                </button>
                            </div>
                        </div>

                        <!-- Optional channel ID -->
                        <div>
                            <label class="text-xs font-medium text-muted-foreground block mb-1.5">
                                Channel ID <span class="text-muted-foreground/60">(optional)</span>
                            </label>
                            <input
                                v-model="channelId"
                                type="text"
                                placeholder="Auto-generated if empty"
                                class="w-full rounded-xl border border-border bg-background px-3.5 py-2.5 text-sm placeholder:text-muted-foreground/60 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/40"
                            />
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex items-center justify-end gap-2 border-t border-border px-5 py-3">
                        <Button variant="outline" size="sm" @click="emit('close')">
                            Cancel
                        </Button>
                        <Button
                            size="sm"
                            :disabled="!selectedConnectorId || creating"
                            @click="handleCreate"
                        >
                            <Spinner v-if="creating" class="h-3.5 w-3.5 mr-1.5" />
                            <Plus v-else class="h-3.5 w-3.5 mr-1.5" />
                            Start Session
                        </Button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
