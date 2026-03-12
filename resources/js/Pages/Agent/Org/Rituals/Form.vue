<script setup>
import { computed, onMounted, ref } from 'vue';
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
import Skeleton from '@/Components/ui/Skeleton.vue';
import { Plus, Save, Trash2 } from 'lucide-vue-next';

const props = defineProps({
    ritualId: {
        type: String,
        default: null,
    },
});

const isEdit = computed(() => props.ritualId !== null);
const loading = ref(true);
const submitting = ref(false);
const formError = ref('');
const validationErrors = ref({});
const orgAgents = ref([]);

const nextPhaseNumber = ref(2);

const form = ref({
    name: '',
    description: '',
    cron_expression: '0 9 * * *',
    timezone: 'UTC',
    escalation_timeout_seconds: 3600,
    notification_level: 'escalations_only',
    context_inputs: [],
    verification_strategy: [],
    delivery_targets: [],
    phases: [
        {
            id: 'phase_1',
            name: 'Initial phase',
            depends_on_csv: '',
            role_slug: '',
        },
    ],
});

const roleOptions = computed(() => [
    { value: '', label: 'Select role' },
    ...orgAgents.value.map((agent) => ({
        value: agent.role_slug,
        label: `${agent.role_slug} (${agent.name})`,
    })),
]);

const notificationOptions = [
    { value: 'escalations_only', label: 'Escalations only' },
    { value: 'lifecycle', label: 'Lifecycle' },
    { value: 'verbose', label: 'Verbose' },
];

const strategyValueTypeOptions = [
    { value: 'string', label: 'Text' },
    { value: 'number', label: 'Number' },
    { value: 'boolean', label: 'Yes/No' },
    { value: 'json', label: 'Structured value' },
];

const booleanValueOptions = [
    { value: 'true', label: 'True' },
    { value: 'false', label: 'False' },
];

const addPhase = () => {
    const index = nextPhaseNumber.value;
    nextPhaseNumber.value += 1;

    form.value.phases.push({
        id: `phase_${index}`,
        name: `Phase ${index}`,
        depends_on_csv: '',
        role_slug: '',
    });
};

const removePhase = (index) => {
    if (form.value.phases.length === 1) {
        return;
    }

    form.value.phases.splice(index, 1);
};

const createEmptyArrayMapRow = () => ({
    key: '',
    values_csv: '',
});

const createEmptyStrategyRow = () => ({
    key: '',
    value_type: 'string',
    value: '',
});

const addContextInput = () => {
    form.value.context_inputs.push(createEmptyArrayMapRow());
};

const removeContextInput = (index) => {
    form.value.context_inputs.splice(index, 1);
};

const addVerificationRule = () => {
    form.value.verification_strategy.push(createEmptyStrategyRow());
};

const removeVerificationRule = (index) => {
    form.value.verification_strategy.splice(index, 1);
};

const addDeliveryTarget = () => {
    form.value.delivery_targets.push(createEmptyArrayMapRow());
};

const removeDeliveryTarget = (index) => {
    form.value.delivery_targets.splice(index, 1);
};

