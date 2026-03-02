<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    uiKey: {
        type: String,
        default: '',
    },
    shortText: {
        type: String,
        default: null,
    },
    longText: {
        type: String,
        default: null,
    },
    severity: {
        type: String,
        default: 'info',
    },
    learnMoreSlug: {
        type: String,
        default: null,
    },
    learnMoreHref: {
        type: String,
        default: null,
    },
    apiBasePath: {
        type: String,
        default: '/agent/api/v1/docs/fragments',
    },
    fetchTimeoutMs: {
        type: Number,
        default: 4000,
    },
});

const fetchedFragment = ref(null);
const isOpen = ref(false);
const openedByFocus = ref(false);
const rootRef = ref(null);

const normalizedInline = computed(() => normalizeFragment({
    ui_key: props.uiKey,
    short_text: props.shortText,
    long_text: props.longText,
    severity: props.severity,
    learn_more_slug: props.learnMoreSlug,
}));

const resolvedFragment = computed(() => fetchedFragment.value ?? normalizedInline.value);

const tooltipId = computed(() => {
    const key = String(resolvedFragment.value?.ui_key ?? props.uiKey ?? 'help')
        .replace(/[^a-zA-Z0-9_-]+/g, '-')
        .replace(/-{2,}/g, '-')
        .replace(/^-|-$/g, '')
        .toLowerCase();

    return `help-hint-${key || 'field'}`;
});

const triggerLabel = computed(() => {
    const key = String(resolvedFragment.value?.ui_key ?? props.uiKey ?? '').trim();

    return key !== '' ? `Show help for ${key}` : 'Show help';
});

const learnMoreUrl = computed(() => {
    const slug = String(resolvedFragment.value?.learn_more_slug ?? props.learnMoreSlug ?? '').trim();
    if (slug !== '') {
        return `/docs/${slug}`;
    }

    if (props.learnMoreHref && props.learnMoreHref.trim() !== '') {
        return props.learnMoreHref.trim();
    }

    return null;
});

const panelClass = computed(() => {
    const severity = resolvedFragment.value?.severity ?? 'info';

    const bySeverity = {
        info: 'border-slate-200 bg-white text-slate-800',
        warning: 'border-amber-200 bg-amber-50 text-amber-900',
        risk: 'border-rose-200 bg-rose-50 text-rose-900',
    };

    return bySeverity[severity] ?? bySeverity.info;
});

const canRender = computed(() => {
    return Boolean(resolvedFragment.value?.short_text);
});

onMounted(async () => {
    document.addEventListener('pointerdown', onPointerDownOutside);

    const key = String(props.uiKey ?? '').trim();
    if (key === '') {
        return;
    }

    const signal = createTimeoutSignal(props.fetchTimeoutMs);

    try {
        const response = await fetch(`${props.apiBasePath}/${encodeURIComponent(key)}`, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
            },
            credentials: 'same-origin',
            signal,
        });

        if (!response.ok) {
            return;
        }

        const payload = await response.json();
        const fragment = normalizeFragment(payload?.data ?? null);

        if (fragment === null) {
            return;
        }

        fetchedFragment.value = fragment;
    } catch {
        // Silent miss behavior: do not render hint on timeout/network/API failures.
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onPointerDownOutside);
});

function show() {
    if (canRender.value) {
        isOpen.value = true;
        openedByFocus.value = true;
    }
}

function hide() {
    isOpen.value = false;
    openedByFocus.value = false;
}

function onBlur(event) {
    const next = event?.relatedTarget;

    if (next && rootRef.value && rootRef.value.contains(next)) {
        return;
    }

    if (openedByFocus.value) {
        hide();
    }
}

function onPointerDownOutside(event) {
    if (!isOpen.value) {
        return;
    }

    const target = event?.target;
    if (target && rootRef.value && rootRef.value.contains(target)) {
        return;
    }

    hide();
}

function toggle() {
    if (!canRender.value) {
        return;
    }

    if (openedByFocus.value) {
        openedByFocus.value = false;
        return;
    }

    isOpen.value = !isOpen.value;
}

function normalizeFragment(fragment) {
    if (!fragment || typeof fragment !== 'object') {
        return null;
    }

    const shortText = typeof fragment.short_text === 'string'
        ? fragment.short_text.trim()
        : '';

    if (shortText === '') {
        return null;
    }

    const severity = typeof fragment.severity === 'string'
        ? fragment.severity
        : 'info';

    const allowedSeverity = ['info', 'warning', 'risk'];

    return {
        ui_key: typeof fragment.ui_key === 'string' ? fragment.ui_key : '',
        short_text: shortText,
        long_text: typeof fragment.long_text === 'string' && fragment.long_text.trim() !== ''
            ? fragment.long_text.trim()
            : null,
        severity: allowedSeverity.includes(severity) ? severity : 'info',
        learn_more_slug: typeof fragment.learn_more_slug === 'string' && fragment.learn_more_slug.trim() !== ''
            ? fragment.learn_more_slug.trim()
            : null,
    };
}

function createTimeoutSignal(timeoutMs) {
    if (typeof AbortController === 'undefined') {
        return undefined;
    }

    const controller = new AbortController();

    if (Number.isFinite(timeoutMs) && timeoutMs > 0) {
        window.setTimeout(() => {
            controller.abort();
        }, timeoutMs);
    }

    return controller.signal;
}
</script>

<template>
    <span
        v-if="canRender"
        ref="rootRef"
        class="relative inline-flex items-center"
    >
        <button
            type="button"
            class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-slate-300 bg-white text-xs font-semibold text-slate-700 transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
            :aria-label="triggerLabel"
            :aria-controls="tooltipId"
            :aria-expanded="isOpen ? 'true' : 'false'"
            @focus="show"
            @blur="onBlur"
            @click="toggle"
            @keydown.esc.prevent="hide"
        >
            ?
        </button>

        <div
            v-if="isOpen"
            :id="tooltipId"
            role="tooltip"
            class="absolute left-1/2 top-[calc(100%+0.5rem)] z-50 w-72 -translate-x-1/2 rounded-md border p-3 text-xs shadow-lg"
            :class="panelClass"
        >
            <p class="font-medium leading-4">
                {{ resolvedFragment.short_text }}
            </p>

            <p v-if="resolvedFragment.long_text" class="mt-2 leading-4 opacity-90">
                {{ resolvedFragment.long_text }}
            </p>

            <Link
                v-if="learnMoreUrl && learnMoreUrl.startsWith('/')"
                :href="learnMoreUrl"
                class="mt-2 inline-flex text-xs font-semibold underline"
                @click="hide"
            >
                Learn more
            </Link>

            <a
                v-else-if="learnMoreUrl"
                :href="learnMoreUrl"
                class="mt-2 inline-flex text-xs font-semibold underline"
                target="_blank"
                rel="noopener noreferrer"
                @click="hide"
            >
                Learn more
            </a>
        </div>
    </span>
</template>
