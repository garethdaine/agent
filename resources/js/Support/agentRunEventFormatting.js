const OUTPUT_EVENT_TYPES = new Set(['stdout', 'stderr']);
const STREAM_ENVELOPE_TYPES = new Set(['stream_event', 'assistant', 'user', 'result']);
const STRUCTURED_ENVELOPE_MARKER_PATTERN = /"type"\s*:\s*"(?:stream_event|assistant|user|result)"|"session_id"\s*:|"parent_tool_use_id"\s*:|\[\d+\s+(?:stdout|stderr|lifecycle)\]/;
const ENVELOPE_CARRY_LIMIT = 32_768;
const DEDUPE_WINDOW_EVENTS = 80;
const DEDUPE_MIN_CHARS = 24;

const stringifyPayload = (payload) => {
    if (typeof payload === 'string') {
        return payload;
    }

    if (payload === null || payload === undefined) {
        return '';
    }

    if (typeof payload === 'object') {
        try {
            return JSON.stringify(payload);
        } catch {
            return '[unprintable payload]';
        }
    }

    return String(payload);
};

const parseJson = (raw) => {
    if (typeof raw !== 'string') {
        return null;
    }

    const trimmed = raw.trim();
    if (trimmed === '') {
        return null;
    }

    const first = trimmed[0];
    if (first !== '{' && first !== '[') {
        return null;
    }

    try {
        return JSON.parse(trimmed);
    } catch {
        return null;
    }
};

const isRecord = (value) => value !== null && typeof value === 'object' && !Array.isArray(value);
const isStructuredEnvelopeObject = (value) => isRecord(value) && STREAM_ENVELOPE_TYPES.has(String(value.type ?? '').trim());

const hasStructuredEnvelopeMarkers = (value) => {
    if (typeof value !== 'string') {
        return false;
    }

    return STRUCTURED_ENVELOPE_MARKER_PATTERN.test(value);
};

const readJsonValueWindow = (text, startIndex) => {
    const opening = text[startIndex];
    if (opening !== '{' && opening !== '[') {
        return { status: 'invalid', end: startIndex + 1 };
    }

    const stack = [opening];
    let inString = false;
    let escaped = false;

    for (let index = startIndex + 1; index < text.length; index += 1) {
        const character = text[index];

        if (inString) {
            if (escaped) {
                escaped = false;
                continue;
            }

            if (character === '\\') {
                escaped = true;
                continue;
            }

            if (character === '"') {
                inString = false;
            }

            continue;
        }

        if (character === '"') {
            inString = true;
            continue;
        }

        if (character === '{' || character === '[') {
            stack.push(character);
            continue;
        }

        if (character === '}' || character === ']') {
            const current = stack[stack.length - 1];
            const isMatch = (character === '}' && current === '{')
                || (character === ']' && current === '[');

            if (!isMatch) {
                return { status: 'invalid', end: startIndex + 1 };
            }

            stack.pop();

            if (stack.length === 0) {
                return { status: 'complete', end: index + 1 };
            }
        }
    }

    return { status: 'incomplete', end: text.length };
};

