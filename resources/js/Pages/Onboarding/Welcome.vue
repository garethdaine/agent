<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import Button from '@/Components/ui/Button.vue';
import { Rocket, SkipForward } from 'lucide-vue-next';

defineProps({
    hasJobs: Boolean,
});

const complete = () => {
    router.post(route('onboarding.complete'));
};

const goToFirstJob = () => {
    router.visit(route('onboarding.first-job'));
};
</script>

<template>
    <AppLayout>
        <Head title="Welcome" />
        <div class="mx-auto max-w-2xl py-12 text-center">
            <h1 class="text-3xl font-semibold tracking-tight text-gray-900 dark:text-white">
                Welcome to Agent Ops
            </h1>
            <p class="mt-4 text-gray-600 dark:text-gray-400">
                Set up your first scheduled agent job to get started, or skip and explore the dashboard.
            </p>
            <div class="mt-10 flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
                <Button
                    class="inline-flex items-center gap-2"
                    @click="goToFirstJob"
                >
                    <Rocket class="h-4 w-4" />
                    Create your first job
                </Button>
                <Button
                    variant="outline"
                    class="inline-flex items-center gap-2"
                    @click="complete"
                >
                    <SkipForward class="h-4 w-4" />
                    Skip for now
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
