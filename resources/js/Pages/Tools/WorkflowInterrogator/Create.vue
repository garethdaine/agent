<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpHint from '@/Components/HelpHint.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardDescription from '@/Components/ui/CardDescription.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import DirectoryPickerInput from '@/Components/ui/DirectoryPickerInput.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Waypoints } from 'lucide-vue-next';
import axios from 'axios';
import { computed, reactive, ref } from 'vue';

const form = reactive({
    name: '',
    runner_type: 'claude',
    model: '',
    project_directory: '/Users/garethdaine/Code/agent',
    interrogation_mode: 'workflow',
    company_name: '',
    company_description: '',
    workflow_title: '',
    workflow_brief: '',
    target_teams_text: '',
    systems_text: '',
    attachments: [],
});

const availableModels = ref([]);
const loadingModels = ref(false);
const validation = ref({});
const error = ref('');
const submitting = ref(false);

const parseLines = (value) => String(value ?? '')
    .split('\n')
    .map((entry) => entry.trim())
    .filter(Boolean);

const fetchModels = async () => {
    loadingModels.value = true;

    try {
        const { data } = await axios.get('/agent/api/v1/interrogation/runner-models', {
            params: { runner_type: form.runner_type },
        });
        availableModels.value = Array.isArray(data?.data) ? data.data : [];
        form.model = data?.default ?? availableModels.value?.[0]?.id ?? '';
    } catch {
        availableModels.value = [];
        form.model = '';
    } finally {
        loadingModels.value = false;
    }
};

fetchModels();

const targetTeamsPreview = computed(() => parseLines(form.target_teams_text));
const systemsPreview = computed(() => parseLines(form.systems_text));

const handleAttachmentsSelected = (event) => {
    const files = Array.from(event?.target?.files ?? []);
    form.attachments = files;
};

const removeAttachment = (index) => {
    form.attachments.splice(index, 1);
};

const submit = async () => {
    submitting.value = true;
    validation.value = {};
    error.value = '';

    try {
        const payload = {
            name: form.name || null,
            runner_type: form.runner_type,
            model: form.runner_type === 'custom' ? null : (form.model || null),
            project_directory: form.project_directory.trim(),
            interrogation_mode: form.interrogation_mode,
            company_name: form.company_name.trim(),
            company_description: form.company_description.trim() || null,
            workflow_title: form.workflow_title.trim(),
            workflow_brief: form.workflow_brief.trim(),
            target_teams: targetTeamsPreview.value,
            systems: systemsPreview.value,
        };

        let data;

        if (form.attachments.length > 0) {
            const formData = new FormData();
            Object.entries(payload).forEach(([key, value]) => {
                if (Array.isArray(value)) {
                    value.forEach((item) => formData.append(`${key}[]`, item));
                    return;
                }

                if (value !== null && value !== undefined) {
                    formData.append(key, value);
                }
            });

            form.attachments.forEach((file) => formData.append('attachments[]', file));

            ({ data } = await axios.post('/agent/api/v1/workflow-interrogator/sessions', formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            }));
        } else {
            ({ data } = await axios.post('/agent/api/v1/workflow-interrogator/sessions', payload));
        }
        const id = data?.data?.id;

        if (id) {
            router.visit(route('tools.workflow-interrogator.wizard', id));
            return;
        }

        router.visit(route('tools.workflow-interrogator.index'));
    } catch (e) {
        const payload = e?.response?.data ?? {};
        const envelope = payload?.error ?? null;

        if (envelope) {
            validation.value = envelope?.details ?? {};
            error.value = envelope?.message ?? 'Failed to create workflow interrogation session.';
        } else if (payload?.errors && typeof payload.errors === 'object') {
            validation.value = payload.errors;
            error.value = payload?.message ?? 'The given data was invalid.';
        } else {
            error.value = payload?.message ?? 'Failed to create workflow interrogation session.';
        }
    } finally {
        submitting.value = false;
    }
};
</script>

