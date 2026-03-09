<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import DirectoryPickerInput from '@/Components/ui/DirectoryPickerInput.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Toggle from '@/Components/ui/Toggle.vue';
import { ArrowLeft, GitBranch, RefreshCw, Search, Settings2 } from 'lucide-vue-next';
import HelpHint from '@/Components/HelpHint.vue';
import axios from 'axios';
import { reactive, ref, watch } from 'vue';

const form = reactive({
    name: '',
    runner_type: 'claude',
    model: '',
    project_directory: '/Users/garethdaine/Code/agent',
    interrogation_type: 'feature',
    feature_brief: '',
    git: {
        commit_enabled: false,
        conventional_commits: false,
        worktree_enabled: false,
        branching_enabled: false,
        branch_prefix: '',
        target_branch: '',
    },
    build_settings: {
        auto_advance_tasks: true,
    },
});

const gitBranches = ref([]);
const gitBranchesLoading = ref(false);
const gitCurrentBranch = ref('');

const submitting = ref(false);
const error = ref('');
const validation = ref({});
const availableModels = ref([]);
const loadingModels = ref(false);
const defaultModel = ref('');

const fetchModels = async (runnerType) => {
    loadingModels.value = true;
    availableModels.value = [];
    form.model = '';

    try {
        const { data } = await axios.get('/agent/api/v1/interrogation/runner-models', {
            params: { runner_type: runnerType },
        });
        availableModels.value = data?.data ?? [];
        defaultModel.value = data?.default ?? '';
        form.model = defaultModel.value;
    } catch {
        availableModels.value = [];
        defaultModel.value = '';
    } finally {
        loadingModels.value = false;
    }
};

const loadGitBranches = async () => {
    const dir = String(form.project_directory ?? '').trim();
    if (dir === '') {
        gitBranches.value = [];
        gitCurrentBranch.value = '';
        return;
    }

    gitBranchesLoading.value = true;

    try {
        const { data } = await axios.get('/agent/api/v1/interrogation/git-branches-preview', {
            params: { project_directory: dir },
        });
        gitBranches.value = Array.isArray(data?.data?.branches) ? data.data.branches : [];
        gitCurrentBranch.value = String(data?.data?.current_branch ?? '');

        if (!form.git.target_branch && gitCurrentBranch.value) {
            form.git.target_branch = gitCurrentBranch.value;
        }
    } catch {
        gitBranches.value = [];
        gitCurrentBranch.value = '';
    } finally {
        gitBranchesLoading.value = false;
    }
};

watch(() => form.runner_type, (runnerType) => {
    fetchModels(runnerType);
}, { immediate: true });

watch(() => form.git.commit_enabled, (enabled) => {
    if (!enabled) {
        form.git.conventional_commits = false;
        form.git.worktree_enabled = false;
        form.git.branching_enabled = false;
        form.git.branch_prefix = '';
        form.git.target_branch = '';
    } else if (gitBranches.value.length === 0) {
        loadGitBranches();
    }
});

watch(() => form.git.branching_enabled, (enabled) => {
    if (enabled) {
        form.git.target_branch = '';
    } else if (!enabled && gitBranches.value.length === 0 && form.git.commit_enabled) {
        loadGitBranches();
    }
});

const submit = async () => {
    submitting.value = true;
    error.value = '';
    validation.value = {};

    try {
        const payload = { ...form };
        payload.git = {
            commit_enabled: form.git.commit_enabled,
            conventional_commits: form.git.conventional_commits,
            worktree_enabled: form.git.worktree_enabled,
            branching_enabled: form.git.branching_enabled,
            branch_prefix: form.git.branch_prefix || null,
            target_branch: !form.git.branching_enabled ? (form.git.target_branch || null) : null,
        };
        payload.build_settings = {
            auto_advance_tasks: form.build_settings.auto_advance_tasks,
        };

        const { data } = await axios.post('/agent/api/v1/interrogation/sessions', payload);
        const id = data?.data?.id;

        if (id) {
            router.visit(route('tools.discovery.wizard', id));
            return;
        }

        router.visit(route('tools.discovery.index'));
    } catch (e) {
        const payload = e?.response?.data ?? {};
        const envelope = payload?.error ?? null;

        if (envelope) {
            validation.value = envelope?.details ?? {};
            error.value = envelope?.message ?? 'Failed to create session.';
        } else if (payload?.errors && typeof payload.errors === 'object') {
            validation.value = payload.errors;
            error.value = payload?.message ?? 'The given data was invalid.';
        } else {
            validation.value = {};
            error.value = payload?.message ?? 'Failed to create session.';
        }
    } finally {
        submitting.value = false;
    }
};
</script>

