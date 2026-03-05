<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import MarkdownRenderer from '@/Components/Markdown/MarkdownRenderer.vue';
import { BookOpen } from 'lucide-vue-next';
import HelpHint from '@/Components/HelpHint.vue';

const props = defineProps({
    entries: {
        type: Array,
        default: () => [],
    },
    activeEntry: {
        type: Object,
        default: null,
    },
    filters: {
        type: Object,
        default: () => ({
            q: '',
            domain: '',
            section: '',
        }),
    },
});

const form = reactive({
    q: String(props.filters?.q ?? ''),
    domain: String(props.filters?.domain ?? ''),
    section: String(props.filters?.section ?? ''),
});

const docsContentRef = ref(null);
const liveSearchRef = ref(null);
const searchInputRef = ref(null);

const instantSearch = reactive({
    open: false,
    loading: false,
    error: '',
    results: [],
});

let searchDebounceTimer = null;
let activeSearchController = null;

const domains = computed(() => {
    return [...new Set(props.entries.map((entry) => String(entry.domain ?? '')).filter((value) => value !== ''))].sort();
});

const sections = computed(() => {
    return [...new Set(props.entries.map((entry) => String(entry.section ?? '')).filter((value) => value !== ''))].sort();
});

const visibleEntries = computed(() => {
    const q = form.q.trim().toLowerCase();

    return props.entries.filter((entry) => {
        const domainMatch = form.domain === '' || String(entry.domain ?? '') === form.domain;
        const sectionMatch = form.section === '' || String(entry.section ?? '') === form.section;

        if (!domainMatch || !sectionMatch) {
            return false;
        }

        if (q === '') {
            return true;
        }

        const haystack = [
            String(entry.title ?? ''),
            String(entry.summary ?? ''),
            String(entry.section ?? ''),
            String(entry.slug ?? ''),
            String(entry.domain ?? ''),
        ].join(' ').toLowerCase();

        return haystack.includes(q);
    });
});

const selectedEntry = computed(() => {
    const slug = String(props.activeEntry?.slug ?? '').trim();

    if (slug !== '') {
        const matching = visibleEntries.value.find((entry) => String(entry.slug ?? '') === slug);
        if (matching) {
            return {
                ...matching,
                ...props.activeEntry,
            };
        }

        return props.activeEntry;
    }

    return visibleEntries.value[0] ?? null;
});

const selectedSlug = computed(() => String(selectedEntry.value?.slug ?? ''));

const selectedBodyMarkdown = computed(() => {
    const value = selectedEntry.value?.body_markdown;
    return typeof value === 'string' ? value : '';
});

const selectedBodyHtml = computed(() => {
    const value = selectedEntry.value?.body_html;
    return typeof value === 'string' ? value : '';
});

const groupedEntries = computed(() => {
    const groups = new Map();

    for (const entry of visibleEntries.value) {
        const key = String(entry.section ?? 'general').trim() || 'general';
        if (!groups.has(key)) {
            groups.set(key, []);
        }

        groups.get(key).push(entry);
    }

    return [...groups.entries()].map(([section, items]) => ({
        section,
        label: sectionLabel(section),
        items,
    }));
});

const tocItems = computed(() => extractHeadings(selectedBodyMarkdown.value));

watch(
    () => [form.q, form.domain, form.section],
    ([query]) => {
        if (typeof window === 'undefined') {
            return;
        }

        if (searchDebounceTimer !== null) {
            window.clearTimeout(searchDebounceTimer);
            searchDebounceTimer = null;
        }

        const normalizedQuery = String(query ?? '').trim();
        if (normalizedQuery === '') {
            clearInstantSearch();
            return;
        }

        instantSearch.open = true;
        searchDebounceTimer = window.setTimeout(() => {
            fetchInstantSearch(normalizedQuery);
        }, 220);
    }
);

onMounted(() => {
    document.addEventListener('pointerdown', onPointerDownOutside);
    document.addEventListener('keydown', onGlobalKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onPointerDownOutside);
    document.removeEventListener('keydown', onGlobalKeydown);

    if (searchDebounceTimer !== null && typeof window !== 'undefined') {
        window.clearTimeout(searchDebounceTimer);
    }

    if (activeSearchController !== null) {
        activeSearchController.abort();
    }
});

