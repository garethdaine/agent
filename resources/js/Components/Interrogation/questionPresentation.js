const normalizeSpace = (value) => value.replace(/\s+/g, ' ').trim();
const normalizeLineEndings = (value) => value.replace(/\r\n/g, '\n').replace(/\r/g, '\n');

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

export const displayQuestionText = (questionText, options = []) => {
    if (typeof questionText !== 'string') {
        return '';
    }

    let text = questionText;

    if (options.length > 0) {
        const optionSectionIndex = text.search(/\n\s*options?\s*:?\s*\n?/i);
        if (optionSectionIndex >= 0) {
            text = text.slice(0, optionSectionIndex);
        }

        const specificallyIndex = text.search(/\bSpecifically:\s*\([A-Z]\)/i);
        if (specificallyIndex >= 0) {
            text = text.slice(0, specificallyIndex);
        }
    }

    return normalizeSpace(text);
};

export const displayQuestionMarkdown = (questionText, options = []) => {
    if (typeof questionText !== 'string') {
        return '';
    }

    let text = normalizeLineEndings(questionText);

    if (options.length > 0) {
        const optionSectionIndex = text.search(/\n\s*options?\s*:?\s*\n?/i);
        if (optionSectionIndex >= 0) {
            text = text.slice(0, optionSectionIndex);
        }

        const specificallyIndex = text.search(/\bSpecifically:\s*\([A-Z]\)/i);
        if (specificallyIndex >= 0) {
            text = text.slice(0, specificallyIndex);
        }

        const inlineOptionsAfterQuestion = text.search(/\?\s+(?:\*\*)?[A-Z]\s*[—:-]/);
        if (inlineOptionsAfterQuestion >= 0) {
            text = text.slice(0, inlineOptionsAfterQuestion + 1);
        }

        const listStyleOptionStart = text.search(/\n\s*(?:\*\*)?[A-Z]\s*[—:-]/);
        if (listStyleOptionStart >= 0) {
            text = text.slice(0, listStyleOptionStart);
        }
    }

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

export const renderMarkdownToHtml = (markdownText) => {
    if (typeof markdownText !== 'string' || markdownText.trim() === '') {
        return '';
    }

    const escaped = escapeHtml(normalizeLineEndings(markdownText).trim());
    const blocks = escaped
        .split(/\n{2,}/)
        .map((block) => block.trim())
        .filter((block) => block !== '');

    return blocks.map((block) => renderMarkdownBlock(block)).join('');
};

const renderMarkdownBlock = (block) => {
    const lines = block.split('\n').map((line) => line.trim()).filter((line) => line !== '');

    if (lines.length > 0 && lines.every((line) => /^[-*]\s+/.test(line))) {
        const items = lines
            .map((line) => line.replace(/^[-*]\s+/, ''))
            .map((line) => `<li>${renderInlineMarkdown(line)}</li>`)
            .join('');

        return `<ul>${items}</ul>`;
    }

    if (lines.length > 0 && lines.every((line) => /^\d+\.\s+/.test(line))) {
        const items = lines
            .map((line) => line.replace(/^\d+\.\s+/, ''))
            .map((line) => `<li>${renderInlineMarkdown(line)}</li>`)
            .join('');

        return `<ol>${items}</ol>`;
    }

    return `<p>${renderInlineMarkdown(block).replace(/\n/g, '<br>')}</p>`;
};

const renderInlineMarkdown = (text) => {
    const codeTokens = [];
    let result = text.replace(/`([^`\n]+)`/g, (_, code) => {
        const token = `__CODE_TOKEN_${codeTokens.length}__`;
        codeTokens.push(`<code>${code}</code>`);
        return token;
    });

    result = result.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    result = result.replace(/\*([^*\n]+)\*/g, '<em>$1</em>');
    result = result.replace(/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');

    for (let index = 0; index < codeTokens.length; index += 1) {
        result = result.replace(`__CODE_TOKEN_${index}__`, codeTokens[index]);
    }

    return result;
};

const escapeHtml = (value) => value
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll('\'', '&#39;');

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
