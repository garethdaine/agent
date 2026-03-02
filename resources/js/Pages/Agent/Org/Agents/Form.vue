<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
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
import { Save } from 'lucide-vue-next';

const props = defineProps({
    agentId: {
        type: String,
        default: null,
    },
});

const isEdit = computed(() => props.agentId !== null);
const loading = ref(true);
const submitting = ref(false);
const formError = ref('');
const validationErrors = ref({});

const delegateeProfiles = ref([]);
const orgAgents = ref([]);

const form = ref({
    name: '',
    role_description: '',
    delegatee_profile_id: '',
    capability_bindings: [],
    authority_overrides: [
        {
            key: '',
            value_type: 'string',
            value: '',
        },
    ],
    default_output_fields: [],
    parent_agent_id: '',
});

const authorityValueTypeOptions = [
    { value: 'string', label: 'Text' },
    { value: 'number', label: 'Number' },
    { value: 'boolean', label: 'Yes/No' },
    { value: 'json', label: 'Structured value' },
];

const booleanValueOptions = [
    { value: 'true', label: 'True' },
    { value: 'false', label: 'False' },
];

const schemaTypeOptions = [
    { value: 'string', label: 'Text' },
    { value: 'number', label: 'Number' },
    { value: 'integer', label: 'Whole number' },
    { value: 'boolean', label: 'Yes/No' },
    { value: 'array', label: 'List' },
    { value: 'object', label: 'Object' },
];

const slugifyRole = (value) => {
    const source = (value ?? '').toString().trim().toLowerCase();
    const slug = source
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 100);

    return slug || 'agent';
};

const autoRoleSlug = computed(() => slugifyRole(form.value.name));

const delegateeOptions = computed(() => [
    { value: '', label: 'Select a delegatee profile' },
    ...delegateeProfiles.value.map((profile) => ({
        value: String(profile.id),
        label: `${profile.name} (${profile.runner_type})`,
    })),
]);

const parentAgentOptions = computed(() => [
    { value: '', label: 'No manager' },
    ...orgAgents.value
        .filter((agent) => agent.id !== props.agentId)
        .map((agent) => ({
            value: agent.id,
            label: `${agent.name} (${agent.role_slug})`,
        })),
]);

const selectedDelegatee = computed(() =>
    delegateeProfiles.value.find((profile) => String(profile.id) === String(form.value.delegatee_profile_id))
);

const availableCapabilities = computed(() => selectedDelegatee.value?.capabilities ?? []);

watch(
    availableCapabilities,
    (capabilities) => {
        const allowed = new Set(capabilities.map((capability) => capability.slug));
        form.value.capability_bindings = form.value.capability_bindings.filter((capability) => allowed.has(capability));
    },
    { immediate: true }
);

const createEmptyAuthorityOverride = () => ({
    key: '',
    value_type: 'string',
    value: '',
});

const createEmptyOutputField = () => ({
    name: '',
    type: 'string',
    required: true,
});

const addAuthorityOverride = () => {
    form.value.authority_overrides.push(createEmptyAuthorityOverride());
};

const removeAuthorityOverride = (index) => {
    if (form.value.authority_overrides.length === 1) {
        form.value.authority_overrides[0] = createEmptyAuthorityOverride();

        return;
    }

    form.value.authority_overrides.splice(index, 1);
};

const addOutputField = () => {
    form.value.default_output_fields.push(createEmptyOutputField());
};

const removeOutputField = (index) => {
    form.value.default_output_fields.splice(index, 1);
};

const inferAuthorityValueType = (value) => {
    if (typeof value === 'boolean') {
        return 'boolean';
    }

    if (typeof value === 'number') {
        return 'number';
    }

    if (value !== null && typeof value === 'object') {
        return 'json';
    }

    return 'string';
};

const normalizeAuthorityOverrideRows = (overrides) => {
    const entries = Object.entries(overrides ?? {});
    if (entries.length === 0) {
        return [createEmptyAuthorityOverride()];
    }

    return entries.map(([key, value]) => {
        const valueType = inferAuthorityValueType(value);

        if (valueType === 'json') {
            return {
                key,
                value_type: 'json',
                value: JSON.stringify(value, null, 2),
            };
        }

        if (valueType === 'boolean') {
            return {
                key,
                value_type: 'boolean',
                value: value ? 'true' : 'false',
            };
        }

        return {
            key,
            value_type: valueType,
            value: String(value ?? ''),
        };
    });
};

