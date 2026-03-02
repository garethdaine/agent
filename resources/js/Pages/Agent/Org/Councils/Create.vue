<script setup>
import { computed, onMounted, ref } from 'vue';
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
import Skeleton from '@/Components/ui/Skeleton.vue';
import { ArrowLeft, Plus, Trash2, Save } from 'lucide-vue-next';

const loading = ref(true);
const submitting = ref(false);
const error = ref('');
const validationErrors = ref({});
const orgAgents = ref([]);

const form = ref({
    name: '',
    description: '',
    synthesis_mode: 'majority',
    use_model_synthesis: false,
    evidence_payload_fields: [],
    member_response_fields: [],
    report_sections: [],
    members: [
        {
            agent_id: '',
            perspective: '',
            weight: '',
            is_chair: false,
        },
    ],
});

const synthesisModeOptions = [
    { value: 'majority', label: 'Majority' },
    { value: 'weighted', label: 'Weighted' },
    { value: 'chair_decides', label: 'Chair decides' },
];

const schemaTypeOptions = [
    { value: 'string', label: 'Text' },
    { value: 'number', label: 'Number' },
    { value: 'integer', label: 'Whole number' },
    { value: 'boolean', label: 'Yes/No' },
    { value: 'array', label: 'List' },
    { value: 'object', label: 'Object' },
];

const agentOptions = computed(() => [
    { value: '', label: 'Select agent' },
    ...orgAgents.value.map((agent) => ({
        value: agent.id,
        label: `${agent.name} (${agent.role_slug})`,
    })),
]);

const addMember = () => {
    form.value.members.push({
        agent_id: '',
        perspective: '',
        weight: '',
        is_chair: false,
    });
};

const removeMember = (index) => {
    if (form.value.members.length === 1) {
        return;
    }

    form.value.members.splice(index, 1);
};

const createEmptySchemaField = () => ({
    name: '',
    type: 'string',
    required: true,
});

const createEmptyReportSection = () => ({
    value: '',
});

const addEvidenceField = () => {
    form.value.evidence_payload_fields.push(createEmptySchemaField());
};

const removeEvidenceField = (index) => {
    form.value.evidence_payload_fields.splice(index, 1);
};

const addMemberResponseField = () => {
    form.value.member_response_fields.push(createEmptySchemaField());
};

const removeMemberResponseField = (index) => {
    form.value.member_response_fields.splice(index, 1);
};

const addReportSection = () => {
    form.value.report_sections.push(createEmptyReportSection());
};

const removeReportSection = (index) => {
    form.value.report_sections.splice(index, 1);
};

