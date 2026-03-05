<script setup>
import { onMounted, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import Card from '@/Components/ui/Card.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Table from '@/Components/ui/Table.vue';
import TableHeader from '@/Components/ui/TableHeader.vue';
import TableBody from '@/Components/ui/TableBody.vue';
import TableRow from '@/Components/ui/TableRow.vue';
import TableHead from '@/Components/ui/TableHead.vue';
import TableCell from '@/Components/ui/TableCell.vue';
import Button from '@/Components/ui/Button.vue';
import Badge from '@/Components/ui/Badge.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import { Calendar, Plus, ArrowLeft, Play, Pause, RefreshCw, Archive, ArchiveRestore, Eye, AlertTriangle, Bot } from 'lucide-vue-next';
import HelpHint from '@/Components/HelpHint.vue';
import { confirmDialog } from '@/Support/confirmDialog';

const rituals = ref([]);
const loading = ref(true);
const error = ref('');
const includeArchived = ref(false);

const loadRituals = async () => {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get('/agent/api/v1/org/rituals', {
            params: {
                include_archived: includeArchived.value,
            },
        });

        rituals.value = data.data ?? [];
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load ritual templates.';
    } finally {
        loading.value = false;
    }
};

const runNow = async (ritual) => {
    try {
        await axios.post(`/agent/api/v1/org/rituals/${ritual.id}/run`);
        await loadRituals();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to run ritual.';
    }
};

const pauseRitual = async (ritual) => {
    try {
        await axios.post(`/agent/api/v1/org/rituals/${ritual.id}/pause`);
        await loadRituals();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to pause ritual.';
    }
};

const resumeRitual = async (ritual) => {
    try {
        await axios.post(`/agent/api/v1/org/rituals/${ritual.id}/resume`);
        await loadRituals();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to resume ritual.';
    }
};

const archiveRitual = async (ritual) => {
    const approved = await confirmDialog(`Archive ritual "${ritual.name}"?`, {
        title: 'Archive Ritual',
        confirmText: 'Archive',
        confirmVariant: 'destructive',
    });

    if (!approved) {
        return;
    }

    try {
        await axios.delete(`/agent/api/v1/org/rituals/${ritual.id}`);
        await loadRituals();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to archive ritual.';
    }
};

const restoreRitual = async (ritual) => {
    try {
        await axios.post(`/agent/api/v1/org/rituals/${ritual.id}/restore`);
        await loadRituals();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to restore ritual.';
    }
};

onMounted(loadRituals);
</script>

<template>
    <AppLayout title="Rituals">
        <Head title="Rituals" />

        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link :href="route('org.index')">
                        <Button variant="ghost" size="sm">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <Bot class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-xl font-semibold leading-tight text-foreground">Rituals</h2>
                        <HelpHint
                            ui-key="org.rituals"
                            short-text="Manage recurring agent rituals and schedules."
                            learn-more-href="/docs/overview"
                        />
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="outline" @click="includeArchived = !includeArchived; loadRituals()">
                        {{ includeArchived ? 'Hide Archived' : 'Show Archived' }}
                    </Button>
                    <Link :href="route('org.rituals.create')">
                        <Button>
                            <Plus class="mr-2 h-4 w-4" />
                            Create Ritual
                        </Button>
                    </Link>
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="space-y-4">
                <p v-if="error && rituals.length > 0" class="rounded-lg border border-destructive bg-destructive/10 px-4 py-3 text-sm text-destructive">
                    {{ error }}
                </p>

                <Card v-if="loading">
                    <CardContent class="py-8">
                        <div class="flex flex-col items-center justify-center gap-4">
                            <Skeleton class="h-4 w-52" />
                            <Skeleton class="h-4 w-40" />
                        </div>
                    </CardContent>
                </Card>

                <Card v-else-if="error && rituals.length === 0">
                    <CardContent class="pt-16 pb-12">
                        <div class="text-center text-muted-foreground">
                            <AlertTriangle class="mx-auto mb-4 h-12 w-12 opacity-50" />
                            <p class="text-lg font-medium text-destructive">Failed to load ritual templates</p>
                            <p class="text-sm">{{ error }}</p>
                            <Button class="mt-4" variant="outline" @click="loadRituals">
                                Retry
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card v-else-if="rituals.length === 0">
                    <CardContent class="pt-16 pb-12">
                        <div class="text-center text-muted-foreground">
                            <Calendar class="mx-auto mb-4 h-12 w-12 opacity-50" />
                            <p class="text-lg font-medium">No rituals yet</p>
                            <p class="text-sm">Create your first ritual template to get started.</p>
                            <Link :href="route('org.rituals.create')" class="mt-4 inline-block">
                                <Button>
                                    <Plus class="mr-2 h-4 w-4" />
                                    Create Ritual
                                </Button>
                            </Link>
                        </div>
                    </CardContent>
                </Card>

                <Card v-else>
                    <CardContent class="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Schedule</TableHead>
                                    <TableHead>Timezone</TableHead>
                                    <TableHead>Notification</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead class="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="ritual in rituals" :key="ritual.id">
                                    <TableCell>
                                        <div class="font-medium text-foreground">{{ ritual.name }}</div>
                                        <div class="text-xs text-muted-foreground">{{ ritual.description || 'No description' }}</div>
                                    </TableCell>
                                    <TableCell class="font-mono text-xs text-muted-foreground">{{ ritual.cron_expression }}</TableCell>
                                    <TableCell class="text-muted-foreground">{{ ritual.timezone }}</TableCell>
                                    <TableCell class="text-muted-foreground">{{ ritual.notification_level }}</TableCell>
                                    <TableCell>
                                        <Badge :variant="ritual.archived_at ? 'outline' : ritual.is_paused ? 'secondary' : 'default'">
                                            {{ ritual.archived_at ? 'Archived' : ritual.is_paused ? 'Paused' : 'Active' }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <Link :href="route('org.rituals.show', ritual.id)">
                                                <Button variant="outline" size="sm">
                                                    <Eye class="mr-1 h-3 w-3" />
                                                    View
                                                </Button>
                                            </Link>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                :disabled="!!ritual.archived_at"
                                                @click="runNow(ritual)"
                                            >
                                                <Play class="mr-1 h-3 w-3" />
                                                Run
                                            </Button>
                                            <Button
                                                v-if="!ritual.archived_at && !ritual.is_paused"
                                                variant="outline"
                                                size="sm"
                                                @click="pauseRitual(ritual)"
                                            >
                                                <Pause class="mr-1 h-3 w-3" />
                                                Pause
                                            </Button>
                                            <Button
                                                v-if="!ritual.archived_at && ritual.is_paused"
                                                variant="outline"
                                                size="sm"
                                                @click="resumeRitual(ritual)"
                                            >
                                                <RefreshCw class="mr-1 h-3 w-3" />
                                                Resume
                                            </Button>
                                            <Button
                                                v-if="!ritual.archived_at"
                                                variant="outline"
                                                size="sm"
                                                class="text-destructive hover:text-destructive"
                                                @click="archiveRitual(ritual)"
                                            >
                                                <Archive class="mr-1 h-3 w-3" />
                                                Archive
                                            </Button>
                                            <Button
                                                v-if="ritual.archived_at"
                                                variant="outline"
                                                size="sm"
                                                @click="restoreRitual(ritual)"
                                            >
                                                <ArchiveRestore class="mr-1 h-3 w-3" />
                                                Restore
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
