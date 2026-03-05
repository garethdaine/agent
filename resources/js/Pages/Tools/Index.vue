<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardDescription from '@/Components/ui/CardDescription.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Search, MessageSquare, Brain, GitBranchPlus, Wrench } from 'lucide-vue-next';
import HelpHint from '@/Components/HelpHint.vue';

const props = defineProps({
    codeAnalysis: {
        type: Object,
        default: () => ({
            available: false,
            indexRoute: null,
            blockedMessage: '',
        }),
    },
});

const baseTools = [
    {
        route: 'tools.discovery.index',
        category: 'Requirements Discovery',
        title: 'Interactive discovery wizard',
        description: 'Guide AI-led requirements interrogation and generate summaries/plans.',
        icon: Search,
    },
    {
        route: 'tools.messenger.index',
        category: 'Messenger',
        title: 'Control plane dashboard',
        description: 'View connector health, chat sessions, recent actions, and control-plane metrics.',
        icon: MessageSquare,
    },
    {
        route: 'tools.memory.index',
        category: 'Agent Memory',
        title: 'Memory dashboard & diagnostics',
        description: 'View memory diagnostics, operating mode, provider usage, and manage retention.',
        icon: Brain,
    },
];

const tools = computed(() => {
    const items = [...baseTools];

    if (props.codeAnalysis?.available) {
        items.splice(1, 0, {
            route: 'tools.code-analysis.index',
            category: 'Code Analysis',
            title: 'AI-driven code analysis',
            description: 'Run stepwise code analysis with live task feedback and full narrative reporting.',
            icon: GitBranchPlus,
        });
    }

    return items;
});
</script>

<template>
    <AppLayout title="Tools">
        <Head title="Tools" />

        <template #header>
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                    <Wrench class="h-5 w-5 text-primary" />
                </div>
                <div class="flex items-center gap-2">
                    <h2 class="text-xl font-semibold leading-tight text-foreground">Tools</h2>
                    <HelpHint
                        ui-key="tools.overview"
                        short-text="Access discovery, analysis, memory, and messenger tools."
                        learn-more-href="/docs/overview"
                    />
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <Link
                        v-for="tool in tools"
                        :key="tool.route"
                        :href="route(tool.route)"
                    >
                        <Card class="h-full cursor-pointer transition-colors hover:border-primary/50">
                            <CardHeader>
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                                        <component :is="tool.icon" class="h-5 w-5 text-primary" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-primary">{{ tool.category }}</p>
                                        <CardTitle class="mt-1">{{ tool.title }}</CardTitle>
                                        <CardDescription class="mt-1">{{ tool.description }}</CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                        </Card>
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
