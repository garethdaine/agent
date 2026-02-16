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

export const isAnswerableQuestionEvent = (event) => {
    return event?.event_type === 'question' && !isCompletionQuestionPayload(event?.payload);
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

export const renderMarkdownToHtml = (markdownText) => {
    if (typeof markdownText !== 'string' || markdownText.trim() === '') {
        return '';
    }

    const normalized = normalizeMarkdownContent(markdownText);
    const fenced = [];
    const markdownWithFenceTokens = extractFencedCodeBlocks(normalized, fenced);
    const escaped = escapeHtml(markdownWithFenceTokens);
    const blocks = mergeAdjacentListBlocks(escaped
        .split(/\n{2,}/)
        .map((block) => block.trim())
        .filter((block) => block !== ''));

    return blocks.map((block) => renderMarkdownBlock(block, fenced)).join('');
};

const renderMarkdownBlock = (block, fencedBlocks = []) => {
    const fenceTokenMatch = block.match(/^__FENCE_BLOCK_(\d+)__$/);
    if (fenceTokenMatch) {
        const index = Number.parseInt(fenceTokenMatch[1] ?? '-1', 10);
        const fenced = Number.isInteger(index) ? fencedBlocks[index] : null;
        if (fenced && typeof fenced.code === 'string') {
            return renderCodeBlock(escapeHtml(fenced.code), fenced.language);
        }
    }

    const lines = block.split('\n').map((line) => line.trim()).filter((line) => line !== '');

    if (isMarkdownTable(lines)) {
        const headers = parseTableRow(lines[0]).map((cell) => sanitizeTableCell(cell));
        const bodyLines = lines.slice(2).filter((line) => line !== '');
        const rows = bodyLines
            .map((line) => normalizeTableRow(parseTableRow(line), headers.length))
            .filter((cells) => cells.some((cell) => cell !== ''));

        const headHtml = headers.map((cell) => `<th class="md-table-th">${renderInlineMarkdown(cell)}</th>`).join('');
        const bodyHtml = rows.map((cells) => {
            const cellHtml = cells.map((cell) => `<td class="md-table-td">${renderInlineMarkdown(cell)}</td>`).join('');
            return `<tr class="md-table-tr">${cellHtml}</tr>`;
        }).join('');

        return `<table class="md-table"><thead><tr>${headHtml}</tr></thead><tbody>${bodyHtml}</tbody></table>`;
    }

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

    const fragments = [];
    let paragraphBuffer = [];

    const flushParagraph = () => {
        if (paragraphBuffer.length === 0) {
            return;
        }

        fragments.push(`<p>${renderInlineMarkdown(paragraphBuffer.join('\n')).replace(/\n/g, '<br>')}</p>`);
        paragraphBuffer = [];
    };

    for (const line of lines) {
        const labeledCodeMatch = line.match(/^([A-Za-z][A-Za-z0-9 _/-]{0,40})\s*:\s*`([\s\S]+)`$/);
        if (labeledCodeMatch && labeledCodeMatch[2].length > 70) {
            flushParagraph();
            fragments.push(`<p><strong>${renderInlineMarkdown(labeledCodeMatch[1])}:</strong></p>`);
            fragments.push(renderCodeBlock(labeledCodeMatch[2], inferCodeLanguage(labeledCodeMatch[2])));
            continue;
        }

        const headingMatch = line.match(/^(#{1,6})\s+(.+)$/);
        if (headingMatch) {
            flushParagraph();
            const level = headingMatch[1].length;
            fragments.push(`<h${level}>${renderInlineMarkdown(headingMatch[2])}</h${level}>`);
            continue;
        }

        paragraphBuffer.push(line);
    }

    flushParagraph();

    if (fragments.length > 0) {
        return fragments.join('');
    }

    return `<p>${renderInlineMarkdown(block).replace(/\n/g, '<br>')}</p>`;
};

const isMarkdownTable = (lines) => {
    if (lines.length < 2) {
        return false;
    }

    if (!lines[0].includes('|')) {
        return false;
    }

    const separator = lines[1].replace(/\s/g, '');

    return /^\|?[:\-|]+\|?$/.test(separator) && separator.includes('-');
};

const parseTableRow = (row) => {
    let working = row.trim();

    if (working.startsWith('|')) {
        working = working.slice(1);
    }
    if (working.endsWith('|')) {
        working = working.slice(0, -1);
    }

    return working
        .split('|')
        .map((cell) => cell.trim())
        .filter((cell, index, array) => !(cell === '' && array.length === 1));
};

const extractFencedCodeBlocks = (markdown, fencedBlocks) => {
    if (typeof markdown !== 'string' || markdown === '') {
        return '';
    }

    return markdown.replace(/```([a-z0-9_-]+)?\n([\s\S]*?)```/gi, (_, language, code) => {
        const index = fencedBlocks.length;
        fencedBlocks.push({
            language: String(language ?? '').trim().toLowerCase(),
            code: String(code ?? ''),
        });

        return `\n\n__FENCE_BLOCK_${index}__\n\n`;
    });
};

const sanitizeTableCell = (cell) => {
    const trimmed = String(cell ?? '').trim();

    if (/^["']+,?$/.test(trimmed)) {
        return '';
    }

    return trimmed.replace(/,$/, '').trim();
};

const normalizeTableRow = (cells, width) => {
    const normalized = (Array.isArray(cells) ? cells : []).map((cell) => sanitizeTableCell(cell));
    const constrained = normalized.slice(0, Math.max(0, width));

    while (constrained.length < width) {
        constrained.push('');
    }

    return constrained;
};

const detectListBlockType = (block) => {
    const lines = block
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line !== '');

    if (lines.length === 0) {
        return null;
    }

    if (lines.every((line) => /^[-*]\s+/.test(line))) {
        return 'ul';
    }

    if (lines.every((line) => /^\d+\.\s+/.test(line))) {
        return 'ol';
    }

    return null;
};

const mergeAdjacentListBlocks = (blocks) => {
    if (!Array.isArray(blocks) || blocks.length === 0) {
        return [];
    }

    const merged = [];

    for (const block of blocks) {
        if (merged.length === 0) {
            merged.push(block);
            continue;
        }

        const currentType = detectListBlockType(block);
        const previousType = detectListBlockType(merged[merged.length - 1]);

        if (currentType !== null && currentType === previousType) {
            merged[merged.length - 1] = `${merged[merged.length - 1]}\n${block}`;
            continue;
        }

        merged.push(block);
    }

    return merged;
};

const inferCodeLanguage = (codeEscaped) => {
    const text = String(codeEscaped ?? '').trim();

    if (text.startsWith('{') || text.startsWith('[') || text.startsWith('&quot;{') || text.startsWith('&quot;[')) {
        return 'json';
    }

    return '';
};

const renderCodeBlock = (codeEscaped, language = '') => {
    const normalizedLanguage = String(language ?? '').trim().toLowerCase();
    const normalizedCode = normalizedLanguage === 'json'
        ? formatEscapedJson(codeEscaped)
        : codeEscaped;
    const highlighted = normalizedLanguage === 'json'
        ? highlightEscapedJson(normalizedCode)
        : normalizedCode;
    const languageClass = normalizedLanguage !== '' ? ` language-${normalizedLanguage}` : '';

    return `<pre class="md-code-block"><code class="md-code${languageClass}">${highlighted}</code></pre>`;
};

const formatEscapedJson = (escaped) => {
    const raw = decodeHtml(escaped).trim();
    if (raw === '') {
        return escaped;
    }

    try {
        const parsed = JSON.parse(raw);
        return escapeHtml(JSON.stringify(parsed, null, 2));
    } catch {
        return escaped;
    }
};

const highlightEscapedJson = (escaped) => {
    let output = escaped;

    output = output.replace(/(&quot;[^&]*?&quot;)(\s*:)/g, '<span class="md-json-key">$1</span>$2');
    output = output.replace(/(:\s*)(&quot;[^&]*?&quot;)/g, '$1<span class="md-json-string">$2</span>');
    output = output.replace(/(:\s*)(-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?)/g, '$1<span class="md-json-number">$2</span>');
    output = output.replace(/(:\s*)(true|false|null)\b/g, '$1<span class="md-json-literal">$2</span>');

    return output;
};

const decodeHtml = (value) => String(value ?? '')
    .replaceAll('&quot;', '"')
    .replaceAll('&#39;', '\'')
    .replaceAll('&lt;', '<')
    .replaceAll('&gt;', '>')
    .replaceAll('&amp;', '&');

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
