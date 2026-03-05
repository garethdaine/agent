<script setup>
import { computed } from 'vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Input from '@/Components/ui/Input.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Select from '@/Components/ui/Select.vue';
import Button from '@/Components/ui/Button.vue';
import { X } from 'lucide-vue-next';

const props = defineProps({
    node: { type: Object, default: null },
    delegateeProfiles: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'update']);

const capabilities = [
    'code_execution',
    'review',
    'testing',
    'documentation',
    'deployment',
    'monitoring',
    'code_generation',
    'code_review',
    'refactoring',
    'analysis',
    'planning',
];

const taskName = computed(() => props.node?.data?.label ?? props.node?.data?.name ?? '');
const capability = computed(() => props.node?.data?.capability ?? props.node?.data?.contract_json?.required_capability ?? 'code_execution');
const delegateeId = computed(() => props.node?.data?.assigned_delegatee_profile_id ?? null);

function updateData(partial) {
    const next = { ...props.node?.data, ...partial };
    if (partial.capability != null || partial.required_capability != null) {
        next.contract_json = { ...(props.node?.data?.contract_json ?? {}), required_capability: partial.capability ?? partial.required_capability ?? next.contract_json?.required_capability };
    }
    emit('update', next);
}
</script>

<template>
    <Card v-if="node" class="w-full max-w-sm shrink-0">
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">Task config</CardTitle>
            <Button variant="ghost" size="icon" @click="emit('close')">
                <X class="h-4 w-4" />
            </Button>
        </CardHeader>
        <CardContent class="space-y-4">
            <div class="space-y-2">
                <InputLabel>Name</InputLabel>
                <Input
                    :model-value="taskName"
                    type="text"
                    placeholder="Task name"
                    @update:model-value="(v) => updateData({ label: v, name: v })"
                />
            </div>
            <div class="space-y-2">
                <InputLabel>Capability</InputLabel>
                <Select
                    :model-value="capability"
                    @update:model-value="(v) => updateData({ capability: v })"
                >
                    <option value="">—</option>
                    <option v-for="c in capabilities" :key="c" :value="c">{{ c }}</option>
                </Select>
            </div>
            <div class="space-y-2">
                <InputLabel>Assign delegatee</InputLabel>
                <Select
                    :model-value="delegateeId != null ? String(delegateeId) : ''"
                    @update:model-value="(v) => updateData({ assigned_delegatee_profile_id: v ? Number(v) : null })"
                >
                    <option value="">None</option>
                    <option
                        v-for="p in delegateeProfiles"
                        :key="p.id"
                        :value="String(p.id)"
                    >
                        {{ p.name ?? `Profile ${p.id}` }}
                    </option>
                </Select>
            </div>
        </CardContent>
    </Card>
</template>
