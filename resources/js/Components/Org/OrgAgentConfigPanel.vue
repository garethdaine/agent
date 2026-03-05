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
    agents: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'update', 'create-profile']);

const name = computed(() => props.node?.data?.name ?? '');
const roleSlug = computed(() => props.node?.data?.role_slug ?? 'agent');
const roleDescription = computed(() => props.node?.data?.role_description ?? '');
const delegateeId = computed(() => props.node?.data?.delegatee_profile_id ?? null);
const parentAgentId = computed(() => props.node?.data?.parent_agent_id ?? null);

const delegateeOptions = computed(() => [
    { value: '', label: '— Select —' },
    ...props.delegateeProfiles.map((p) => ({
        value: String(p.id),
        label: p.name ?? `Profile ${p.id}`,
    })),
]);

const parentOptions = computed(() => {
    const nodeId = props.node?.id;
    const filtered = props.agents.filter((a) => a.id !== nodeId);
    return [
        { value: '', label: 'None' },
        ...filtered.map((a) => ({
            value: a.id,
            label: a.name ?? a.data?.name ?? a.id,
        })),
    ];
});

function updateData(partial) {
    emit('update', { ...props.node?.data, ...partial });
}
</script>

<template>
    <Card v-if="node" class="w-full max-w-sm shrink-0">
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">Agent config</CardTitle>
            <Button variant="ghost" size="icon" @click="emit('close')">
                <X class="h-4 w-4" />
            </Button>
        </CardHeader>
        <CardContent class="space-y-4">
            <div class="space-y-2">
                <InputLabel>Name</InputLabel>
                <Input
                    :model-value="name"
                    type="text"
                    placeholder="Agent name"
                    @update:model-value="(v) => updateData({ name: v })"
                />
            </div>
            <div class="space-y-2">
                <InputLabel>Role</InputLabel>
                <Input
                    :model-value="roleSlug"
                    type="text"
                    placeholder="e.g. agent"
                    @update:model-value="(v) => updateData({ role_slug: v })"
                />
            </div>
            <div class="space-y-2">
                <InputLabel>Role description</InputLabel>
                <Input
                    :model-value="roleDescription"
                    type="text"
                    placeholder="Short description"
                    @update:model-value="(v) => updateData({ role_description: v })"
                />
            </div>
            <div class="space-y-2">
                <InputLabel>Delegatee profile</InputLabel>
                <div class="flex gap-2">
                    <div class="min-w-0 flex-1">
                        <Select
                            :model-value="delegateeId != null ? String(delegateeId) : ''"
                            :options="delegateeOptions"
                            @update:model-value="(v) => updateData({ delegatee_profile_id: v ? Number(v) : null })"
                        />
                    </div>
                    <Button type="button" variant="outline" size="sm" @click="emit('create-profile')">
                        New
                    </Button>
                </div>
            </div>
            <div class="space-y-2">
                <InputLabel>Reports to (manager)</InputLabel>
                <Select
                    :model-value="parentAgentId ?? ''"
                    :options="parentOptions"
                    @update:model-value="(v) => updateData({ parent_agent_id: v || null })"
                />
            </div>
        </CardContent>
    </Card>
</template>
