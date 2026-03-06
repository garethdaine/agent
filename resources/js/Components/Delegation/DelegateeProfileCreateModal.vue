<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import Modal from '@/Components/Modal.vue';
import Input from '@/Components/ui/Input.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Select from '@/Components/ui/Select.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import Button from '@/Components/ui/Button.vue';
import DirectoryPickerInput from '@/Components/ui/DirectoryPickerInput.vue';
import ApprovalModeSelect from '@/Components/Agent/ApprovalModeSelect.vue';

const DEFAULT_COMMAND_TEMPLATES = {
    claude: 'claude --verbose -p --output-format stream-json --include-partial-messages {{task_markdown_path}}',
    codex: 'codex exec --json {{task_markdown_path}}',
    custom: '{{task_markdown_path}}',
};

const props = defineProps({
    show: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'created']);

const submitting = ref(false);
const error = ref('');
const validationErrors = ref({});

const capabilities = ref([]);
const form = ref({
    name: '',
    runner_type: 'claude',
    command_template: DEFAULT_COMMAND_TEMPLATES.claude,
    working_directory: '',
    capability_ids: [],
    soul_personality: '',
    soul_system_prompt: '',
    soul_user_context: '',
});

const runnerTypeOptions = [
    { value: 'claude', label: 'claude' },
    { value: 'codex', label: 'codex' },
    { value: 'custom', label: 'custom' },
];

watch(
    () => form.value.runner_type,
    (runnerType) => {
        form.value.command_template = DEFAULT_COMMAND_TEMPLATES[runnerType] ?? DEFAULT_COMMAND_TEMPLATES.custom;
    }
);

watch(
    () => props.show,
    (visible) => {
        if (visible) {
            error.value = '';
            validationErrors.value = {};
            form.value = {
                name: '',
                runner_type: 'claude',
                command_template: DEFAULT_COMMAND_TEMPLATES.claude,
                working_directory: '',
                capability_ids: [],
                soul_personality: '',
                soul_system_prompt: '',
                soul_user_context: '',
            };
            loadCapabilities();
        }
    }
);

const loadCapabilities = async () => {
    try {
        const { data } = await axios.get('/agent/api/v1/delegation/capabilities');
        capabilities.value = data.data ?? [];
    } catch (e) {
        console.error('Failed to load capabilities', e);
    }
};

const allCapabilitiesSelected = computed(
    () =>
        capabilities.value.length > 0 &&
        form.value.capability_ids.length === capabilities.value.length,
);

const toggleCapability = (capId) => {
    const idx = form.value.capability_ids.indexOf(capId);
    if (idx === -1) {
        form.value.capability_ids.push(capId);
    } else {
        form.value.capability_ids.splice(idx, 1);
    }
};

const selectAllCapabilities = () => {
    form.value.capability_ids = capabilities.value.map((c) => c.id);
};

const deselectAllCapabilities = () => {
    form.value.capability_ids = [];
};

const submit = async () => {
    submitting.value = true;
    error.value = '';
    validationErrors.value = {};

    try {
        const payload = {
            name: form.value.name.trim(),
            runner_type: form.value.runner_type,
            command_template: form.value.command_template,
            working_directory: form.value.working_directory.trim() || '/',
            env_json: {},
            config_json: {},
            is_active: true,
            capability_ids: form.value.capability_ids,
            soul: {
                personality: form.value.soul_personality || null,
                system_prompt: form.value.soul_system_prompt || null,
                user_context: form.value.soul_user_context || null,
            },
        };
        const { data } = await axios.post('/agent/api/v1/delegation/delegatee-profiles', payload);
        emit('created', data.data);
        emit('close');
    } catch (e) {
        if (e?.response?.data?.error?.details) {
            validationErrors.value = e.response.data.error.details;
        } else {
            error.value = e?.response?.data?.error?.message ?? 'Failed to create profile.';
        }
    } finally {
        submitting.value = false;
    }
};

const close = () => {
    if (!submitting.value) emit('close');
};
</script>