function docsShowHref(slug) {
    return route('docs.show', {
        slug,
        q: form.q || undefined,
        domain: form.domain || undefined,
        section: form.section || undefined,
    });
}

function resolveResultHref(result) {
    const url = typeof result?.url === 'string' ? result.url.trim() : '';
    if (url !== '') {
        return url;
    }

    const slug = typeof result?.slug === 'string' ? result.slug.trim() : '';
    if (slug !== '') {
        return docsShowHref(slug);
    }

    return null;
}

function sectionLabel(value) {
    return String(value)
        .replace(/[._-]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

function normalizeHeadingText(value) {
    return String(value).replace(/\s+/g, ' ').trim().toLowerCase();
}

function extractHeadings(markdown) {
    if (typeof markdown !== 'string' || markdown.trim() === '') {
        return [];
    }

    const lines = markdown.split(/\r?\n/);
    const headings = [];
    let inCodeFence = false;

    for (const line of lines) {
        if (/^```/.test(line.trim())) {
            inCodeFence = !inCodeFence;
            continue;
        }

        if (inCodeFence) {
            continue;
        }

        const match = line.match(/^(#{2,3})\s+(.+)$/);
        if (!match) {
            continue;
        }

        const level = match[1].length;
        const text = match[2]
            .replace(/`/g, '')
            .replace(/\[(.*?)\]\(.*?\)/g, '$1')
            .replace(/[*_~]+/g, '')
            .trim();

        if (text !== '') {
            headings.push({
                level,
                text,
                key: `${level}-${text}-${headings.length}`,
            });
        }
    }

    return headings;
}

function scrollToHeading(text) {
    if (!docsContentRef.value) {
        return;
    }

    const target = [...docsContentRef.value.querySelectorAll('h1, h2, h3, h4')]
        .find((heading) => normalizeHeadingText(heading.textContent ?? '') === normalizeHeadingText(text));

    if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function clearInstantSearch() {
    instantSearch.open = false;
    instantSearch.loading = false;
    instantSearch.error = '';
    instantSearch.results = [];

    if (activeSearchController !== null) {
        activeSearchController.abort();
        activeSearchController = null;
    }
}

async function fetchInstantSearch(query) {
    if (activeSearchController !== null) {
        activeSearchController.abort();
    }

    if (typeof AbortController !== 'undefined') {
        activeSearchController = new AbortController();
    } else {
        activeSearchController = null;
    }

    instantSearch.loading = true;
    instantSearch.error = '';

    const params = new URLSearchParams({
        q: query,
        limit: '12',
    });

    if (form.domain !== '') {
        params.set('domain', form.domain);
    }

    if (form.section !== '') {
        params.set('section', form.section);
    }

    try {
        const response = await fetch(`/agent/api/v1/docs/search?${params.toString()}`, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
            },
            credentials: 'same-origin',
            signal: activeSearchController?.signal,
        });

        if (!response.ok) {
            throw new Error(`Search request failed with status ${response.status}`);
        }

        const payload = await response.json();
        const remoteResults = Array.isArray(payload?.data) ? payload.data : [];
        instantSearch.results = remoteResults.length > 0 ? remoteResults : buildLocalSearchResults(query);
        instantSearch.open = true;
    } catch (error) {
        if (error?.name === 'AbortError') {
            return;
        }

        instantSearch.error = 'Search is temporarily unavailable.';
        instantSearch.results = [];
        instantSearch.open = true;
    } finally {
        instantSearch.loading = false;
    }
}

function buildLocalSearchResults(query) {
    const q = String(query ?? '').trim().toLowerCase();
    if (q === '') {
        return [];
    }

    return visibleEntries.value
        .map((entry) => {
            const title = String(entry.title ?? '');
            const summary = String(entry.summary ?? '');
            const section = String(entry.section ?? '');
            const domain = String(entry.domain ?? '');
            const slug = String(entry.slug ?? '').trim();

            const haystack = [title, summary, section, domain, slug].join(' ').toLowerCase();
            if (!haystack.includes(q)) {
                return null;
            }

            return {
                title,
                snippet: summary || `Open ${title}`,
                domain,
                section: section || null,
                slug: slug !== '' ? slug : null,
                url: slug !== '' ? docsShowHref(slug) : null,
                route_affinity: false,
                updated_at: null,
            };
        })
        .filter((item) => item !== null)
        .slice(0, 12);
}

function closeInstantSearch() {
    instantSearch.open = false;
}

function onPointerDownOutside(event) {
    if (!instantSearch.open || !liveSearchRef.value) {
        return;
    }

    if (liveSearchRef.value.contains(event.target)) {
        return;
    }

    closeInstantSearch();
}

function onGlobalKeydown(event) {
    if (event.key.toLowerCase() === 'k' && (event.ctrlKey || event.metaKey)) {
        event.preventDefault();
        searchInputRef.value?.focus();
        instantSearch.open = true;
        return;
    }

    if (event.key === 'Escape') {
        closeInstantSearch();
    }
}
</script>

<template>
    <AppLayout title="Docs">
        <Head title="Docs" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <BookOpen class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-semibold text-foreground truncate">Docs Center</h2>
                        <HelpHint
                            ui-key="docs.overview"
                            short-text="Browse documentation and operational guides."
                            learn-more-href="/docs/overview"
                        />
                    </div>
                </div>
                <span class="text-xs text-muted-foreground">Press `Ctrl/Cmd + K` to search</span>
            </div>
        </template>

        <div class="py-4">
            <div class="mx-auto max-w-[1700px] px-4 sm:px-6 lg:px-8">
                <div class="grid gap-6 lg:grid-cols-[280px_minmax(0,1fr)_260px]">
                    <aside class="hidden lg:block lg:sticky lg:top-20 lg:self-start">
                        <div class="max-h-[calc(100vh-8rem)] overflow-y-auto rounded-xl border border-border/70 bg-background/40 p-4">
                            <p class="mb-3 text-xs uppercase tracking-wide text-muted-foreground">Navigation</p>

                            <div v-if="groupedEntries.length === 0" class="rounded-md border border-border/60 bg-background/60 px-3 py-2 text-sm text-muted-foreground">
                                No docs found for these filters.
                            </div>

                            <nav v-else class="space-y-4">
                                <section
                                    v-for="group in groupedEntries"
                                    :key="group.section"
                                    class="space-y-1"
                                >
                                    <h3 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ group.label }}</h3>
                                    <Link
                                        v-for="entry in group.items"
                                        :key="entry.slug"
                                        :href="docsShowHref(entry.slug)"
                                        class="block rounded-md px-2 py-1.5 text-sm transition"
                                        :class="selectedSlug === entry.slug ? 'bg-primary/15 text-primary' : 'text-foreground hover:bg-accent/40'"
                                    >
                                        {{ entry.title }}
                                    </Link>
                                </section>
                            </nav>
                        </div>
                    </aside>

                    <main class="min-w-0 space-y-4">
                        <div
                            ref="liveSearchRef"
                            class="relative rounded-xl border border-border/70 bg-background/40 p-3"
                        >
                            <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_170px_170px]">
                                <div class="relative">
                                    <input
                                        ref="searchInputRef"
                                        v-model="form.q"
                                        type="text"
                                        autocomplete="off"
                                        placeholder="Search docs as you type..."
                                        class="w-full rounded-md border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/40"
                                        @focus="instantSearch.open = form.q.trim() !== ''"
                                    >
                                </div>

                                <select
                                    v-model="form.domain"
                                    class="rounded-md border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/40"
                                >
                                    <option value="">All domains</option>
                                    <option v-for="domain in domains" :key="domain" :value="domain">{{ domain }}</option>
                                </select>

                                <select
                                    v-model="form.section"
                                    class="rounded-md border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/40"
                                >
                                    <option value="">All sections</option>
                                    <option v-for="section in sections" :key="section" :value="section">{{ section }}</option>
                                </select>
                            </div>

                            <div
                                v-if="instantSearch.open"
                                class="absolute left-3 right-3 top-[calc(100%+0.5rem)] z-50 overflow-hidden rounded-xl border border-border bg-background shadow-xl"
                            >
                                <div class="border-b border-border/70 px-3 py-2 text-xs text-muted-foreground">
                                    Live Search Results
                                </div>

                                <div v-if="instantSearch.loading" class="px-3 py-3 text-sm text-muted-foreground">
                                    Searching...
                                </div>

                                <div v-else-if="instantSearch.error" class="px-3 py-3 text-sm text-rose-400">
                                    {{ instantSearch.error }}
                                </div>

                                <div v-else-if="instantSearch.results.length === 0" class="px-3 py-3 text-sm text-muted-foreground">
                                    No results for "{{ form.q }}".
                                </div>

                                <nav v-else class="max-h-80 overflow-y-auto py-1">
                                    <template v-for="result in instantSearch.results" :key="`${result.domain}:${result.title}:${result.updated_at}`">
                                        <Link
                                            v-if="resolveResultHref(result)"
                                            :href="resolveResultHref(result)"
                                            class="block px-3 py-2 hover:bg-accent/40"
                                            @click="closeInstantSearch"
                                        >
                                            <p class="text-sm font-medium text-foreground">{{ result.title }}</p>
                                            <p class="mt-0.5 line-clamp-2 text-xs text-muted-foreground">{{ result.snippet }}</p>
                                            <p class="mt-0.5 text-[10px] uppercase tracking-wide text-muted-foreground">
                                                {{ result.domain }}<span v-if="result.section"> · {{ result.section }}</span>
                                            </p>
                                        </Link>

                                        <div
                                            v-else
                                            class="px-3 py-2 opacity-60"
                                        >
                                            <p class="text-sm font-medium text-foreground">{{ result.title }}</p>
                                            <p class="mt-0.5 line-clamp-2 text-xs text-muted-foreground">{{ result.snippet }}</p>
                                        </div>
                                    </template>
                                </nav>
                            </div>
                        </div>

                        <article class="rounded-xl border border-border/70 bg-background/40 p-6 sm:p-8">
                            <template v-if="selectedEntry">
                                <p class="text-xs uppercase tracking-wide text-muted-foreground">
                                    {{ selectedEntry.domain }} · {{ selectedEntry.section }}
                                </p>
                                <h1 class="mt-2 text-3xl font-semibold text-foreground">{{ selectedEntry.title }}</h1>
                                <p class="mt-3 text-base text-muted-foreground">{{ selectedEntry.summary }}</p>

                                <div
                                    ref="docsContentRef"
                                    data-docs-content
                                    class="docs-markdown prose prose-slate mt-8 max-w-none text-foreground dark:prose-invert"
                                >
                                    <MarkdownRenderer
                                        v-if="selectedBodyMarkdown.trim() !== ''"
                                        :markdown="selectedBodyMarkdown"
                                        :normalize="false"
                                    />
                                    <div v-else-if="selectedBodyHtml.trim() !== ''" v-html="selectedBodyHtml" />
                                    <p v-else class="text-sm text-muted-foreground">No markdown body is available for this entry.</p>
                                </div>
                            </template>

                            <p v-else class="text-sm text-muted-foreground">
                                Select a page from the left navigation to view documentation.
                            </p>
                        </article>
                    </main>

                    <aside class="hidden xl:block xl:sticky xl:top-20 xl:self-start">
                        <div class="max-h-[calc(100vh-8rem)] overflow-y-auto rounded-xl border border-border/70 bg-background/40 p-4">
                            <p class="mb-3 text-xs uppercase tracking-wide text-muted-foreground">On this page</p>

                            <nav v-if="tocItems.length > 0" class="space-y-1">
                                <button
                                    v-for="item in tocItems"
                                    :key="item.key"
                                    type="button"
                                    class="block w-full rounded-md px-2 py-1 text-left text-sm text-muted-foreground transition hover:bg-accent/40 hover:text-foreground"
                                    :class="item.level === 3 ? 'pl-5' : ''"
                                    @click="scrollToHeading(item.text)"
                                >
                                    {{ item.text }}
                                </button>
                            </nav>

                            <p v-else class="text-sm text-muted-foreground">No section headings detected for this page.</p>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
