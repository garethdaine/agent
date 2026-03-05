<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/ui/Card.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import { Head, router } from '@inertiajs/vue3';
import Button from '@/Components/ui/Button.vue';
import Badge from '@/Components/ui/Badge.vue';
import { Rocket, SkipForward, MessageSquare, CheckCircle, Circle, Briefcase, Shield } from 'lucide-vue-next';

const props = defineProps({
    hasJobs: Boolean,
    hasConnectors: Boolean,
    hasConnectedChannel: Boolean,
});

const complete = () => {
    router.post(route('onboarding.complete'));
};

const goToFirstJob = () => {
    router.visit(route('onboarding.first-job'));
};

const goToMessenger = () => {
    router.visit(route('tools.messenger.index'));
};

const goToDiagnostics = () => {
    router.visit(route('tools.diagnostics.index'));
};

const goToSecurity = () => {
    router.visit(route('tools.security.index'));
};

const steps = [
    {
        id: 'channel',
        label: 'Connect a messenger channel',
        description: 'Set up Discord, Slack, Telegram, or WhatsApp so your agent can communicate.',
        done: props.hasConnectedChannel,
        action: goToMessenger,
        actionLabel: 'Connect channel',
    },
    {
        id: 'job',
        label: 'Create your first scheduled job',
        description: 'Define a task for the agent to run on a schedule (e.g. code review, deployment check).',
        done: props.hasJobs,
        action: goToFirstJob,
        actionLabel: 'Create job',
    },
    {
        id: 'diagnostics',
        label: 'Run diagnostics',
        description: 'Verify database, Redis, queue, and scheduler health.',
        done: false,
        action: goToDiagnostics,
        actionLabel: 'Run diagnostics',
    },
    {
        id: 'security',
        label: 'Run security audit',
        description: 'Check runtime mode, tool policy, log redaction, and session timeout.',
        done: false,
        action: goToSecurity,
        actionLabel: 'Run audit',
    },
];
</script>

<template>
    <AppLayout>
        <Head title="Get Started" />
        <div class="mx-auto max-w-2xl py-12 px-4">
            <div class="text-center">
                <h1 class="text-3xl font-semibold tracking-tight text-foreground">
                    Welcome to Agent Ops
                </h1>
                <p class="mt-3 text-muted-foreground">
                    Complete these steps to get your agent running. You can skip and come back anytime.
                </p>
            </div>

            <div class="mt-10 space-y-3">
                <Card v-for="step in steps" :key="step.id">
                    <CardContent class="flex items-start gap-4 py-4">
                        <div class="mt-0.5 shrink-0">
                            <CheckCircle v-if="step.done" class="h-5 w-5 text-green-500" />
                            <Circle v-else class="h-5 w-5 text-muted-foreground/40" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-foreground">{{ step.label }}</span>
                                <Badge v-if="step.done" variant="default" class="text-[10px]">Done</Badge>
                            </div>
                            <p class="mt-0.5 text-xs text-muted-foreground">{{ step.description }}</p>
                        </div>
                        <Button
                            v-if="!step.done"
                            size="sm"
                            variant="outline"
                            @click="step.action"
                        >
                            {{ step.actionLabel }}
                        </Button>
                    </CardContent>
                </Card>
            </div>

            <div class="mt-8 flex flex-col items-center gap-3 sm:flex-row sm:justify-center">
                <Button
                    class="inline-flex items-center gap-2"
                    @click="complete"
                >
                    <Rocket class="h-4 w-4" />
                    I'm done — go to dashboard
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