const normalizeOutputFieldRows = (schema) => {
    const properties = schema?.properties;
    if (!properties || typeof properties !== 'object') {
        return [];
    }

    const requiredSet = new Set(Array.isArray(schema.required) ? schema.required : []);

    return Object.entries(properties).map(([name, definition]) => {
        const selectedType = definition?.type;

        return {
            name,
            type: ['string', 'number', 'integer', 'boolean', 'array', 'object'].includes(selectedType) ? selectedType : 'string',
            required: requiredSet.has(name),
        };
    });
};

const parseAuthorityOverrideValue = (entry, index) => {
    if (entry.value_type === 'boolean') {
        return entry.value === 'true';
    }

    if (entry.value_type === 'number') {
        const parsed = Number(entry.value);
        if (Number.isNaN(parsed)) {
            throw new Error(`Authority override ${index + 1} must be a valid number.`);
        }

        return parsed;
    }

    if (entry.value_type === 'json') {
        const trimmed = (entry.value ?? '').trim();
        if (trimmed === '') {
            return null;
        }

        try {
            return JSON.parse(trimmed);
        } catch (error) {
            throw new Error(`Authority override ${index + 1} must be valid structured data.`);
        }
    }

    return entry.value ?? '';
};

const buildAuthorityOverrides = () => {
    const overrides = {};

    form.value.authority_overrides.forEach((entry, index) => {
        const key = (entry.key ?? '').trim();
        if (!key) {
            return;
        }

        overrides[key] = parseAuthorityOverrideValue(entry, index);
    });

    return overrides;
};

const buildDefaultOutputSchema = () => {
    const fields = form.value.default_output_fields
        .map((field) => ({
            name: (field.name ?? '').trim(),
            type: field.type,
            required: !!field.required,
        }))
        .filter((field) => field.name !== '');

    if (fields.length === 0) {
        return null;
    }

    const properties = {};
    const required = [];

    fields.forEach((field) => {
        properties[field.name] = { type: field.type };
        if (field.required) {
            required.push(field.name);
        }
    });

    return {
        type: 'object',
        properties,
        ...(required.length > 0 ? { required } : {}),
    };
};

const loadReferenceData = async () => {
    const [delegateeResponse, orgAgentsResponse] = await Promise.all([
        axios.get('/agent/api/v1/delegation/delegatee-profiles', { params: { per_page: 100 } }),
        axios.get('/agent/api/v1/org/agents', { params: { include_archived: false } }),
    ]);

    delegateeProfiles.value = delegateeResponse.data?.data ?? [];
    orgAgents.value = orgAgentsResponse.data?.data ?? [];
};

const loadAgent = async () => {
    if (!isEdit.value) {
        return;
    }

    const { data } = await axios.get(`/agent/api/v1/org/agents/${props.agentId}`);
    const agent = data.data;

    form.value = {
        name: agent.name ?? '',
        role_description: agent.role_description ?? '',
        delegatee_profile_id: agent.delegatee_profile_id ? String(agent.delegatee_profile_id) : '',
        capability_bindings: [...(agent.capability_bindings ?? [])],
        authority_overrides: normalizeAuthorityOverrideRows(agent.authority_overrides ?? {}),
        default_output_fields: normalizeOutputFieldRows(agent.default_output_schema),
        parent_agent_id: agent.parent_agent_id ?? '',
    };
};

const toggleCapability = (slug) => {
    const index = form.value.capability_bindings.indexOf(slug);
    if (index === -1) {
        form.value.capability_bindings.push(slug);
        return;
    }

    form.value.capability_bindings.splice(index, 1);
};

const mapValidationErrors = (error) => {
    const details = error?.response?.data?.errors;
    if (details && typeof details === 'object') {
        return details;
    }

    const envelopeDetails = error?.response?.data?.error?.details;
    if (envelopeDetails && typeof envelopeDetails === 'object') {
        return envelopeDetails;
    }

    return {};
};

