<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpHint from '@/Components/HelpHint.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardDescription from '@/Components/ui/CardDescription.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Button from '@/Components/ui/Button.vue';
import Badge from '@/Components/ui/Badge.vue';
import { Head } from '@inertiajs/vue3';
import { UserCheck, Check, X } from 'lucide-vue-next';
import axios from 'axios';
import { ref, onMounted } from 'vue';

const loading = ref(false);
const error = ref('');
const pairings = ref([]);
const statusFilter = ref('pending');

const STATUSES = ['pending', 'approved', 'revoked'];

const load = async () => {
    loading.value = true;
    error.value = '';
    try {
        const params = {};
        if (statusFilter.value) params.status = statusFilter.value;
        const { data } = await axios.get('/agent/api/v1/messenger/pairings', { params });
        pairings.value = data.data;
    } catch (e) {
        error.value = e?.response?.data?.message ?? 'Failed to load pairings.';
    } finally {
        loading.value = false;
    }
};

const approve = async (id) => {
    try {
        await axios.post(`/agent/api/v1/messenger/pairings/${id}/approve`);
        load();
    } catch (e) {
        error.value = e?.response?.data?.error ?? 'Approve failed.';
    }
};

const revoke = async (id) => {
    try {
        await axios.post(`/agent/api/v1/messenger/pairings/${id}/revoke`);
        load();
    } catch (e) {
        error.value = e?.response?.data?.error ?? 'Revoke failed.';
    }
};

const badgeVariant = (status) => {
    if (status === 'approved') return 'default';
    if (status === 'revoked') return 'destructive';
    return 'secondary';
};

const fmtDate = (iso) => {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
};

onMounted(load);
</script>

<template>
    <AppLayout title="Pairings">
        <Head title="Pairings" />

        <template #header>
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                    <UserCheck class="h-5 w-5 text-primary" />
                </div>
                <div class="flex items-center gap-2">
                    <h2 class="text-xl font-semibold leading-tight text-foreground">
                        Identity Pairings
                    </h2>
                    <HelpHint
                        ui-key="messenger.pairings"
                        short-text="Manage messenger identity pairings. Approve or revoke DM access per user per connector."
                        learn-more-href="/docs/overview"
                    />
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-5xl space-y-4">
                <div v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    {{ error }}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Pairings</CardTitle>
                        <CardDescription>
                            When a connector's DM policy is set to "pairing", users must be approved before they can DM the agent. Approve or revoke access here.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-for="s in STATUSES"
                                :key="s"
                                size="sm"
                                :variant="statusFilter === s ? 'default' : 'outline'"
                                @click="statusFilter = s; load()"
                            >
                                {{ s.charAt(0).toUpperCase() + s.slice(1) }}
                            </Button>
                            <Button
                                size="sm"
                                :variant="!statusFilter ? 'default' : 'outline'"
                                @click="statusFilter = ''; load()"
                            >
                                All
                            </Button>
                        </div>

                        <div v-if="pairings.length" class="overflow-x-auto rounded-md border">
                            <table class="w-full text-sm">
                                <thead class="border-b bg-muted/50">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium">User</th>
                                        <th class="px-3 py-2 text-left font-medium">Connector</th>
                                        <th class="px-3 py-2 text-left font-medium">Status</th>
                                        <th class="px-3 py-2 text-left font-medium">Created</th>
                                        <th class="px-3 py-2 text-left font-medium">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="p in pairings"
                                        :key="p.id"
                                        class="border-b last:border-0"
                                    >
                                        <td class="px-3 py-2 text-xs">
                                            {{ p.provider_username || p.provider_user_id }}
                                        </td>
                                        <td class="px-3 py-2 text-xs text-muted-foreground">
                                            {{ p.connector_name }} ({{ p.connector_provider }})
                                        </td>
                                        <td class="px-3 py-2">
                                            <Badge :variant="badgeVariant(p.status)" class="text-xs">
                                                {{ p.status }}
                                            </Badge>
                                        </td>
                                        <td class="px-3 py-2 text-xs text-muted-foreground whitespace-nowrap">
                                            {{ fmtDate(p.created_at) }}
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="flex gap-1">
                                                <Button
                                                    v-if="p.status === 'pending'"
                                                    size="sm"
                                                    variant="default"
                                                    @click="approve(p.id)"
                                                >
                                                    <Check class="h-3 w-3 mr-1" />
                                                    Approve
                                                </Button>
                                                <Button
                                                    v-if="p.status !== 'revoked'"
                                                    size="sm"
                                                    variant="outline"
                                                    @click="revoke(p.id)"
                                                >
                                                    <X class="h-3 w-3 mr-1" />
                                                    Revoke
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <p v-else-if="!loading" class="text-sm text-muted-foreground">
                            No {{ statusFilter || '' }} pairings found.
                        </p>
                        <p v-if="loading" class="text-sm text-muted-foreground">Loading...</p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
