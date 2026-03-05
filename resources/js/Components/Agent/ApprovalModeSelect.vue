<script setup>
import { computed, watch } from 'vue';
import Select from '@/Components/ui/Select.vue';
import { ShieldCheck, ShieldAlert, ShieldOff } from 'lucide-vue-next';

const APPROVAL_PROFILES = {
    codex: [
        {
            value: 'approval_required',
            label: 'Approval required',
            description: 'Agent pauses and asks before making changes.',
            icon: 'ShieldCheck',
            tokens: [],
        },
        {
            value: 'web_pre_approved',
            label: 'Web tools pre-approved',
            description: 'Web search allowed; other changes still need approval.',
            icon: 'ShieldAlert',
            tokens: ['--search'],
        },
        {
            value: 'full_access',
            label: 'Full access (auto-approve)',
            description: 'Agent runs without stopping for approval. Use only for trusted tasks.',
            icon: 'ShieldOff',
            tokens: ['--dangerously-bypass-approvals-and-sandbox', '--search'],
        },
    ],
    claude: [
        {
            value: 'approval_required',
            label: 'Approval required',
            description: 'Agent pauses and asks before making changes.',
            icon: 'ShieldCheck',
            tokens: [],
        },
        {
            value: 'full_access',
            label: 'Full access (auto-approve)',
            description: 'Agent runs without stopping for approval. Use only for trusted tasks.',
            icon: 'ShieldOff',
            tokens: ['--dangerously-skip-permissions'],
        },
    ],
};

const ALL_DANGEROUS_TOKENS = [
    '--dangerously-skip-permissions',
    '--dangerously-bypass-approvals-and-sandbox',
    '--search',
];

const props = defineProps({
    runnerType: { type: String, required: true },
    modelValue: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const profiles = computed(() => APPROVAL_PROFILES[props.runnerType] ?? []);

const options = computed(() =>
    profiles.value.map((p) => ({ value: p.value, label: p.label })),
);

const detectMode = (template) => {
    if (!template) return 'approval_required';
    const profs = APPROVAL_PROFILES[props.runnerType] ?? [];
    for (let i = profs.length - 1; i >= 0; i--) {
        const p = profs[i];
        if (p.tokens.length > 0 && p.tokens.every((t) => template.includes(t))) {
            return p.value;
        }
    }
    return 'approval_required';
};

const currentMode = computed(() => detectMode(props.modelValue));

const selectedProfile = computed(
    () => profiles.value.find((p) => p.value === currentMode.value) ?? profiles.value[0],
);

const applyMode = (newMode) => {
    if (newMode === currentMode.value) return;
    const targetProfile = profiles.value.find((p) => p.value === newMode);
    if (!targetProfile) return;

    let template = props.modelValue;
    for (const token of ALL_DANGEROUS_TOKENS) {
        template = template.replace(new RegExp(`\\s*${token.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}`, 'g'), '');
    }
    template = template.replace(/\s{2,}/g, ' ').trim();

    if (targetProfile.tokens.length > 0) {
        const parts = template.split(/\s+/);
        const insertIdx = parts.length > 0 ? 1 : 0;
        parts.splice(insertIdx, 0, ...targetProfile.tokens);
        template = parts.join(' ');
    }

    emit('update:modelValue', template);
};

const iconComponent = computed(() => {
    const map = { ShieldCheck, ShieldAlert, ShieldOff };
    return map[selectedProfile.value?.icon] ?? ShieldCheck;
});

watch(() => props.runnerType, () => {
    if (profiles.value.length === 0) return;
    const validModes = profiles.value.map((p) => p.value);
    if (!validModes.includes(currentMode.value)) {
        applyMode(validModes[0]);
    }
});
</script>

<template>
    <div v-if="profiles.length > 0" class="space-y-2">
        <div class="flex items-center gap-2">
            <component :is="iconComponent" class="h-4 w-4 text-muted-foreground" />
            <Select
                :model-value="currentMode"
                :options="options"
                :disabled="disabled"
                @update:model-value="applyMode"
            />
        </div>
        <p class="text-xs text-muted-foreground">{{ selectedProfile?.description }}</p>
    </div>
    <div v-else class="text-xs text-muted-foreground">
        No approval modes for custom runners. Manage flags in the command template directly.
    </div>
</template>
