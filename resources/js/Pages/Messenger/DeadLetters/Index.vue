<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardDescription from '@/Components/ui/CardDescription.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Button from '@/Components/ui/Button.vue';
import Badge from '@/Components/ui/Badge.vue';
import Table from '@/Components/ui/Table.vue';
import TableHeader from '@/Components/ui/TableHeader.vue';
import TableBody from '@/Components/ui/TableBody.vue';
import TableRow from '@/Components/ui/TableRow.vue';
import TableHead from '@/Components/ui/TableHead.vue';
import TableCell from '@/Components/ui/TableCell.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { RefreshCw, RotateCcw, Trash2, Eye, CheckSquare, Square, MailWarning } from 'lucide-vue-next';
import axios from 'axios';
import { computed, ref } from 'vue';
import { confirmDialog } from '@/Support/confirmDialog';
import HelpHint from '@/Components/HelpHint.vue';

const props = defineProps({
    deadLetters: Object,
    connectors: Array,
    currentConnector: String,
});

const selectedIds = ref([]);
const bulkRetrying = ref(false);
const bulkError = ref('');
const bulkSuccess = ref('');
const retryingId = ref(null);

const allSelected = computed(() => {
    if (!props.deadLetters?.data?.length) return false;
    return props.deadLetters.data.every(dl => selectedIds.value.includes(dl.id));
});

const someSelected = computed(() => selectedIds.value.length > 0);

const toggleSelectAll = () => {
    if (allSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = props.deadLetters.data.map(dl => dl.id);
    }
};

const toggleSelect = (id) => {
    const index = selectedIds.value.indexOf(id);
    if (index === -1) {
        selectedIds.value.push(id);
    } else {
        selectedIds.value.splice(index, 1);
    }
};

const isSelected = (id) => selectedIds.value.includes(id);

