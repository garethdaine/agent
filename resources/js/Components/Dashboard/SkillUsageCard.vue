<script setup>
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import axios from 'axios';
import { onMounted, ref } from 'vue';

const loading = ref(true);
const errorMessage = ref('');
const skills = ref([]);

const loadUsage = async () => {
    loading.value = true;
    errorMessage.value = '';

    try {
        const { data } = await axios.get('/agent/api/v1/skills/dashboard/usage');
        skills.value = data.data ?? [];
    } catch (error) {
        errorMessage.value = error?.response?.data?.error?.message ?? 'Unable to load skill usage.';
    } finally {
        loading.value = false;
    }
};

onMounted(loadUsage);
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="text-xs uppercase tracking-wide text-muted-foreground font-medium">Skill Usage (30d)</CardTitle>
        </CardHeader>
        <CardContent>
            <Skeleton v-if="loading" class="h-24 w-full" />
            <p v-else-if="errorMessage" class="text-sm text-destructive">{{ errorMessage }}</p>
            <div v-else-if="skills.length === 0" class="text-sm text-muted-foreground">No skill data yet.</div>
            <div v-else class="space-y-3">
                <div
                    v-for="skill in skills.slice(0, 5)"
                    :key="skill.skill_id"
                    class="flex items-center justify-between text-sm"
                >
                    <div class="flex flex-col min-w-0">
                        <span class="font-medium text-foreground truncate">{{ skill.skill_name }}</span>
                        <span class="text-xs text-muted-foreground">{{ skill.avg_duration_ms }}ms avg</span>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <span class="text-xs text-muted-foreground">{{ skill.invocation_count }} runs</span>
                        <span
                            class="text-xs font-medium"
                            :class="skill.success_rate >= 90 ? 'text-green-600' : skill.success_rate >= 70 ? 'text-amber-600' : 'text-red-600'"
                        >
                            {{ skill.success_rate }}%
                        </span>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
