<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpHint from '@/Components/HelpHint.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardDescription from '@/Components/ui/CardDescription.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import { Head } from '@inertiajs/vue3';
import { Settings, Shield, Terminal, MessageSquare, Pencil, Eye, Save } from 'lucide-vue-next';
import axios from 'axios';
import { ref, onMounted, reactive } from 'vue';

const loading = ref(false);
const saving = ref(false);
const error = ref('');
const success = ref('');
const config = ref(null);
const schema = ref(null);
const mode = ref('view');
const formValues = reactive({});

const load = async () => {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await axios.get('/agent/api/v1/configuration');
        config.value = data.data;
        schema.value = data.data.schema;

        if (data.data.values) {
            Object.assign(formValues, data.data.values);
        }
    } catch (e) {
        error.value = e?.response?.data?.message ?? 'Failed to load configuration.';
    } finally {
        loading.value = false;
    }
};

const saveConfig = async () => {
    saving.value = true;
    error.value = '';
    success.value = '';

    const payload = {};
    for (const [key, value] of Object.entries(formValues)) {
        payload[key.replace(/\./g, '_')] = value;
    }

    try {
        const { data } = await axios.put('/agent/api/v1/configuration', payload);
        success.value = `Updated ${data.data.changes?.length ?? 0} setting(s).`;
        mode.value = 'view';
        await load();
    } catch (e) {
        error.value = e?.response?.data?.message ?? 'Failed to save configuration.';
    } finally {
        saving.value = false;
    }
};

onMounted(load);
</script>