const buildObjectSchema = (fields) => {
    const normalizedFields = fields
        .map((field) => ({
            name: (field.name ?? '').trim(),
            type: field.type,
            required: !!field.required,
        }))
        .filter((field) => field.name !== '');

    if (normalizedFields.length === 0) {
        return null;
    }

    const properties = {};
    const required = [];

    normalizedFields.forEach((field) => {
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

const buildReportSections = () => {
    const sections = form.value.report_sections
        .map((section) => (section.value ?? '').trim())
        .filter(Boolean);

    return sections.length > 0 ? sections : null;
};

const loadOrgAgents = async () => {
    const { data } = await axios.get('/agent/api/v1/org/agents', {
        params: {
            include_archived: false,
        },
    });

    orgAgents.value = data.data ?? [];
};

const mapValidationErrors = (requestError) => {
    if (requestError?.response?.data?.errors) {
        return requestError.response.data.errors;
    }

    if (requestError?.response?.data?.error?.details) {
        return requestError.response.data.error.details;
    }

    return {};
};

const submit = async () => {
    submitting.value = true;
    error.value = '';
    validationErrors.value = {};

    try {
        const payload = {
            name: form.value.name,
            description: form.value.description || null,
            synthesis_mode: form.value.synthesis_mode,
            use_model_synthesis: form.value.use_model_synthesis,
            member_list: form.value.members.map((member) => ({
                agent_id: member.agent_id,
                perspective: member.perspective,
                weight: member.weight === '' ? undefined : Number(member.weight),
                is_chair: !!member.is_chair,
            })),
            evidence_payload_schema: buildObjectSchema(form.value.evidence_payload_fields),
            member_response_schema: buildObjectSchema(form.value.member_response_fields),
            report_sections: buildReportSections(),
        };

        await axios.post('/agent/api/v1/org/councils', payload);
        router.visit(route('org.councils.index'));
    } catch (requestError) {
        validationErrors.value = mapValidationErrors(requestError);
        error.value = requestError?.message?.includes('must be valid')
            ? requestError.message
            : requestError?.response?.data?.error?.message
                ?? requestError?.response?.data?.message
                ?? 'Failed to create council template.';
    } finally {
        submitting.value = false;
    }
};

onMounted(async () => {
    loading.value = true;
    error.value = '';

    try {
        await loadOrgAgents();
    } catch (requestError) {
        error.value = requestError?.response?.data?.error?.message ?? 'Failed to load agents.';
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <AppLayout title="Create Council">
        <Head title="Create Council" />

        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('org.councils.index')">
                    <Button variant="ghost" size="sm">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                </Link>
                <h2 class="text-xl font-semibold leading-tight text-foreground">Create Council</h2>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-4xl space-y-4">
                <p v-if="error" class="rounded-lg border border-destructive bg-destructive/10 px-4 py-3 text-sm text-destructive">
                    {{ error }}
                </p>

                <Card v-if="loading">
                    <CardContent class="space-y-3 py-8">
                        <Skeleton class="h-4 w-52" />
                        <Skeleton class="h-9 w-full" />
                        <Skeleton class="h-24 w-full" />
                    </CardContent>
                </Card>

                <Card v-else>
                    <CardHeader>
                        <CardTitle>New Council Template</CardTitle>
                        <CardDescription>Define members, synthesis mode, and evidence/report contracts.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-5">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">Name</label>
                                <Input v-model="form.name" type="text" :error="!!validationErrors.name" placeholder="Executive Review Council" />
                                <p v-if="validationErrors.name" class="text-xs text-destructive">{{ validationErrors.name[0] }}</p>
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-foreground">Synthesis Mode</label>
                                <Select v-model="form.synthesis_mode" :options="synthesisModeOptions" />
                                <p v-if="validationErrors.synthesis_mode" class="text-xs text-destructive">{{ validationErrors.synthesis_mode[0] }}</p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-foreground">Description</label>
                            <Textarea v-model="form.description" :rows="3" :error="!!validationErrors.description" placeholder="Cross-functional weekly strategic review." />
                        </div>

                        <div class="space-y-3 rounded-lg border border-border p-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-foreground">Council Members</h3>
                                <Button variant="outline" size="sm" @click="addMember">
                                    <Plus class="mr-2 h-3 w-3" />
                                    Add Member
                                </Button>
                            </div>

                            <div v-for="(member, index) in form.members" :key="`member-${index}`" class="rounded-md border border-border p-3">
                                <div class="mb-3 flex items-center justify-between">
                                    <p class="text-sm font-medium text-foreground">Member {{ index + 1 }}</p>
                                    <Button variant="ghost" size="sm" :disabled="form.members.length === 1" @click="removeMember(index)">
                                        <Trash2 class="h-3 w-3" />
                                    </Button>
                                </div>

                                <div class="grid gap-3 md:grid-cols-2">
                                    <div class="space-y-1">
                                        <label class="block text-xs font-medium text-muted-foreground">Agent</label>
                                        <Select v-model="member.agent_id" :options="agentOptions" />
                                    </div>
                                    <div class="space-y-1">
                                        <label class="block text-xs font-medium text-muted-foreground">Perspective</label>
                                        <Input v-model="member.perspective" type="text" placeholder="Risk reviewer" />
                                    </div>
                                    <div class="space-y-1">
                                        <label class="block text-xs font-medium text-muted-foreground">Weight (optional)</label>
                                        <Input v-model="member.weight" type="number" placeholder="1.0" />
                                    </div>
                                    <div class="space-y-1">
                                        <label class="mt-6 flex items-center gap-2 text-xs text-muted-foreground">
                                            <input v-model="member.is_chair" type="checkbox" class="h-4 w-4 rounded border-input text-primary focus:ring-primary" />
                                            Is chair
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <p v-if="validationErrors.member_list" class="text-xs text-destructive">{{ validationErrors.member_list[0] }}</p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="space-y-3 rounded-lg border border-border p-4">
                                <div class="flex items-center justify-between">
                                    <label class="block text-sm font-medium text-foreground">Evidence Payload Fields</label>
                                    <Button variant="outline" size="sm" @click="addEvidenceField">Add Field</Button>
                                </div>
                                <p class="text-xs text-muted-foreground">Build expected evidence input fields.</p>
                                <div v-if="form.evidence_payload_fields.length === 0" class="rounded-md border border-dashed border-border px-3 py-4 text-xs text-muted-foreground">
                                    No fields configured.
                                </div>
                                <div class="space-y-2">
                                    <div
                                        v-for="(field, index) in form.evidence_payload_fields"
                                        :key="`evidence-field-${index}`"
                                        class="grid gap-2 rounded-md border border-border p-3"
                                    >
                                        <Input v-model="field.name" type="text" placeholder="Field name" />
                                        <Select v-model="field.type" :options="schemaTypeOptions" />
                                        <div class="flex items-center justify-between gap-2">
                                            <label class="flex items-center gap-2 text-xs text-muted-foreground">
                                                <input v-model="field.required" type="checkbox" class="h-4 w-4 rounded border-input text-primary focus:ring-primary" />
                                                Required
                                            </label>
                                            <Button variant="ghost" size="sm" @click="removeEvidenceField(index)">Remove</Button>
                                        </div>
                                    </div>
                                </div>
                                <p v-if="validationErrors.evidence_payload_schema" class="text-xs text-destructive">{{ validationErrors.evidence_payload_schema[0] }}</p>
                            </div>

                            <div class="space-y-3 rounded-lg border border-border p-4">
                                <div class="flex items-center justify-between">
                                    <label class="block text-sm font-medium text-foreground">Member Response Fields</label>
                                    <Button variant="outline" size="sm" @click="addMemberResponseField">Add Field</Button>
                                </div>
                                <p class="text-xs text-muted-foreground">Define what each member response should contain.</p>
                                <div v-if="form.member_response_fields.length === 0" class="rounded-md border border-dashed border-border px-3 py-4 text-xs text-muted-foreground">
                                    No fields configured.
                                </div>
                                <div class="space-y-2">
                                    <div
                                        v-for="(field, index) in form.member_response_fields"
                                        :key="`member-response-field-${index}`"
                                        class="grid gap-2 rounded-md border border-border p-3"
                                    >
                                        <Input v-model="field.name" type="text" placeholder="Field name" />
                                        <Select v-model="field.type" :options="schemaTypeOptions" />
                                        <div class="flex items-center justify-between gap-2">
                                            <label class="flex items-center gap-2 text-xs text-muted-foreground">
                                                <input v-model="field.required" type="checkbox" class="h-4 w-4 rounded border-input text-primary focus:ring-primary" />
                                                Required
                                            </label>
                                            <Button variant="ghost" size="sm" @click="removeMemberResponseField(index)">Remove</Button>
                                        </div>
                                    </div>
                                </div>
                                <p v-if="validationErrors.member_response_schema" class="text-xs text-destructive">{{ validationErrors.member_response_schema[0] }}</p>
                            </div>

                            <div class="space-y-3 rounded-lg border border-border p-4">
                                <div class="flex items-center justify-between">
                                    <label class="block text-sm font-medium text-foreground">Report Sections</label>
                                    <Button variant="outline" size="sm" @click="addReportSection">Add Section</Button>
                                </div>
                                <p class="text-xs text-muted-foreground">List sections to include in the synthesized report.</p>
                                <div v-if="form.report_sections.length === 0" class="rounded-md border border-dashed border-border px-3 py-4 text-xs text-muted-foreground">
                                    No report sections configured. Defaults will be used.
                                </div>
                                <div class="space-y-2">
                                    <div
                                        v-for="(section, index) in form.report_sections"
                                        :key="`report-section-${index}`"
                                        class="flex items-center gap-2 rounded-md border border-border p-3"
                                    >
                                        <Input v-model="section.value" type="text" placeholder="Section name (e.g. agreements)" />
                                        <Button variant="ghost" size="sm" @click="removeReportSection(index)">Remove</Button>
                                    </div>
                                </div>
                                <p v-if="validationErrors.report_sections" class="text-xs text-destructive">{{ validationErrors.report_sections[0] }}</p>
                            </div>
                        </div>

                        <div class="pt-2">
                            <Button :disabled="submitting" @click="submit">
                                <Save class="mr-2 h-4 w-4" />
                                {{ submitting ? 'Saving...' : 'Create Council' }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
