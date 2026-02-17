const normalizeSpace = (value) => value.replace(/\s+/g, ' ').trim();
const normalizeLineEndings = (value) => value.replace(/\r\n/g, '\n').replace(/\r/g, '\n');

export const normalizeMarkdownContent = (value) => {
    if (typeof value !== 'string') {
        return '';
    }

    let normalized = value;

    // Some runner responses contain JSON-escaped newlines/tabs in string payloads.
    if (normalized.includes('\\n')) {
        normalized = normalized.replaceAll('\\n', '\n');
    }
    if (normalized.includes('\\t')) {
        normalized = normalized.replaceAll('\\t', '\t');
    }
    if (normalized.includes('\\"')) {
        normalized = normalized.replaceAll('\\"', '"');
    }

    return normalizeLineEndings(normalized).trim();
};

export const normalizePrivateNotesContent = (value) => {
    const normalized = normalizeMarkdownContent(value);
    if (normalized === '') {
        return '';
    }

    const hasMarkdownStructure = /(^|\n)\s*(#{1,6}\s+|[-*]\s+|\d+\.\s+|>\s+|\|.+\|)/m.test(normalized);
    if (hasMarkdownStructure) {
        return normalized;
    }

    const numberedMarkers = normalized.match(/\(\d+\)\s+/g) ?? [];
    if (numberedMarkers.length < 2) {
        return normalized;
    }

    let converted = normalized.replace(/\s+\((\d+)\)\s+/g, '\n\n$1. ');
    converted = converted.replace(/:\n\n1\.\s/, ':\n\n1. ');

    return converted.trim();
};

export const isCompletionQuestionPayload = (payload) => {
    if (!payload || typeof payload !== 'object') {
        return false;
    }

    const explicitComplete = payload.is_complete;
    if (explicitComplete === true || explicitComplete === 'true' || explicitComplete === 1 || explicitComplete === '1') {
        return true;
    }

    const progressEstimate = Number(payload.progress_estimate ?? 0);
    if (Number.isFinite(progressEstimate) && progressEstimate >= 100) {
        return true;
    }

    const questionText = String(payload.question_text ?? '').trim().toLowerCase();
    if (questionText === '') {
        return false;
    }

    return /\b(?:requirements\s+)?interrogation\s+is\s+now\s+complete\b/.test(questionText)
        || /\binterrogation\s+completed\b/.test(questionText);
};

export const isOperationalMetaQuestionPayload = (payload) => {
    if (!payload || typeof payload !== 'object') {
        return false;
    }

    const questionText = String(payload.question_text ?? '').trim();
    if (questionText === '') {
        return false;
    }

    const lower = questionText.toLowerCase();
    if (lower.includes('in this workspace/session state')) {
        return true;
    }

    const asksForVerbatimReplay = /\b(?:paste|quote|repeat|restate|provide|copy)\b/i.test(questionText)
        && /\b(?:exact|full\s+text|verbatim|word[- ]for[- ]word)\b/i.test(questionText)
        && /\b(?:latest|previous|resumed|unanswered|question\s+text|interrogation\s+question|answer\s+text|that\s+question|user'?s\s+answer)\b/i.test(questionText);
    if (asksForVerbatimReplay) {
        return true;
    }

    const asksForPriorQaReplay = /\b(?:what\s+is|provide|paste|quote|repeat|restate|copy)\b/i.test(questionText)
        && /\b(?:answer\s+(?:content|text)|question\s+text)\b/i.test(questionText)
        && /\b(?:that\s+question|latest|previous|resumed|unanswered)\b/i.test(questionText);
    if (asksForPriorQaReplay) {
        return true;
    }

    const operationalVerb = /\b(resum(?:e|ed|ing)|continu(?:e|ed|ing)|locat(?:e|ed|ing)|retriev(?:e|ed|ing)|load(?:ed|ing)?|search(?:ed|ing)?|inspect(?:ed|ing)?|analyz(?:e|ed|ing)|processing)\b/i.test(questionText);
    const operationalContext = /\b(workspace|session|state|thread|phase|saved|latest|runner output|interrogation)\b/i.test(questionText);
    const isQuestion = questionText.includes('?');

    return operationalVerb && operationalContext && !isQuestion;
};

export const isAnswerableQuestionEvent = (event) => {
    return event?.event_type === 'question'
        && !isCompletionQuestionPayload(event?.payload)
        && !isOperationalMetaQuestionPayload(event?.payload);
};

export const normalizeOptionLabel = (value) => {
    if (typeof value !== 'string') {
        return '';
    }

    let label = value.trim();
    label = label.replace(/^[-*]\s*/, '');
    label = label.replace(/^[A-Z]\s*[:.)-]\s*/i, '');

    return normalizeSpace(label);
};

export const choiceOptionsFromQuestion = (question) => {
    const raw = Array.isArray(question?.options) ? question.options : [];
    const normalized = raw
        .map((option) => normalizeOptionLabel(option))
        .filter((option) => option !== '');

    return Array.from(new Set(normalized));
};

export const shouldAllowMultipleChoices = (question) => {
    const text = typeof question?.question_text === 'string' ? question.question_text : '';
    const answerType = typeof question?.answer_type === 'string' ? question.answer_type : '';
    const options = choiceOptionsFromQuestion(question);

    if (question?.allow_multiple === true || answerType.toLowerCase() === 'multi_choice') {
        return true;
    }

    if (options.length < 2) {
        return false;
    }

    return /(select|choose).*(multiple|one or more|all that apply|up to)/i.test(text)
        || /multi[-\s]?select/i.test(text)
        || /check all that apply/i.test(text);
};

export const normalizeQuestionCategory = (value) => {
    if (typeof value !== 'string') {
        return '';
    }

    const normalized = value
        .trim()
        .toLowerCase()
        .replaceAll('_', '-')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');

    return normalized;
};

export const displayQuestionText = (questionText, options = []) => {
    if (typeof questionText !== 'string') {
        return '';
    }

    const text = stripEmbeddedOptionSections(questionText, options);

    return normalizeSpace(text);
};

export const displayQuestionMarkdown = (questionText, options = []) => {
    if (typeof questionText !== 'string') {
        return '';
    }

    const text = stripEmbeddedOptionSections(questionText, options);

    return text.trim();
};

export const shortQuestionText = (questionText, max = 180) => {
    const text = displayQuestionText(questionText);

    if (text.length <= max) {
        return text;
    }

    return `${text.slice(0, Math.max(0, max - 1)).trimEnd()}...`;
};

export const formatReasoning = (reasoningText) => {
    if (typeof reasoningText !== 'string' || reasoningText.trim() === '') {
        return {
            summary: '',
            bullets: [],
            paragraphs: [],
            fullText: '',
        };
    }

    const cleanedReasoning = stripOperationalReasoning(reasoningText);
    const fullText = normalizeLineEndings(cleanedReasoning).trim();

    if (fullText === '') {
        return {
            summary: '',
            bullets: [],
            paragraphs: [],
            fullText: '',
        };
    }

    const paragraphs = fullText
        .split(/\n\s*\n/)
        .map((part) => normalizeSpace(part))
        .filter((part) => part !== '');

    const normalized = normalizeSpace(fullText);

    const sentenceMatch = normalized.match(/(.{20,240}?[.?!])(\s|$)/);
    const summary = sentenceMatch?.[1]
        ? sentenceMatch[1]
        : (normalized.length <= 220 ? normalized : `${normalized.slice(0, 220).trimEnd()}...`);

    const bullets = extractReasoningBullets(fullText);

    return {
        summary,
        bullets,
        paragraphs: paragraphs.slice(0, 4),
        fullText,
    };
};

const stripOperationalReasoning = (reasoningText) => {
    const lines = normalizeLineEndings(reasoningText)
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line !== '');

    const filtered = lines.filter((line) => {
        const lower = line.toLowerCase();

        if (lower.includes('id mismatch')) {
            return false;
        }

        if (lower.includes('edited-answer notification')) {
            return false;
        }

        return !/^(re-issuing|re-presenting|awaiting|waiting for|holding at|holding|retrying)\b/i.test(line);
    });

    return (filtered.length > 0 ? filtered : lines).join('\n');
};

