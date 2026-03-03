<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardDescription from '@/Components/ui/CardDescription.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Button from '@/Components/ui/Button.vue';
import Badge from '@/Components/ui/Badge.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, RotateCcw, Trash2, Clock, AlertCircle } from 'lucide-vue-next';
import { ref } from 'vue';
import { confirmDialog } from '@/Support/confirmDialog';

const props = defineProps({
    deadLetter: Object,
});

const retrying = ref(false);

const retrySingle = async () => {
    const approved = await confirmDialog('Retry this message? It will be re-dispatched for processing.', {
        title: 'Retry Message',
        confirmText: 'Retry',
    });

    if (!approved) {
        return;
    }

    retrying.value = true;
    router.post(route('messenger.dead-letters.retry', { id: props.deadLetter.id }), {}, {
        onFinish: () => {
            retrying.value = false;
        },
    });
};

const deleteSingle = async () => {
    const approved = await confirmDialog('Delete this dead letter? This cannot be undone.', {
        title: 'Delete Dead Letter',
        confirmText: 'Delete',
        confirmVariant: 'destructive',
    });

    if (!approved) {
        return;
    }

    router.delete(route('messenger.dead-letters.destroy', { id: props.deadLetter.id }));
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleString();
};

const formatJson = (data) => {
    try {
        return JSON.stringify(data, null, 2);
    } catch {
        return String(data);
    }
};

const getProviderBadgeVariant = (provider) => {
    const variants = {
        slack: 'default',
        telegram: 'secondary',
        discord: 'outline',
        whatsapp: 'secondary',
    };
    return variants[provider] || 'outline';
};
</script>

<template>
    <AppLayout title="Dead Letter Details">
        <Head :title="`Dead Letter #${deadLetter.id}`" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <Link :href="route('messenger.dead-letters.index')">
                        <Button variant="outline" size="sm">
                            <ArrowLeft class="h-4 w-4 mr-1" />
                            Back
                        </Button>
                    </Link>
                    <div>
                        <h2 class="text-xl font-semibold leading-tight text-foreground">Dead Letter #{{ deadLetter.id }}</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Failed at {{ formatDate(deadLetter.failed_at) }}
                        </p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Button
                        variant="default"
                        size="sm"
                        :disabled="retrying"
                        @click="retrySingle"
                    >
                        <RotateCcw class="h-4 w-4 mr-1" :class="{ 'animate-spin': retrying }" />
                        {{ retrying ? 'Retrying...' : 'Retry' }}
                    </Button>
                    <Button variant="destructive" size="sm" @click="deleteSingle">
                        <Trash2 class="h-4 w-4 mr-1" />
                        Delete
                    </Button>
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-5xl space-y-6">
                <!-- Flash messages -->
                <div v-if="$page.props.flash?.success" class="rounded-md border border-green-500/50 bg-green-500/10 px-3 py-2 text-sm text-green-700 dark:text-green-400">
                    {{ $page.props.flash.success }}
                </div>
                <div v-if="$page.props.flash?.error" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    {{ $page.props.flash.error }}
                </div>

                <!-- Summary Card -->
                <Card>
                    <CardHeader>
                        <CardTitle>Summary</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <dt class="text-sm font-medium text-muted-foreground">Connector</dt>
                                <dd class="mt-1">
                                    <span class="font-medium">{{ deadLetter.connector_account?.name || 'Unknown' }}</span>
                                    <Badge
                                        :variant="getProviderBadgeVariant(deadLetter.connector_account?.provider)"
                                        class="ml-2 text-xs"
                                    >
                                        {{ deadLetter.connector_account?.provider || 'unknown' }}
                                    </Badge>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-muted-foreground">Attempts</dt>
                                <dd class="mt-1">
                                    <Badge variant="outline">{{ deadLetter.attempts }}</Badge>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-muted-foreground">Failed At</dt>
                                <dd class="mt-1 text-sm">{{ formatDate(deadLetter.failed_at) }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-muted-foreground">Last Retry</dt>
                                <dd class="mt-1 text-sm">{{ deadLetter.retried_at ? formatDate(deadLetter.retried_at) : 'Never' }}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <!-- Current Error -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <AlertCircle class="h-5 w-5 text-destructive" />
                            Current Error
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="rounded-md bg-destructive/10 border border-destructive/30 p-4">
                            <p class="text-sm text-destructive font-mono whitespace-pre-wrap break-words">
                                {{ deadLetter.error_message }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Error History Timeline -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Clock class="h-5 w-5" />
                            Error History
                        </CardTitle>
                        <CardDescription>
                            Timeline of all errors encountered for this message
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="deadLetter.error_history?.length" class="space-y-4">
                            <div
                                v-for="(entry, index) in deadLetter.error_history"
                                :key="index"
                                class="relative pl-6 border-l-2 border-muted pb-4 last:pb-0"
                            >
                                <div class="absolute -left-1.5 top-0 h-3 w-3 rounded-full bg-muted-foreground" />
                                <div class="text-xs text-muted-foreground mb-1">
                                    {{ formatDate(entry.at) }}
                                </div>
                                <div class="text-sm text-destructive font-mono whitespace-pre-wrap break-words bg-muted/50 rounded p-2">
                                    {{ entry.error }}
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">
                            No error history available.
                        </p>
                    </CardContent>
                </Card>

                <!-- Original Payload -->
                <Card>
                    <CardHeader>
                        <CardTitle>Original Payload</CardTitle>
                        <CardDescription>
                            The complete message payload that was being processed when the failure occurred
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <pre class="text-sm font-mono bg-muted/50 rounded-md p-4 overflow-x-auto whitespace-pre-wrap break-words">{{ formatJson(deadLetter.original_payload) }}</pre>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
