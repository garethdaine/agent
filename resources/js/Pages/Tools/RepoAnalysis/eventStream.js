export const mergeEventsInSequence = (existingEvents = [], incomingEvents = [], limit = 1200) => {
    const merged = new Map();

    [...existingEvents, ...incomingEvents].forEach((event) => {
        const sequence = Number(event?.sequence ?? 0);
        if (!Number.isInteger(sequence) || sequence <= 0) {
            return;
        }

        merged.set(sequence, {
            ...event,
            sequence,
        });
    });

    return Array.from(merged.values())
        .sort((left, right) => left.sequence - right.sequence)
        .slice(-Math.max(1, Number(limit) || 1));
};

export const nextEventCursor = (events = []) => {
    if (!Array.isArray(events) || events.length === 0) {
        return 0;
    }

    return events.reduce((maxSequence, event) => {
        const sequence = Number(event?.sequence ?? 0);
        return Number.isInteger(sequence) && sequence > maxSequence ? sequence : maxSequence;
    }, 0);
};
