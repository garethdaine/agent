<script setup>
import { computed, reactive } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import MarkdownRenderer from '@/Components/Markdown/MarkdownRenderer.vue';

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

const domains = computed(() => {
    return [...new Set(props.entries.map((entry) => String(entry.domain ?? '')).filter((value) => value !== ''))].sort();
});

const sections = computed(() => {
    return [...new Set(props.entries.map((entry) => String(entry.section ?? '')).filter((value) => value !== ''))].sort();
});

const selectedSlug = computed(() => String(props.activeEntry?.slug ?? props.entries?.[0]?.slug ?? ''));

const selectedBodyMarkdown = computed(() => {
    const value = props.activeEntry?.body_markdown;
    if (typeof value === 'string' && value.trim() !== '') {
        return value;
    }

    return '';
});

const selectedBodyHtml = computed(() => {
    const value = props.activeEntry?.body_html;
    if (typeof value === 'string' && value.trim() !== '') {
        return value;
    }

    return '';
});

function applyFilters() {
    router.get(
        route('docs.index'),
        {
            q: form.q || undefined,
            domain: form.domain || undefined,
            section: form.section || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
}

function resetFilters() {
    form.q = '';
    form.domain = '';
    form.section = '';
    applyFilters();
}

function docsShowHref(slug) {
    return route('docs.show', {
        slug,
        q: form.q || undefined,
        domain: form.domain || undefined,
        section: form.section || undefined,
    });
}
</script>

<template>
    <AppLayout title="Docs">
        <Head title="Docs" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-semibold text-xl text-foreground leading-tight">Docs Center</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-[1600px] px-4 sm:px-6 lg:px-8">
                <div class="grid gap-6 lg:grid-cols-[340px_minmax(0,1fr)]">
                    <aside class="space-y-4">
                        <Card>
                            <CardHeader class="pb-2">
                                <CardTitle class="text-sm">Search Documentation</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-3 pt-0">
                                <form class="space-y-3" @submit.prevent="applyFilters">
                                    <input
                                        v-model="form.q"
                                        type="text"
                                        placeholder="Search title, summary, body..."
                                        class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/40"
                                    >

                                    <select
                                        v-model="form.domain"
                                        class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/40"
                                    >
                                        <option value="">All domains</option>
                                        <option v-for="domain in domains" :key="domain" :value="domain">{{ domain }}</option>
                                    </select>

                                    <select
                                        v-model="form.section"
                                        class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/40"
                                    >
                                        <option value="">All sections</option>
                                        <option v-for="section in sections" :key="section" :value="section">{{ section }}</option>
                                    </select>

                                    <div class="flex items-center gap-2">
                                        <button
                                            type="submit"
                                            class="rounded-md bg-primary px-3 py-2 text-xs font-semibold text-primary-foreground hover:opacity-90"
                                        >
                                            Search
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-md border border-border px-3 py-2 text-xs font-semibold text-muted-foreground hover:bg-accent"
                                            @click="resetFilters"
                                        >
                                            Reset
                                        </button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader class="pb-2">
                                <CardTitle class="text-sm">Documentation Pages</CardTitle>
                            </CardHeader>
                            <CardContent class="pt-0">
                                <p v-if="entries.length === 0" class="text-sm text-muted-foreground">
                                    No docs match the current filters.
                                </p>

                                <nav v-else class="space-y-1">
                                    <Link
                                        v-for="entry in entries"
                                        :key="entry.slug"
                                        :href="docsShowHref(entry.slug)"
                                        class="block rounded-md border border-transparent px-3 py-2 transition hover:border-border hover:bg-accent/40"
                                        :class="selectedSlug === entry.slug ? 'border-primary/40 bg-primary/10' : ''"
                                    >
                                        <p class="text-sm font-semibold text-foreground">{{ entry.title }}</p>
                                        <p class="mt-1 line-clamp-2 text-xs text-muted-foreground">{{ entry.summary }}</p>
                                        <p class="mt-1 text-[10px] uppercase tracking-wide text-muted-foreground">
                                            {{ entry.domain }} · {{ entry.section }}
                                        </p>
                                    </Link>
                                </nav>
                            </CardContent>
                        </Card>
                    </aside>

                    <section class="min-w-0">
                        <Card>
                            <CardHeader class="pb-2">
                                <CardTitle class="text-xl">{{ activeEntry?.title ?? 'Select a documentation page' }}</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-3 pt-0">
                                <template v-if="activeEntry">
                                    <p class="text-sm text-muted-foreground">{{ activeEntry.summary }}</p>
                                    <p class="text-xs text-muted-foreground uppercase tracking-wide">
                                        {{ activeEntry.domain }} · {{ activeEntry.section }}
                                    </p>

                                    <div class="rounded-md border border-border/70 bg-background/60 p-4">
                                        <MarkdownRenderer
                                            v-if="selectedBodyMarkdown !== ''"
                                            :markdown="selectedBodyMarkdown"
                                            class="prose prose-invert max-w-none"
                                            :normalize="false"
                                        />
                                        <div
                                            v-else-if="selectedBodyHtml !== ''"
                                            class="prose prose-invert max-w-none"
                                            v-html="selectedBodyHtml"
                                        />
                                        <p v-else class="text-sm text-muted-foreground">No markdown body is available for this entry.</p>
                                    </div>
                                </template>

                                <p v-else class="text-sm text-muted-foreground">
                                    Select a page from the left sidebar to view full documentation.
                                </p>
                            </CardContent>
                        </Card>
                    </section>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
