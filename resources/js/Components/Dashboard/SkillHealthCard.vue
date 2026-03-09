<script setup>
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Badge from '@/Components/ui/Badge.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import axios from 'axios';
import { onMounted, ref } from 'vue';

const loading = ref(true);
const errorMessage = ref('');
const skills = ref([]);

const loadHealth = async () => {
    loading.value = true;
    errorMessage.value = '';

    try {
        const { data } = await axios.get('/agent/api/v1/skills/dashboard/health');
        skills.value = data.data ?? [];
    } catch (error) {
        errorMessage.value = error?.response?.data?.error?.message ?? 'Unable to load skill health.';
    } finally {
        loading.value = false;
    }
};

const statusVariant = (status) => {
    if (status === 'active') return 'default';
    if (status === 'pending_review') return 'secondary';
    if (status === 'paused') return 'outline-solid';
    return 'destructive';
};

const riskColor = (level) => {
    if (level === 'low') return 'text-green-600';
    if (level === 'standard') return 'text-foreground';
    if (level === 'elevated') return 'text-amber-600';
    return 'text-red-600';
};

onMounted(loadHealth);
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="text-xs uppercase tracking-wide text-muted-foreground font-medium">Skill Health</CardTitle>
        </CardHeader>
        <CardContent>
            <Skeleton v-if="loading" class="h-24 w-full" />
            <p v-else-if="errorMessage" class="text-sm text-destructive">{{ errorMessage }}</p>
            <div v-else-if="skills.length === 0" class="text-sm text-muted-foreground">No skills installed.</div>
            <div v-else class="space-y-2">
                <div
                    v-for="skill in skills"
                    :key="skill.id"
                    class="flex items-center justify-between rounded border border-border px-2 py-1.5 text-sm"
                >
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="font-medium text-foreground truncate">{{ skill.name }}</span>
                        <Badge :variant="statusVariant(skill.status)" class="text-[10px] px-1.5 py-0">{{ skill.status }}</Badge>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span :class="riskColor(skill.risk_level)" class="text-xs">{{ skill.risk_level }}</span>
                        <span v-if="skill.has_drift" class="text-xs text-amber-600 font-medium">drift</span>
                        <span v-if="!skill.validation_pass" class="text-xs text-red-600 font-medium">fail</span>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
