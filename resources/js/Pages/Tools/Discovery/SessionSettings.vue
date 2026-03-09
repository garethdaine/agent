<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardDescription from '@/Components/ui/CardDescription.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import { Head, Link } from '@inertiajs/vue3';
import Toggle from '@/Components/ui/Toggle.vue';
import { ArrowLeft, ExternalLink, GitBranch, RefreshCw, Search, Settings2, Trash2, Plus } from 'lucide-vue-next';
import HelpHint from '@/Components/HelpHint.vue';
import axios from 'axios';
import { computed, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps({
    sessionId: {
        type: Number,
        required: true,
    },
});

const loading = ref(true);
const saving = ref(false);
const error = ref('');
const notice = ref('');
const session = ref(null);
const providerConnecting = ref(false);
const providerDisconnecting = ref(false);
const providerProjectsLoading = ref(false);
const providerProjectsLoaded = ref(false);
const providerSettingsSaving = ref(false);
const techStackSubmitting = ref(false);
const validation = ref({});

const form = reactive({
    name: '',
    feature_brief: '',
    model: '',
});

const availableModels = ref([]);
const loadingModels = ref(false);
const defaultModel = ref('');

const gitForm = reactive({
    commit_enabled: false,
    conventional_commits: false,
    worktree_enabled: false,
    branching_enabled: false,
    branch_prefix: '',
    target_branch: '',
});
const gitSaving = ref(false);
const gitBranches = ref([]);
const gitBranchesLoading = ref(false);
const gitCurrentBranch = ref('');

const buildSettingsForm = reactive({
    auto_advance_tasks: true,
});
const buildSettingsSaving = ref(false);

const techStackDraft = reactive({
    name: '',
    documentation_url: '',
});
const providerProjectForm = reactive({
    project_mode: 'create_new',
    existing_project_id: '',
});
const providerTeamForm = reactive({
    team_id: '',
});
const linearProjects = ref([]);
const linearTeams = ref([]);

const taskProviders = computed(() => (Array.isArray(session.value?.task_providers) ? session.value.task_providers : []));
const linearProvider = computed(() => taskProviders.value.find((provider) => String(provider?.driver ?? '').toLowerCase() === 'linear') ?? null);
const techStacks = computed(() => (Array.isArray(session.value?.tech_stacks) ? session.value.tech_stacks : []));
const selectedLinearTeam = computed(() => {
    const targetId = String(providerTeamForm.team_id ?? '').trim();
    if (targetId === '') {
        return null;
    }

    return linearTeams.value.find((team) => String(team?.id ?? '').trim() === targetId) ?? null;
});
const selectedLinearProject = computed(() => {
    const targetId = String(providerProjectForm.existing_project_id ?? '').trim();
    if (targetId === '') {
        return null;
    }

    return linearProjects.value.find((project) => String(project?.id ?? '').trim() === targetId) ?? null;
});

const firstValidationError = (field) => {
    const messages = validation.value?.[field];

    return Array.isArray(messages) && messages.length > 0 ? messages[0] : '';
};

const fetchModels = async (runnerType) => {
    loadingModels.value = true;
    availableModels.value = [];

    try {
        const { data } = await axios.get('/agent/api/v1/interrogation/runner-models', {
            params: { runner_type: runnerType },
        });
        availableModels.value = data?.data ?? [];
        defaultModel.value = data?.default ?? '';
    } catch {
        availableModels.value = [];
        defaultModel.value = '';
    } finally {
        loadingModels.value = false;
    }
};

const loadSession = async () => {
    loading.value = true;
    error.value = '';
    validation.value = {};

    try {
        const { data } = await axios.get(`/agent/api/v1/interrogation/sessions/${props.sessionId}`, {
            params: { include_events: 0 },
        });

        session.value = data?.data ?? null;
        form.name = String(session.value?.name ?? '');
        form.feature_brief = String(session.value?.feature_brief ?? '');
        form.model = String(session.value?.model ?? '');

        const runnerType = String(session.value?.runner_type ?? 'claude');
        await fetchModels(runnerType);
        syncGitFormFromSession();
        syncBuildSettingsFromSession();
        syncProviderProjectDraftFromSession();

        if (linearProvider.value) {
            await loadLinearProjects();
        } else {
            linearProjects.value = [];
            providerProjectsLoaded.value = false;
        }
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load session settings.';
    } finally {
        loading.value = false;
    }
};

const syncProviderProjectDraftFromSession = () => {
    const mode = String(linearProvider.value?.project_mode ?? 'create_new').trim().toLowerCase();
    providerProjectForm.project_mode = mode === 'existing' ? 'existing' : 'create_new';
    providerProjectForm.existing_project_id = providerProjectForm.project_mode === 'existing'
        ? String(linearProvider.value?.selected_project_id ?? '').trim()
        : '';
    providerTeamForm.team_id = String(linearProvider.value?.team_id ?? '').trim();
};

const loadLinearProjects = async ({ force = false } = {}) => {
    if (!linearProvider.value) {
        linearProjects.value = [];
        providerProjectsLoaded.value = false;
        return;
    }

    if (providerProjectsLoading.value || (providerProjectsLoaded.value && !force)) {
        return;
    }

    providerProjectsLoading.value = true;

    try {
        const { data } = await axios.get(`/agent/api/v1/interrogation/sessions/${props.sessionId}/providers/linear/projects`);
        const projects = Array.isArray(data?.data?.projects) ? data.data.projects : [];
        const teams = Array.isArray(data?.data?.teams) ? data.data.teams : [];
        linearProjects.value = projects;
        linearTeams.value = teams;
        const selectedTeamId = String(data?.data?.selected_team_id ?? '').trim();
        if (selectedTeamId !== '') {
            providerTeamForm.team_id = selectedTeamId;
        }
        providerProjectsLoaded.value = true;
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load Linear projects.';
    } finally {
        providerProjectsLoading.value = false;
    }
};

const syncGitFormFromSession = () => {
    const git = session.value?.git_settings ?? {};
    gitForm.commit_enabled = !!git.commit_enabled;
    gitForm.conventional_commits = !!git.conventional_commits;
    gitForm.worktree_enabled = !!git.worktree_enabled;
    gitForm.branching_enabled = !!git.branching_enabled;
    gitForm.branch_prefix = String(git.branch_prefix ?? '');
    gitForm.target_branch = String(git.target_branch ?? '');
};

const syncBuildSettingsFromSession = () => {
    const bs = session.value?.build_settings ?? {};
    buildSettingsForm.auto_advance_tasks = bs.auto_advance_tasks !== false;
};

const loadGitBranches = async () => {
    gitBranchesLoading.value = true;

    try {
        const { data } = await axios.get(`/agent/api/v1/interrogation/sessions/${props.sessionId}/git-branches`);
        gitBranches.value = Array.isArray(data?.data?.branches) ? data.data.branches : [];
        gitCurrentBranch.value = String(data?.data?.current_branch ?? '');

        if (!gitForm.target_branch && gitCurrentBranch.value) {
            gitForm.target_branch = gitCurrentBranch.value;
        }
    } catch {
        gitBranches.value = [];
        gitCurrentBranch.value = '';
    } finally {
        gitBranchesLoading.value = false;
    }
};

const saveGitSettings = async () => {
    gitSaving.value = true;
    error.value = '';
    validation.value = {};

    try {
        await axios.patch(`/agent/api/v1/interrogation/sessions/${props.sessionId}`, {
            git: {
                commit_enabled: gitForm.commit_enabled,
                conventional_commits: gitForm.conventional_commits,
                worktree_enabled: gitForm.worktree_enabled,
                branching_enabled: gitForm.branching_enabled,
                branch_prefix: gitForm.branch_prefix || null,
                target_branch: !gitForm.branching_enabled ? (gitForm.target_branch || null) : null,
            },
        });

        notice.value = 'Git settings updated.';
        await loadSession();
    } catch (e) {
        const payload = e?.response?.data ?? {};
        validation.value = payload?.errors ?? payload?.error?.details ?? {};
        error.value = payload?.error?.message ?? payload?.message ?? 'Failed to update git settings.';
    } finally {
        gitSaving.value = false;
    }
};

const saveBuildSettings = async () => {
    buildSettingsSaving.value = true;
    error.value = '';
    validation.value = {};

    try {
        await axios.patch(`/agent/api/v1/interrogation/sessions/${props.sessionId}`, {
            build_settings: {
                auto_advance_tasks: buildSettingsForm.auto_advance_tasks,
            },
        });

        notice.value = 'Build settings updated.';
        await loadSession();
    } catch (e) {
        const payload = e?.response?.data ?? {};
        validation.value = payload?.errors ?? payload?.error?.details ?? {};
        error.value = payload?.error?.message ?? payload?.message ?? 'Failed to update build settings.';
    } finally {
        buildSettingsSaving.value = false;
    }
};

const saveSession = async () => {
    saving.value = true;
    error.value = '';
    validation.value = {};

    try {
        await axios.patch(`/agent/api/v1/interrogation/sessions/${props.sessionId}`, {
            name: form.name,
            feature_brief: form.feature_brief,
            model: form.model || null,
        });

        notice.value = 'Session settings updated.';
        await loadSession();
    } catch (e) {
        const payload = e?.response?.data ?? {};
        validation.value = payload?.errors ?? payload?.error?.details ?? {};
        error.value = payload?.error?.message ?? payload?.message ?? 'Failed to update session settings.';
    } finally {
        saving.value = false;
    }
};

const startProviderOAuth = async (driver = 'linear') => {
    providerConnecting.value = true;
    error.value = '';

    try {
        const { data } = await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/providers/${driver}/oauth/start`, {
            return_to: 'settings',
        });

        const url = String(data?.data?.authorization_url ?? '').trim();
        if (url === '') {
            throw new Error('OAuth redirect URL was not returned.');
        }

        window.location.assign(url);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? e?.message ?? 'Failed to start provider authentication.';
        providerConnecting.value = false;
    }
};

const disconnectProvider = async (driver = 'linear') => {
    providerDisconnecting.value = true;
    error.value = '';

    try {
        await axios.delete(`/agent/api/v1/interrogation/sessions/${props.sessionId}/providers/${driver}`);
        notice.value = `${driver} disconnected.`;
        linearProjects.value = [];
        linearTeams.value = [];
        providerProjectsLoaded.value = false;
        providerTeamForm.team_id = '';
        providerProjectForm.project_mode = 'create_new';
        providerProjectForm.existing_project_id = '';
        await loadSession();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to disconnect provider.';
    } finally {
        providerDisconnecting.value = false;
    }
};

const saveProviderProjectSettings = async () => {
    if (!linearProvider.value) {
        return;
    }

    providerSettingsSaving.value = true;
    error.value = '';
    validation.value = {};

    try {
        await axios.patch(`/agent/api/v1/interrogation/sessions/${props.sessionId}/providers/linear/settings`, {
            team_id: String(providerTeamForm.team_id ?? '').trim() || null,
            project_mode: providerProjectForm.project_mode,
            existing_project_id: providerProjectForm.project_mode === 'existing'
                ? String(providerProjectForm.existing_project_id ?? '').trim()
                : null,
        });

        const teamLabel = selectedLinearTeam.value?.name || linearProvider.value?.team_name || 'selected team';
        notice.value = providerProjectForm.project_mode === 'existing'
            ? `Linear team set to ${teamLabel}. Tasks will sync into ${selectedLinearProject.value?.name ?? 'the selected project'}.`
            : `Linear team set to ${teamLabel}. Linear will create a new project when syncing tasks.`;
        await loadSession();
    } catch (e) {
        const payload = e?.response?.data ?? {};
        validation.value = payload?.errors ?? payload?.error?.details ?? {};
        error.value = payload?.error?.message ?? 'Failed to update task provider settings.';
    } finally {
        providerSettingsSaving.value = false;
    }
};

const autoSaveTeamAndReloadProjects = async (teamId) => {
    if (!linearProvider.value || providerSettingsSaving.value) {
        return;
    }

    providerSettingsSaving.value = true;
    error.value = '';

    try {
        await axios.patch(`/agent/api/v1/interrogation/sessions/${props.sessionId}/providers/linear/settings`, {
            team_id: teamId || null,
            project_mode: providerProjectForm.project_mode,
            existing_project_id: null,
        });

        await loadSession();
        await loadLinearProjects({ force: true });
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to update Linear team.';
    } finally {
        providerSettingsSaving.value = false;
    }
};

const addTechStack = async () => {
    const name = String(techStackDraft.name ?? '').trim();
    const documentationUrl = String(techStackDraft.documentation_url ?? '').trim();

    if (name === '' || documentationUrl === '') {
        error.value = 'Tech stack name and documentation URL are required.';
        return;
    }

    techStackSubmitting.value = true;
    error.value = '';

    try {
        await axios.post(`/agent/api/v1/interrogation/sessions/${props.sessionId}/tech-stacks`, {
            name,
            documentation_url: documentationUrl,
        });

        techStackDraft.name = '';
        techStackDraft.documentation_url = '';
        notice.value = 'Tech stack entry added.';
        await loadSession();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to add tech stack entry.';
    } finally {
        techStackSubmitting.value = false;
    }
};

const removeTechStack = async (stackId) => {
    error.value = '';

    try {
        await axios.delete(`/agent/api/v1/interrogation/sessions/${props.sessionId}/tech-stacks/${Number(stackId)}`);
        notice.value = 'Tech stack entry removed.';
        await loadSession();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to remove tech stack entry.';
    }
};

onMounted(async () => {
    await loadSession();

    const params = new URLSearchParams(window.location.search);
    const providerConnected = String(params.get('provider_connected') ?? '').trim();
    const providerError = String(params.get('provider_error') ?? '').trim();

    if (providerConnected !== '') {
        notice.value = `${providerConnected} connected successfully.`;
    }

    if (providerError !== '') {
        error.value = providerError;
    }

    if (providerConnected !== '' || providerError !== '') {
        const cleanUrl = `${window.location.pathname}${window.location.hash || ''}`;
        window.history.replaceState({}, document.title, cleanUrl);
    }
});

watch(() => gitForm.commit_enabled, (enabled) => {
    if (!enabled) {
        gitForm.conventional_commits = false;
        gitForm.worktree_enabled = false;
        gitForm.branching_enabled = false;
        gitForm.branch_prefix = '';
        gitForm.target_branch = '';
    } else if (gitBranches.value.length === 0) {
        loadGitBranches();
    }
});

watch(() => gitForm.branching_enabled, (enabled) => {
    if (enabled) {
        gitForm.target_branch = '';
    } else if (!enabled && gitBranches.value.length === 0 && gitForm.commit_enabled) {
        loadGitBranches();
    }
});

watch(linearProvider, (provider) => {
    if (!provider) {
        linearProjects.value = [];
        linearTeams.value = [];
        providerProjectsLoaded.value = false;
        providerTeamForm.team_id = '';
        providerProjectForm.project_mode = 'create_new';
        providerProjectForm.existing_project_id = '';
        return;
    }

    syncProviderProjectDraftFromSession();
}, { immediate: false });

watch(() => providerProjectForm.project_mode, async (mode) => {
    if (mode !== 'existing') {
        providerProjectForm.existing_project_id = '';
        return;
    }

    if (!providerProjectsLoaded.value && linearProvider.value) {
        await loadLinearProjects();
    }
});

watch(() => providerTeamForm.team_id, async (nextTeamId) => {
    const nextId = String(nextTeamId ?? '').trim();
    const currentTeamId = String(linearProvider.value?.team_id ?? '').trim();
    if (nextId === currentTeamId || nextId === '') {
        return;
    }

    providerProjectForm.existing_project_id = '';

    await autoSaveTeamAndReloadProjects(nextId);
});
</script>

<template>
    <AppLayout title="Session Settings">
        <Head title="Session Settings" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <Search class="h-5 w-5 text-primary" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-semibold text-foreground truncate">Session Settings</h2>
                            <HelpHint
                                ui-key="discovery.session-settings"
                                short-text="Adjust settings for this discovery session."
                                learn-more-href="/docs/overview"
                            />
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">{{ session?.name || `Session #${sessionId}` }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('tools.discovery.wizard', sessionId)">
                        <Button variant="outline" size="sm">
                            <ExternalLink class="h-4 w-4" />
                            Open Wizard
                        </Button>
                    </Link>
                    <Link :href="route('tools.discovery.index')">
                        <Button variant="outline" size="sm">
                            <ArrowLeft class="h-4 w-4" />
                            Back
                        </Button>
                    </Link>
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-5xl space-y-4">
                <div v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">{{ error }}</div>
                <div v-if="notice" class="rounded-md border border-success/50 bg-success/10 px-3 py-2 text-sm text-success">{{ notice }}</div>

                <Card v-if="loading">
                    <CardContent class="pt-6">
                        <Skeleton class="h-32 w-full" />
                    </CardContent>
                </Card>

                <template v-else>
                    <Card>
                        <CardHeader>
                            <CardTitle>Session Details</CardTitle>
                            <CardDescription>Update initial brief and naming for this discovery session.</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium">Session Name</label>
                                <Input
                                    v-model="form.name"
                                    type="text"
                                    class="mt-1"
                                    placeholder="Session name"
                                    :error="!!firstValidationError('name')"
                                />
                                <p v-if="firstValidationError('name')" class="mt-1 text-xs text-destructive">{{ firstValidationError('name') }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium">Initial Brief</label>
                                <Textarea
                                    v-model="form.feature_brief"
                                    :rows="8"
                                    class="mt-1"
                                    placeholder="Feature brief or initial context for this session"
                                    :error="!!firstValidationError('feature_brief')"
                                />
                                <p v-if="firstValidationError('feature_brief')" class="mt-1 text-xs text-destructive">{{ firstValidationError('feature_brief') }}</p>
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium">Runner</label>
                                    <div class="mt-1 flex h-9 w-full items-center rounded-md border border-input bg-muted/50 px-3 text-sm text-muted-foreground">
                                        {{ session?.runner_type ?? 'claude' }}
                                    </div>
                                    <p class="mt-1 text-xs text-muted-foreground">Runner type is set at session creation.</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium">Model</label>
                                    <select
                                        v-model="form.model"
                                        :disabled="loadingModels"
                                        class="mt-1 flex h-9 w-full rounded-md border border-input bg-input-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50"
                                    >
                                        <option v-if="loadingModels" value="">Loading models...</option>
                                        <option v-for="m in availableModels" :key="m.id" :value="m.id">
                                            {{ m.name }}{{ m.id === defaultModel ? ' (default)' : '' }}
                                        </option>
                                    </select>
                                    <p class="mt-1 text-xs text-muted-foreground">Model used for discovery and build runs.</p>
                                    <p v-if="firstValidationError('model')" class="mt-1 text-xs text-destructive">{{ firstValidationError('model') }}</p>
                                </div>
                            </div>

                            <div class="flex justify-end">
                                <Button :disabled="saving" @click="saveSession">
                                    {{ saving ? 'Saving...' : 'Save Session Settings' }}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div class="flex items-center gap-2">
                                <GitBranch class="h-4 w-4 text-muted-foreground" />
                                <CardTitle>Git Operations</CardTitle>
                            </div>
                            <CardDescription>Configure how build tasks interact with git. All settings are off by default.</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-3">
                                <div class="flex items-center justify-between gap-4 rounded-md border border-border bg-muted/50 px-3 py-2">
                                    <div>
                                        <p class="text-sm font-medium">Commit work</p>
                                        <p class="text-xs text-muted-foreground">Allow the AI to commit changes during build tasks.</p>
                                    </div>
                                    <Toggle v-model="gitForm.commit_enabled" />
                                </div>

                                <template v-if="gitForm.commit_enabled">
                                    <div class="flex items-center justify-between gap-4 rounded-md border border-border bg-muted/50 px-3 py-2 ml-4">
                                        <div>
                                            <p class="text-sm font-medium">Conventional commits</p>
                                            <p class="text-xs text-muted-foreground">Use conventional commit message format (feat:, fix:, etc.).</p>
                                        </div>
                                        <Toggle v-model="gitForm.conventional_commits" />
                                    </div>

                                    <div class="flex items-center justify-between gap-4 rounded-md border border-border bg-muted/50 px-3 py-2 ml-4">
                                        <div>
                                            <p class="text-sm font-medium">Use worktrees</p>
                                            <p class="text-xs text-muted-foreground">Work in a separate git worktree so the main checkout stays clean.</p>
                                        </div>
                                        <Toggle v-model="gitForm.worktree_enabled" />
                                    </div>

                                    <div class="flex items-center justify-between gap-4 rounded-md border border-border bg-muted/50 px-3 py-2 ml-4">
                                        <div>
                                            <p class="text-sm font-medium">Feature branching</p>
                                            <p class="text-xs text-muted-foreground">Create a feature branch per task (gitflow). When off, uses trunk-based development on a selected branch.</p>
                                        </div>
                                        <Toggle v-model="gitForm.branching_enabled" />
                                    </div>

                                    <div v-if="gitForm.branching_enabled" class="ml-4 space-y-2">
                                        <label class="block text-sm font-medium">Branch prefix (optional)</label>
                                        <Input
                                            v-model="gitForm.branch_prefix"
                                            type="text"
                                            placeholder="e.g. feature/, agent/"
                                            :error="!!firstValidationError('git.branch_prefix')"
                                        />
                                        <p class="text-xs text-muted-foreground">Prepended to auto-generated branch names.</p>
                                        <p v-if="firstValidationError('git.branch_prefix')" class="text-xs text-destructive">{{ firstValidationError('git.branch_prefix') }}</p>
                                    </div>

                                    <div v-if="!gitForm.branching_enabled" class="ml-4 space-y-2">
                                        <label class="block text-sm font-medium">Target branch</label>
                                        <div class="flex items-center gap-2">
                                            <select
                                                v-model="gitForm.target_branch"
                                                :disabled="gitBranchesLoading"
                                                class="flex h-9 w-full rounded-md border border-input bg-input-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50"
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
                                                :disabled="gitBranchesLoading"
                                                @click="loadGitBranches"
                                            >
                                                <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': gitBranchesLoading }" />
                                            </Button>
                                        </div>
                                        <p class="text-xs text-muted-foreground">Branch to commit to when using trunk-based development.</p>
                                        <p v-if="firstValidationError('git.target_branch')" class="text-xs text-destructive">{{ firstValidationError('git.target_branch') }}</p>
                                    </div>
                                </template>
                            </div>

                            <div class="flex justify-end">
                                <Button :disabled="gitSaving" @click="saveGitSettings">
                                    {{ gitSaving ? 'Saving...' : 'Save Git Settings' }}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div class="flex items-center gap-2">
                                <Settings2 class="h-4 w-4 text-muted-foreground" />
                                <CardTitle>Build Execution</CardTitle>
                            </div>
                            <CardDescription>Control how the build progresses through tasks.</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex items-center justify-between gap-4 rounded-md border border-border bg-muted/50 px-3 py-2">
                                <div>
                                    <p class="text-sm font-medium">Auto-advance tasks</p>
                                    <p class="text-xs text-muted-foreground">When off, the build pauses after each task completes for you to review changes before continuing.</p>
                                </div>
                                <Toggle v-model="buildSettingsForm.auto_advance_tasks" />
                            </div>

                            <div class="flex justify-end">
                                <Button :disabled="buildSettingsSaving" @click="saveBuildSettings">
                                    {{ buildSettingsSaving ? 'Saving...' : 'Save Build Settings' }}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Task Provider (Optional)</CardTitle>
                            <CardDescription>Connect Linear to sync approved build tasks for this session.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="rounded-md border border-border bg-muted/50 p-3">
                                <p v-if="linearProvider" class="text-sm">
                                    Connected to Linear
                                    <span v-if="linearProvider.provider_workspace_name">({{ linearProvider.provider_workspace_name }})</span>
                                    <span v-if="linearProvider.team_name">· Team: {{ linearProvider.team_name }}</span>
                                </p>
                                <p v-else class="text-sm text-muted-foreground">Linear is not connected for this session.</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <Button
                                        v-if="!linearProvider"
                                        size="sm"
                                        :disabled="providerConnecting"
                                        @click="startProviderOAuth('linear')"
                                    >
                                        {{ providerConnecting ? 'Redirecting...' : 'Connect Linear' }}
                                    </Button>
                                    <Button
                                        v-else
                                        variant="outline"
                                        size="sm"
                                        :disabled="providerDisconnecting"
                                        @click="disconnectProvider('linear')"
                                    >
                                        {{ providerDisconnecting ? 'Disconnecting...' : 'Disconnect Linear' }}
                                    </Button>
                                </div>

                                <div v-if="linearProvider" class="mt-4 space-y-3 rounded-md border border-border bg-card p-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Team</p>
                                    <div class="space-y-2">
                                        <div class="flex items-center gap-2">
                                            <select
                                                v-model="providerTeamForm.team_id"
                                                class="flex h-9 w-full rounded-md border border-input bg-input-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            >
                                                <option value="">Select a Linear team...</option>
                                                <option v-for="team in linearTeams" :key="team.id" :value="team.id">
                                                    {{ team.name || team.id }}
                                                </option>
                                            </select>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                :disabled="providerProjectsLoading"
                                                @click="loadLinearProjects({ force: true })"
                                            >
                                                <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': providerProjectsLoading }" />
                                            </Button>
                                        </div>
                                        <p v-if="firstValidationError('team_id')" class="text-xs text-destructive">{{ firstValidationError('team_id') }}</p>
                                        <p v-if="selectedLinearTeam" class="text-xs text-muted-foreground">
                                            Selected team: {{ selectedLinearTeam.name || selectedLinearTeam.id }}
                                        </p>
                                    </div>

                                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Project Sync Target</p>
                                    <label class="flex items-center gap-2 text-sm">
                                        <input v-model="providerProjectForm.project_mode" type="radio" value="create_new" class="h-4 w-4 border-input text-primary focus:ring-primary" />
                                        Create a new Linear project for this session
                                    </label>
                                    <label class="flex items-center gap-2 text-sm">
                                        <input v-model="providerProjectForm.project_mode" type="radio" value="existing" class="h-4 w-4 border-input text-primary focus:ring-primary" />
                                        Use an existing Linear project
                                    </label>

                                    <div v-if="providerProjectForm.project_mode === 'existing'" class="space-y-2">
                                        <div class="flex items-center gap-2">
                                            <select
                                                v-model="providerProjectForm.existing_project_id"
                                                class="flex h-9 w-full rounded-md border border-input bg-input-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            >
                                                <option value="">Select a Linear project...</option>
                                                <option v-for="project in linearProjects" :key="project.id" :value="project.id">
                                                    {{ project.name || project.id }}
                                                </option>
                                            </select>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                :disabled="providerProjectsLoading"
                                                @click="loadLinearProjects({ force: true })"
                                            >
                                                <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': providerProjectsLoading }" />
                                            </Button>
                                        </div>
                                        <p v-if="firstValidationError('existing_project_id')" class="text-xs text-destructive">{{ firstValidationError('existing_project_id') }}</p>
                                        <p v-if="selectedLinearProject?.url" class="text-xs text-muted-foreground">
                                            Selected project:
                                            <a :href="selectedLinearProject.url" target="_blank" rel="noreferrer" class="text-primary underline">{{ selectedLinearProject.name || selectedLinearProject.id }}</a>
                                        </p>
                                    </div>

                                    <div class="flex justify-end">
                                        <Button
                                            size="sm"
                                            :disabled="providerSettingsSaving || providerTeamForm.team_id.trim() === '' || (providerProjectForm.project_mode === 'existing' && providerProjectForm.existing_project_id.trim() === '')"
                                            @click="saveProviderProjectSettings"
                                        >
                                            {{ providerSettingsSaving ? 'Saving...' : 'Save Provider Settings' }}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Tech Stack</CardTitle>
                            <CardDescription>Manage tech stack entries used as context throughout this session.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-2">
                                <div v-for="stack in techStacks" :key="stack.id" class="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border bg-muted/50 px-3 py-2 text-sm">
                                    <div>
                                        <p class="font-medium">{{ stack.name }}</p>
                                        <a :href="stack.documentation_url" target="_blank" rel="noreferrer" class="text-xs text-primary underline">{{ stack.documentation_url }}</a>
                                    </div>
                                    <Button variant="destructive" size="sm" @click="removeTechStack(stack.id)">
                                        <Trash2 class="h-4 w-4" />
                                        Remove
                                    </Button>
                                </div>
                                <div v-if="techStacks.length === 0" class="rounded-md border border-dashed border-border px-3 py-2 text-sm text-muted-foreground">
                                    No tech stack entries added yet.
                                </div>
                            </div>

                            <div class="mt-4 grid grid-cols-1 gap-2 md:grid-cols-3">
                                <Input
                                    v-model="techStackDraft.name"
                                    type="text"
                                    placeholder="Stack name (e.g. Laravel 12)"
                                />
                                <Input
                                    v-model="techStackDraft.documentation_url"
                                    type="url"
                                    class="md:col-span-2"
                                    placeholder="Documentation URL"
                                />
                            </div>
                            <div class="mt-2 flex justify-end">
                                <Button variant="outline" :disabled="techStackSubmitting" @click="addTechStack">
                                    <Plus class="h-4 w-4" />
                                    {{ techStackSubmitting ? 'Adding...' : 'Add Tech Stack' }}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
