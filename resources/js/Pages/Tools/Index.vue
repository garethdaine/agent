<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardDescription from '@/Components/ui/CardDescription.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Search, Database, MessageSquare, Settings, Brain } from 'lucide-vue-next';

const tools = [
    {
        route: 'tools.discovery.index',
        category: 'Requirements Discovery',
        title: 'Interactive discovery wizard',
        description: 'Guide AI-led requirements interrogation and generate summaries/plans.',
        icon: Search,
    },
    {
        route: 'tools.backups.settings',
        category: 'Operations',
        title: 'Database backup settings',
        description: 'Configure daily DB backups, retention window, timezone, and run immediate backups.',
        icon: Database,
    },
    {
        route: 'tools.messenger.index',
        category: 'Messenger',
        title: 'Control plane dashboard',
        description: 'View connector health, chat sessions, recent actions, and control-plane metrics.',
        icon: MessageSquare,
    },
    {
        route: 'tools.features.settings',
        category: 'Configuration',
        title: 'Feature flag settings',
        description: 'Enable or disable runtime feature flags without changing env/config files.',
        icon: Settings,
    },
    {
        route: 'tools.memory.settings',
        category: 'Agent Memory',
        title: 'Memory settings & diagnostics',
        description: 'Configure memory providers, view usage, and manage memory retention.',
        icon: Brain,
    },
];
</script>

<template>
    <AppLayout title="Tools">
        <Head title="Tools" />

        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-foreground">Tools</h2>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-[1440px]">
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
