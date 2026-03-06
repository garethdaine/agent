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
import { Scale, ArrowLeft, Plus, AlertTriangle, Bot } from 'lucide-vue-next';
import HelpHint from '@/Components/HelpHint.vue';
import { confirmDialog } from '@/Support/confirmDialog';

const councils = ref([]);
const loading = ref(true);
const error = ref('');
const includeArchived = ref(false);

const loadCouncils = async () => {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get('/agent/api/v1/org/councils', {
            params: {
                include_archived: includeArchived.value,
            },
        });

        councils.value = data.data ?? [];
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load council templates.';
    } finally {
        loading.value = false;
    }
};

const archiveCouncil = async (council) => {
    const approved = await confirmDialog(`Archive council "${council.name}"?`, {
        title: 'Archive Council',
        confirmText: 'Archive',
        confirmVariant: 'destructive',
    });

    if (!approved) {
        return;
    }

    try {
        await axios.delete(`/agent/api/v1/org/councils/${council.id}`);
        await loadCouncils();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to archive council.';
    }
};

onMounted(loadCouncils);
</script>

<template>
    <AppLayout title="Councils">
        <Head title="Councils" />

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
                        <h2 class="text-base font-semibold text-foreground truncate">Councils</h2>
                        <HelpHint
                            ui-key="org.councils"
                            short-text="Manage agent councils and governance structures."
                            learn-more-href="/docs/overview"
                        />
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="outline" @click="includeArchived = !includeArchived; loadCouncils()">
                        {{ includeArchived ? 'Hide Archived' : 'Show Archived' }}
                    </Button>
                    <Link :href="route('org.councils.create')">
                        <Button>
                            <Plus class="mr-2 h-4 w-4" />
                            Create Council
                        </Button>
                    </Link>
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="space-y-4">
                <p v-if="error && councils.length > 0" class="rounded-lg border border-destructive bg-destructive/10 px-4 py-3 text-sm text-destructive">
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

                <Card v-else-if="error && councils.length === 0">
                    <CardContent class="pt-16 pb-12">
                        <div class="text-center text-muted-foreground">
                            <AlertTriangle class="mx-auto mb-4 h-12 w-12 opacity-50" />
                            <p class="text-lg font-medium text-destructive">Failed to load council templates</p>
                            <p class="text-sm">{{ error }}</p>
                            <Button class="mt-4" variant="outline" @click="loadCouncils">
                                Retry
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card v-else-if="councils.length === 0">
                    <CardContent class="pt-16 pb-12">
                        <div class="text-center text-muted-foreground">
                            <Scale class="mx-auto mb-4 h-12 w-12 opacity-50" />
                            <p class="text-lg font-medium">No councils yet</p>
                            <p class="text-sm">Create a council template to orchestrate multi-agent decisions.</p>
                            <Link :href="route('org.councils.create')" class="mt-4 inline-block">
                                <Button>
                                    <Plus class="mr-2 h-4 w-4" />
                                    Create Council
                                </Button>
                            </Link>
                        </div>
                    </CardContent>
                </Card>

                <Card v-else class="overflow-hidden">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Synthesis Mode</TableHead>
                                    <TableHead>Members</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead class="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="council in councils" :key="council.id">
                                    <TableCell>
                                        <div class="font-medium text-foreground">{{ council.name }}</div>
                                        <div class="text-xs text-muted-foreground">{{ council.description || 'No description' }}</div>
                                    </TableCell>
                                    <TableCell class="text-muted-foreground">{{ council.synthesis_mode }}</TableCell>
                                    <TableCell class="text-muted-foreground">{{ council.member_list?.length ?? 0 }}</TableCell>
                                    <TableCell>
                                        <Badge :variant="council.archived_at ? 'outline' : 'default'">
                                            {{ council.archived_at ? 'Archived' : 'Active' }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <Button
                                            v-if="!council.archived_at"
                                            variant="outline"
                                            size="sm"
                                            class="text-destructive hover:text-destructive"
                                            @click="archiveCouncil(council)"
                                        >
                                            Archive
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