const submit = async () => {
    submitting.value = true;
    formError.value = '';
    validationErrors.value = {};

    try {
        const payload = {
            name: form.value.name,
            role_slug: autoRoleSlug.value,
            role_description: form.value.role_description,
            delegatee_profile_id: Number(form.value.delegatee_profile_id),
            capability_bindings: form.value.capability_bindings,
            authority_overrides: buildAuthorityOverrides(),
            default_output_schema: buildDefaultOutputSchema(),
            parent_agent_id: form.value.parent_agent_id || null,
        };

        if (isEdit.value) {
            await axios.put(`/agent/api/v1/org/agents/${props.agentId}`, payload);
        } else {
            await axios.post('/agent/api/v1/org/agents', payload);
        }

        router.visit(route('org.agents.index'));
    } catch (error) {
        validationErrors.value = mapValidationErrors(error);
        formError.value = error?.message?.includes('must be valid')
            ? error.message
            : error?.response?.data?.error?.message ?? error?.response?.data?.message ?? 'Failed to save agent.';
    } finally {
        submitting.value = false;
    }
};

onMounted(async () => {
    loading.value = true;
    formError.value = '';

    try {
        await loadReferenceData();
        await loadAgent();
    } catch (error) {
        formError.value = error?.response?.data?.error?.message ?? 'Failed to load agent form data.';
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="space-y-4">
        <p v-if="formError" class="rounded-lg border border-destructive bg-destructive/10 px-4 py-3 text-sm text-destructive">
            {{ formError }}
        </p>

        <template v-if="loading">
            <Card>
                <CardContent class="space-y-3 py-8">
                    <Skeleton class="h-4 w-48" />
                    <Skeleton class="h-9 w-full" />
                    <Skeleton class="h-9 w-full" />
                    <Skeleton class="h-24 w-full" />
                </CardContent>
            </Card>
        </template>

        <template v-else>
            <Card>
                <CardHeader>
                    <CardTitle>{{ isEdit ? 'Edit Agent' : 'New Agent' }}</CardTitle>
                    <CardDescription>Bind an agent identity to a delegatee profile and authority scope.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-5">
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-foreground">Name</label>
                        <Input v-model="form.name" type="text" :error="!!validationErrors.name" placeholder="Research Analyst" />
                        <p v-if="validationErrors.name" class="text-xs text-destructive">{{ validationErrors.name[0] }}</p>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-foreground">Manager</label>
                        <Select v-model="form.parent_agent_id" :options="parentAgentOptions" />
                        <p v-if="validationErrors.parent_agent_id" class="text-xs text-destructive">{{ validationErrors.parent_agent_id[0] }}</p>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-foreground">Role Description</label>
                        <Textarea
                            v-model="form.role_description"
                            :error="!!validationErrors.role_description"
                            :rows="4"
                            placeholder="Handles scoped research, summaries, and evidence collation."
                        />
                        <p v-if="validationErrors.role_description" class="text-xs text-destructive">{{ validationErrors.role_description[0] }}</p>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-foreground">Delegatee Profile</label>
                        <Select v-model="form.delegatee_profile_id" :options="delegateeOptions" />
                        <p v-if="validationErrors.delegatee_profile_id" class="text-xs text-destructive">{{ validationErrors.delegatee_profile_id[0] }}</p>
                        <p v-if="delegateeProfiles.length === 0" class="text-xs text-muted-foreground">
                            No delegatee profiles available. Create one under Delegation first.
                        </p>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-foreground">Capability Bindings</label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="capability in availableCapabilities"
                                :key="capability.id"
                                type="button"
                                class="rounded-full px-3 py-1 text-sm font-medium transition-colors"
                                :class="form.capability_bindings.includes(capability.slug) ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-accent'"
                                @click="toggleCapability(capability.slug)"
                            >
                                {{ capability.slug }}
                            </button>
                            <span v-if="availableCapabilities.length === 0" class="text-sm text-muted-foreground">
                                Select a delegatee profile to choose capabilities.
                            </span>
                        </div>
                        <p v-if="validationErrors.capability_bindings" class="text-xs text-destructive">{{ validationErrors.capability_bindings[0] }}</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-3 rounded-lg border border-border p-4">
                            <div class="flex items-center justify-between">
                                <label class="block text-sm font-medium text-foreground">Authority Overrides</label>
                                <Button variant="outline" size="sm" @click="addAuthorityOverride">Add Rule</Button>
                            </div>
                            <p class="text-xs text-muted-foreground">Optional guardrails and control flags for this agent.</p>
                            <div class="space-y-3">
                                <div
                                    v-for="(entry, index) in form.authority_overrides"
                                    :key="`authority-${index}`"
                                    class="grid gap-2 rounded-md border border-border p-3"
                                >
                                    <Input v-model="entry.key" type="text" placeholder="Rule key (e.g. max_budget)" />
                                    <div class="grid gap-2 md:grid-cols-[1fr_2fr_auto]">
                                        <Select v-model="entry.value_type" :options="authorityValueTypeOptions" />
                                        <template v-if="entry.value_type === 'boolean'">
                                            <Select v-model="entry.value" :options="booleanValueOptions" />
                                        </template>
                                        <template v-else-if="entry.value_type === 'json'">
                                            <Textarea v-model="entry.value" :rows="3" placeholder='{"limit": 3}' />
                                        </template>
                                        <template v-else>
                                            <Input v-model="entry.value" :type="entry.value_type === 'number' ? 'number' : 'text'" placeholder="Value" />
                                        </template>
                                        <Button variant="ghost" size="sm" @click="removeAuthorityOverride(index)">Remove</Button>
                                    </div>
                                </div>
                            </div>
                            <p v-if="validationErrors.authority_overrides" class="text-xs text-destructive">{{ validationErrors.authority_overrides[0] }}</p>
                        </div>

                        <div class="space-y-3 rounded-lg border border-border p-4">
                            <div class="flex items-center justify-between">
                                <label class="block text-sm font-medium text-foreground">Default Output</label>
                                <Button variant="outline" size="sm" @click="addOutputField">Add Field</Button>
                            </div>
                            <p class="text-xs text-muted-foreground">Define expected output fields without writing schema JSON.</p>
                            <div v-if="form.default_output_fields.length === 0" class="rounded-md border border-dashed border-border px-3 py-4 text-xs text-muted-foreground">
                                No output fields configured. Leave empty to allow flexible output.
                            </div>
                            <div class="space-y-3">
                                <div
                                    v-for="(field, index) in form.default_output_fields"
                                    :key="`output-field-${index}`"
                                    class="grid gap-2 rounded-md border border-border p-3"
                                >
                                    <Input v-model="field.name" type="text" placeholder="Field name (e.g. summary)" />
                                    <div class="grid gap-2 md:grid-cols-[1fr_auto_auto]">
                                        <Select v-model="field.type" :options="schemaTypeOptions" />
                                        <label class="flex items-center gap-2 text-xs text-muted-foreground">
                                            <input v-model="field.required" type="checkbox" class="h-4 w-4 rounded border-input text-primary focus:ring-primary" />
                                            Required
                                        </label>
                                        <Button variant="ghost" size="sm" @click="removeOutputField(index)">Remove</Button>
                                    </div>
                                </div>
                            </div>
                            <p v-if="validationErrors.default_output_schema" class="text-xs text-destructive">{{ validationErrors.default_output_schema[0] }}</p>
                        </div>
                    </div>

                    <div class="pt-2">
                        <Button :disabled="submitting" @click="submit">
                            <Save class="mr-2 h-4 w-4" />
                            {{ submitting ? 'Saving...' : isEdit ? 'Save Changes' : 'Create Agent' }}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="pt-6">
                    <div class="flex flex-wrap gap-2">
                        <Badge variant="outline">User scoped</Badge>
                        <Badge variant="outline">Authority narrowing only</Badge>
                        <Badge variant="outline">Audited mutation</Badge>
                    </div>
                </CardContent>
            </Card>
        </template>
    </div>
</template>
