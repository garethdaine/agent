export const formatInterrogationError = (input, options = {}) => {
    const allowDetails = options.allowDetails !== false;
    const maxSummaryLength = Number(options.maxSummaryLength || 220);

    const raw = String(input ?? '').trim();
    if (raw === '') {
        return { summary: '', details: '' };
    }

    const compact = raw.replace(/\s+/g, ' ').trim();

    if (/exceeded the timeout of|timed out|timeout/i.test(compact)) {
        return {
            summary: 'This step timed out while waiting for the AI runner. Please retry.',
            details: allowDetails ? compact : '',
        };
    }

    if (compact.startsWith('The process "') || compact.includes('--system-prompt') || compact.includes('--json-schema')) {
        return {
            summary: 'The AI runner failed while executing this step. Please retry.',
            details: allowDetails ? compact : '',
        };
    }

    if (compact.length <= maxSummaryLength) {
        return { summary: compact, details: '' };
    }

    return {
        summary: `${compact.slice(0, maxSummaryLength)}...`,
        details: allowDetails ? compact : '',
    };
};