<template>
    <Modal :show="show" max-width="lg" @close="close">
        <div class="px-6 py-4">
            <h3 class="text-lg font-medium text-foreground">Create delegatee profile</h3>
            <p class="mt-1 text-sm text-muted-foreground">
                Add a new profile to use for this agent. You can edit it later under Delegation → Delegatee Profiles.
            </p>

            <div class="mt-4 space-y-4">
                <div class="space-y-2">
                    <InputLabel>Name</InputLabel>
                    <Input
                        v-model="form.name"
                        type="text"
                        placeholder="My profile"
                        :disabled="submitting"
                        :class="{ 'border-destructive': validationErrors.name?.length }"
                    />
                    <p v-if="validationErrors.name" class="text-xs text-destructive">{{ validationErrors.name[0] }}</p>
                </div>

                <div class="space-y-2">
                    <InputLabel>Runner type</InputLabel>
                    <Select
                        v-model="form.runner_type"
                        :options="runnerTypeOptions"
                    />
                </div>

                <div class="space-y-2">
                    <InputLabel>Approval mode</InputLabel>
                    <ApprovalModeSelect
                        :runner-type="form.runner_type"
                        :model-value="form.command_template"
                        :disabled="submitting"
                        @update:model-value="(v) => (form.command_template = v)"
                    />
                </div>

                <div class="space-y-2">
                    <InputLabel>Command template</InputLabel>
                    <Input
                        v-model="form.command_template"
                        type="text"
                        class="font-mono text-sm"
                        placeholder="{{task_markdown_path}}"
                        :disabled="submitting"
                        :class="{ 'border-destructive': validationErrors.command_template?.length }"
                    />
                    <p v-if="validationErrors.command_template" class="text-xs text-destructive">{{ validationErrors.command_template[0] }}</p>
                </div>

                <div class="space-y-2">
                    <InputLabel>Working directory</InputLabel>
                    <DirectoryPickerInput
                        v-model="form.working_directory"
                        placeholder="/path/to/working/directory"
                        :disabled="submitting"
                        :error="!!validationErrors.working_directory?.length"
                    />
                    <p v-if="validationErrors.working_directory" class="text-xs text-destructive">{{ validationErrors.working_directory[0] }}</p>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <InputLabel class="mb-0">Capabilities</InputLabel>
                        <div v-if="capabilities.length > 0" class="flex gap-2">
                            <button
                                v-if="!allCapabilitiesSelected"
                                type="button"
                                class="text-xs text-primary hover:underline"
                                @click="selectAllCapabilities"
                            >
                                Select all
                            </button>
                            <button
                                v-if="allCapabilitiesSelected"
                                type="button"
                                class="text-xs text-primary hover:underline"
                                @click="deselectAllCapabilities"
                            >
                                Deselect all
                            </button>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="cap in capabilities"
                            :key="cap.id"
                            type="button"
                            class="rounded-full px-3 py-1 text-sm font-medium transition-colors"
                            :class="form.capability_ids.includes(cap.id) ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-accent'"
                            :disabled="submitting"
                            @click="toggleCapability(cap.id)"
                        >
                            {{ cap.slug }}
                        </button>
                        <span v-if="capabilities.length === 0" class="text-sm text-muted-foreground">Loading…</span>
                    </div>
                </div>

                <div class="border-t border-border pt-4 space-y-3">
                    <InputLabel class="mb-0 text-xs font-medium text-muted-foreground uppercase tracking-wider">Soul / Identity</InputLabel>
                    <div class="space-y-2">
                        <InputLabel>Personality</InputLabel>
                        <Textarea
                            v-model="form.soul_personality"
                            :rows="2"
                            placeholder="How this agent should behave and communicate..."
                            :disabled="submitting"
                        />
                    </div>
                    <div class="space-y-2">
                        <InputLabel>System prompt</InputLabel>
                        <Textarea
                            v-model="form.soul_system_prompt"
                            :rows="3"
                            placeholder="Core instructions for the agent..."
                            :disabled="submitting"
                        />
                    </div>
                    <div class="space-y-2">
                        <InputLabel>User context</InputLabel>
                        <Textarea
                            v-model="form.soul_user_context"
                            :rows="2"
                            placeholder="Additional context about you or your project..."
                            :disabled="submitting"
                        />
                    </div>
                </div>

                <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
            </div>
        </div>

        <div class="flex justify-end gap-2 border-t border-border px-6 py-4 bg-muted/30">
            <Button variant="outline" :disabled="submitting" @click="close">
                Cancel
            </Button>
            <Button :disabled="submitting || !form.name.trim()" @click="submit">
                {{ submitting ? 'Creating…' : 'Create profile' }}
            </Button>
        </div>
    </Modal>
</template>
