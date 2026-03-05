<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import { LayoutDashboard, BookOpen } from 'lucide-vue-next';

defineProps({
    metrics: {
        type: Object,
        required: true,
    },
    helpUrl: {
        type: String,
        default: '/docs',
    },
});
</script>

<template>
    <Head title="Operator overview" />

    <AppLayout title="Operator overview">
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <LayoutDashboard class="h-5 w-5 text-primary" />
                    </div>
                    <h2 class="font-semibold text-xl text-foreground leading-tight">Operator overview</h2>
                </div>
                <Link
                    :href="helpUrl"
                    class="inline-flex items-center gap-2 rounded-md border border-border px-3 py-2 text-sm hover:bg-muted"
                >
                    <BookOpen class="h-4 w-4" />
                    Help & Docs
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="px-4 sm:px-6 lg:px-8">
                <p class="mb-6 text-muted-foreground">
                    High-level view of your automated jobs and today’s run results.
                </p>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-xs uppercase tracking-wide text-muted-foreground font-medium">Total jobs</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="text-2xl font-semibold text-foreground">{{ metrics.total_jobs }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">Scheduled automations</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle class="text-xs uppercase tracking-wide text-muted-foreground font-medium">Runs today</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="text-2xl font-semibold text-foreground">{{ metrics.runs_today }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">Executions so far</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle class="text-xs uppercase tracking-wide text-muted-foreground font-medium">Successful today</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="text-2xl font-semibold text-foreground">{{ metrics.successful_runs_today }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">Completed without failure</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle class="text-xs uppercase tracking-wide text-muted-foreground font-medium">Success rate (today)</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="text-2xl font-semibold text-foreground">{{ metrics.success_rate_percent }}%</p>
                            <p class="mt-1 text-xs text-muted-foreground">Of today’s runs</p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