const inferStrategyValueType = (value) => {
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

const normalizeArrayMapRows = (value) => {
    if (value === null || value === undefined) {
        return [];
    }

    // Plain string → single row with the string as the key and no values
    if (typeof value === 'string') {
        return value.trim() ? [{ key: value.trim(), values_csv: '' }] : [];
    }

    // Plain array → each element becomes a row key
    if (Array.isArray(value)) {
        return value
            .filter((item) => item !== null && item !== undefined && String(item).trim() !== '')
            .map((item) => ({
                key: String(item).trim(),
                values_csv: '',
            }));
    }

    // Object/map → expected format {key: [values]}
    const entries = Object.entries(value);
    if (entries.length === 0) {
        return [];
    }

    return entries.map(([key, values]) => ({
        key,
        values_csv: Array.isArray(values)
            ? values.join(', ')
            : String(values ?? ''),
    }));
};

const normalizeStrategyRows = (value) => {
    if (value === null || value === undefined) {
        return [];
    }

    // Plain string → single row with key "strategy" and the string as the value
    if (typeof value === 'string') {
        return value.trim()
            ? [{ key: 'strategy', value_type: 'string', value: value.trim() }]
            : [];
    }

    // Plain array → each element becomes a row with auto-generated key
    if (Array.isArray(value)) {
        return value
            .filter((item) => item !== null && item !== undefined && String(item).trim() !== '')
            .map((item, index) => ({
                key: `rule_${index + 1}`,
                value_type: inferStrategyValueType(item),
                value: String(item),
            }));
    }

    // Object/map → expected format {key: value}
    const entries = Object.entries(value);
    if (entries.length === 0) {
        return [];
    }

    return entries.map(([key, rawValue]) => {
        const valueType = inferStrategyValueType(rawValue);

        if (valueType === 'json') {
            return {
                key,
                value_type: 'json',
                value: JSON.stringify(rawValue, null, 2),
            };
        }

        if (valueType === 'boolean') {
            return {
                key,
                value_type: 'boolean',
                value: rawValue ? 'true' : 'false',
            };
        }

        return {
            key,
            value_type: valueType,
            value: String(rawValue ?? ''),
        };
    });
};

const buildArrayMap = (entries) => {
    const output = {};

    entries.forEach((entry) => {
        const key = (entry.key ?? '').trim();
        if (!key) {
            return;
        }

        output[key] = (entry.values_csv ?? '')
            .split(',')
            .map((item) => item.trim())
            .filter(Boolean);
    });

    return Object.keys(output).length > 0 ? output : null;
};

const parseStrategyValue = (entry, index) => {
    if (entry.value_type === 'boolean') {
        return entry.value === 'true';
    }

    if (entry.value_type === 'number') {
        const parsed = Number(entry.value);
        if (Number.isNaN(parsed)) {
            throw new Error(`Verification rule ${index + 1} must use a valid number.`);
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
            throw new Error(`Verification rule ${index + 1} must use valid structured data.`);
        }
    }

    return entry.value ?? '';
};

const buildStrategyMap = (entries) => {
    const output = {};

    entries.forEach((entry, index) => {
        const key = (entry.key ?? '').trim();
        if (!key) {
            return;
        }

        output[key] = parseStrategyValue(entry, index);
    });

    return Object.keys(output).length > 0 ? output : null;
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

const validatePhases = () => {
    const phaseIds = new Set();

    for (const phase of form.value.phases) {
        if (!phase.id || !phase.name || !phase.role_slug) {
            throw new Error('Every phase must include id, name, and role mapping.');
        }

        if (phaseIds.has(phase.id)) {
            throw new Error(`Duplicate phase id "${phase.id}" is not allowed.`);
        }

        phaseIds.add(phase.id);
    }
};

const buildPayload = () => {
    validatePhases();

    const phaseGraph = form.value.phases.map((phase) => ({
        id: phase.id.trim(),
        name: phase.name.trim(),
        depends_on: phase.depends_on_csv
            .split(',')
            .map((item) => item.trim())
            .filter(Boolean),
    }));

    const phaseRoleMappings = {};
    for (const phase of form.value.phases) {
        phaseRoleMappings[phase.id.trim()] = phase.role_slug;
    }

    return {
        name: form.value.name,
        description: form.value.description || null,
        cron_expression: form.value.cron_expression,
        timezone: form.value.timezone || 'UTC',
        phase_graph: phaseGraph,
        phase_role_mappings: phaseRoleMappings,
        context_inputs: buildArrayMap(form.value.context_inputs),
        verification_strategy: buildStrategyMap(form.value.verification_strategy),
        delivery_targets: buildArrayMap(form.value.delivery_targets),
        escalation_timeout_seconds: Number(form.value.escalation_timeout_seconds),
        notification_level: form.value.notification_level,
    };
};

const loadReferenceData = async () => {
    const { data } = await axios.get('/agent/api/v1/org/agents', {
        params: { include_archived: false },
    });

    orgAgents.value = data.data ?? [];
};

const loadRitual = async () => {
    if (!isEdit.value) {
        return;
    }

    const { data } = await axios.get(`/agent/api/v1/org/rituals/${props.ritualId}`);
    const ritual = data.data;

    const phaseRoleMappings = ritual.phase_role_mappings ?? {};
    const phases = (ritual.phase_graph ?? []).map((phase) => ({
        id: phase.id ?? '',
        name: phase.name ?? '',
        depends_on_csv: (phase.depends_on ?? []).join(', '),
        role_slug: phaseRoleMappings[phase.id] ?? '',
    }));

    form.value = {
        name: ritual.name ?? '',
        description: ritual.description ?? '',
        cron_expression: ritual.cron_expression ?? '0 9 * * *',
        timezone: ritual.timezone ?? 'UTC',
        escalation_timeout_seconds: ritual.escalation_timeout_seconds ?? 3600,
        notification_level: ritual.notification_level ?? 'escalations_only',
        context_inputs: normalizeArrayMapRows(ritual.context_inputs),
        verification_strategy: normalizeStrategyRows(ritual.verification_strategy),
        delivery_targets: normalizeArrayMapRows(ritual.delivery_targets),
        phases: phases.length > 0 ? phases : form.value.phases,
    };

    nextPhaseNumber.value = form.value.phases.length + 1;
};

const submit = async () => {
    submitting.value = true;
    formError.value = '';
    validationErrors.value = {};

    try {
        const payload = buildPayload();

        if (isEdit.value) {
            await axios.put(`/agent/api/v1/org/rituals/${props.ritualId}`, payload);
        } else {
            await axios.post('/agent/api/v1/org/rituals', payload);
        }

        router.visit(route('org.rituals.index'));
    } catch (error) {
        validationErrors.value = mapValidationErrors(error);
        formError.value = error?.message?.includes('must use valid')
            ? error.message
            : error?.response?.data?.error?.message ?? error?.response?.data?.message ?? 'Failed to save ritual template.';
    } finally {
        submitting.value = false;
    }
};

onMounted(async () => {
    loading.value = true;
    formError.value = '';

    try {
        await loadReferenceData();
        await loadRitual();
    } catch (error) {
        formError.value = error?.response?.data?.error?.message ?? 'Failed to load ritual form data.';
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
                    <CardTitle>{{ isEdit ? 'Edit Ritual Template' : 'New Ritual Template' }}</CardTitle>
                    <CardDescription>Configure schedule, phase graph, and role mappings for recurring org workflows.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-5">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-foreground">Name</label>
                            <Input v-model="form.name" type="text" :error="!!validationErrors.name" placeholder="Daily Standup Ritual" />
                            <p v-if="validationErrors.name" class="text-xs text-destructive">{{ validationErrors.name[0] }}</p>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-foreground">Notification Level</label>
                            <Select v-model="form.notification_level" :options="notificationOptions" />
                            <p v-if="validationErrors.notification_level" class="text-xs text-destructive">{{ validationErrors.notification_level[0] }}</p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-foreground">Description</label>
                        <Textarea v-model="form.description" :rows="3" :error="!!validationErrors.description" placeholder="Summarize status and blockers by role." />
                        <p v-if="validationErrors.description" class="text-xs text-destructive">{{ validationErrors.description[0] }}</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-foreground">Cron Expression</label>
                            <Input v-model="form.cron_expression" type="text" :error="!!validationErrors.cron_expression" placeholder="0 9 * * *" />
                            <p v-if="validationErrors.cron_expression" class="text-xs text-destructive">{{ validationErrors.cron_expression[0] }}</p>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-foreground">Timezone</label>
                            <Input v-model="form.timezone" type="text" :error="!!validationErrors.timezone" placeholder="UTC" />
                            <p v-if="validationErrors.timezone" class="text-xs text-destructive">{{ validationErrors.timezone[0] }}</p>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-foreground">Escalation Timeout (seconds)</label>
                            <Input
                                v-model="form.escalation_timeout_seconds"
                                type="number"
                                :error="!!validationErrors.escalation_timeout_seconds"
                                placeholder="3600"
                            />
                            <p v-if="validationErrors.escalation_timeout_seconds" class="text-xs text-destructive">
                                {{ validationErrors.escalation_timeout_seconds[0] }}
                            </p>
                        </div>
                    </div>

                    <div class="space-y-3 rounded-lg border border-border p-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-foreground">Phases</h3>
                            <Button variant="outline" size="sm" @click="addPhase">
                                <Plus class="mr-2 h-3 w-3" />
                                Add Phase
                            </Button>
                        </div>

                        <div class="space-y-3">
                            <div v-for="(phase, index) in form.phases" :key="`phase-${index}`" class="rounded-md border border-border p-3">
                                <div class="mb-3 flex items-center justify-between">
                                    <p class="text-sm font-medium text-foreground">Phase {{ index + 1 }}</p>
                                    <Button variant="ghost" size="sm" :disabled="form.phases.length === 1" @click="removePhase(index)">
                                        <Trash2 class="h-3 w-3" />
                                    </Button>
                                </div>

                                <div class="grid gap-3 md:grid-cols-2">
                                    <div class="space-y-1">
                                        <label class="block text-xs font-medium text-muted-foreground">Phase ID</label>
                                        <Input v-model="phase.id" type="text" placeholder="collect_inputs" />
                                    </div>

                                    <div class="space-y-1">
                                        <label class="block text-xs font-medium text-muted-foreground">Phase Name</label>
                                        <Input v-model="phase.name" type="text" placeholder="Collect Inputs" />
                                    </div>

                                    <div class="space-y-1">
                                        <label class="block text-xs font-medium text-muted-foreground">Role</label>
                                        <Select v-model="phase.role_slug" :options="roleOptions" />
                                    </div>

                                    <div class="space-y-1">
                                        <label class="block text-xs font-medium text-muted-foreground">Depends On (comma separated phase IDs)</label>
                                        <Input v-model="phase.depends_on_csv" type="text" placeholder="collect_inputs" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="space-y-3 rounded-lg border border-border p-4">
                            <div class="flex items-center justify-between">
                                <label class="block text-sm font-medium text-foreground">Context Inputs</label>
                                <Button variant="outline" size="sm" @click="addContextInput">Add Input</Button>
                            </div>
                            <p class="text-xs text-muted-foreground">Map each context source to a list of values.</p>
                            <div v-if="form.context_inputs.length === 0" class="rounded-md border border-dashed border-border px-3 py-4 text-xs text-muted-foreground">
                                No context inputs configured.
                            </div>
                            <div class="space-y-2">
                                <div
                                    v-for="(entry, index) in form.context_inputs"
                                    :key="`context-input-${index}`"
                                    class="grid gap-2 rounded-md border border-border p-3"
                                >
                                    <Input v-model="entry.key" type="text" placeholder="Source (e.g. memory)" />
                                    <Input v-model="entry.values_csv" type="text" placeholder="Values (comma separated)" />
                                    <Button variant="ghost" size="sm" @click="removeContextInput(index)">Remove</Button>
                                </div>
                            </div>
                            <p v-if="validationErrors.context_inputs" class="text-xs text-destructive">{{ validationErrors.context_inputs[0] }}</p>
                        </div>

                        <div class="space-y-3 rounded-lg border border-border p-4">
                            <div class="flex items-center justify-between">
                                <label class="block text-sm font-medium text-foreground">Verification Strategy</label>
                                <Button variant="outline" size="sm" @click="addVerificationRule">Add Rule</Button>
                            </div>
                            <p class="text-xs text-muted-foreground">Set verification rules with typed values.</p>
                            <div v-if="form.verification_strategy.length === 0" class="rounded-md border border-dashed border-border px-3 py-4 text-xs text-muted-foreground">
                                No verification rules configured.
                            </div>
                            <div class="space-y-2">
                                <div
                                    v-for="(rule, index) in form.verification_strategy"
                                    :key="`verification-rule-${index}`"
                                    class="grid gap-2 rounded-md border border-border p-3"
                                >
                                    <Input v-model="rule.key" type="text" placeholder="Rule key (e.g. review)" />
                                    <Select v-model="rule.value_type" :options="strategyValueTypeOptions" />
                                    <template v-if="rule.value_type === 'boolean'">
                                        <Select v-model="rule.value" :options="booleanValueOptions" />
                                    </template>
                                    <template v-else-if="rule.value_type === 'json'">
                                        <Textarea v-model="rule.value" :rows="3" placeholder='{"mode":"strict"}' />
                                    </template>
                                    <template v-else>
                                        <Input v-model="rule.value" :type="rule.value_type === 'number' ? 'number' : 'text'" placeholder="Rule value" />
                                    </template>
                                    <Button variant="ghost" size="sm" @click="removeVerificationRule(index)">Remove</Button>
                                </div>
                            </div>
                            <p v-if="validationErrors.verification_strategy" class="text-xs text-destructive">{{ validationErrors.verification_strategy[0] }}</p>
                        </div>

                        <div class="space-y-3 rounded-lg border border-border p-4">
                            <div class="flex items-center justify-between">
                                <label class="block text-sm font-medium text-foreground">Delivery Targets</label>
                                <Button variant="outline" size="sm" @click="addDeliveryTarget">Add Target</Button>
                            </div>
                            <p class="text-xs text-muted-foreground">Map each destination to delivery channels or recipients.</p>
                            <div v-if="form.delivery_targets.length === 0" class="rounded-md border border-dashed border-border px-3 py-4 text-xs text-muted-foreground">
                                No delivery targets configured.
                            </div>
                            <div class="space-y-2">
                                <div
                                    v-for="(entry, index) in form.delivery_targets"
                                    :key="`delivery-target-${index}`"
                                    class="grid gap-2 rounded-md border border-border p-3"
                                >
                                    <Input v-model="entry.key" type="text" placeholder="Target (e.g. messenger)" />
                                    <Input v-model="entry.values_csv" type="text" placeholder="Values (comma separated)" />
                                    <Button variant="ghost" size="sm" @click="removeDeliveryTarget(index)">Remove</Button>
                                </div>
                            </div>
                            <p v-if="validationErrors.delivery_targets" class="text-xs text-destructive">{{ validationErrors.delivery_targets[0] }}</p>
                        </div>
                    </div>

                    <div class="pt-2">
                        <Button :disabled="submitting" @click="submit">
                            <Save class="mr-2 h-4 w-4" />
                            {{ submitting ? 'Saving...' : isEdit ? 'Save Changes' : 'Create Ritual' }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </template>
    </div>
</template>
