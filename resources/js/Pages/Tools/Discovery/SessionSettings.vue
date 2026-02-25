<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
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
});

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

const saveSession = async () => {
    saving.value = true;
    error.value = '';
    validation.value = {};

    try {
        await axios.patch(`/agent/api/v1/interrogation/sessions/${props.sessionId}`, {
            name: form.name,
            feature_brief: form.feature_brief,
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

watch(() => providerTeamForm.team_id, (nextTeamId) => {
    const currentTeamId = String(linearProvider.value?.team_id ?? '').trim();
    if (String(nextTeamId ?? '').trim() === currentTeamId) {
        return;
    }

    if (providerProjectForm.project_mode === 'existing') {
        providerProjectForm.project_mode = 'create_new';
        providerProjectForm.existing_project_id = '';
    }
});
</script>

<template>
    <AppLayout title="Session Settings">
        <Head title="Session Settings" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Session Settings</h2>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ session?.name || `Session #${sessionId}` }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('tools.discovery.wizard', sessionId)" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200">Open Wizard</Link>
                    <Link :href="route('tools.discovery.index')" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200">Back</Link>
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-5xl space-y-4">
                <p v-if="error" class="rounded border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">{{ error }}</p>
                <p v-if="notice" class="rounded border border-green-300 bg-green-50 px-3 py-2 text-sm text-green-700 dark:border-green-900/60 dark:bg-green-950/40 dark:text-green-300">{{ notice }}</p>

                <div v-if="loading" class="rounded-lg border border-gray-200 bg-white p-8 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                    Loading session settings...
                </div>

                <template v-else>
                    <div class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Session Details</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Update initial brief and naming for this discovery session.</p>

                        <div class="mt-4 space-y-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Session Name</label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    class="mt-1 w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                    placeholder="Session name"
                                />
                                <p v-if="firstValidationError('name')" class="mt-1 text-xs text-red-600">{{ firstValidationError('name') }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Initial Brief</label>
                                <textarea
                                    v-model="form.feature_brief"
                                    rows="8"
                                    class="mt-1 w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                    placeholder="Feature brief or initial context for this session"
                                />
                                <p v-if="firstValidationError('feature_brief')" class="mt-1 text-xs text-red-600">{{ firstValidationError('feature_brief') }}</p>
                            </div>
                        </div>

                        <div class="mt-4 flex justify-end">
                            <button
                                type="button"
                                class="rounded bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
                                :disabled="saving"
                                @click="saveSession"
                            >
                                {{ saving ? 'Saving...' : 'Save Session Settings' }}
                            </button>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Task Provider (Optional)</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Connect Linear to sync approved build tasks for this session.</p>

                        <div class="mt-3 rounded border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900/40">
                            <p v-if="linearProvider" class="text-sm text-gray-800 dark:text-gray-100">
                                Connected to Linear
                                <span v-if="linearProvider.provider_workspace_name">({{ linearProvider.provider_workspace_name }})</span>
                                <span v-if="linearProvider.team_name">· Team: {{ linearProvider.team_name }}</span>
                            </p>
                            <p v-else class="text-sm text-gray-700 dark:text-gray-200">Linear is not connected for this session.</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button
                                    v-if="!linearProvider"
                                    type="button"
                                    class="rounded bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
                                    :disabled="providerConnecting"
                                    @click="startProviderOAuth('linear')"
                                >
                                    {{ providerConnecting ? 'Redirecting...' : 'Connect Linear' }}
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    class="rounded border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700/50"
                                    :disabled="providerDisconnecting"
                                    @click="disconnectProvider('linear')"
                                >
                                    {{ providerDisconnecting ? 'Disconnecting...' : 'Disconnect Linear' }}
                                </button>
                            </div>

                            <div v-if="linearProvider" class="mt-4 space-y-3 rounded border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900/50">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Team</p>
                                <div class="space-y-2">
                                    <div class="flex items-center gap-2">
                                        <select
                                            v-model="providerTeamForm.team_id"
                                            class="w-full rounded border border-gray-300 px-2 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                        >
                                            <option value="">Select a Linear team...</option>
                                            <option v-for="team in linearTeams" :key="team.id" :value="team.id">
                                                {{ team.name || team.id }}
                                            </option>
                                        </select>
                                        <button
                                            type="button"
                                            class="rounded border border-gray-300 px-2 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700/50"
                                            :disabled="providerProjectsLoading"
                                            @click="loadLinearProjects({ force: true })"
                                        >
                                            {{ providerProjectsLoading ? 'Loading...' : 'Refresh' }}
                                        </button>
                                    </div>
                                    <p v-if="firstValidationError('team_id')" class="text-xs text-red-600">{{ firstValidationError('team_id') }}</p>
                                    <p v-if="selectedLinearTeam" class="text-xs text-gray-500 dark:text-gray-400">
                                        Selected team: {{ selectedLinearTeam.name || selectedLinearTeam.id }}
                                    </p>
                                </div>

                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Project Sync Target</p>
                                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                                    <input v-model="providerProjectForm.project_mode" type="radio" value="create_new" class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                    Create a new Linear project for this session
                                </label>
                                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                                    <input v-model="providerProjectForm.project_mode" type="radio" value="existing" class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                    Use an existing Linear project
                                </label>

                                <div v-if="providerProjectForm.project_mode === 'existing'" class="space-y-2">
                                    <div class="flex items-center gap-2">
                                        <select
                                            v-model="providerProjectForm.existing_project_id"
                                            class="w-full rounded border border-gray-300 px-2 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                        >
                                            <option value="">Select a Linear project...</option>
                                            <option v-for="project in linearProjects" :key="project.id" :value="project.id">
                                                {{ project.name || project.id }}
                                            </option>
                                        </select>
                                        <button
                                            type="button"
                                            class="rounded border border-gray-300 px-2 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700/50"
                                            :disabled="providerProjectsLoading"
                                            @click="loadLinearProjects({ force: true })"
                                        >
                                            {{ providerProjectsLoading ? 'Loading...' : 'Refresh' }}
                                        </button>
                                    </div>
                                    <p v-if="firstValidationError('existing_project_id')" class="text-xs text-red-600">{{ firstValidationError('existing_project_id') }}</p>
                                    <p v-if="selectedLinearProject?.url" class="text-xs text-gray-500 dark:text-gray-400">
                                        Selected project:
                                        <a :href="selectedLinearProject.url" target="_blank" rel="noreferrer" class="text-indigo-700 underline dark:text-indigo-300">{{ selectedLinearProject.name || selectedLinearProject.id }}</a>
                                    </p>
                                </div>

                                <div class="flex justify-end">
                                    <button
                                        type="button"
                                        class="rounded bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
                                        :disabled="providerSettingsSaving || providerTeamForm.team_id.trim() === '' || (providerProjectForm.project_mode === 'existing' && providerProjectForm.existing_project_id.trim() === '')"
                                        @click="saveProviderProjectSettings"
                                    >
                                        {{ providerSettingsSaving ? 'Saving...' : 'Save Provider Settings' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Tech Stack</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Manage tech stack entries used as context throughout this session.</p>

                        <div class="mt-3 space-y-2">
                            <div v-for="stack in techStacks" :key="stack.id" class="flex flex-wrap items-center justify-between gap-2 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900/40">
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ stack.name }}</p>
                                    <a :href="stack.documentation_url" target="_blank" rel="noreferrer" class="text-xs text-indigo-700 underline dark:text-indigo-300">{{ stack.documentation_url }}</a>
                                </div>
                                <button
                                    type="button"
                                    class="rounded border border-red-300 px-2 py-1 text-xs text-red-700 hover:bg-red-50 dark:border-red-800/70 dark:text-red-300 dark:hover:bg-red-950/30"
                                    @click="removeTechStack(stack.id)"
                                >
                                    Remove
                                </button>
                            </div>
                            <div v-if="techStacks.length === 0" class="rounded border border-dashed border-gray-300 px-3 py-2 text-xs text-gray-500 dark:border-gray-600 dark:text-gray-400">
                                No tech stack entries added yet.
                            </div>
                        </div>

                        <div class="mt-3 grid grid-cols-1 gap-2 md:grid-cols-3">
                            <input
                                v-model="techStackDraft.name"
                                type="text"
                                class="rounded border border-gray-300 px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                placeholder="Stack name (e.g. Laravel 12)"
                            />
                            <input
                                v-model="techStackDraft.documentation_url"
                                type="url"
                                class="rounded border border-gray-300 px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 md:col-span-2"
                                placeholder="Documentation URL"
                            />
                        </div>
                        <div class="mt-2 flex justify-end">
                            <button
                                type="button"
                                class="rounded border border-indigo-300 px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50 disabled:opacity-50 dark:border-indigo-800/70 dark:text-indigo-300 dark:hover:bg-indigo-950/30"
                                :disabled="techStackSubmitting"
                                @click="addTechStack"
                            >
                                {{ techStackSubmitting ? 'Adding...' : 'Add Tech Stack' }}
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
