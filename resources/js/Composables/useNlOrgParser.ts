import { ref } from 'vue';
import type { Ref } from 'vue';
import axios, { AxiosError } from 'axios';

export interface ChatTurn {
    role: 'user' | 'assistant';
    content: string;
}

export interface Annotation {
    message: string;
    type: 'error' | 'warning' | 'ambiguity' | 'missing_participants';
    char_offset_start: number | null;
    char_offset_end: number | null;
    suggestion: string | null;
}

export interface ChangesetEntry {
    entity_type: string;
    operation: 'add' | 'update' | 'remove';
    payload: Record<string, unknown>;
    temp_id: string | null;
}

export interface NlOrgParseResult {
    confidence: number;
    changeset: ChangesetEntry[];
    annotations: Annotation[];
    resolved_references: Record<string, string>;
    error: string | null;
}

const MAX_CHAT_HISTORY_TURNS = 10;

export function useNlOrgParser() {
    const loading: Ref<boolean> = ref(false);
    const error: Ref<string | null> = ref(null);
    const parseResult: Ref<NlOrgParseResult | null> = ref(null);
    const parseAttemptId: Ref<string | null> = ref(null);
    const chatHistory: Ref<ChatTurn[]> = ref([]);
    const successMessage: Ref<string | null> = ref(null);
    const lastInput: Ref<string | null> = ref(null);

    let successTimer: ReturnType<typeof setTimeout> | null = null;

    async function parse(input: string): Promise<void> {
        if (loading.value) return;

        loading.value = true;
        error.value = null;
        successMessage.value = null;
        lastInput.value = input;

        try {
            const response = await axios.post('/agent/api/v1/org/nl-parse', {
                input,
                chat_history: chatHistory.value,
            });

            const data = response.data;

            parseResult.value = {
                confidence: data.confidence,
                changeset: data.changeset ?? [],
                annotations: data.annotations ?? [],
                resolved_references: data.resolved_references ?? {},
                error: data.error ?? null,
            };
            parseAttemptId.value = data.parse_attempt_id;

            chatHistory.value.push({ role: 'user', content: input });

            const summary = buildAssistantSummary(parseResult.value);
            chatHistory.value.push({ role: 'assistant', content: summary });

            if (chatHistory.value.length > MAX_CHAT_HISTORY_TURNS * 2) {
                chatHistory.value = chatHistory.value.slice(-MAX_CHAT_HISTORY_TURNS * 2);
            }
        } catch (e) {
            handleError(e);
        } finally {
            loading.value = false;
        }
    }

    async function apply(): Promise<void> {
        if (loading.value || !parseAttemptId.value) return;

        loading.value = true;
        error.value = null;

        try {
            await axios.post('/agent/api/v1/org/nl-apply', {
                parse_attempt_id: parseAttemptId.value,
            });

            parseResult.value = null;
            parseAttemptId.value = null;

            showSuccessMessage('Changes applied successfully.');
        } catch (e) {
            handleError(e);
        } finally {
            loading.value = false;
        }
    }

    function discard(): void {
        parseResult.value = null;
        parseAttemptId.value = null;
    }

    function clearContext(): void {
        chatHistory.value = [];
        parseResult.value = null;
        parseAttemptId.value = null;
        successMessage.value = null;
        lastInput.value = null;
    }

    async function retry(): Promise<void> {
        if (!lastInput.value || loading.value) return;

        // Remove the last failed exchange (user + assistant messages)
        if (chatHistory.value.length >= 2) {
            const lastTwo = chatHistory.value.slice(-2);
            if (lastTwo[0]?.role === 'user' && lastTwo[1]?.role === 'assistant') {
                chatHistory.value = chatHistory.value.slice(0, -2);
            }
        }

        error.value = null;
        await parse(lastInput.value);
    }

    function showSuccessMessage(message: string): void {
        if (successTimer) {
            clearTimeout(successTimer);
        }
        successMessage.value = message;
        successTimer = setTimeout(() => {
            successMessage.value = null;
            successTimer = null;
        }, 3000);
    }

    function handleError(e: unknown): void {
        if (e instanceof AxiosError && e.response) {
            const status = e.response.status;
            const message = e.response.data?.message || e.response.data?.error;

            if (status === 422) {
                error.value = message || 'This changeset has already been applied.';
            } else if (status === 403) {
                error.value = message || 'You are not authorized to perform this action.';
            } else {
                error.value = message || 'An unexpected error occurred.';
            }
        } else {
            error.value = 'A network error occurred. Please check your connection and try again.';
        }
    }

    function buildAssistantSummary(result: NlOrgParseResult): string {
        if (result.error) {
            return `Error: ${result.error}`;
        }

        const parts: string[] = [];
        const counts: Record<string, number> = {};

        for (const entry of result.changeset) {
            const key = `${entry.operation} ${entry.entity_type}`;
            counts[key] = (counts[key] || 0) + 1;
        }

        for (const [key, count] of Object.entries(counts)) {
            parts.push(`${count} ${key}`);
        }

        const summary = parts.length > 0
            ? `Parsed: ${parts.join(', ')}`
            : 'No changes detected.';

        const confidence = `Confidence: ${(result.confidence * 100).toFixed(0)}%`;

        return `${summary} (${confidence})`;
    }

    return {
        loading,
        error,
        parseResult,
        parseAttemptId,
        chatHistory,
        successMessage,
        lastInput,
        parse,
        apply,
        discard,
        clearContext,
        retry,
    };
}
