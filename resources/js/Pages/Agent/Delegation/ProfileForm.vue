<script setup>
import { ref, onMounted, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardDescription from '@/Components/ui/CardDescription.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import Button from '@/Components/ui/Button.vue';
import Badge from '@/Components/ui/Badge.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import Spinner from '@/Components/ui/Spinner.vue';
import { ArrowLeft, Save } from 'lucide-vue-next';

const props = defineProps({
    profileId: {
        type: Number,
        default: null,
    },
});

const isEdit = computed(() => props.profileId !== null);

const loading = ref(true);
const submitting = ref(false);
const error = ref('');
const validationErrors = ref({});

const capabilities = ref([]);

const form = ref({
    name: '',
    runner_type: 'claude',
    command_template: '',
    working_directory: '',
    env_json: '{}',
    config_json: '{}',
    is_active: true,
    capability_ids: [],
});

const runnerTypeOptions = [
    { value: 'claude', label: 'claude' },
    { value: 'codex', label: 'codex' },
    { value: 'custom', label: 'custom' },
];

const loadCapabilities = async () => {
    try {
        const { data } = await axios.get('/agent/api/v1/delegation/capabilities');
        capabilities.value = data.data;
    } catch (e) {
        console.error('Failed to load capabilities', e);
    }
};

const loadProfile = async () => {
    if (!isEdit.value) {
        loading.value = false;
        return;
    }

    try {
        const { data } = await axios.get(`/agent/api/v1/delegation/delegatee-profiles/${props.profileId}`);
        const profile = data.data;
        form.value = {
            name: profile.name || '',
            runner_type: profile.runner_type || 'claude',
            command_template: profile.command_template || '',
            working_directory: profile.working_directory || '',
            env_json: profile.env_json ? JSON.stringify(profile.env_json, null, 2) : '{}',
            config_json: profile.config_json ? JSON.stringify(profile.config_json, null, 2) : '{}',
            is_active: profile.is_active ?? true,
            capability_ids: profile.capabilities?.map(c => c.id) || [],
        };
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load profile.';
    } finally {
        loading.value = false;
    }
};

const submit = async () => {
    submitting.value = true;
    error.value = '';
    validationErrors.value = {};

    try {
        const payload = {
            name: form.value.name,
            runner_type: form.value.runner_type,
            command_template: form.value.command_template,
            working_directory: form.value.working_directory,
            env_json: JSON.parse(form.value.env_json),
            config_json: JSON.parse(form.value.config_json),
            is_active: form.value.is_active,
            capability_ids: form.value.capability_ids,
        };

        if (isEdit.value) {
            await axios.put(`/agent/api/v1/delegation/delegatee-profiles/${props.profileId}`, payload);
        } else {
            await axios.post('/agent/api/v1/delegation/delegatee-profiles', payload);
        }

        router.visit(route('agent.delegation.profiles.index'));
    } catch (e) {
        if (e?.response?.data?.error?.details) {
            validationErrors.value = e.response.data.error.details;
        } else {
            error.value = e?.response?.data?.error?.message ?? 'Failed to save profile.';
        }
    } finally {
        submitting.value = false;
    }
};

const toggleCapability = (capId) => {
    const idx = form.value.capability_ids.indexOf(capId);
    if (idx === -1) {
        form.value.capability_ids.push(capId);
    } else {
        form.value.capability_ids.splice(idx, 1);
    }
};

onMounted(async () => {
    await loadCapabilities();
    await loadProfile();
});
</script>

<template>
    <AppLayout :title="isEdit ? 'Edit Profile' : 'Create Profile'">
        <Head :title="isEdit ? 'Edit Profile' : 'Create Profile'" />

        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('agent.delegation.profiles.index')">
                    <Button variant="ghost" size="icon">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                </Link>
                <div>
                    <div class="text-sm text-muted-foreground mb-1">
                        <Link :href="route('agent.delegation.profiles.index')" class="hover:text-foreground transition-colors">
                            Back to Profiles
                        </Link>
                    </div>
                    <h2 class="text-xl font-semibold leading-tight text-foreground">
                        {{ isEdit ? 'Edit Delegatee Profile' : 'Create Delegatee Profile' }}
                    </h2>
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-4xl space-y-6">
                <p v-if="error" class="rounded-lg border border-destructive bg-destructive/10 px-4 py-3 text-sm text-destructive">
                    {{ error }}
                </p>

                <div v-if="loading" class="flex flex-col items-center justify-center py-12 gap-4">
                    <Skeleton class="h-4 w-48" />
                    <Skeleton class="h-4 w-32" />
                </div>

                <template v-else>
                    <Card>
                        <CardHeader>
                            <CardTitle>Profile Details</CardTitle>
                            <CardDescription>Configure the delegatee profile settings</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">Name</label>
                                <Input v-model="form.name" type="text" placeholder="My Delegatee Profile" :error="!!validationErrors.name" />
                                <p v-if="validationErrors.name" class="text-xs text-destructive">{{ validationErrors.name[0] }}</p>
                            </div>

                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">Runner Type</label>
                                <Select v-model="form.runner_type" :options="runnerTypeOptions" />
                                <p v-if="validationErrors.runner_type" class="text-xs text-destructive">{{ validationErrors.runner_type[0] }}</p>
                            </div>

                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">Command Template</label>
                                <Input
                                    v-model="form.command_template"
                                    type="text"
                                    class="font-mono"
                                    placeholder="claude -p {{task_markdown_path}}"
                                    :error="!!validationErrors.command_template"
                                />
                                <p class="text-xs text-muted-foreground">Available placeholders: {{task_markdown_path}}, {{working_directory}}</p>
                                <p v-if="validationErrors.command_template" class="text-xs text-destructive">{{ validationErrors.command_template[0] }}</p>
                            </div>

                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">Working Directory</label>
                                <Input
                                    v-model="form.working_directory"
                                    type="text"
                                    class="font-mono"
                                    placeholder="/path/to/working/directory"
                                    :error="!!validationErrors.working_directory"
                                />
                                <p v-if="validationErrors.working_directory" class="text-xs text-destructive">{{ validationErrors.working_directory[0] }}</p>
                            </div>

                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">Environment Variables (JSON)</label>
                                <textarea
                                    v-model="form.env_json"
                                    rows="4"
                                    class="block w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-xs text-foreground placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/20"
                                    :class="{ 'border-destructive': validationErrors.env_json }"
                                />
                                <p v-if="validationErrors.env_json" class="text-xs text-destructive">{{ validationErrors.env_json[0] }}</p>
                            </div>

                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">Config (JSON)</label>
                                <textarea
                                    v-model="form.config_json"
                                    rows="4"
                                    class="block w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-xs text-foreground placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/20"
                                    :class="{ 'border-destructive': validationErrors.config_json }"
                                />
                                <p v-if="validationErrors.config_json" class="text-xs text-destructive">{{ validationErrors.config_json[0] }}</p>
                            </div>

                            <div class="space-y-3">
                                <label class="block text-sm font-medium text-foreground">Capabilities</label>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="cap in capabilities"
                                        :key="cap.id"
                                        type="button"
                                        class="rounded-full px-3 py-1 text-sm font-medium transition-colors"
                                        :class="form.capability_ids.includes(cap.id) ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-accent'"
                                        @click="toggleCapability(cap.id)"
                                    >
                                        {{ cap.slug }}
                                    </button>
                                    <span v-if="capabilities.length === 0" class="text-sm text-muted-foreground">No capabilities available.</span>
                                </div>
                            </div>

                            <div>
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input
                                        v-model="form.is_active"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-input text-primary focus:ring-primary"
                                    />
                                    <span class="text-sm text-foreground">Active</span>
                                </label>
                            </div>
                        </CardContent>
                    </Card>

                    <div class="flex items-center justify-end gap-3">
                        <Link :href="route('agent.delegation.profiles.index')">
                            <Button variant="outline">Cancel</Button>
                        </Link>
                        <Button
                            :disabled="submitting || !form.name"
                            @click="submit"
                        >
                            <Spinner v-if="submitting" size="sm" class="mr-2" />
                            <Save v-else class="mr-2 h-4 w-4" />
                            {{ submitting ? 'Saving...' : 'Save Profile' }}
                        </Button>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
