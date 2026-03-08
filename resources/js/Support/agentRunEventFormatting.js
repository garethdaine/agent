const OUTPUT_EVENT_TYPES = new Set(['stdout', 'stderr']);
const STREAM_ENVELOPE_TYPES = new Set(['stream_event', 'assistant', 'user', 'result']);
const STRUCTURED_ENVELOPE_MARKER_PATTERN = /"type"\s*:\s*"(?:stream_event|assistant|user|result)"|"session_id"\s*:|"parent_tool_use_id"\s*:|\[\d+\s+(?:stdout|stderr|lifecycle)\]/;
const ENVELOPE_CARRY_LIMIT = 32_768;
const DEDUPE_WINDOW_EVENTS = 80;
const DEDUPE_MIN_CHARS = 24;
const ESCAPED_LINE_BREAK_PATTERN = /\\r\\n|\\n|\\r/g;
const LINE_NUMBERED_SNIPPET_LINE_PATTERN = /^\s*(\d+)\s*(?:→|->|=>)\s?(.*)$/u;
const LINE_NUMBERED_PREFIX_PATTERN = /^\s*\d+\s*\|\s?/u;
const LINE_NUMBERED_PREFIX_CAPTURE_PATTERN = /^\s*(\d+)\s*\|\s?/u;
const MCP_CONNECTION_REFUSED_PATTERN = /rmcp::transport::worker[\s\S]{0,1800}?transport channel closed[\s\S]{0,1800}?(https?:\/\/[^\s"']+\/mcp)[\s\S]{0,1800}?connection refused/i;

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

const parseSequentialJsonPayload = (raw) => {
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

    const { parsedValues, remainder, hasIncompleteJson } = extractJsonObjects(trimmed);
    if (hasIncompleteJson || parsedValues.length < 2) {
        return null;
    }

    if (String(remainder ?? '').trim() !== '') {
        return null;
    }

    return parsedValues.map((value) => JSON.stringify(value, null, 2)).join('\n\n');
};

const parseChunkedTextPayload = (raw) => {
    const parsed = parseJson(raw);
    if (!isRecord(parsed) || typeof parsed.text !== 'string' || typeof parsed.continuation !== 'boolean') {
        return null;
    }

    return {
        text: parsed.text,
        continuation: parsed.continuation,
    };
};

const decodeEscapedLineBreaks = (value) => {
    if (typeof value !== 'string' || value === '') {
        return value;
    }

    const escapedBreaks = value.match(ESCAPED_LINE_BREAK_PATTERN);
    if (!Array.isArray(escapedBreaks) || escapedBreaks.length === 0) {
        return value;
    }

    const looksLikeStructuredEscapedBlock = /\\n\s{0,4}(?:\d+\s*(?:→|->|=>)|#{1,6}\s|[-*]\s|\d+\.\s)/u.test(value);
    if (escapedBreaks.length < 2 && !looksLikeStructuredEscapedBlock && !value.includes('→\\n')) {
        return value;
    }

    return value
        .replace(/\\r\\n/g, '\n')
        .replace(/\\n/g, '\n')
        .replace(/\\r/g, '\n')
        .replace(/\\t/g, '\t')
        .replace(/\\"/g, '"');
};

const normalizeLineNumberedSnippet = (value) => {
    if (typeof value !== 'string' || value.trim() === '') {
        return value;
    }

    const lines = value.split('\n');
    let transformed = 0;

    const normalizedLines = lines.map((line) => {
        const match = line.match(LINE_NUMBERED_SNIPPET_LINE_PATTERN);
        if (!match) {
            return line;
        }

        transformed += 1;
        const lineNumber = String(match[1] ?? '').trim();
        const content = String(match[2] ?? '').replace(/\s+$/g, '');

        return content === '' ? `${lineNumber} |` : `${lineNumber} | ${content}`;
    });

    if (transformed < 3) {
        return value;
    }

    const cleanedLines = normalizedLines.filter((line) => line.trim() !== '→');
    return cleanedLines.join('\n');
};

const stripLineNumberPrefixes = (value) => {
    if (typeof value !== 'string' || value.trim() === '') {
        return value;
    }

    const lines = value.split('\n');
    let transformed = 0;

    const stripped = lines.map((line) => {
        if (!LINE_NUMBERED_PREFIX_PATTERN.test(line)) {
            return line;
        }

        transformed += 1;
        return line.replace(LINE_NUMBERED_PREFIX_PATTERN, '');
    });

    if (transformed < 3) {
        return value;
    }

    return stripped.join('\n');
};

const summarizeReadExcerptPayload = (value, eventType) => {
    if (eventType !== 'stdout' || typeof value !== 'string' || value.trim() === '') {
        return null;
    }

    const lines = value.split('\n');
    const lineNumbers = lines
        .map((line) => {
            const match = line.match(LINE_NUMBERED_PREFIX_CAPTURE_PATTERN);
            return match ? Number(match[1]) : null;
        })
        .filter((lineNumber) => Number.isFinite(lineNumber));

    if (lineNumbers.length < 8) {
        return null;
    }

    const markdownCandidate = stripLineNumberPrefixes(value);
    if (!looksLikeMarkdown(markdownCandidate)) {
        return null;
    }

    const firstLine = Math.min(...lineNumbers);
    const lastLine = Math.max(...lineNumbers);

    if (Number.isFinite(firstLine) && Number.isFinite(lastLine) && lastLine >= firstLine) {
        return `Read excerpt omitted (${lineNumbers.length} lines, source lines ${firstLine}-${lastLine})`;
    }

    return `Read excerpt omitted (${lineNumbers.length} lines)`;
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
    const reasoningStep = entry?.reasoning_step ?? null;

    return {
        entry,
        eventType,
        sequence,
        payloadRaw,
        fallbackTimestamp,
        key,
        prefix: buildPrefix(sequence, eventType),
        reasoningStep,
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

    if (type === 'rate_limit_detected') {
        const resetAt = String(payloadObject.reset_at ?? '').trim();
        const timezone = String(payloadObject.timezone ?? '').trim();
        const at = String(payloadObject.at ?? fallbackTimestamp ?? '').trim();
        const parts = ['Rate limit detected'];

        if (resetAt !== '') {
            try {
                const resetDate = new Date(resetAt);
                const timeStr = resetDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                const tzSuffix = timezone && timezone !== 'UTC' ? ` (${timezone})` : '';
                parts.push(`resets ${timeStr}${tzSuffix}`);
            } catch {
                parts.push(`resets ${resetAt}`);
            }
        }

        if (at !== '' && !resetAt) {
            parts.push(`at ${at}`);
        }

        return parts.join(' | ');
    }

    if (type === 'redaction_notice') {
        const segments = Number(payloadObject.replaced_segments ?? 0);
        return segments > 0 ? `Redacted ${segments} sensitive segment${segments !== 1 ? 's' : ''}` : 'Redaction applied';
    }

    if (type === 'truncation_notice') {
        const msg = String(payloadObject.message ?? '').trim();
        return msg || 'Output truncated (5MB limit)';
    }

    return null;
};

const normalizeOutputPayload = (eventType, rawPayload) => {
    if (!OUTPUT_EVENT_TYPES.has(eventType)) {
        return rawPayload;
    }

    let normalizedPayload = rawPayload;
    const parsed = parseJson(rawPayload);
    if (parsed && typeof parsed === 'object' && !Array.isArray(parsed) && typeof parsed.text === 'string') {
        normalizedPayload = parsed.text;
    }

    normalizedPayload = decodeEscapedLineBreaks(normalizedPayload);
    normalizedPayload = normalizeLineNumberedSnippet(normalizedPayload);

    return normalizedPayload;
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
    reasoningStep = null,
}) => ({
    key,
    prefix,
    payload,
    tone,
    format,
    reasoningStep,
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

    const normalizedText = normalizeOutputPayload(buffer.eventType, text);
    const readExcerptSummary = summarizeReadExcerptPayload(normalizedText, buffer.eventType);

    if (readExcerptSummary !== null) {
        formattedEntries.push(makeFormattedEntry({
            key: partial ? `${buffer.key}:partial` : buffer.key,
            prefix: buffer.prefix,
            payload: readExcerptSummary,
            tone: 'structured',
            format: 'pre',
        }));

        if (state && state.lastTextBySession instanceof Map) {
            state.lastTextBySession.set(buffer.sessionId, readExcerptSummary);
        }

        streamTextBuffers.delete(streamBufferKey);
        return;
    }

    const markdownPayload = stripLineNumberPrefixes(normalizedText);
    const payload = partial ? `${markdownPayload} …` : markdownPayload;
    const markdown = looksLikeMarkdown(markdownPayload);

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
        const fragmentContext = {
            ...context,
            key: `${context.key}:fragment:${index}`,
        };

        if (isStructuredEnvelopeObject(parsedValue)) {
            const handledEnvelope = handleStructuredEnvelope(fragmentContext, parsedValue, formattedEntries, state);
            handled = handled || handledEnvelope;
            return;
        }

        const contentSnapshot = extractContentSnapshotFromPayload(parsedValue);
        if (contentSnapshot !== null) {
            formattedEntries.push(makeFormattedEntry({
                key: fragmentContext.key,
                prefix: fragmentContext.prefix,
                payload: contentSnapshot,
                tone: 'plain',
                format: 'pre',
            }));
            handled = true;
        }
    });

    if (hasIncompleteJson) {
        writeEnvelopeCarry(state, eventType, remainder);
    } else {
        writeEnvelopeCarry(state, eventType, '');
    }

    return handled || hasIncompleteJson || existingCarry !== '' || hasStructuredEnvelopeMarkers(rawPayload);
};

const stitchChunkedOutputEntries = (entries) => {
    if (!Array.isArray(entries) || entries.length === 0) {
        return [];
    }

    const stitched = [];
    let pending = null;

    const flushPending = () => {
        if (!pending) {
            return;
        }

        stitched.push(pending);
        pending = null;
    };

    entries.forEach((entry) => {
        const rawPayload = stringifyPayload(entry?.payload);
        const chunk = parseChunkedTextPayload(rawPayload);

        if (!chunk) {
            flushPending();
            stitched.push(entry);
            return;
        }

        if (!chunk.continuation) {
            flushPending();
            pending = {
                ...entry,
                payload: chunk.text,
            };

            return;
        }

        if (!pending || String(pending?.event_type ?? '') !== String(entry?.event_type ?? '')) {
            flushPending();
            stitched.push({
                ...entry,
                payload: chunk.text,
            });
            return;
        }

        pending = {
            ...pending,
            payload: `${String(pending.payload ?? '')}${chunk.text}`,
        };
    });

    flushPending();

    return stitched;
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
                reasoningStep: context.reasoningStep,
            });
        }

        if (lifecyclePayload !== null) {
            const summary = summarizeUnknownJsonPayload(lifecyclePayload);
            if (summary !== null) {
                return makeFormattedEntry({
                    key: context.key,
                    prefix: context.prefix,
                    payload: summary,
                    tone: 'lifecycle',
                    format: 'pre',
                    reasoningStep: context.reasoningStep,
                });
            }
            return null;
        }
    }

    const normalizedPayload = normalizeOutputPayload(context.eventType, context.payloadRaw);
    const parsedPayload = parseJson(normalizedPayload);

    if (parsedPayload !== null) {
        const contentSnapshot = extractContentSnapshotFromPayload(parsedPayload);
        if (contentSnapshot !== null) {
            return makeFormattedEntry({
                key: context.key,
                prefix: context.prefix,
                payload: contentSnapshot,
                tone: context.eventType === 'stderr' ? 'stderr' : 'plain',
                format: 'pre',
                reasoningStep: context.reasoningStep,
            });
        }
        const envelopeSummary = formatStructuredEnvelopeSummary(parsedPayload);
        if (envelopeSummary !== null) {
            return makeFormattedEntry({
                key: context.key,
                prefix: context.prefix,
                payload: envelopeSummary,
                tone: 'structured',
                format: 'pre',
                reasoningStep: context.reasoningStep,
            });
        }
        const summary = summarizeUnknownJsonPayload(parsedPayload);
        if (summary !== null) {
            return makeFormattedEntry({
                key: context.key,
                prefix: context.prefix,
                payload: summary,
                tone: context.eventType === 'stderr' ? 'stderr' : 'structured',
                format: 'pre',
                reasoningStep: context.reasoningStep,
            });
        }
        return null;
    }

    // Multi-line JSON: payload may contain newline-separated JSON objects
    // that fail single parseJson() but can be individually formatted.
    if (normalizedPayload.includes('\n') && normalizedPayload.trimStart().startsWith('{')) {
        const lines = normalizedPayload.split('\n').filter((l) => l.trim() !== '');
        const summaries = [];
        for (const line of lines) {
            const lineJson = parseJson(line.trim());
            if (lineJson === null) continue;
            const snap = extractContentSnapshotFromPayload(lineJson);
            if (snap !== null) { summaries.push(snap); continue; }
            const env = formatStructuredEnvelopeSummary(lineJson);
            if (env !== null) { summaries.push(env); continue; }
            const unk = summarizeUnknownJsonPayload(lineJson);
            if (unk !== null) summaries.push(unk);
        }
        if (summaries.length > 0) {
            return makeFormattedEntry({
                key: context.key,
                prefix: context.prefix,
                payload: summaries.join('\n'),
                tone: 'structured',
                format: 'pre',
                reasoningStep: context.reasoningStep,
            });
        }
    }

    const readExcerptSummary = summarizeReadExcerptPayload(normalizedPayload, context.eventType);
    if (readExcerptSummary !== null) {
        return makeFormattedEntry({
            key: context.key,
            prefix: context.prefix,
            payload: readExcerptSummary,
            tone: 'structured',
            format: 'pre',
            reasoningStep: context.reasoningStep,
        });
    }

    const markdownCandidate = stripLineNumberPrefixes(normalizedPayload);

    if ((context.eventType === 'stdout' || context.eventType === 'stderr') && looksLikeMarkdown(markdownCandidate)) {
        return makeFormattedEntry({
            key: context.key,
            prefix: context.prefix,
            payload: markdownCandidate,
            tone: context.eventType === 'stderr' ? 'stderr' : 'markdown',
            format: 'markdown',
            reasoningStep: context.reasoningStep,
        });
    }

    if (NOISE_PAYLOAD_MARKERS.test(normalizedPayload)) {
        return null;
    }

    return makeFormattedEntry({
        key: context.key,
        prefix: context.prefix,
        payload: normalizedPayload,
        tone: context.eventType === 'stderr' ? 'stderr' : 'plain',
        format: 'pre',
        reasoningStep: context.reasoningStep,
    });
};