const extractJsonObjects = (text) => {
    if (typeof text !== 'string' || text === '') {
        return {
            parsedValues: [],
            remainder: '',
            hasIncompleteJson: false,
        };
    }

    const parsedValues = [];
    let cursor = 0;
    let remainder = '';
    let hasIncompleteJson = false;

    while (cursor < text.length) {
        const relativeStart = text.slice(cursor).search(/[{\[]/);
        if (relativeStart === -1) {
            break;
        }

        const startIndex = cursor + relativeStart;
        const window = readJsonValueWindow(text, startIndex);

        if (window.status === 'incomplete') {
            remainder = text.slice(startIndex);
            hasIncompleteJson = true;
            break;
        }

        if (window.status === 'invalid') {
            cursor = startIndex + 1;
            continue;
        }

        const candidate = text.slice(startIndex, window.end);

        try {
            parsedValues.push(JSON.parse(candidate));
            cursor = window.end;
        } catch {
            cursor = startIndex + 1;
        }
    }

    return {
        parsedValues,
        remainder,
        hasIncompleteJson,
    };
};

const writeEnvelopeCarry = (state, eventType, value) => {
    if (!(state.envelopeCarryByType instanceof Map)) {
        return;
    }

    const normalized = String(value ?? '');
    if (normalized.trim() === '') {
        state.envelopeCarryByType.delete(eventType);
        return;
    }

    state.envelopeCarryByType.set(eventType, normalized.slice(-ENVELOPE_CARRY_LIMIT));
};

const buildPrefix = (sequence, eventType) => {
    if (Number.isFinite(sequence) && sequence > 0) {
        return `[${sequence} ${eventType}]`;
    }

    return `[${eventType}]`;
};

const buildEntryContext = (entry) => {
    const eventType = String(entry?.event_type ?? '').trim() || 'event';
    const sequence = Number(entry?.sequence ?? 0);
    const payloadRaw = stringifyPayload(entry?.payload);
    const fallbackTimestamp = String(entry?.event_ts ?? entry?.created_at ?? '').trim() || null;
    const key = String(entry?.id ?? `${sequence}:${eventType}`);

    return {
        entry,
        eventType,
        sequence,
        payloadRaw,
        fallbackTimestamp,
        key,
        prefix: buildPrefix(sequence, eventType),
    };
};

const formatLifecycleSummary = (payloadObject, fallbackTimestamp = null) => {
    if (!payloadObject || typeof payloadObject !== 'object' || Array.isArray(payloadObject)) {
        return null;
    }

    const type = String(payloadObject.type ?? '').trim().toLowerCase();
    if (type === 'state_transition') {
        const from = String(payloadObject.from ?? '').trim();
        const to = String(payloadObject.to ?? '').trim();
        const at = String(payloadObject.at ?? fallbackTimestamp ?? '').trim();
        const pid = payloadObject.pid;
        const exitCode = payloadObject.exit_code;
        const signal = payloadObject.signal;

        const parts = [];
        if (from !== '' && to !== '') {
            parts.push(`${from} -> ${to}`);
        } else if (to !== '') {
            parts.push(`state -> ${to}`);
        } else if (from !== '') {
            parts.push(`from ${from}`);
        } else {
            parts.push('state transition');
        }

        if (at !== '') {
            parts.push(`at ${at}`);
        }

        if (Number.isFinite(Number(pid))) {
            parts.push(`pid ${pid}`);
        }

        if (exitCode !== null && exitCode !== undefined && exitCode !== '') {
            parts.push(`exit ${exitCode}`);
        }

        if (signal !== null && signal !== undefined && signal !== '') {
            parts.push(`signal ${signal}`);
        }

        return parts.join(' | ');
    }

    if (type === 'heartbeat') {
        const elapsedSeconds = Number(payloadObject.elapsed_seconds);
        const at = String(payloadObject.at ?? fallbackTimestamp ?? '').trim();
        const parts = ['heartbeat'];

        if (Number.isFinite(elapsedSeconds) && elapsedSeconds >= 0) {
            parts.push(`${elapsedSeconds}s elapsed`);
        }

        if (at !== '') {
            parts.push(`at ${at}`);
        }

        return parts.join(' | ');
    }

    return null;
};

const normalizeOutputPayload = (eventType, rawPayload) => {
    if (!OUTPUT_EVENT_TYPES.has(eventType)) {
        return rawPayload;
    }

    const parsed = parseJson(rawPayload);
    if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
        return rawPayload;
    }

    if (typeof parsed.text === 'string') {
        return parsed.text;
    }

    return rawPayload;
};

const looksLikeMarkdown = (value) => {
    if (typeof value !== 'string') {
        return false;
    }

    const text = value.trim();
    if (text === '') {
        return false;
    }

    if (!text.includes('\n') && text.length < 120) {
        return false;
    }

    return /(^|\n)\s*(#{1,6}\s+|[-*]\s+|\d+\.\s+|>\s+|```|~~~|\|.+\|)/m.test(text)
        || /(\*\*[^*]+\*\*)|(`[^`]+`)|(\[[^\]]+\]\([^)]+\))/m.test(text);
};

const makeFormattedEntry = ({
    key,
    prefix,
    payload,
    tone = 'plain',
    format = 'pre',
}) => ({
    key,
    prefix,
    payload,
    tone,
    format,
});

const normalizeTextForDeduping = (value) => String(value ?? '').replace(/\s+/g, ' ').trim();

const isDedupableEntry = (entry) => {
    if (!isRecord(entry)) {
        return false;
    }

    const tone = String(entry.tone ?? '').trim();
    if (!['plain', 'markdown'].includes(tone)) {
        return false;
    }

    const normalized = normalizeTextForDeduping(entry.payload);
    if (normalized.length < DEDUPE_MIN_CHARS) {
        return false;
    }

    if (normalized.startsWith('Tool call:')) {
        return false;
    }

    return true;
};

const dedupeFormattedEntries = (entries) => {
    if (!Array.isArray(entries) || entries.length === 0) {
        return [];
    }

    const lastSeenIndexBySignature = new Map();

    return entries.filter((entry, index) => {
        if (!isDedupableEntry(entry)) {
            return true;
        }

        const normalized = normalizeTextForDeduping(entry.payload);
        const signature = `${String(entry.tone ?? 'plain').trim()}::${normalized}`;
        const previousIndex = lastSeenIndexBySignature.get(signature);

        if (typeof previousIndex === 'number' && (index - previousIndex) <= DEDUPE_WINDOW_EVENTS) {
            return false;
        }

        lastSeenIndexBySignature.set(signature, index);

        if (lastSeenIndexBySignature.size > 1024) {
            Array.from(lastSeenIndexBySignature.entries()).forEach(([key, seenIndex]) => {
                if ((index - seenIndex) > DEDUPE_WINDOW_EVENTS) {
                    lastSeenIndexBySignature.delete(key);
                }
            });
        }

        return true;
    });
};

const summarizeToolUseResult = (toolUseResult) => {
    if (!isRecord(toolUseResult)) {
        return [];
    }

    const messages = [];

    if (isRecord(toolUseResult.file)) {
        const filePath = String(toolUseResult.file.filePath ?? '').trim();
        const numLines = Number(toolUseResult.file.numLines ?? NaN);

        if (filePath !== '') {
            if (Number.isFinite(numLines) && numLines > 0) {
                messages.push(`Read: ${filePath} (${numLines} lines)`);
            } else {
                messages.push(`Read: ${filePath}`);
            }
        }
    }

    if (Array.isArray(toolUseResult.filenames)) {
        const count = toolUseResult.filenames.length;
        messages.push(`Glob matched ${count} file${count === 1 ? '' : 's'}`);
    }

    if (Array.isArray(toolUseResult.newTodos)) {
        messages.push(`Todo list updated (${toolUseResult.newTodos.length} item${toolUseResult.newTodos.length === 1 ? '' : 's'})`);
    }

    if (messages.length === 0 && typeof toolUseResult.type === 'string' && String(toolUseResult.type).trim() !== '') {
        messages.push(`Tool result: ${String(toolUseResult.type).trim()}`);
    }

    return messages;
};

const getStreamSessionId = (payloadObject) => String(payloadObject?.session_id ?? '').trim() || 'stream';

const getStreamBufferKey = (sessionId, eventType, index) => `${sessionId}:${eventType}:${index}`;

const flushTextBuffer = (formattedEntries, streamTextBuffers, streamBufferKey, { partial = false, state = null } = {}) => {
    const buffer = streamTextBuffers.get(streamBufferKey);
    if (!buffer) {
        return;
    }

    const text = String(buffer.text ?? '');
    const trimmed = text.trim();
    if (trimmed === '') {
        streamTextBuffers.delete(streamBufferKey);
        return;
    }

    const payload = partial ? `${text} …` : text;
    const markdown = looksLikeMarkdown(text);

    formattedEntries.push(makeFormattedEntry({
        key: partial ? `${buffer.key}:partial` : buffer.key,
        prefix: buffer.prefix,
        payload,
        tone: buffer.eventType === 'stderr' ? 'stderr' : (markdown ? 'markdown' : 'plain'),
        format: markdown ? 'markdown' : 'pre',
    }));

    if (state && state.lastTextBySession instanceof Map) {
        state.lastTextBySession.set(buffer.sessionId, trimmed);
    }

    streamTextBuffers.delete(streamBufferKey);
};

const handleStreamEventPayload = (context, payloadObject, formattedEntries, state) => {
    const event = isRecord(payloadObject.event) ? payloadObject.event : null;
    if (!event) {
        return false;
    }

    const streamEventType = String(event.type ?? '').trim();
    const sessionId = getStreamSessionId(payloadObject);

    if (streamEventType === 'content_block_start') {
        const index = Number(event.index ?? 0);
        const contentBlock = isRecord(event.content_block) ? event.content_block : null;
        const blockType = String(contentBlock?.type ?? '').trim();

        if (blockType === 'text') {
            const bufferKey = getStreamBufferKey(sessionId, context.eventType, index);
            state.streamTextBuffers.set(bufferKey, {
                key: `${context.key}:stream-text:${index}`,
                prefix: context.prefix,
                eventType: context.eventType,
                sessionId,
                text: '',
            });

            return true;
        }

        if (blockType === 'tool_use') {
            const toolName = String(contentBlock?.name ?? '').trim();
            if (toolName !== '') {
                formattedEntries.push(makeFormattedEntry({
                    key: `${context.key}:tool:${index}`,
                    prefix: context.prefix,
                    payload: `Tool call: ${toolName}`,
                    tone: 'structured',
                    format: 'pre',
                }));
            }

            return true;
        }

        return true;
    }

    if (streamEventType === 'content_block_delta') {
        const index = Number(event.index ?? 0);
        const delta = isRecord(event.delta) ? event.delta : null;
        const deltaType = String(delta?.type ?? '').trim();
        const bufferKey = getStreamBufferKey(sessionId, context.eventType, index);

        if (deltaType === 'text_delta') {
            const textPart = String(delta?.text ?? '');
            const existing = state.streamTextBuffers.get(bufferKey) ?? {
                key: `${context.key}:stream-text:${index}`,
                prefix: context.prefix,
                eventType: context.eventType,
                sessionId,
                text: '',
            };

            existing.text += textPart;
            state.streamTextBuffers.set(bufferKey, existing);
            return true;
        }

        if (deltaType === 'input_json_delta') {
            return true;
        }

        if (deltaType !== '') {
            return true;
        }

        return false;
    }

    if (streamEventType === 'content_block_stop') {
        const index = Number(event.index ?? 0);
        const bufferKey = getStreamBufferKey(sessionId, context.eventType, index);
        flushTextBuffer(formattedEntries, state.streamTextBuffers, bufferKey, { state });
        return true;
    }

    if (streamEventType === 'message_stop') {
        Array.from(state.streamTextBuffers.keys())
            .filter((bufferKey) => bufferKey.startsWith(`${sessionId}:${context.eventType}:`))
            .forEach((bufferKey) => {
                flushTextBuffer(formattedEntries, state.streamTextBuffers, bufferKey, { state });
            });

        return true;
    }

    if (streamEventType === 'message_start' || streamEventType === 'message_delta') {
        return true;
    }

    return false;
};

const handleAssistantPayload = (context, payloadObject, formattedEntries, state) => {
    const message = isRecord(payloadObject.message) ? payloadObject.message : null;
    const blocks = Array.isArray(message?.content) ? message.content : [];
    const sessionId = getStreamSessionId(payloadObject);

    if (blocks.length === 0) {
        return true;
    }

    blocks.forEach((block, index) => {
        if (!isRecord(block)) {
            return;
        }

        const blockType = String(block.type ?? '').trim();

        if (blockType === 'text') {
            const text = String(block.text ?? '');
            const trimmed = text.trim();
            if (trimmed === '') {
                return;
            }

            if (state.lastTextBySession.get(sessionId) === trimmed) {
                return;
            }

            const markdown = looksLikeMarkdown(text);
            formattedEntries.push(makeFormattedEntry({
                key: `${context.key}:assistant:${index}`,
                prefix: context.prefix,
                payload: text,
                tone: markdown ? 'markdown' : 'plain',
                format: markdown ? 'markdown' : 'pre',
            }));
            state.lastTextBySession.set(sessionId, trimmed);
            return;
        }

        if (blockType === 'tool_use') {
            const toolName = String(block.name ?? '').trim();
            if (toolName === '') {
                return;
            }

            formattedEntries.push(makeFormattedEntry({
                key: `${context.key}:assistant-tool:${index}`,
                prefix: context.prefix,
                payload: `Tool call: ${toolName}`,
                tone: 'structured',
                format: 'pre',
            }));
        }
    });

    return true;
};

const handleUserPayload = (context, payloadObject, formattedEntries) => {
    const summaryMessages = summarizeToolUseResult(payloadObject.tool_use_result);

    if (summaryMessages.length > 0) {
        summaryMessages.forEach((summary, index) => {
            formattedEntries.push(makeFormattedEntry({
                key: `${context.key}:tool-result:${index}`,
                prefix: context.prefix,
                payload: summary,
                tone: 'structured',
                format: 'pre',
            }));
        });

        return true;
    }

    const message = isRecord(payloadObject.message) ? payloadObject.message : null;
    const contentBlocks = Array.isArray(message?.content) ? message.content : [];
    const shortTextBlocks = contentBlocks
        .filter((block) => isRecord(block) && String(block.type ?? '').trim() === 'text')
        .map((block) => String(block.text ?? '').trim())
        .filter((text) => text !== '' && text.length <= 240);

    if (shortTextBlocks.length > 0) {
        shortTextBlocks.forEach((text, index) => {
            formattedEntries.push(makeFormattedEntry({
                key: `${context.key}:user-text:${index}`,
                prefix: context.prefix,
                payload: text,
                tone: 'plain',
                format: 'pre',
            }));
        });

        return true;
    }

    return true;
};

const handleResultPayload = (context, payloadObject, formattedEntries) => {
    if (!isRecord(payloadObject.result)) {
        return true;
    }

    const status = String(payloadObject.result.status ?? '').trim();
    const duration = Number(payloadObject.result.duration_ms ?? NaN);

    const details = [];
    if (status !== '') {
        details.push(`status: ${status}`);
    }
    if (Number.isFinite(duration) && duration >= 0) {
        details.push(`duration: ${duration}ms`);
    }

    if (details.length > 0) {
        formattedEntries.push(makeFormattedEntry({
            key: `${context.key}:result`,
            prefix: context.prefix,
            payload: `Result (${details.join(', ')})`,
            tone: 'structured',
            format: 'pre',
        }));
    }

    return true;
};

const handleStructuredEnvelope = (context, payloadObject, formattedEntries, state) => {
    const envelopeType = String(payloadObject.type ?? '').trim();

    if (!STREAM_ENVELOPE_TYPES.has(envelopeType)) {
        return false;
    }

    if (envelopeType === 'stream_event') {
        return handleStreamEventPayload(context, payloadObject, formattedEntries, state);
    }

    if (envelopeType === 'assistant') {
        return handleAssistantPayload(context, payloadObject, formattedEntries, state);
    }

    if (envelopeType === 'user') {
        return handleUserPayload(context, payloadObject, formattedEntries);
    }

    if (envelopeType === 'result') {
        return handleResultPayload(context, payloadObject, formattedEntries);
    }

    return false;
};

const handleStructuredEnvelopeFragments = (context, normalizedPayload, formattedEntries, state) => {
    if (!OUTPUT_EVENT_TYPES.has(context.eventType)) {
        return false;
    }

    const eventType = context.eventType;
    const existingCarry = String(state.envelopeCarryByType.get(eventType) ?? '');
    const rawPayload = typeof normalizedPayload === 'string' ? normalizedPayload : String(normalizedPayload ?? '');
    const shouldInspect = existingCarry !== '' || hasStructuredEnvelopeMarkers(rawPayload);

    if (!shouldInspect) {
        return false;
    }

    const combined = `${existingCarry}${rawPayload}`;
    const { parsedValues, remainder, hasIncompleteJson } = extractJsonObjects(combined);

    let handled = false;

    parsedValues.forEach((parsedValue, index) => {
        if (!isStructuredEnvelopeObject(parsedValue)) {
            return;
        }

        const fragmentContext = {
            ...context,
            key: `${context.key}:fragment:${index}`,
        };

        const handledEnvelope = handleStructuredEnvelope(fragmentContext, parsedValue, formattedEntries, state);
        handled = handled || handledEnvelope;
    });

    if (hasIncompleteJson) {
        writeEnvelopeCarry(state, eventType, remainder);
    } else {
        writeEnvelopeCarry(state, eventType, '');
    }

    return handled || hasIncompleteJson || existingCarry !== '' || hasStructuredEnvelopeMarkers(rawPayload);
};

export const formatAgentRunEventEntry = (entry) => {
    const context = buildEntryContext(entry);

    if (context.eventType === 'lifecycle') {
        const lifecyclePayload = parseJson(context.payloadRaw);
        const lifecycleSummary = formatLifecycleSummary(lifecyclePayload, context.fallbackTimestamp);

        if (lifecycleSummary) {
            return makeFormattedEntry({
                key: context.key,
                prefix: context.prefix,
                payload: lifecycleSummary,
                tone: 'lifecycle',
                format: 'pre',
            });
        }

        if (lifecyclePayload !== null) {
            return makeFormattedEntry({
                key: context.key,
                prefix: context.prefix,
                payload: JSON.stringify(lifecyclePayload, null, 2),
                tone: 'structured',
                format: 'pre',
            });
        }
    }

    const normalizedPayload = normalizeOutputPayload(context.eventType, context.payloadRaw);
    const parsedPayload = parseJson(normalizedPayload);

    if (parsedPayload !== null) {
        return makeFormattedEntry({
            key: context.key,
            prefix: context.prefix,
            payload: JSON.stringify(parsedPayload, null, 2),
            tone: context.eventType === 'stderr' ? 'stderr' : 'structured',
            format: 'pre',
        });
    }

    if ((context.eventType === 'stdout' || context.eventType === 'stderr') && looksLikeMarkdown(normalizedPayload)) {
        return makeFormattedEntry({
            key: context.key,
            prefix: context.prefix,
            payload: normalizedPayload,
            tone: context.eventType === 'stderr' ? 'stderr' : 'markdown',
            format: 'markdown',
        });
    }

    return makeFormattedEntry({
        key: context.key,
        prefix: context.prefix,
        payload: normalizedPayload,
        tone: context.eventType === 'stderr' ? 'stderr' : 'plain',
        format: 'pre',
    });
};

export const formatAgentRunEventEntries = (entries) => {
    if (!Array.isArray(entries)) {
        return [];
    }

    const formattedEntries = [];
    const state = {
        streamTextBuffers: new Map(),
        lastTextBySession: new Map(),
        envelopeCarryByType: new Map(),
    };

    entries.forEach((entry) => {
        const context = buildEntryContext(entry);

        if (context.eventType === 'lifecycle') {
            const lifecycleEntry = formatAgentRunEventEntry(entry);
            if (String(lifecycleEntry?.payload ?? '').trim() !== '') {
                formattedEntries.push(lifecycleEntry);
            }
            return;
        }

        const normalizedPayload = normalizeOutputPayload(context.eventType, context.payloadRaw);
        const parsedPayload = parseJson(normalizedPayload);

        if (isRecord(parsedPayload)) {
            const handled = handleStructuredEnvelope(context, parsedPayload, formattedEntries, state);
            if (handled) {
                return;
            }
        }

        if (handleStructuredEnvelopeFragments(context, normalizedPayload, formattedEntries, state)) {
            return;
        }

        const formatted = formatAgentRunEventEntry(entry);
        if (String(formatted?.payload ?? '').trim() !== '') {
            formattedEntries.push(formatted);
        }
    });

    state.streamTextBuffers.forEach((_, streamBufferKey) => {
        flushTextBuffer(formattedEntries, state.streamTextBuffers, streamBufferKey, {
            partial: true,
            state,
        });
    });

    const nonEmptyEntries = formattedEntries.filter((entry) => String(entry?.payload ?? '').trim() !== '');

    return dedupeFormattedEntries(nonEmptyEntries);
};