<template>
    <AppLayout title="New Workflow Interrogation">
        <Head title="New Workflow Interrogation" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <Waypoints class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-semibold text-foreground truncate">New Workflow Interrogation</h2>
                        <HelpHint
                            ui-key="workflow-interrogator.create"
                            short-text="Create a standalone workflow discovery session and choose the runner for that session."
                            learn-more-href="/docs/overview"
                        />
                    </div>
                </div>
                <Link :href="route('tools.workflow-interrogator.index')">
                    <Button variant="outline" size="sm">
                        <ArrowLeft class="h-4 w-4" />
                        Back
                    </Button>
                </Link>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-5xl space-y-4">
                <Card class="border-primary/20 bg-linear-to-br from-primary/[0.08] via-background to-background">
                    <CardHeader>
                        <CardTitle>Additive-only workflow discovery</CardTitle>
                        <CardDescription>
                            This feature is separate from Requirements Discovery. Start from the brief and supporting context, then interrogate the workflow in iterative batches until ambiguity is materially exhausted.
                        </CardDescription>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Session setup</CardTitle>
                        <CardDescription>
                            Provide the workflow brief, the selected working folder, and any supporting files or images. The interrogator is expected to close the remaining gaps through iterative rounds rather than relying on a perfect initial brief.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-5">
                        <div v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                            {{ error }}
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-foreground">Internal session name</label>
                                <Input v-model="form.name" class="mt-1" type="text" />
                                <p v-if="validation.name" class="mt-1 text-sm text-destructive">{{ validation.name[0] }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-foreground">Interrogation mode</label>
                                <select
                                    v-model="form.interrogation_mode"
                                    class="mt-1 flex h-9 w-full rounded-md border border-input bg-input-background px-3 py-1 text-sm shadow-xs focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    <option value="workflow">New workflow / automation opportunity</option>
                                    <option value="general">Existing workflow / general concern</option>
                                </select>
                                <p v-if="validation.interrogation_mode" class="mt-1 text-sm text-destructive">{{ validation.interrogation_mode[0] }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-foreground">Runner</label>
                                <select
                                    v-model="form.runner_type"
                                    class="mt-1 flex h-9 w-full rounded-md border border-input bg-input-background px-3 py-1 text-sm shadow-xs focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring"
                                    @change="fetchModels"
                                >
                                    <option value="claude">claude</option>
                                    <option value="codex">codex</option>
                                    <option value="custom">custom</option>
                                </select>
                                <p class="mt-1 text-xs text-muted-foreground">Select the runner this session should use. Custom uses the configured custom executable and generated markdown prompts.</p>
                                <p v-if="validation.runner_type" class="mt-1 text-sm text-destructive">{{ validation.runner_type[0] }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-foreground">Model</label>
                                <select
                                    v-model="form.model"
                                    class="mt-1 flex h-9 w-full rounded-md border border-input bg-input-background px-3 py-1 text-sm shadow-xs focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50"
                                    :disabled="loadingModels || form.runner_type === 'custom'"
                                >
                                    <option v-if="loadingModels" value="">Loading models...</option>
                                    <option v-else-if="form.runner_type === 'custom'" value="">No model selection for custom runner</option>
                                    <option v-for="model in availableModels" :key="model.id" :value="model.id">
                                        {{ model.name }}
                                    </option>
                                </select>
                                <p class="mt-1 text-xs text-muted-foreground" v-if="form.runner_type === 'custom'">Custom runner sessions do not use a managed model list.</p>
                                <p v-if="validation.model" class="mt-1 text-sm text-destructive">{{ validation.model[0] }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-foreground">Selected working folder</label>
                            <DirectoryPickerInput
                                v-model="form.project_directory"
                                class="mt-1"
                                label="Selected Working Folder"
                                mode="directory"
                                placeholder="/absolute/path/to/project"
                            />
                            <p class="mt-1 text-xs text-muted-foreground">Used as optional supporting context. Workflow Interrogator should start from the brief first, not default to codebase inspection.</p>
                            <p v-if="validation.project_directory" class="mt-1 text-sm text-destructive">{{ validation.project_directory[0] }}</p>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-foreground">Company name</label>
                                <Input v-model="form.company_name" class="mt-1" type="text" />
                                <p v-if="validation.company_name" class="mt-1 text-sm text-destructive">{{ validation.company_name[0] }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-foreground">Workflow title</label>
                                <Input v-model="form.workflow_title" class="mt-1" type="text" />
                                <p v-if="validation.workflow_title" class="mt-1 text-sm text-destructive">{{ validation.workflow_title[0] }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-foreground">Company description</label>
                            <Textarea v-model="form.company_description" class="mt-1 min-h-24" />
                            <p v-if="validation.company_description" class="mt-1 text-sm text-destructive">{{ validation.company_description[0] }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-foreground">Workflow brief</label>
                            <Textarea v-model="form.workflow_brief" class="mt-1 min-h-40" />
                            <p class="mt-1 text-xs text-muted-foreground">Describe the current workflow, what is painful, what should improve, and any constraints you already know.</p>
                            <p v-if="validation.workflow_brief" class="mt-1 text-sm text-destructive">{{ validation.workflow_brief[0] }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-foreground">Supporting files / images</label>
                            <input
                                type="file"
                                multiple
                                accept=".txt,.md,.markdown,.csv,.json,.yaml,.yml,.pdf,image/png,image/jpeg,image/webp,image/gif"
                                class="mt-1 block w-full cursor-pointer rounded border border-input bg-card px-3 py-2 text-xs text-foreground file:mr-3 file:rounded file:border-0 file:bg-primary file:px-3 file:py-1 file:text-xs file:font-semibold file:text-white hover:file:bg-primary/50"
                                @change="handleAttachmentsSelected"
                            >
                            <p class="mt-1 text-xs text-muted-foreground">Optional. Upload SOPs, screenshots, notes, diagrams, exports, or other context the interrogator should use.</p>
                            <p v-if="validation.attachments" class="mt-1 text-sm text-destructive">{{ validation.attachments[0] }}</p>
                            <p v-if="validation['attachments.0']" class="mt-1 text-sm text-destructive">{{ validation['attachments.0'][0] }}</p>
                            <div v-if="form.attachments.length > 0" class="mt-3 flex flex-wrap gap-2">
                                <span
                                    v-for="(file, index) in form.attachments"
                                    :key="`${file.name}-${index}`"
                                    class="inline-flex items-center gap-2 rounded border border-primary/30 bg-primary/5 px-2 py-1 text-xs text-primary"
                                >
                                    <span>{{ file.name }}</span>
                                    <button
                                        type="button"
                                        class="rounded px-1 text-[10px] font-semibold hover:bg-primary/10"
                                        @click="removeAttachment(index)"
                                    >
                                        Remove
                                    </button>
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-foreground">Target teams</label>
                                <Textarea
                                    v-model="form.target_teams_text"
                                    class="mt-1 min-h-32"
                                    placeholder="One team or function per line"
                                />
                                <p v-if="validation.target_teams" class="mt-1 text-sm text-destructive">{{ validation.target_teams[0] }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-foreground">Current systems and tools</label>
                                <Textarea
                                    v-model="form.systems_text"
                                    class="mt-1 min-h-32"
                                    placeholder="One system or tool per line"
                                />
                                <p v-if="validation.systems" class="mt-1 text-sm text-destructive">{{ validation.systems[0] }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                            <div class="rounded-lg border border-border bg-muted/20 p-4">
                                <h3 class="text-sm font-semibold text-foreground">Target teams preview</h3>
                                <ul class="mt-2 space-y-1 text-sm text-muted-foreground">
                                    <li v-for="team in targetTeamsPreview" :key="team">• {{ team }}</li>
                                    <li v-if="targetTeamsPreview.length === 0">No teams added yet.</li>
                                </ul>
                            </div>
                            <div class="rounded-lg border border-border bg-muted/20 p-4">
                                <h3 class="text-sm font-semibold text-foreground">Systems preview</h3>
                                <ul class="mt-2 space-y-1 text-sm text-muted-foreground">
                                    <li v-for="system in systemsPreview" :key="system">• {{ system }}</li>
                                    <li v-if="systemsPreview.length === 0">No systems added yet.</li>
                                </ul>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <Button :disabled="submitting" @click="submit">
                                {{ submitting ? 'Creating session...' : 'Create session' }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