<template>
    <AppLayout title="Configuration">
        <Head title="Configuration" />

        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <Settings class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-semibold text-foreground truncate">
                            Configuration
                        </h2>
                        <HelpHint
                            ui-key="settings.configuration"
                            short-text="View and edit runtime, messenger, security, and agent configuration."
                            learn-more-href="/docs/overview"
                        />
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        v-if="mode === 'view' && schema"
                        variant="outline"
                        size="sm"
                        @click="mode = 'edit'"
                    >
                        <Pencil class="h-4 w-4" />
                        Edit
                    </Button>
                    <Button
                        v-if="mode === 'edit'"
                        variant="outline"
                        size="sm"
                        @click="mode = 'view'"
                    >
                        <Eye class="h-4 w-4" />
                        View
                    </Button>
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-4xl space-y-6">
                <div v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    {{ error }}
                </div>
                <div v-if="success" class="rounded-md border border-green-500/50 bg-green-500/10 px-3 py-2 text-sm text-green-600">
                    {{ success }}
                </div>

                <p v-if="loading" class="text-sm text-muted-foreground">Loading configuration...</p>

                <!-- Edit mode: schema-driven form -->
                <template v-if="mode === 'edit' && schema">
                    <Card v-for="(section, sectionKey) in schema" :key="sectionKey">
                        <CardHeader>
                            <CardTitle>{{ section.label }}</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div
                                v-for="field in section.fields"
                                :key="field.key"
                                class="space-y-1"
                            >
                                <label :for="field.key" class="text-sm font-medium">
                                    {{ field.label }}
                                </label>
                                <p v-if="field.description" class="text-xs text-muted-foreground">
                                    {{ field.description }}
                                </p>

                                <select
                                    v-if="field.type === 'select'"
                                    :id="field.key"
                                    v-model="formValues[field.key]"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring"
                                >
                                    <option v-for="opt in field.options" :key="opt" :value="opt">
                                        {{ opt }}
                                    </option>
                                </select>

                                <input
                                    v-else-if="field.type === 'number'"
                                    :id="field.key"
                                    v-model.number="formValues[field.key]"
                                    type="number"
                                    :min="field.min"
                                    :max="field.max"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring"
                                />

                                <div v-else-if="field.type === 'boolean'" class="flex items-center gap-2">
                                    <input
                                        :id="field.key"
                                        v-model="formValues[field.key]"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-input text-primary focus:ring-ring"
                                    />
                                    <label :for="field.key" class="text-sm">
                                        {{ formValues[field.key] ? 'Enabled' : 'Disabled' }}
                                    </label>
                                </div>

                                <input
                                    v-else-if="field.type === 'url'"
                                    :id="field.key"
                                    v-model="formValues[field.key]"
                                    type="url"
                                    placeholder="https://..."
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring"
                                />

                                <input
                                    v-else
                                    :id="field.key"
                                    v-model="formValues[field.key]"
                                    type="text"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <div class="flex justify-end gap-2">
                        <Button variant="outline" @click="mode = 'view'">Cancel</Button>
                        <Button :disabled="saving" @click="saveConfig">
                            <Save class="h-4 w-4" />
                            {{ saving ? 'Saving...' : 'Save Changes' }}
                        </Button>
                    </div>
                </template>

                <!-- View mode: read-only cards -->
                <template v-if="mode === 'view' && config">
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Terminal class="h-4 w-4" />
                                Runtime
                            </CardTitle>
                            <CardDescription>Execution mode, approvals, tool policies, and session limits.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <dt class="text-xs font-medium text-muted-foreground">Default Mode</dt>
                                    <dd class="mt-0.5">
                                        <Badge :variant="config.runtime.default_mode === 'safe' ? 'secondary' : config.runtime.default_mode === 'full' ? 'destructive' : 'default'">
                                            {{ config.runtime.default_mode }}
                                        </Badge>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium text-muted-foreground">Approval Model</dt>
                                    <dd class="mt-0.5 text-sm">{{ config.runtime.approval_model }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium text-muted-foreground">Session Timeout</dt>
                                    <dd class="mt-0.5 text-sm">{{ config.runtime.session_timeout_minutes }} min</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium text-muted-foreground">Concurrent Session Limit</dt>
                                    <dd class="mt-0.5 text-sm">{{ config.runtime.concurrent_session_limit }}</dd>
                                </div>
                                <div class="sm:col-span-2">
                                    <dt class="text-xs font-medium text-muted-foreground">Tool Deny List</dt>
                                    <dd class="mt-0.5">
                                        <div v-if="config.runtime.tool_deny?.length" class="flex flex-wrap gap-1">
                                            <Badge v-for="t in config.runtime.tool_deny" :key="t" variant="destructive" class="font-mono text-[10px]">{{ t }}</Badge>
                                        </div>
                                        <span v-else class="text-xs text-muted-foreground">None (all tools allowed)</span>
                                    </dd>
                                </div>
                                <div class="sm:col-span-2">
                                    <dt class="text-xs font-medium text-muted-foreground">Tool Allow List</dt>
                                    <dd class="mt-0.5">
                                        <div v-if="config.runtime.tool_allow?.length" class="flex flex-wrap gap-1">
                                            <Badge v-for="t in config.runtime.tool_allow" :key="t" variant="default" class="font-mono text-[10px]">{{ t }}</Badge>
                                        </div>
                                        <span v-else class="text-xs text-muted-foreground">Not set (open)</span>
                                    </dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <MessageSquare class="h-4 w-4" />
                                Messenger
                            </CardTitle>
                            <CardDescription>Default runner, streaming behaviour.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <dt class="text-xs font-medium text-muted-foreground">Default Runner</dt>
                                    <dd class="mt-0.5 text-sm">{{ config.messenger.default_runner_type }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium text-muted-foreground">Streaming</dt>
                                    <dd class="mt-0.5">
                                        <Badge :variant="config.messenger.streaming_enabled ? 'default' : 'secondary'">
                                            {{ config.messenger.streaming_enabled ? 'Enabled' : 'Disabled' }}
                                        </Badge>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium text-muted-foreground">Chunk Size</dt>
                                    <dd class="mt-0.5 text-sm">{{ config.messenger.streaming_chunk_size }} chars</dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Shield class="h-4 w-4" />
                                Security
                            </CardTitle>
                            <CardDescription>Log redaction, 2FA, API tokens.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <div>
                                    <dt class="text-xs font-medium text-muted-foreground">Log Redaction</dt>
                                    <dd class="mt-0.5">
                                        <Badge :variant="config.security.log_redaction_enabled ? 'default' : 'destructive'">
                                            {{ config.security.log_redaction_enabled ? 'Enabled' : 'Disabled' }}
                                        </Badge>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium text-muted-foreground">Two-Factor Auth</dt>
                                    <dd class="mt-0.5">
                                        <Badge :variant="config.security.two_factor_available ? 'default' : 'secondary'">
                                            {{ config.security.two_factor_available ? 'Available' : 'Not available' }}
                                        </Badge>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium text-muted-foreground">API Tokens</dt>
                                    <dd class="mt-0.5">
                                        <Badge :variant="config.security.api_tokens_enabled ? 'default' : 'secondary'">
                                            {{ config.security.api_tokens_enabled ? 'Enabled' : 'Disabled' }}
                                        </Badge>
                                    </dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Terminal class="h-4 w-4" />
                                Agent
                            </CardTitle>
                            <CardDescription>Allowed directories, runners, forbidden env keys.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-xs font-medium text-muted-foreground">Allowed Working Directories</dt>
                                    <dd class="mt-0.5">
                                        <div v-if="config.agent.allowed_working_directory_bases?.length" class="flex flex-wrap gap-1">
                                            <Badge v-for="d in config.agent.allowed_working_directory_bases" :key="d" variant="outline" class="font-mono text-[10px]">{{ d }}</Badge>
                                        </div>
                                        <span v-else class="text-xs text-muted-foreground">None configured</span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium text-muted-foreground">Runner Executables</dt>
                                    <dd class="mt-0.5 flex flex-wrap gap-1">
                                        <Badge v-for="r in config.agent.runner_executables" :key="r" variant="outline" class="text-[10px]">{{ r }}</Badge>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium text-muted-foreground">Forbidden Env Keys</dt>
                                    <dd class="mt-0.5">
                                        <div v-if="config.agent.forbidden_env_keys?.length" class="flex flex-wrap gap-1">
                                            <Badge v-for="k in config.agent.forbidden_env_keys" :key="k" variant="destructive" class="font-mono text-[10px]">{{ k }}</Badge>
                                        </div>
                                        <span v-else class="text-xs text-muted-foreground">None</span>
                                    </dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
