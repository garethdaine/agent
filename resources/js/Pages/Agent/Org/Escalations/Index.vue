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
import { AlertTriangle, ArrowLeft } from 'lucide-vue-next';

const escalations = ref([]);
const loading = ref(true);
const error = ref('');

const loadEscalations = async () => {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get('/agent/api/v1/org/escalations');
        escalations.value = data.data ?? [];
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load escalations.';
    } finally {
        loading.value = false;
    }
};

const resolveEscalation = async (escalation, resolution) => {
    const notes = prompt(`Optional notes for ${resolution}:`) ?? '';

    try {
        await axios.post(`/agent/api/v1/org/escalations/${escalation.id}/resolve`, {
            resolution,
            notes,
        });
        await loadEscalations();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to resolve escalation.';
    }
};

onMounted(loadEscalations);
</script>

<template>
    <AppLayout title="Escalations">
        <Head title="Escalations" />

        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('org.index')">
                    <Button variant="ghost" size="sm">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                </Link>
                <h2 class="text-xl font-semibold leading-tight text-foreground">Escalations</h2>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-[1440px] space-y-4">
                <p v-if="error" class="rounded-lg border border-destructive bg-destructive/10 px-4 py-3 text-sm text-destructive">
                    {{ error }}
                </p>

                <Card>
                    <CardContent class="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Run ID</TableHead>
                                    <TableHead>Escalated To</TableHead>
                                    <TableHead>Created</TableHead>
                                    <TableHead>Timeout</TableHead>
                                    <TableHead class="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-if="loading">
                                    <TableCell colspan="6" class="py-8">
                                        <div class="flex flex-col items-center justify-center gap-4">
                                            <Skeleton class="h-4 w-52" />
                                            <Skeleton class="h-4 w-40" />
                                        </div>
                                    </TableCell>
                                </TableRow>

                                <TableRow v-for="escalation in escalations" :key="escalation.id">
                                    <TableCell>
                                        <Badge variant="outline">{{ escalation.escalation_type }}</Badge>
                                    </TableCell>
                                    <TableCell class="font-mono text-xs text-muted-foreground">{{ escalation.ritual_run_id }}</TableCell>
                                    <TableCell class="text-muted-foreground">
                                        {{ escalation.escalated_to_agent?.name ?? escalation.escalated_to_agent_id }}
                                    </TableCell>
                                    <TableCell class="text-muted-foreground">
                                        {{ escalation.created_at ? new Date(escalation.created_at).toLocaleString() : '-' }}
                                    </TableCell>
                                    <TableCell class="text-muted-foreground">
                                        {{ escalation.timeout_at ? new Date(escalation.timeout_at).toLocaleString() : '-' }}
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <Button variant="outline" size="sm" @click="resolveEscalation(escalation, 'approved')">
                                                Approve
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                class="text-destructive hover:text-destructive"
                                                @click="resolveEscalation(escalation, 'rejected')"
                                            >
                                                Reject
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>

                                <TableRow v-if="!loading && escalations.length === 0">
                                    <TableCell colspan="6" class="pt-16 pb-12">
                                        <div class="text-center text-muted-foreground">
                                            <AlertTriangle class="mx-auto mb-4 h-12 w-12 opacity-50" />
                                            <p class="text-lg font-medium">No pending escalations</p>
                                            <p class="text-sm">Escalations requiring your attention will appear here.</p>
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
