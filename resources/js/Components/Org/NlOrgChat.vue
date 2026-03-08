<script setup lang="ts">
import { ref, nextTick, watch, computed } from 'vue';
import { useNlOrgParser } from '@/Composables/useNlOrgParser';
import type { Annotation } from '@/Composables/useNlOrgParser';
import Button from '@/Components/ui/Button.vue';
import { X, Trash2, Send, Loader2, AlertCircle, AlertTriangle, HelpCircle, Users, RotateCcw } from 'lucide-vue-next';

const MAX_INPUT_LENGTH = 2000;

const emit = defineEmits<{
    'close': [];
    'preview-changeset': [changeset: unknown[]];
    'apply-changeset': [];
    'discard-changeset': [];
}>();

const {
    loading,
    error,
    parseResult,
    chatHistory,
    successMessage,
    lastInput,
    parse,
    apply,
    discard,
    clearContext,
    retry,
} = useNlOrgParser();

const input = ref('');
const conversationArea = ref<HTMLElement | null>(null);

const inputTrimmed = computed(() => input.value.trim());
const canSend = computed(() => inputTrimmed.value.length > 0 && !loading.value);

function scrollToBottom() {
    nextTick(() => {
        if (conversationArea.value) {
            conversationArea.value.scrollTop = conversationArea.value.scrollHeight;
        }
    });
}

watch(
    () => chatHistory.value.length,
    () => scrollToBottom(),
);

watch(error, (val) => {
    if (val) scrollToBottom();
});

async function handleSend() {
    if (!canSend.value) return;

    const text = inputTrimmed.value;
    input.value = '';

    await parse(text);

    if (parseResult.value && parseResult.value.changeset.length > 0) {
        emit('preview-changeset', parseResult.value.changeset);
    }
}

async function handleApply() {
    await apply();
    emit('apply-changeset');
}

function handleDiscard() {
    discard();
    emit('discard-changeset');
}

async function handleRetry() {
    await retry();

    if (parseResult.value && parseResult.value.changeset.length > 0) {
        emit('preview-changeset', parseResult.value.changeset);
    }
}

function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        handleSend();
    }
}

function annotationClasses(type: Annotation['type']): string {
    const map: Record<Annotation['type'], string> = {
        error: 'bg-red-50 border-red-300 text-red-800 dark:bg-red-900/20 dark:border-red-700 dark:text-red-300',
        warning: 'bg-amber-50 border-amber-300 text-amber-800 dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300',
        ambiguity: 'bg-yellow-50 border-yellow-300 text-yellow-800 dark:bg-yellow-900/20 dark:border-yellow-700 dark:text-yellow-300',
        missing_participants: 'bg-orange-50 border-orange-300 text-orange-800 dark:bg-orange-900/20 dark:border-orange-700 dark:text-orange-300',
    };
    return map[type] ?? '';
}

function annotationIcon(type: Annotation['type']) {
    const map: Record<Annotation['type'], typeof AlertCircle> = {
        error: AlertCircle,
        warning: AlertTriangle,
        ambiguity: HelpCircle,
        missing_participants: Users,
    };
    return map[type] ?? AlertCircle;
}

const confidenceThreshold = 0.7;
</script>

