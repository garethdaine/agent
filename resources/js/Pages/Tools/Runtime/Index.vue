<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpHint from '@/Components/HelpHint.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardDescription from '@/Components/ui/CardDescription.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Badge from '@/Components/ui/Badge.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Sliders } from 'lucide-vue-next';
import axios from 'axios';
import { ref, onMounted } from 'vue';
import Button from '@/Components/ui/Button.vue';

const loading = ref(true);
const error = ref('');
const policy = ref(null);

const loadPolicy = async () => {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await axios.get('/agent/api/v1/runtime/policy');
        policy.value = data.data;
    } catch (e) {
        error.value = e?.response?.data?.message ?? 'Failed to load runtime policy.';
    } finally {
        loading.value = false;
    }
};

onMounted(loadPolicy);
</script>

<template>
    <AppLayout title="Runtime">
        <Head title="Runtime" />

        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <Sliders class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-semibold text-foreground truncate">
                            Runtime
                        </h2>
                        <HelpHint
                            ui-key="runtime.policy"
                            short-text="Default mode and tool deny/allow list. Configure in config/runtime.php or RUNTIME_TOOL_DENY / RUNTIME_TOOL_ALLOW."
                            learn-more-href="/docs/overview"
                        />
                    </div>
                </div>
                <Link :href="route('tools.index')">
                    <Button variant="outline" size="sm">
                        <ArrowLeft class="h-4 w-4" />
                        Back
                    </Button>
                </Link>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-4xl space-y-4">
                <div v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    {{ error }}
                </div>

                <Card v-if="policy">
                    <CardHeader>
                        <CardTitle>Policy</CardTitle>
                        <CardDescription>
                            Default execution mode and tool access. Denied tools are blocked even when the mode allows them. Edit in <code class="rounded bg-muted px-1 py-0.5 text-xs">config/runtime.php</code> or set <code class="rounded bg-muted px-1 py-0.5 text-xs">RUNTIME_TOOL_DENY</code> / <code class="rounded bg-muted px-1 py-0.5 text-xs">RUNTIME_TOOL_ALLOW</code>.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div>
                            <h3 class="text-sm font-medium text-foreground mb-1">Default mode</h3>
                            <Badge variant="secondary">{{ policy.default_mode }}</Badge>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Modes: {{ policy.modes?.join(', ') ?? '—' }}
                            </p>
                        </div>

                        <div v-if="policy.tool_allowlist_active">
                            <h3 class="text-sm font-medium text-foreground mb-1">Tool allow list (active)</h3>
                            <p class="text-xs text-muted-foreground mb-2">Only these tools are allowed.</p>
                            <ul v-if="policy.tool_allow?.length" class="list-inside list-disc text-sm text-muted-foreground space-y-0.5">
                                <li v-for="t in policy.tool_allow" :key="t" class="font-mono">{{ t }}</li>
                            </ul>
                            <p v-else class="text-sm text-muted-foreground">(empty — no tools allowed)</p>
                        </div>

                        <div>
                            <h3 class="text-sm font-medium text-foreground mb-1">Tool deny list</h3>
                            <p class="text-xs text-muted-foreground mb-2">These tools are never allowed (adapter or qualified name, e.g. fs.write).</p>
                            <ul v-if="policy.tool_deny?.length" class="list-inside list-disc text-sm text-muted-foreground space-y-0.5">
                                <li v-for="t in policy.tool_deny" :key="t" class="font-mono">{{ t }}</li>
                            </ul>
                            <p v-else class="text-sm text-muted-foreground">(none)</p>
                        </div>
                    </CardContent>
                </Card>

                <Card v-else-if="!loading && !error">
                    <CardContent class="py-8 text-center text-muted-foreground">
                        No policy data.
                    </CardContent>
                </Card>

                <Card v-else-if="loading">
                    <CardContent class="py-8 text-center text-muted-foreground">
                        Loading…
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