<template>
    <AppLayout title="New Discovery Session">
        <Head title="New Discovery Session" />

        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <Search class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-semibold text-foreground truncate">New Discovery Session</h2>
                        <HelpHint
                            ui-key="discovery.create"
                            short-text="Start a new requirements discovery session."
                            learn-more-href="/docs/overview"
                        />
                    </div>
                </div>
                <Link :href="route('tools.discovery.index')">
                    <Button variant="outline" size="sm">
                        <ArrowLeft class="h-4 w-4" />
                        Back
                    </Button>
                </Link>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-4xl">
                <Card>
                    <CardHeader>
                        <CardTitle>Create Session</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">{{ error }}</div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium">Name (optional)</label>
                                <Input v-model="form.name" type="text" class="mt-1" :error="!!validation.name" />
                                <p v-if="validation.name" class="mt-1 text-sm text-destructive">{{ validation.name[0] }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium">Runner</label>
                                <select
                                    v-model="form.runner_type"
                                    class="mt-1 flex h-9 w-full rounded-md border border-input bg-input-background px-3 py-1 text-sm shadow-xs focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    <option value="claude">claude</option>
                                    <option value="codex">codex</option>
                                </select>
                                <p v-if="validation.runner_type" class="mt-1 text-sm text-destructive">{{ validation.runner_type[0] }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium">Model</label>
                            <select
                                v-model="form.model"
                                :disabled="loadingModels"
                                class="mt-1 flex h-9 w-full rounded-md border border-input bg-input-background px-3 py-1 text-sm shadow-xs focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50"
                            >
                                <option v-if="loadingModels" value="">Loading models...</option>
                                <option v-for="m in availableModels" :key="m.id" :value="m.id">
                                    {{ m.name }}{{ m.id === defaultModel ? ' (default)' : '' }}
                                </option>
                            </select>
                            <p class="mt-1 text-xs text-muted-foreground">Model used for discovery and build runs.</p>
                            <p v-if="validation.model" class="mt-1 text-sm text-destructive">{{ validation.model[0] }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium">Project Directory</label>
                            <DirectoryPickerInput v-model="form.project_directory" class="mt-1" :error="!!validation.project_directory" />
                            <p class="mt-1 text-xs text-muted-foreground">Absolute path where discovery commands run.</p>
                            <p v-if="validation.project_directory" class="mt-1 text-sm text-destructive">{{ validation.project_directory[0] }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium">Interrogation Type</label>
                            <select
                                v-model="form.interrogation_type"
                                class="mt-1 flex h-9 w-full rounded-md border border-input bg-input-background px-3 py-1 text-sm shadow-xs focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                <option value="feature">feature</option>
                                <option value="general">general</option>
                            </select>
                            <p v-if="validation.interrogation_type" class="mt-1 text-sm text-destructive">{{ validation.interrogation_type[0] }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium">Feature Brief</label>
                            <Textarea
                                v-model="form.feature_brief"
                                :rows="10"
                                class="mt-1 font-mono"
                                placeholder="Describe the feature scope, users, and constraints."
                                :error="!!validation.feature_brief"
                            />
                            <p class="mt-1 text-xs text-muted-foreground">Required for feature sessions.</p>
                            <p v-if="validation.feature_brief" class="mt-1 text-sm text-destructive">{{ validation.feature_brief[0] }}</p>
                        </div>

                        <div class="space-y-3">
                            <div class="flex items-center gap-2">
                                <GitBranch class="h-4 w-4 text-muted-foreground" />
                                <label class="text-sm font-semibold">Git Operations</label>
                            </div>
                            <p class="text-xs text-muted-foreground">Configure how build tasks interact with git. All settings are off by default.</p>

                            <div class="flex items-center justify-between gap-4 rounded-md border border-border bg-muted/50 px-3 py-2">
                                <div>
                                    <p class="text-sm font-medium">Commit work</p>
                                    <p class="text-xs text-muted-foreground">Allow the AI to commit changes during build tasks.</p>
                                </div>
                                <Toggle v-model="form.git.commit_enabled" />
                            </div>

                            <template v-if="form.git.commit_enabled">
                                <div class="flex items-center justify-between gap-4 rounded-md border border-border bg-muted/50 px-3 py-2 ml-4">
                                    <div>
                                        <p class="text-sm font-medium">Conventional commits</p>
                                        <p class="text-xs text-muted-foreground">Use conventional commit message format (feat:, fix:, etc.).</p>
                                    </div>
                                    <Toggle v-model="form.git.conventional_commits" />
                                </div>

                                <div class="flex items-center justify-between gap-4 rounded-md border border-border bg-muted/50 px-3 py-2 ml-4">
                                    <div>
                                        <p class="text-sm font-medium">Use worktrees</p>
                                        <p class="text-xs text-muted-foreground">Work in a separate git worktree so the main checkout stays clean.</p>
                                    </div>
                                    <Toggle v-model="form.git.worktree_enabled" />
                                </div>

                                <div class="flex items-center justify-between gap-4 rounded-md border border-border bg-muted/50 px-3 py-2 ml-4">
                                    <div>
                                        <p class="text-sm font-medium">Feature branching</p>
                                        <p class="text-xs text-muted-foreground">Create a feature branch per task (gitflow). When off, uses trunk-based development on a selected branch.</p>
                                    </div>
                                    <Toggle v-model="form.git.branching_enabled" />
                                </div>

                                <div v-if="form.git.branching_enabled" class="ml-4 space-y-2">
                                    <label class="block text-sm font-medium">Branch prefix (optional)</label>
                                    <Input
                                        v-model="form.git.branch_prefix"
                                        type="text"
                                        placeholder="e.g. feature/, agent/"
                                        :error="!!validation['git.branch_prefix']"
                                    />
                                    <p class="text-xs text-muted-foreground">Prepended to auto-generated branch names.</p>
                                    <p v-if="validation['git.branch_prefix']" class="text-xs text-destructive">{{ validation['git.branch_prefix'][0] }}</p>
                                </div>

                                <div v-if="!form.git.branching_enabled" class="ml-4 space-y-2">
                                    <label class="block text-sm font-medium">Target branch</label>
                                    <div class="flex items-center gap-2">
                                        <select
                                            v-model="form.git.target_branch"
                                            :disabled="gitBranchesLoading"
                                            class="flex h-9 w-full rounded-md border border-input bg-input-background px-3 py-1 text-sm shadow-xs focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50"
                                        >
                                            <option v-if="gitBranchesLoading" value="">Loading branches...</option>
                                            <option v-if="!gitBranchesLoading && gitBranches.length === 0" value="">No branches found</option>
                                            <option v-for="branch in gitBranches" :key="branch" :value="branch">
                                                {{ branch }}{{ branch === gitCurrentBranch ? ' (current)' : '' }}
                                            </option>
                                        </select>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            type="button"
                                            :disabled="gitBranchesLoading"
                                            @click="loadGitBranches"
                                        >
                                            <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': gitBranchesLoading }" />
                                        </Button>
                                    </div>
                                    <p class="text-xs text-muted-foreground">Branch to commit to when using trunk-based development.</p>
                                    <p v-if="validation['git.target_branch']" class="text-xs text-destructive">{{ validation['git.target_branch'][0] }}</p>
                                </div>
                            </template>
                        </div>

                        <div class="space-y-3">
                            <div class="flex items-center gap-2">
                                <Settings2 class="h-4 w-4 text-muted-foreground" />
                                <label class="text-sm font-semibold">Build Execution</label>
                            </div>

                            <div class="flex items-center justify-between gap-4 rounded-md border border-border bg-muted/50 px-3 py-2">
                                <div>
                                    <p class="text-sm font-medium">Auto-advance tasks</p>
                                    <p class="text-xs text-muted-foreground">When off, the build pauses after each task completes for you to review changes before continuing.</p>
                                </div>
                                <Toggle v-model="form.build_settings.auto_advance_tasks" />
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <Button :disabled="submitting" @click="submit">
                                {{ submitting ? 'Creating...' : 'Create Session' }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
