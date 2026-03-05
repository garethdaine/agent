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
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Shield, ShieldCheck, ShieldAlert, Info } from 'lucide-vue-next';
import axios from 'axios';
import { ref } from 'vue';

const loading = ref(false);
const error = ref('');
const result = ref(null);

const runAudit = async () => {
    loading.value = true;
    error.value = '';
    result.value = null;

    try {
        const { data } = await axios.get('/agent/api/v1/security/audit');
        result.value = data.data;
    } catch (e) {
        error.value = e?.response?.data?.message ?? 'Failed to run security audit.';
    } finally {
        loading.value = false;
    }
};

const severityIcon = (severity) => {
    switch (severity) {
        case 'critical':
            return ShieldAlert;
        case 'warn':
            return Shield;
        default:
            return Info;
    }
};

const severityVariant = (severity) => {
    switch (severity) {
        case 'critical':
            return 'destructive';
        case 'warn':
            return 'secondary';
        default:
            return 'outline';
    }
};
</script>

<template>
    <AppLayout title="Security">
        <Head title="Security" />

        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <ShieldCheck class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-semibold text-foreground truncate">
                            Security
                        </h2>
                        <HelpHint
                            ui-key="security.audit"
                            short-text="Run security checks on config, runtime, and logging. Fix any critical findings."
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

                <Card>
                    <CardHeader>
                        <CardTitle>Security Audit</CardTitle>
                        <CardDescription>
                            Run checks against config, runtime defaults, and logging. Critical findings should be fixed before production.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <Button
                            :disabled="loading"
                            @click="runAudit"
                        >
                            {{ loading ? 'Running audit...' : 'Run audit' }}
                        </Button>

                        <div v-if="result" class="space-y-4">
                            <div class="flex flex-wrap gap-2">
                                <Badge
                                    :variant="result.summary.ok ? 'default' : 'destructive'"
                                    class="gap-1"
                                >
                                    <ShieldCheck v-if="result.summary.ok" class="h-3 w-3" />
                                    <ShieldAlert v-else class="h-3 w-3" />
                                    {{ result.summary.ok ? 'No critical issues' : `${result.summary.critical} critical` }}
                                </Badge>
                                <Badge v-if="result.summary.warn > 0" variant="secondary">
                                    {{ result.summary.warn }} warning(s)
                                </Badge>
                                <Badge v-if="result.summary.info > 0" variant="outline">
                                    {{ result.summary.info }} info
                                </Badge>
                            </div>

                            <div v-if="result.findings.length === 0" class="text-sm text-muted-foreground">
                                No findings.
                            </div>

                            <div v-else class="overflow-x-auto rounded-md border">
                                <table class="w-full text-sm">
                                    <thead class="border-b bg-muted/50">
                                        <tr>
                                            <th class="px-3 py-2 text-left font-medium">Check</th>
                                            <th class="px-3 py-2 text-left font-medium">Severity</th>
                                            <th class="px-3 py-2 text-left font-medium">Message</th>
                                            <th class="px-3 py-2 text-left font-medium">Fix</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(f, i) in result.findings"
                                            :key="i"
                                            class="border-b last:border-0"
                                        >
                                            <td class="px-3 py-2 font-mono text-xs">
                                                {{ f.check_id }}
                                            </td>
                                            <td class="px-3 py-2">
                                                <Badge :variant="severityVariant(f.severity)">
                                                    {{ f.severity }}
                                                </Badge>
                                            </td>
                                            <td class="px-3 py-2 text-muted-foreground">
                                                {{ f.message }}
                                            </td>
                                            <td class="px-3 py-2 text-muted-foreground">
                                                {{ f.fix ?? '—' }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
