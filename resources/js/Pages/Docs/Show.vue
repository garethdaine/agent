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
    entry: {
        type: Object,
        required: true,
    },
    entries: {
        type: Array,
        default: () => [],
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
    return [...new Set(props.entries.map((item) => String(item.domain ?? '')).filter((value) => value !== ''))].sort();
});

const sections = computed(() => {
    return [...new Set(props.entries.map((item) => String(item.section ?? '')).filter((value) => value !== ''))].sort();
});

const bodyMarkdown = computed(() => {
    const value = props.entry?.body_markdown;
    return typeof value === 'string' ? value : '';
});

const bodyHtml = computed(() => {
    const value = props.entry?.body_html;
    return typeof value === 'string' ? value : '';
});

function applyFilters() {
    router.get(
        route('docs.show', {
            slug: props.entry.slug,
            q: form.q || undefined,
            domain: form.domain || undefined,
            section: form.section || undefined,
        }),
        {},
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
    router.get(route('docs.show', { slug: props.entry.slug }), {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
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
    <AppLayout :title="entry.title ?? 'Docs'">
        <Head :title="entry.title ?? 'Docs'" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-semibold text-xl text-foreground leading-tight">{{ entry.title }}</h2>
                <Link :href="route('docs.index')" class="text-sm text-primary hover:underline">Back to Docs</Link>
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
                                        v-for="item in entries"
                                        :key="item.slug"
                                        :href="docsShowHref(item.slug)"
                                        class="block rounded-md border border-transparent px-3 py-2 transition hover:border-border hover:bg-accent/40"
                                        :class="entry.slug === item.slug ? 'border-primary/40 bg-primary/10' : ''"
                                    >
                                        <p class="text-sm font-semibold text-foreground">{{ item.title }}</p>
                                        <p class="mt-1 line-clamp-2 text-xs text-muted-foreground">{{ item.summary }}</p>
                                        <p class="mt-1 text-[10px] uppercase tracking-wide text-muted-foreground">
                                            {{ item.domain }} · {{ item.section }}
                                        </p>
                                    </Link>
                                </nav>
                            </CardContent>
                        </Card>
                    </aside>

                    <section class="min-w-0">
                        <Card>
                            <CardHeader class="pb-2">
                                <CardTitle class="text-xl">{{ entry.title }}</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-3 pt-0">
                                <p class="text-sm text-muted-foreground">{{ entry.summary }}</p>
                                <p class="text-xs text-muted-foreground uppercase tracking-wide">
                                    {{ entry.domain }} · {{ entry.section }}
                                </p>

                                <div class="rounded-md border border-border/70 bg-background/60 p-4">
                                    <MarkdownRenderer
                                        v-if="bodyMarkdown.trim() !== ''"
                                        :markdown="bodyMarkdown"
                                        :normalize="false"
                                        class="prose prose-invert max-w-none"
                                    />
                                    <div
                                        v-else-if="bodyHtml.trim() !== ''"
                                        class="prose prose-invert max-w-none"
                                        v-html="bodyHtml"
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </section>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
