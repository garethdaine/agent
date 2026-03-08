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
const connectors = ref([]);

const loadUsage = async () => {
    loading.value = true;
    errorMessage.value = '';
    try {
        const { data } = await axios.get('/agent/api/v1/connectors', {
            params: { status: 'connected' },
        });
        const items = data.data ?? [];
        connectors.value = items
            .filter(c => (c.action_count_24h ?? 0) > 0)
            .sort((a, b) => (b.action_count_24h ?? 0) - (a.action_count_24h ?? 0))
            .slice(0, 5);
    } catch (error) {
        errorMessage.value = error?.response?.data?.error?.message ?? 'Unable to load connector usage.';
    } finally {
        loading.value = false;
    }
};

const maxCount = () => {
    if (connectors.value.length === 0) return 1;
    return Math.max(...connectors.value.map(c => c.action_count_24h ?? 0), 1);
};

const barWidth = (count) => {
    return ((count / maxCount()) * 100) + '%';
};

const successRate = (conn) => {
    const total = conn.action_count_24h ?? 0;
    const errors = conn.error_count_24h ?? 0;
    if (total === 0) return 100;
    return Math.round(((total - errors) / total) * 100);
};

onMounted(loadUsage);
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="text-xs uppercase tracking-wide text-muted-foreground font-medium">Connector Usage (24h)</CardTitle>
        </CardHeader>
        <CardContent>
            <Skeleton v-if="loading" class="h-24 w-full" />
            <p v-else-if="errorMessage" class="text-sm text-destructive">{{ errorMessage }}</p>
            <div v-else-if="connectors.length === 0" class="text-sm text-muted-foreground">No connector activity in the last 24 hours.</div>
            <div v-else class="space-y-3">
                <div v-for="conn in connectors" :key="conn.id" class="space-y-1">
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-medium text-foreground truncate">{{ conn.display_name || conn.name }}</span>
                        <span class="text-xs text-muted-foreground shrink-0">
                            {{ conn.action_count_24h }} actions &middot; {{ successRate(conn) }}% success
                        </span>
                    </div>
                    <div class="h-1.5 w-full rounded-full bg-muted">
                        <div
                            class="h-1.5 rounded-full bg-primary transition-all"
                            :style="{ width: barWidth(conn.action_count_24h ?? 0) }"
                        ></div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