const stripEmbeddedOptionSections = (questionText, options = []) => {
    let text = normalizeLineEndings(questionText);

    text = extractKeyQuestionSegment(text);

    if (options.length === 0) {
        return text;
    }

    const candidates = [
        text.search(/\n\s*options?\s*:?\s*\n?/i),
        text.search(/\bSpecifically:\s*\([A-Z]\)/i),
        text.search(/\n\s*(?:\*\*)?(?:Option\s+)?[A-Z](?:\s*[).:—-]\s+|\s+—\s+)/i),
        text.search(/\?\s+(?:\*\*)?(?:Option\s+)?[A-Z]\s*[—:-]/i),
        text.search(/\n\s*(?:\*\*)?\([A-Z]\)\s+/i),
    ].filter((index) => index >= 0);

    if (candidates.length === 0) {
        return text;
    }

    const firstIndex = Math.min(...candidates);
    if (firstIndex <= 0) {
        return text;
    }

    return text.slice(0, firstIndex);
};

const extractKeyQuestionSegment = (questionText) => {
    const keyQuestionPatterns = [
        /\bthe\s+key\s+question\s*:\s*/i,
        /\bkey\s+question\s*:\s*/i,
        /\bcritical\s+design\s+question\s*:\s*/i,
    ];

    const matches = keyQuestionPatterns
        .map((pattern) => {
            const match = questionText.match(pattern);
            if (!match || typeof match.index !== 'number') {
                return null;
            }

            return {
                index: match.index,
                length: match[0].length,
            };
        })
        .filter((entry) => entry !== null);

    if (matches.length === 0) {
        return questionText;
    }

    const earliest = matches.reduce((best, current) => (current.index < best.index ? current : best), matches[0]);

    return questionText.slice(earliest.index + earliest.length).trimStart();
};

const extractReasoningBullets = (reasoningText) => {
    const lines = reasoningText.split('\n').map((line) => line.trim()).filter((line) => line !== '');
    const byLine = lines
        .map((line) => {
            const listMatch = line.match(/^[-*•]\s+(.+)/);
            if (listMatch) {
                return normalizeSpace(listMatch[1]);
            }

            const numberedMatch = line.match(/^(?:\(?\d+\)?[.)])\s+(.+)/);
            if (numberedMatch) {
                return normalizeSpace(numberedMatch[1]);
            }

            return '';
        })
        .filter((line) => line !== '');

    if (byLine.length >= 2) {
        return Array.from(new Set(byLine)).slice(0, 8);
    }

    const inlineNumbered = Array.from(reasoningText.matchAll(/\(\d+\)\s*([^;]+)(?=(?:;\s*\(\d+\)|$))/g))
        .map((match) => normalizeSpace(match[1] ?? ''))
        .filter((line) => line !== '');

    return Array.from(new Set(inlineNumbered)).slice(0, 8);
};