<template>
    <div class="flex h-full w-full flex-col rounded-lg border border-border bg-card shadow-lg">
        <!-- Header -->
        <div class="flex shrink-0 items-center justify-between px-4 pt-4 pb-2">
            <h3 class="text-sm font-medium text-foreground">Workforce Chat</h3>
            <div class="flex items-center gap-1">
                <Button
                    variant="ghost"
                    size="icon"
                    class="h-7 w-7"
                    title="Clear conversation"
                    :disabled="loading"
                    @click="clearContext"
                >
                    <Trash2 class="h-4 w-4" />
                </Button>
                <Button variant="ghost" size="icon" class="h-7 w-7" @click="emit('close')">
                    <X class="h-4 w-4" />
                </Button>
            </div>
        </div>

        <!-- Scrollable conversation area -->
        <div ref="conversationArea" class="flex-1 overflow-y-auto px-4 py-3 space-y-3">
            <template v-for="(turn, i) in chatHistory" :key="i">
                <!-- User message -->
                <div v-if="turn.role === 'user'" class="flex justify-end">
                    <div class="max-w-[80%] rounded-lg px-3 py-2 bg-primary text-primary-foreground text-sm whitespace-pre-wrap break-words">
                        {{ turn.content }}
                    </div>
                </div>

                <!-- Assistant message -->
                <div v-else class="flex justify-start">
                    <div class="max-w-[80%] rounded-lg px-3 py-2 bg-muted text-foreground text-sm space-y-2">
                        <p class="whitespace-pre-wrap break-words">{{ turn.content }}</p>

                        <!-- Show annotations and confidence for the most recent assistant message with a parseResult -->
                        <template v-if="i === chatHistory.length - 1 && parseResult">
                            <!-- Confidence badge -->
                            <div class="flex items-center gap-2">
                                <span
                                    class="inline-block rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="parseResult.confidence >= confidenceThreshold
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
                                        : 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'"
                                >
                                    {{ (parseResult.confidence * 100).toFixed(0) }}% confidence
                                </span>
                            </div>

                            <!-- Low confidence hint -->
                            <p
                                v-if="parseResult.confidence < confidenceThreshold"
                                class="text-xs text-muted-foreground italic"
                            >
                                Low confidence — review changes carefully before applying.
                            </p>

                            <!-- Annotations -->
                            <div
                                v-for="(annotation, ai) in parseResult.annotations"
                                :key="ai"
                                class="rounded border px-2 py-1.5 text-xs flex items-start gap-1.5"
                                :class="annotationClasses(annotation.type)"
                            >
                                <component :is="annotationIcon(annotation.type)" class="h-3.5 w-3.5 mt-0.5 shrink-0" />
                                <div>
                                    <p>{{ annotation.message }}</p>
                                    <p v-if="annotation.suggestion" class="mt-1 italic">
                                        Suggestion: {{ annotation.suggestion }}
                                    </p>
                                </div>
                            </div>

                            <!-- Context overflow error -->
                            <div
                                v-if="parseResult.error"
                                class="rounded border border-red-300 bg-red-50 px-2 py-1.5 text-xs text-red-800 dark:border-red-700 dark:bg-red-900/20 dark:text-red-300"
                            >
                                <p class="font-medium">Context Overflow</p>
                                <p>{{ parseResult.error }}</p>
                                <p class="mt-1 italic">Try using incremental mode with smaller, targeted commands.</p>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            <!-- Success banner -->
            <div v-if="successMessage" class="flex justify-start">
                <div class="max-w-[80%] rounded-lg px-3 py-2 bg-green-50 border border-green-200 text-green-800 dark:bg-green-900/20 dark:border-green-700 dark:text-green-300 text-sm">
                    {{ successMessage }}
                </div>
            </div>

            <!-- Error from API (not tied to a parse result) -->
            <div v-if="error" class="flex justify-start">
                <div class="max-w-[80%] rounded-lg px-3 py-2 bg-red-50 border border-red-300 text-red-800 dark:bg-red-900/20 dark:border-red-700 dark:text-red-300 text-sm space-y-2">
                    <p>{{ error }}</p>
                    <Button
                        v-if="lastInput"
                        variant="outline"
                        size="sm"
                        class="h-7 text-xs"
                        :disabled="loading"
                        @click="handleRetry"
                    >
                        <RotateCcw class="h-3 w-3 mr-1" />
                        Retry
                    </Button>
                </div>
            </div>

            <!-- Loading indicator -->
            <div v-if="loading" class="flex justify-start">
                <div class="rounded-lg px-3 py-2 bg-muted text-muted-foreground text-sm flex items-center gap-2">
                    <Loader2 class="h-4 w-4 animate-spin" />
                    Analysing your description...
                </div>
            </div>
        </div>

        <!-- Action buttons (visible when parseResult is non-null) -->
        <div v-if="parseResult" class="shrink-0 px-4 py-2 border-t border-border flex gap-2">
            <Button
                variant="default"
                size="sm"
                class="flex-1 bg-green-600 hover:bg-green-700 text-white"
                :disabled="loading"
                @click="handleApply"
            >
                Apply
            </Button>
            <Button
                variant="destructive"
                size="sm"
                class="flex-1"
                :disabled="loading"
                @click="handleDiscard"
            >
                Discard
            </Button>
        </div>

        <!-- Input area -->
        <div class="shrink-0 px-4 py-3 border-t border-border">
            <div class="flex gap-2">
                <div class="relative flex-1">
                    <textarea
                        v-model="input"
                        :maxlength="MAX_INPUT_LENGTH"
                        :disabled="loading"
                        rows="1"
                        class="w-full resize-none rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-1 focus:ring-ring disabled:opacity-50 disabled:cursor-not-allowed"
                        placeholder="Describe your org structure..."
                        @keydown="handleKeydown"
                    />
                    <span class="absolute bottom-2 right-2 text-[10px] text-muted-foreground">
                        {{ input.length }}/{{ MAX_INPUT_LENGTH }}
                    </span>
                </div>
                <Button
                    variant="default"
                    size="icon"
                    :disabled="!canSend"
                    @click="handleSend"
                >
                    <Loader2 v-if="loading" class="h-4 w-4 animate-spin" />
                    <Send v-else class="h-4 w-4" />
                </Button>
            </div>
        </div>
    </div>
</template>