export const formatAgentRunEventEntries = (entries) => {
    if (!Array.isArray(entries)) {
        return [];
    }

    const stitchedEntries = stitchChunkedOutputEntries(entries);
    const formattedEntries = [];
    const state = {
        streamTextBuffers: new Map(),
        lastTextBySession: new Map(),
        envelopeCarryByType: new Map(),
    };

    stitchedEntries.forEach((entry) => {
        const context = buildEntryContext(entry);

        if (context.eventType === 'lifecycle') {
            const lifecycleEntry = formatAgentRunEventEntry(entry);
            if (String(lifecycleEntry?.payload ?? '').trim() !== '') {
                formattedEntries.push(lifecycleEntry);
            }
            return;
        }

        const normalizedPayload = normalizeOutputPayload(context.eventType, context.payloadRaw);
        const { parsedValues } = extractJsonObjects(normalizedPayload);

        if (parsedValues.length > 1) {
            let added = false;
            parsedValues.forEach((parsedValue, fragIndex) => {
                const fragContext = { ...context, key: `${context.key}:frag:${fragIndex}` };
                if (isStructuredEnvelopeObject(parsedValue)) {
                    if (handleStructuredEnvelope(fragContext, parsedValue, formattedEntries, state)) {
                        added = true;
                    }
                } else {
                    const contentSnapshot = extractContentSnapshotFromPayload(parsedValue);
                    if (contentSnapshot !== null) {
                        formattedEntries.push(makeFormattedEntry({
                            key: fragContext.key,
                            prefix: fragContext.prefix,
                            payload: contentSnapshot,
                            tone: 'plain',
                            format: 'pre',
                        }));
                        added = true;
                    } else {
                        const envelopeSummary = formatStructuredEnvelopeSummary(parsedValue);
                        if (envelopeSummary !== null) {
                            formattedEntries.push(makeFormattedEntry({
                                key: fragContext.key,
                                prefix: fragContext.prefix,
                                payload: envelopeSummary,
                                tone: 'structured',
                                format: 'pre',
                            }));
                            added = true;
                        }
                    }
                }
            });
            if (added || parsedValues.some((p) => isStructuredEnvelopeObject(p))) {
                return;
            }
        }

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

const formatTimelineTimestamp = (value) => {
    const text = String(value ?? '').trim();
    if (text === '') {
        return '';
    }

    const asDate = new Date(text);
    if (!Number.isFinite(asDate.getTime())) {
        return '';
    }

    return asDate.toLocaleTimeString();
};

const extractMcpConnectionIssue = (value) => {
    const text = String(value ?? '');
    if (text === '') {
        return null;
    }

    const match = text.match(MCP_CONNECTION_REFUSED_PATTERN);
    if (!match) {
        return null;
    }

    const endpoint = String(match[1] ?? '').trim() || 'http://localhost:3333/mcp';

    return {
        key: 'mcp_server_unavailable',
        code: 'MCP_SERVER_UNAVAILABLE',
        title: 'MCP server unavailable',
        detail: `Could not connect to ${endpoint} (connection refused).`,
        suggestedAction: 'Start/restart the MCP server on port 3333 or update MCP endpoint config.',
        endpoint,
    };
};

const extractCommandExecutionFromEnvelope = (payloadObject) => {
    if (!isRecord(payloadObject)) {
        return null;
    }

    const item = isRecord(payloadObject.item)
        ? payloadObject.item
        : (isRecord(payloadObject.event?.item) ? payloadObject.event.item : null);

    if (!item || String(item.type ?? '').trim() !== 'command_execution') {
        return null;
    }

    const command = String(item.command ?? '').trim();
    const status = String(item.status ?? '').trim().toLowerCase() || 'running';
    const exitCode = Number(item.exit_code ?? NaN);
    const durationMs = Number(item.duration_ms ?? NaN);
    const output = String(item.aggregated_output ?? item.output ?? '').trim();

    return {
        command,
        status,
        exitCode: Number.isFinite(exitCode) ? exitCode : null,
        durationMs: Number.isFinite(durationMs) ? durationMs : null,
        output,
    };
};

const extractEnvelopeText = (payloadObject) => {
    if (!isRecord(payloadObject)) {
        return '';
    }

    const content = payloadObject.message ?? payloadObject.text ?? payloadObject.content;
    if (typeof content === 'string' && content.trim() !== '') {
        return content.trim();
    }
    if (Array.isArray(content)) {
        return '';
    }

    if (isRecord(payloadObject.item)) {
        const fromItem = payloadObject.item.message ?? payloadObject.item.text ?? payloadObject.item.content;
        if (typeof fromItem === 'string' && fromItem.trim() !== '') {
            return fromItem.trim();
        }
    }

    if (isRecord(payloadObject.event) && isRecord(payloadObject.event.delta)) {
        const deltaText = String(payloadObject.event.delta.text ?? '').trim();
        if (deltaText !== '') {
            return deltaText;
        }
    }

    return '';
};

const extractContentSnapshotFromPayload = (payload) => {
    if (payload === null || payload === undefined) {
        return null;
    }

    if (Array.isArray(payload)) {
        const lines = [];
        for (const item of payload) {
            if (!item || typeof item !== 'object') continue;
            const rawContent = item.content ?? item.text ?? item.title ?? '';
            const content = (typeof rawContent === 'string' ? rawContent : stringifyPayload(rawContent)).trim();
            const status = String(item.status ?? '').trim().toLowerCase();
            const activeForm = String(item.activeForm ?? item.active_form ?? '').trim();
            if (content !== '') {
                const statusLabel = status && status !== 'pending' ? ` [${status}]` : '';
                lines.push(`• ${content}${statusLabel}`);
            } else if (activeForm !== '') {
                lines.push(`• ${activeForm}${status ? ` [${status}]` : ''}`);
            }
        }
        return lines.length > 0 ? lines.join('\n') : null;
    }

    if (typeof payload === 'object') {
        const content = payload.content ?? payload.text ?? payload.message;
        if (Array.isArray(content)) {
            return extractContentSnapshotFromPayload(content);
        }
        if (typeof content === 'string' && content.trim() !== '') {
            return content.trim();
        }
        const todos = payload.todos ?? payload.items ?? payload.tasks;
        if (Array.isArray(todos)) {
            return extractContentSnapshotFromPayload(todos);
        }
        const event = payload.event;
        if (event && typeof event === 'object') {
            const delta = event.delta;
            if (delta && typeof delta === 'object' && delta.partial_json) {
                return null;
            }
        }
    }

    return null;
};

const NOISE_JSON_TYPES = new Set([
    'message_start', 'message_delta', 'content_block_start', 'content_block_delta', 'content_block_stop',
    'message_stop', 'input_json_delta', 'signature_delta', 'ping', 'pong',
]);

const NOISE_PAYLOAD_MARKERS = /"type"\s*:\s*"(?:stream_event|input_json_delta|content_block_delta|signature_delta)"/;

const inferKindFromSummaryText = (text) => {
    const t = String(text ?? '').trim();
    if (t.startsWith('Tool call:')) return 'tool_call';
    if (t.startsWith('Todo list updated')) return 'tool_result';
    return 'structured';
};

const formatStructuredEnvelopeSummary = (obj) => {
    if (!isRecord(obj)) {
        return null;
    }

    const type = String(obj.type ?? '').trim();
    if (type === 'stream_event') {
        const event = isRecord(obj.event) ? obj.event : null;
        const eventType = event ? String(event.type ?? '').trim() : '';
        if (NOISE_JSON_TYPES.has(eventType)) {
            return null;
        }
        const delta = isRecord(event?.delta) ? event.delta : null;
        const deltaType = delta ? String(delta.type ?? '').trim() : '';
        if (deltaType === 'input_json_delta' || deltaType === 'signature_delta') {
            return null;
        }
        if (eventType === 'content_block_start') {
            const block = event.content_block;
            const blockType = String(block?.type ?? '').trim();
            if (blockType === 'tool_use') {
                const name = String(block?.name ?? '').trim();
                return name ? `Tool call: ${name}` : null;
            }
        }
        if (eventType === 'message_delta' && isRecord(event.usage)) {
            const out = Number(event.usage.output_tokens ?? NaN);
            return Number.isFinite(out) && out > 0 ? `Output: ${out} tokens` : null;
        }
        return null;
    }

    if (type === 'assistant') {
        const message = isRecord(obj.message) ? obj.message : null;
        const modelName = String(message?.model ?? obj.model ?? '').trim();
        const blocks = Array.isArray(message?.content) ? message.content : [];
        const usage = isRecord(message?.usage) ? message.usage : obj.usage;
        const inTokens = Number(usage?.input_tokens ?? NaN);
        const outTokens = Number(usage?.output_tokens ?? NaN);
        const tokenStr = Number.isFinite(outTokens) && outTokens > 0 ? ` (${outTokens} output tokens)` : '';
        const isSynthetic = modelName === '<synthetic>' || (Number.isFinite(inTokens) && inTokens === 0 && Number.isFinite(outTokens) && outTokens === 0);

        for (const block of blocks) {
            if (!isRecord(block)) continue;
            const blockType = String(block.type ?? '').trim();
            if (blockType === 'tool_use') {
                const name = String(block.name ?? '').trim();
                return name ? `Tool call: ${name}${tokenStr}` : null;
            }
            if (blockType === 'text') {
                const text = String(block.text ?? '').trim();
                if (text.length > 0 && text.length <= 200) return text;
                if (text.length > 200) return `${text.slice(0, 200).trimEnd()}…`;
            }
            if (blockType === 'thinking') {
                const thinking = String(block.thinking ?? '').trim();
                if (thinking.length > 0) return `Thinking…${tokenStr}`;
            }
        }
        // Suppress synthetic messages with no meaningful content
        if (isSynthetic) return null;
        return tokenStr ? `Assistant response${tokenStr}` : null;
    }

    if (type === 'user') {
        const toolResult = obj.tool_use_result;
        if (isRecord(toolResult)) {
            const msgs = summarizeToolUseResult(toolResult);
            return msgs.length > 0 ? msgs[0] : null;
        }
        return null;
    }

    if (type === 'result') {
        const result = isRecord(obj.result) ? obj.result : null;
        if (!result) return null;
        const status = String(result.status ?? '').trim();
        const duration = Number(result.duration_ms ?? NaN);
        const parts = [];
        if (status) parts.push(status);
        if (Number.isFinite(duration)) parts.push(`${duration}ms`);
        return parts.length > 0 ? `Result: ${parts.join(', ')}` : null;
    }

    if (type === 'rate_limit_event') {
        const info = isRecord(obj.rate_limit_info) ? obj.rate_limit_info : {};
        const status = String(info.status ?? '').trim();
        const limitType = String(info.rateLimitType ?? '').trim().replace(/_/g, ' ');
        const resetsAt = Number(info.resetsAt ?? NaN);
        const overageStatus = String(info.overageStatus ?? '').trim();
        const parts = ['Rate limit'];
        if (status) parts.push(status);
        if (limitType) parts.push(`(${limitType})`);
        if (Number.isFinite(resetsAt) && resetsAt > 0) {
            const resetDate = new Date(resetsAt * 1000);
            parts.push(`- resets ${resetDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`);
        }
        if (overageStatus && overageStatus !== status) parts.push(`[overage: ${overageStatus}]`);
        return parts.join(' ');
    }

    if (type === 'error' || type === 'turn.failed') {
        const errorObj = isRecord(obj.error) ? obj.error : null;
        const msg = String(errorObj?.message ?? obj.message ?? obj.text ?? '').trim();
        const code = String(errorObj?.code ?? obj.code ?? '').trim();
        const label = type === 'error' ? 'Error' : 'Turn failed';
        if (msg) return code ? `${label}: ${msg} (${code})` : `${label}: ${msg}`;
        if (code) return `${label}: ${code}`;
        return label;
    }

    return null;
};

const safeDisplayText = (value) => {
    if (value === null || value === undefined) return '';
    if (typeof value === 'string') return value;
    if (typeof value === 'object') {
        const extracted = extractContentSnapshotFromPayload(value) ?? formatStructuredEnvelopeSummary(value);
        if (extracted) return extracted;
        return '';
    }
    return String(value);
};

const summarizeUnknownJsonPayload = (payloadObject) => {
    if (!isRecord(payloadObject)) {
        return null;
    }

    const type = String(payloadObject.type ?? '').trim();
    if (NOISE_JSON_TYPES.has(type)) {
        return null;
    }

    const event = isRecord(payloadObject.event) ? payloadObject.event : null;
    const eventType = event ? String(event.type ?? '').trim() : '';
    if (NOISE_JSON_TYPES.has(eventType)) {
        return null;
    }

    const rawMsg = payloadObject.message ?? payloadObject.text ?? payloadObject.content ?? '';
    const msg = (typeof rawMsg === 'string' ? rawMsg : (typeof rawMsg === 'object' ? stringifyPayload(rawMsg) : String(rawMsg))).trim();
    if (msg !== '' && msg !== '[object Object]' && msg.length <= 200) {
        return msg;
    }

    const item = isRecord(payloadObject.item) ? payloadObject.item : null;
    const itemType = item ? String(item.type ?? '').trim() : '';
    const rawItemText = item ? (item.text ?? item.message ?? '') : '';
    const itemText = (typeof rawItemText === 'string' ? rawItemText : stringifyPayload(rawItemText)).trim();
    if (itemText !== '' && itemText.length <= 200) {
        return itemText;
    }

    if (type === 'message_start' || type === 'message_delta') {
        return null;
    }

    if (type !== '') {
        const human = type.replace(/_/g, ' ');
        if (eventType === 'text_delta' || (event?.delta && String(event.delta?.type ?? '').trim() === 'text_delta')) {
            return null;
        }
        if (itemType === 'command_execution') {
            return null;
        }
        return human.charAt(0).toUpperCase() + human.slice(1);
    }

    if (payloadObject.status && payloadObject.duration_ms != null) {
        return `Completed (${payloadObject.status}, ${payloadObject.duration_ms}ms)`;
    }

    return null;
};

const summarizeCodexMachineEvent = (payloadObject) => {
    if (!isRecord(payloadObject)) {
        return '';
    }

    const type = String(payloadObject.type ?? '').trim();
    if (type === '') {
        return '';
    }

    if (type === 'thread.started') {
        const threadId = String(payloadObject.thread_id ?? '').trim();
        return threadId !== '' ? `Thread started (${threadId})` : 'Thread started';
    }

    if (type === 'turn.started') {
        return 'Turn started';
    }

    if (type === 'turn.completed') {
        const usage = isRecord(payloadObject.usage) ? payloadObject.usage : null;
        if (!usage) {
            return 'Turn completed';
        }

        const inputTokens = Number(usage.input_tokens ?? NaN);
        const outputTokens = Number(usage.output_tokens ?? NaN);
        const cachedInputTokens = Number(usage.cached_input_tokens ?? NaN);
        const summary = [];

        if (Number.isFinite(inputTokens) && inputTokens >= 0) {
            summary.push(`in ${inputTokens.toLocaleString()}`);
        }
        if (Number.isFinite(outputTokens) && outputTokens >= 0) {
            summary.push(`out ${outputTokens.toLocaleString()}`);
        }
        if (Number.isFinite(cachedInputTokens) && cachedInputTokens > 0) {
            summary.push(`cached ${cachedInputTokens.toLocaleString()}`);
        }

        return summary.length > 0 ? `Turn completed (${summary.join(', ')})` : 'Turn completed';
    }

    if (type === 'item.started' || type === 'item.completed') {
        const item = isRecord(payloadObject.item) ? payloadObject.item : null;
        if (!item) {
            return type === 'item.started' ? 'Item started' : 'Item completed';
        }

        const itemType = String(item.type ?? '').trim();
        if (itemType === 'command_execution') {
            return '';
        }

        if (itemType === 'file_change' && Array.isArray(item.changes)) {
            const count = item.changes.length;
            return count > 0
                ? `File changes ${type === 'item.started' ? 'started' : 'completed'} (${count} file${count === 1 ? '' : 's'})`
                : `File changes ${type === 'item.started' ? 'started' : 'completed'}`;
        }

        const text = String(item.text ?? item.message ?? '').trim();
        if (text !== '') {
            return text;
        }

        if (itemType !== '') {
            return `${itemType.replace(/_/g, ' ')} ${type === 'item.started' ? 'started' : 'completed'}`;
        }

        return type === 'item.started' ? 'Item started' : 'Item completed';
    }

    return '';
};

const collapseConsecutiveTimelineEntries = (entries) => {
    const collapsed = [];

    entries.forEach((entry) => {
        const previous = collapsed[collapsed.length - 1];
        if (!previous) {
            collapsed.push({ ...entry, repeatCount: 1 });
            return;
        }

        const sameEntry = previous.kind === entry.kind
            && previous.eventType === entry.eventType
            && String(previous.displayFormat ?? '') === String(entry.displayFormat ?? '')
            && String(previous.text ?? '') === String(entry.text ?? '')
            && String(previous.command?.command ?? '') === String(entry.command?.command ?? '');

        if (!sameEntry) {
            collapsed.push({ ...entry, repeatCount: 1 });
            return;
        }

        previous.repeatCount = Number(previous.repeatCount ?? 1) + 1;
        previous.sequence = entry.sequence;
        previous.timestamp = entry.timestamp;
        previous.timestampLabel = entry.timestampLabel;
        if (entry.command?.output) {
            previous.command = {
                ...previous.command,
                ...entry.command,
            };
        }
    });

    return collapsed;
};

const pushOrUpdateIssue = (issuesByKey, issue, sequence, timestamp) => {
    if (!issue || !issue.key) {
        return;
    }

    const existing = issuesByKey.get(issue.key);
    if (!existing) {
        issuesByKey.set(issue.key, {
            ...issue,
            count: 1,
            latestSequence: Number(sequence ?? 0),
            latestTimestamp: timestamp ?? null,
            logDetections: 1,
            countFromMetadata: 0,
        });
        return;
    }

    const nextLogDetections = Number(existing.logDetections ?? 0) + 1;
    const metadataCount = Number(existing.countFromMetadata ?? 0);

    existing.logDetections = nextLogDetections;
    existing.count = Math.max(nextLogDetections, metadataCount, Number(existing.count ?? 0));
    existing.latestSequence = Number(sequence ?? existing.latestSequence ?? 0);
    existing.latestTimestamp = timestamp ?? existing.latestTimestamp ?? null;
    if (issue.endpoint) {
        existing.endpoint = issue.endpoint;
        existing.detail = issue.detail;
    }
};

export const buildAgentRunEventPresentation = (entries, runMetadata = {}) => {
    if (!Array.isArray(entries)) {
        return {
            timelineEntries: [],
            heartbeatEntries: [],
            heartbeatSummary: null,
            issues: [],
        };
    }

    const stitchedEntries = stitchChunkedOutputEntries(entries);
    const timeline = [];
    const heartbeatEntries = [];
    const issuesByKey = new Map();

    const metadataIssues = Array.isArray(runMetadata?.issues) ? runMetadata.issues : [];
    metadataIssues.forEach((issue) => {
        const key = String(issue?.key ?? issue?.code ?? '').trim();
        if (key === '') {
            return;
        }

        issuesByKey.set(key, {
            key,
            code: String(issue?.code ?? key).trim(),
            title: String(issue?.title ?? 'Issue detected').trim(),
            detail: String(issue?.detail ?? '').trim(),
            suggestedAction: String(issue?.suggested_action ?? '').trim(),
            endpoint: String(issue?.endpoint ?? '').trim() || null,
            count: Math.max(1, Number(issue?.count ?? 1)),
            latestSequence: 0,
            latestTimestamp: String(issue?.last_detected_at ?? '').trim() || null,
            logDetections: 0,
            countFromMetadata: Math.max(1, Number(issue?.count ?? 1)),
        });
    });

    stitchedEntries.forEach((entry, index) => {
        const context = buildEntryContext(entry);
        const timestamp = String(entry?.event_ts ?? entry?.created_at ?? '').trim() || null;
        const timestampLabel = formatTimelineTimestamp(timestamp);
        const normalizedPayload = normalizeOutputPayload(context.eventType, context.payloadRaw);
        const issueFromPayload = extractMcpConnectionIssue(normalizedPayload);
        if (issueFromPayload) {
            pushOrUpdateIssue(issuesByKey, issueFromPayload, context.sequence, timestamp);
        }

        if (context.eventType === 'lifecycle') {
            const lifecyclePayload = parseJson(context.payloadRaw);
            const lifecycleType = String(lifecyclePayload?.type ?? '').trim().toLowerCase();
            const lifecycleSummary = formatLifecycleSummary(lifecyclePayload, context.fallbackTimestamp) ?? String(context.payloadRaw ?? '').trim();

            if (lifecycleSummary === '') {
                return;
            }

            const lifecycleEntry = {
                key: `${context.key}:lifecycle:${index}`,
                sequence: context.sequence,
                eventType: context.eventType,
                kind: 'lifecycle',
                displayFormat: 'text',
                text: lifecycleSummary,
                timestamp,
                timestampLabel,
            };

            if (lifecycleType === 'heartbeat') {
                heartbeatEntries.push(lifecycleEntry);
            } else {
                timeline.push(lifecycleEntry);
            }

            return;
        }

        const parsedPayload = parseJson(normalizedPayload);
        const contentSnapshot = extractContentSnapshotFromPayload(parsedPayload);
        if (contentSnapshot !== null) {
            timeline.push({
                key: `${context.key}:content:${index}`,
                sequence: context.sequence,
                eventType: context.eventType,
                kind: 'stdout',
                displayFormat: 'text',
                text: contentSnapshot,
                timestamp,
                timestampLabel,
            });
            return;
        }

        const envelopeSummary = formatStructuredEnvelopeSummary(parsedPayload);
        if (envelopeSummary !== null) {
            timeline.push({
                key: `${context.key}:envelope-summary:${index}`,
                sequence: context.sequence,
                eventType: context.eventType,
                kind: inferKindFromSummaryText(envelopeSummary),
                displayFormat: 'text',
                text: envelopeSummary,
                timestamp,
                timestampLabel,
            });
            return;
        }

        const { parsedValues } = extractJsonObjects(normalizedPayload);
        if (parsedValues.length > 1) {
            const summaries = parsedValues
                .map((p) => formatStructuredEnvelopeSummary(p) ?? extractContentSnapshotFromPayload(p))
                .filter((s) => s !== null && s !== '');
            if (summaries.length > 0) {
                const joined = summaries.join('\n');
                timeline.push({
                    key: `${context.key}:multi:${index}`,
                    sequence: context.sequence,
                    eventType: context.eventType,
                    kind: inferKindFromSummaryText(summaries[0]),
                    displayFormat: 'text',
                    text: joined,
                    timestamp,
                    timestampLabel,
                });
            }
            return;
        }

        const commandExecution = extractCommandExecutionFromEnvelope(parsedPayload);
        if (commandExecution) {
            const issueFromCommandOutput = extractMcpConnectionIssue(commandExecution.output);
            if (issueFromCommandOutput) {
                pushOrUpdateIssue(issuesByKey, issueFromCommandOutput, context.sequence, timestamp);
            }

            timeline.push({
                key: `${context.key}:command:${index}`,
                sequence: context.sequence,
                eventType: context.eventType,
                kind: 'command',
                displayFormat: 'text',
                command: commandExecution,
                text: commandExecution.command !== '' ? commandExecution.command : 'Executed command',
                timestamp,
                timestampLabel,
            });

            return;
        }

        const envelopeText = extractEnvelopeText(parsedPayload);
        if (envelopeText !== '') {
            const markdown = looksLikeMarkdown(envelopeText);
            timeline.push({
                key: `${context.key}:envelope:${index}`,
                sequence: context.sequence,
                eventType: context.eventType,
                kind: context.eventType === 'stderr' ? 'stderr' : 'stdout',
                displayFormat: markdown ? 'markdown' : 'text',
                text: envelopeText,
                timestamp,
                timestampLabel,
            });

            return;
        }

        const codexMachineSummary = summarizeCodexMachineEvent(parsedPayload);
        if (codexMachineSummary !== '') {
            timeline.push({
                key: `${context.key}:machine:${index}`,
                sequence: context.sequence,
                eventType: context.eventType,
                kind: 'structured',
                displayFormat: 'text',
                text: codexMachineSummary,
                timestamp,
                timestampLabel,
            });

            return;
        }

        if (parsedPayload !== null) {
            const summary = summarizeUnknownJsonPayload(parsedPayload);
            if (summary !== null) {
                timeline.push({
                    key: `${context.key}:json:${index}`,
                    sequence: context.sequence,
                    eventType: context.eventType,
                    kind: context.eventType === 'stderr' ? 'stderr' : 'structured',
                    displayFormat: 'text',
                    text: summary,
                    timestamp,
                    timestampLabel,
                });
            }
            return;
        }

        const readExcerptSummary = summarizeReadExcerptPayload(normalizedPayload, context.eventType);
        if (readExcerptSummary !== null) {
            timeline.push({
                key: `${context.key}:excerpt:${index}`,
                sequence: context.sequence,
                eventType: context.eventType,
                kind: 'structured',
                displayFormat: 'text',
                text: readExcerptSummary,
                timestamp,
                timestampLabel,
            });

            return;
        }

        const sequentialJson = parseSequentialJsonPayload(normalizedPayload);
        if (sequentialJson !== null) {
            const { parsedValues } = extractJsonObjects(normalizedPayload);
            const summaries = parsedValues
                .map((p) => formatStructuredEnvelopeSummary(p) ?? extractContentSnapshotFromPayload(p) ?? summarizeUnknownJsonPayload(p))
                .filter((s) => s !== null);
            if (summaries.length > 0) {
                const text = summaries.length === 1
                    ? summaries[0]
                    : `${summaries.length} events: ${summaries.slice(0, 3).join('; ')}${summaries.length > 3 ? '…' : ''}`;
                timeline.push({
                    key: `${context.key}:json-sequence:${index}`,
                    sequence: context.sequence,
                    eventType: context.eventType,
                    kind: context.eventType === 'stderr' ? 'stderr' : inferKindFromSummaryText(summaries[0]),
                    displayFormat: 'text',
                    text,
                    timestamp,
                    timestampLabel,
                });
            }
            return;
        }

        const text = String(stripLineNumberPrefixes(normalizedPayload) ?? '').trim();
        if (text === '') {
            return;
        }

        if (NOISE_PAYLOAD_MARKERS.test(text)) {
            return;
        }

        if (text.startsWith('{') || text.startsWith('[')) {
            const { parsedValues } = extractJsonObjects(text);
            const summaries = parsedValues
                .map((p) => formatStructuredEnvelopeSummary(p) ?? extractContentSnapshotFromPayload(p) ?? summarizeUnknownJsonPayload(p))
                .filter((s) => s !== null && s !== '');
            if (summaries.length > 0) {
                timeline.push({
                    key: `${context.key}:json-extract:${index}`,
                    sequence: context.sequence,
                    eventType: context.eventType,
                    kind: inferKindFromSummaryText(summaries[0]),
                    displayFormat: 'text',
                    text: summaries.join('\n'),
                    timestamp,
                    timestampLabel,
                });
            }
            return;
        }

        const markdown = looksLikeMarkdown(text);

        timeline.push({
            key: `${context.key}:text:${index}`,
            sequence: context.sequence,
            eventType: context.eventType,
            kind: context.eventType === 'stderr' ? 'stderr' : 'stdout',
            displayFormat: markdown ? 'markdown' : 'text',
            text,
            timestamp,
            timestampLabel,
        });
    });

    const collapsedTimeline = collapseConsecutiveTimelineEntries(
        timeline.sort((a, b) => Number(a.sequence ?? 0) - Number(b.sequence ?? 0))
    );
    const collapsedHeartbeat = collapseConsecutiveTimelineEntries(
        heartbeatEntries.sort((a, b) => Number(a.sequence ?? 0) - Number(b.sequence ?? 0))
    );

    const issues = Array.from(issuesByKey.values())
        .filter((issue) => String(issue.title ?? '').trim() !== '' && String(issue.detail ?? '').trim() !== '')
        .sort((a, b) => Number(b.latestSequence ?? 0) - Number(a.latestSequence ?? 0))
        .map((issue) => ({
            key: issue.key,
            code: issue.code,
            title: issue.title,
            detail: issue.detail,
            suggestedAction: issue.suggestedAction,
            endpoint: issue.endpoint,
            count: Math.max(1, Number(issue.count ?? 1)),
            latestSequence: Number(issue.latestSequence ?? 0),
            latestTimestamp: issue.latestTimestamp ?? null,
        }));

    return {
        timelineEntries: collapsedTimeline,
        heartbeatEntries: collapsedHeartbeat,
        heartbeatSummary: collapsedHeartbeat.length > 0
            ? {
                count: collapsedHeartbeat.reduce((carry, entry) => carry + Number(entry.repeatCount ?? 1), 0),
                latestTimestamp: collapsedHeartbeat[collapsedHeartbeat.length - 1]?.timestamp ?? null,
                latestTimestampLabel: collapsedHeartbeat[collapsedHeartbeat.length - 1]?.timestampLabel ?? '',
            }
            : null,
        issues,
    };
};
