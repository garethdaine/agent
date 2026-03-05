<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import Button from '@/Components/ui/Button.vue';
import { ArrowRight, CheckCircle } from 'lucide-vue-next';

const complete = () => {
    router.post(route('onboarding.complete'));
};

const goToCreateJob = () => {
    router.visit(route('agent.jobs.create'));
};
</script>

<template>
    <AppLayout>
        <Head title="First job" />
        <div class="mx-auto max-w-2xl py-12">
            <h1 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
                Create your first job
            </h1>
            <p class="mt-2 text-gray-600 dark:text-gray-400">
                Jobs run on a schedule and execute your chosen runner (Claude, Codex, or custom). You'll need a task markdown file and a working directory.
            </p>
            <ol class="mt-8 list-inside list-decimal space-y-2 text-gray-700 dark:text-gray-300">
                <li>Choose a runner type (e.g. Codex)</li>
                <li>Set a cron schedule (e.g. daily at 9:00)</li>
                <li>Point to a task markdown file and working directory</li>
                <li>Save and run manually once to test</li>
            </ol>
            <div class="mt-10 flex flex-wrap gap-4">
                <Button
                    class="inline-flex items-center gap-2"
                    @click="goToCreateJob"
                >
                    Go to Jobs
                    <ArrowRight class="h-4 w-4" />
                </Button>
                <Button
                    variant="outline"
                    class="inline-flex items-center gap-2"
                    @click="complete"
                >
                    <CheckCircle class="h-4 w-4" />
                    I'll do this later
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
