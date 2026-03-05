<script setup>
import { onMounted, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Table from '@/Components/ui/Table.vue';
import TableHeader from '@/Components/ui/TableHeader.vue';
import TableBody from '@/Components/ui/TableBody.vue';
import TableRow from '@/Components/ui/TableRow.vue';
import TableHead from '@/Components/ui/TableHead.vue';
import TableCell from '@/Components/ui/TableCell.vue';
import Button from '@/Components/ui/Button.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import { DollarSign, ArrowLeft, Bot } from 'lucide-vue-next';
import HelpHint from '@/Components/HelpHint.vue';

const loading = ref(true);
const error = ref('');
const summary = ref(null);

const loadSummary = async () => {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get('/agent/api/v1/org/costs/summary');
        summary.value = data.data ?? null;
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load cost summary.';
    } finally {
        loading.value = false;
    }
};

onMounted(loadSummary);
</script>

<template>
    <AppLayout title="Costs">
        <Head title="Costs" />

        <template #header>
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
                    <h2 class="text-base font-semibold text-foreground truncate">Costs</h2>
                    <HelpHint
                        ui-key="org.costs"
                        short-text="Review cost allocation across the org layer."
                        learn-more-href="/docs/overview"
                    />
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="space-y-4">
                <p v-if="error" class="rounded-lg border border-destructive bg-destructive/10 px-4 py-3 text-sm text-destructive">
                    {{ error }}
                </p>

                <Card v-if="loading">
                    <CardContent class="space-y-3 py-8">
                        <Skeleton class="h-4 w-52" />
                        <Skeleton class="h-4 w-40" />
                        <Skeleton class="h-24 w-full" />
                    </CardContent>
                </Card>

                <template v-else-if="summary">
                    <div class="grid gap-4 md:grid-cols-3">
                        <Card>
                            <CardHeader>
                                <CardTitle>Total Cost (USD)</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p class="text-2xl font-semibold text-foreground">${{ Number(summary.total.total_cost_usd ?? 0).toFixed(4) }}</p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>Total Tokens</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p class="text-2xl font-semibold text-foreground">{{ Number(summary.total.total_tokens ?? 0).toLocaleString() }}</p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>Total Runtime (ms)</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p class="text-2xl font-semibold text-foreground">{{ Number(summary.total.total_runtime_ms ?? 0).toLocaleString() }}</p>
                            </CardContent>
                        </Card>
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle>Per Agent</CardTitle>
                        </CardHeader>
                        <CardContent class="p-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Agent ID</TableHead>
                                        <TableHead>Tokens</TableHead>
                                        <TableHead>Runtime (ms)</TableHead>
                                        <TableHead>Cost (USD)</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="item in (summary.per_agent ?? [])" :key="item.agent_id">
                                        <TableCell class="font-mono text-xs">{{ item.agent_id }}</TableCell>
                                        <TableCell>{{ Number(item.total_tokens ?? 0).toLocaleString() }}</TableCell>
                                        <TableCell>{{ Number(item.total_runtime_ms ?? 0).toLocaleString() }}</TableCell>
                                        <TableCell>${{ Number(item.total_cost_usd ?? 0).toFixed(4) }}</TableCell>
                                    </TableRow>
                                    <TableRow v-if="!summary.per_agent || summary.per_agent.length === 0">
                                        <TableCell colspan="4" class="pt-16 pb-12 text-center text-muted-foreground">No per-agent cost data yet.</TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Per Ritual Run</CardTitle>
                        </CardHeader>
                        <CardContent class="p-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Ritual Run ID</TableHead>
                                        <TableHead>Tokens</TableHead>
                                        <TableHead>Runtime (ms)</TableHead>
                                        <TableHead>Cost (USD)</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="item in (summary.per_ritual_run ?? [])" :key="item.ritual_run_id">
                                        <TableCell class="font-mono text-xs">{{ item.ritual_run_id }}</TableCell>
                                        <TableCell>{{ Number(item.total_tokens ?? 0).toLocaleString() }}</TableCell>
                                        <TableCell>{{ Number(item.total_runtime_ms ?? 0).toLocaleString() }}</TableCell>
                                        <TableCell>${{ Number(item.total_cost_usd ?? 0).toFixed(4) }}</TableCell>
                                    </TableRow>
                                    <TableRow v-if="!summary.per_ritual_run || summary.per_ritual_run.length === 0">
                                        <TableCell colspan="4" class="pt-16 pb-12 text-center text-muted-foreground">No per-ritual cost data yet.</TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </template>

                <Card v-else>
                    <CardContent class="pt-16 pb-12">
                        <div class="text-center text-muted-foreground">
                            <DollarSign class="mx-auto mb-4 h-12 w-12 opacity-50" />
                            <p class="text-lg font-medium">No cost data yet</p>
                            <p class="text-sm">Cost tracking and summaries will appear here.</p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
