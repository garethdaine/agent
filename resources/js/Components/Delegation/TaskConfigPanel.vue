<script setup>
import { computed } from 'vue';
import Card from '@/Components/ui/Card.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Input from '@/Components/ui/Input.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Select from '@/Components/ui/Select.vue';
import Button from '@/Components/ui/Button.vue';
import { X, Trash2 } from 'lucide-vue-next';
import { confirmDialog } from '@/Support/confirmDialog';

const props = defineProps({
    node: { type: Object, default: null },
    delegateeProfiles: { type: Array, default: () => [] },
    capabilities: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'update', 'delete']);

const taskName = computed(() => props.node?.data?.label ?? props.node?.data?.name ?? '');
const capability = computed(() => props.node?.data?.capability ?? props.node?.data?.contract_json?.required_capability ?? '');
const delegateeId = computed(() => props.node?.data?.assigned_delegatee_profile_id ?? null);

const capabilityOptions = computed(() => [
    { value: '', label: '-- Select --' },
    ...props.capabilities.map((c) => ({
        value: c.slug,
        label: c.name ?? c.slug,
    })),
]);

const delegateeOptions = computed(() => [
    { value: '', label: 'None' },
    ...props.delegateeProfiles.map((p) => ({
        value: String(p.id),
        label: p.name ?? `Profile ${p.id}`,
    })),
]);

function updateData(partial) {
    const next = { ...props.node?.data, ...partial };
    if (partial.capability != null || partial.required_capability != null) {
        next.contract_json = { ...(props.node?.data?.contract_json ?? {}), required_capability: partial.capability ?? partial.required_capability ?? next.contract_json?.required_capability };
    }
    emit('update', next);
}

async function handleDelete() {
    const name = props.node?.data?.name ?? props.node?.data?.label ?? 'this task';
    const approved = await confirmDialog(`Are you sure you want to delete "${name}"? This action cannot be undone.`, {
        title: 'Delete Task',
        confirmText: 'Delete',
        confirmVariant: 'destructive',
    });
    if (approved) {
        emit('delete', props.node.id);
    }
}
</script>

<template>
    <Card v-if="node" class="w-full max-w-sm shrink-0">
        <div class="flex items-center justify-between px-4 pt-4 pb-2">
            <h3 class="text-sm font-medium text-foreground">Task config</h3>
            <Button variant="ghost" size="icon" class="h-7 w-7" @click="emit('close')">
                <X class="h-4 w-4" />
            </Button>
        </div>
        <CardContent class="space-y-4 pt-0">
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
                    :options="capabilityOptions"
                    @update:model-value="(v) => updateData({ capability: v })"
                />
            </div>
            <div class="space-y-2">
                <InputLabel>Assign delegatee</InputLabel>
                <Select
                    :model-value="delegateeId != null ? String(delegateeId) : ''"
                    :options="delegateeOptions"
                    @update:model-value="(v) => updateData({ assigned_delegatee_profile_id: v ? Number(v) : null })"
                />
            </div>

            <div class="pt-2 border-t border-border">
                <Button variant="destructive" size="sm" class="w-full" @click="handleDelete">
                    <Trash2 class="mr-2 h-4 w-4" />
                    Delete task
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
