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
import { Users, Plus, ArrowLeft, Pencil, ArchiveRestore, Archive, AlertTriangle } from 'lucide-vue-next';
import { confirmDialog } from '@/Support/confirmDialog';

const agents = ref([]);
const loading = ref(true);
const error = ref('');
const includeArchived = ref(false);

const loadAgents = async () => {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get('/agent/api/v1/org/agents', {
            params: {
                include_archived: includeArchived.value,
            },
        });

        agents.value = data.data ?? [];
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load agents.';
    } finally {
        loading.value = false;
    }
};

const archiveAgent = async (agent) => {
    const approved = await confirmDialog(`Archive agent "${agent.name}"?`, {
        title: 'Archive Agent',
        confirmText: 'Archive',
        confirmVariant: 'destructive',
    });

    if (!approved) {
        return;
    }

    try {
        await axios.delete(`/agent/api/v1/org/agents/${agent.id}`);
        await loadAgents();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to archive agent.';
    }
};

const restoreAgent = async (agent) => {
    try {
        await axios.post(`/agent/api/v1/org/agents/${agent.id}/restore`);
        await loadAgents();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to restore agent.';
    }
};

onMounted(loadAgents);
</script>

<template>
    <AppLayout title="Agents">
        <Head title="Agents" />

        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link :href="route('org.index')">
                        <Button variant="ghost" size="sm">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <h2 class="text-xl font-semibold leading-tight text-foreground">Agents</h2>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="outline" @click="includeArchived = !includeArchived; loadAgents()">
                        {{ includeArchived ? 'Hide Archived' : 'Show Archived' }}
                    </Button>
                    <Link :href="route('org.agents.create')">
                        <Button>
                            <Plus class="mr-2 h-4 w-4" />
                            Create Agent
                        </Button>
                    </Link>
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-[1440px] space-y-4">
                <p v-if="error && agents.length > 0" class="rounded-lg border border-destructive bg-destructive/10 px-4 py-3 text-sm text-destructive">
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

                <Card v-else-if="error && agents.length === 0">
                    <CardContent class="pt-16 pb-12">
                        <div class="text-center text-muted-foreground">
                            <AlertTriangle class="mx-auto mb-4 h-12 w-12 opacity-50" />
                            <p class="text-lg font-medium text-destructive">Failed to load agents</p>
                            <p class="text-sm">{{ error }}</p>
                            <Button class="mt-4" variant="outline" @click="loadAgents">
                                Retry
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card v-else-if="agents.length === 0">
                    <CardContent class="pt-16 pb-12">
                        <div class="text-center text-muted-foreground">
                            <Users class="mx-auto mb-4 h-12 w-12 opacity-50" />
                            <p class="text-lg font-medium">No agents yet</p>
                            <p class="text-sm">Create your first agent to get started.</p>
                            <Link :href="route('org.agents.create')" class="mt-4 inline-block">
                                <Button>
                                    <Plus class="mr-2 h-4 w-4" />
                                    Create Agent
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
                                    <TableHead>Role</TableHead>
                                    <TableHead>Delegatee Profile</TableHead>
                                    <TableHead>Capabilities</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead class="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="agent in agents" :key="agent.id">
                                    <TableCell>
                                        <div class="font-medium text-foreground">{{ agent.name }}</div>
                                        <div class="text-xs text-muted-foreground">{{ agent.role_description }}</div>
                                    </TableCell>
                                    <TableCell class="text-muted-foreground">{{ agent.role_slug }}</TableCell>
                                    <TableCell class="text-muted-foreground">
                                        {{ agent.delegatee_profile?.name ?? 'Unknown profile' }}
                                    </TableCell>
                                    <TableCell>
                                        <div class="flex flex-wrap gap-1">
                                            <Badge
                                                v-for="capability in (agent.capability_bindings ?? [])"
                                                :key="`${agent.id}-${capability}`"
                                                variant="secondary"
                                            >
                                                {{ capability }}
                                            </Badge>
                                            <span
                                                v-if="!agent.capability_bindings || agent.capability_bindings.length === 0"
                                                class="text-sm text-muted-foreground"
                                            >
                                                None
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge :variant="agent.archived_at ? 'outline' : 'default'">
                                            {{ agent.archived_at ? 'Archived' : 'Active' }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <Link :href="route('org.agents.edit', agent.id)">
                                                <Button variant="outline" size="sm" :disabled="!!agent.archived_at">
                                                    <Pencil class="mr-1 h-3 w-3" />
                                                    Edit
                                                </Button>
                                            </Link>
                                            <Button
                                                v-if="!agent.archived_at"
                                                variant="outline"
                                                size="sm"
                                                class="text-destructive hover:text-destructive"
                                                @click="archiveAgent(agent)"
                                            >
                                                <Archive class="mr-1 h-3 w-3" />
                                                Archive
                                            </Button>
                                            <Button v-else variant="outline" size="sm" @click="restoreAgent(agent)">
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