const filterByConnector = (connectorId) => {
    router.get(route('messenger.dead-letters.index'), {
        connector: connectorId || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const retrySingle = async (id) => {
    const approved = await confirmDialog('Retry this message? It will be re-dispatched for processing.', {
        title: 'Retry Message',
        confirmText: 'Retry',
    });

    if (!approved) {
        return;
    }

    retryingId.value = id;
    bulkError.value = '';
    bulkSuccess.value = '';

    try {
        router.post(route('messenger.dead-letters.retry', { id }), {}, {
            preserveScroll: true,
        });
    } finally {
        retryingId.value = null;
    }
};

const retryBulk = async () => {
    if (!selectedIds.value.length) {
        bulkError.value = 'No messages selected.';
        return;
    }

    const approved = await confirmDialog(`Retry ${selectedIds.value.length} selected message(s)?`, {
        title: 'Retry Selected Messages',
        confirmText: 'Retry',
    });

    if (!approved) {
        return;
    }

    bulkRetrying.value = true;
    bulkError.value = '';
    bulkSuccess.value = '';

    try {
        const response = await axios.post(route('messenger.dead-letters.retry-bulk'), {
            ids: selectedIds.value,
        });

        const results = response.data;
        const successCount = results.filter(r => r.success).length;
        const failCount = results.filter(r => !r.success).length;

        if (failCount === 0) {
            bulkSuccess.value = `Successfully retried ${successCount} message(s).`;
        } else {
            bulkError.value = `Retried ${successCount}, failed ${failCount} message(s).`;
        }

        selectedIds.value = [];
        router.reload({ only: ['deadLetters'] });
    } catch (error) {
        bulkError.value = error.response?.data?.message || 'Failed to retry messages.';
    } finally {
        bulkRetrying.value = false;
    }
};

const deleteSingle = async (id) => {
    const approved = await confirmDialog('Delete this dead letter? This cannot be undone.', {
        title: 'Delete Dead Letter',
        confirmText: 'Delete',
        confirmVariant: 'destructive',
    });

    if (!approved) {
        return;
    }

    router.delete(route('messenger.dead-letters.destroy', { id }), {
        preserveScroll: true,
    });
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleString();
};

const truncate = (str, maxLength = 50) => {
    if (!str) return '-';
    if (str.length <= maxLength) return str;
    return str.substring(0, maxLength) + '...';
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

const refresh = () => {
    router.reload({ only: ['deadLetters'] });
};
</script>

<template>
    <AppLayout title="Failed Messages">
        <Head title="Failed Messages - Dead Letter Queue" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <MailWarning class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-semibold text-foreground truncate">Failed Messages</h2>
                        <HelpHint
                            ui-key="dead-letters.overview"
                            short-text="Review failed messages and retry or discard them."
                            learn-more-href="/docs/overview"
                        />
                    </div>
                </div>
                <Button variant="outline" size="sm" @click="refresh">
                    <RefreshCw class="h-4 w-4 mr-1" />
                    Refresh
                </Button>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl space-y-4">
                <!-- Flash messages -->
                <div v-if="bulkError" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    {{ bulkError }}
                </div>
                <div v-if="bulkSuccess" class="rounded-md border border-green-500/50 bg-green-500/10 px-3 py-2 text-sm text-green-700 dark:text-green-400">
                    {{ bulkSuccess }}
                </div>
                <div v-if="$page.props.flash?.success" class="rounded-md border border-green-500/50 bg-green-500/10 px-3 py-2 text-sm text-green-700 dark:text-green-400">
                    {{ $page.props.flash.success }}
                </div>
                <div v-if="$page.props.flash?.error" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    {{ $page.props.flash.error }}
                </div>

                <Card>
                    <CardHeader>
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <CardTitle>Dead Letter Queue</CardTitle>
                                <CardDescription>
                                    {{ deadLetters.total || 0 }} failed message(s)
                                </CardDescription>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <!-- Connector filter -->
                                <select
                                    :value="currentConnector || ''"
                                    @change="filterByConnector($event.target.value)"
                                    class="h-9 rounded-md border border-input bg-input-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    <option value="">All Connectors</option>
                                    <option
                                        v-for="connector in connectors"
                                        :key="connector.id"
                                        :value="connector.id"
                                    >
                                        {{ connector.name }} ({{ connector.provider }})
                                    </option>
                                </select>

                                <!-- Bulk retry button -->
                                <Button
                                    variant="default"
                                    size="sm"
                                    :disabled="!someSelected || bulkRetrying"
                                    @click="retryBulk"
                                >
                                    <RotateCcw class="h-4 w-4 mr-1" :class="{ 'animate-spin': bulkRetrying }" />
                                    {{ bulkRetrying ? 'Retrying...' : `Retry Selected (${selectedIds.length})` }}
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="w-12">
                                        <button
                                            type="button"
                                            @click="toggleSelectAll"
                                            class="flex items-center justify-center"
                                        >
                                            <CheckSquare v-if="allSelected" class="h-4 w-4" />
                                            <Square v-else class="h-4 w-4" />
                                        </button>
                                    </TableHead>
                                    <TableHead>Connector</TableHead>
                                    <TableHead>Message Preview</TableHead>
                                    <TableHead>Error</TableHead>
                                    <TableHead>Attempts</TableHead>
                                    <TableHead>Failed At</TableHead>
                                    <TableHead>Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="deadLetter in deadLetters.data" :key="deadLetter.id">
                                    <TableCell>
                                        <button
                                            type="button"
                                            @click="toggleSelect(deadLetter.id)"
                                            class="flex items-center justify-center"
                                        >
                                            <CheckSquare v-if="isSelected(deadLetter.id)" class="h-4 w-4 text-primary" />
                                            <Square v-else class="h-4 w-4" />
                                        </button>
                                    </TableCell>
                                    <TableCell>
                                        <div class="flex flex-col gap-1">
                                            <span class="font-medium">{{ deadLetter.connector_account?.name || 'Unknown' }}</span>
                                            <Badge :variant="getProviderBadgeVariant(deadLetter.connector_account?.provider)" class="w-fit text-xs">
                                                {{ deadLetter.connector_account?.provider || 'unknown' }}
                                            </Badge>
                                        </div>
                                    </TableCell>
                                    <TableCell class="max-w-xs">
                                        <span class="text-sm text-muted-foreground break-words">
                                            {{ truncate(JSON.stringify(deadLetter.original_payload), 60) }}
                                        </span>
                                    </TableCell>
                                    <TableCell class="max-w-xs">
                                        <span class="text-sm text-destructive break-words">
                                            {{ truncate(deadLetter.error_message, 60) }}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="outline">{{ deadLetter.attempts }}</Badge>
                                    </TableCell>
                                    <TableCell class="text-sm text-muted-foreground whitespace-nowrap">
                                        {{ formatDate(deadLetter.failed_at) }}
                                    </TableCell>
                                    <TableCell>
                                        <div class="flex gap-2">
                                            <Link :href="route('messenger.dead-letters.show', { id: deadLetter.id })">
                                                <Button variant="outline" size="sm">
                                                    <Eye class="h-4 w-4" />
                                                </Button>
                                            </Link>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                :disabled="retryingId === deadLetter.id"
                                                @click="retrySingle(deadLetter.id)"
                                            >
                                                <RotateCcw class="h-4 w-4" :class="{ 'animate-spin': retryingId === deadLetter.id }" />
                                            </Button>
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                @click="deleteSingle(deadLetter.id)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="!deadLetters.data?.length">
                                    <TableCell colspan="7" class="text-center text-muted-foreground py-8">
                                        No failed messages found.
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>

                        <!-- Pagination -->
                        <div v-if="deadLetters.links?.length > 3" class="mt-4 flex flex-wrap items-center justify-center gap-1">
                            <template v-for="(link, index) in deadLetters.links" :key="index">
                                <Link
                                    v-if="link.url"
                                    :href="link.url"
                                    class="px-3 py-1 text-sm rounded-md border"
                                    :class="link.active ? 'bg-primary text-primary-foreground border-primary' : 'bg-background border-input hover:bg-muted'"
                                    v-html="link.label"
                                />
                                <span
                                    v-else
                                    class="px-3 py-1 text-sm text-muted-foreground"
                                    v-html="link.label"
                                />
                            </template>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
