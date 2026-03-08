<script setup>
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Badge from '@/Components/ui/Badge.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import { onMounted, ref } from 'vue';

const loading = ref(true);
const errorMessage = ref('');
const connections = ref([]);

const loadHealth = async () => {
    loading.value = true;
    errorMessage.value = '';
    try {
        const { data } = await axios.get('/agent/api/v1/connectors', {
            params: { status: 'connected' },
        });
        connections.value = (data.data ?? []).slice(0, 8);
    } catch (error) {
        errorMessage.value = error?.response?.data?.error?.message ?? 'Unable to load connector health.';
    } finally {
        loading.value = false;
    }
};

const healthIndicator = (score) => {
    if (score >= 0.8) return 'bg-green-500';
    if (score >= 0.5) return 'bg-yellow-500';
    return 'bg-red-500';
};

const healthLabel = (score) => {
    if (score >= 0.8) return 'Healthy';
    if (score >= 0.5) return 'Degraded';
    return 'Unhealthy';
};

onMounted(loadHealth);
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="text-xs uppercase tracking-wide text-muted-foreground font-medium">Connector Health</CardTitle>
        </CardHeader>
        <CardContent>
            <Skeleton v-if="loading" class="h-24 w-full" />
            <p v-else-if="errorMessage" class="text-sm text-destructive">{{ errorMessage }}</p>
            <div v-else-if="connections.length === 0" class="text-sm text-muted-foreground">No connectors connected.</div>
            <div v-else class="space-y-2">
                <Link
                    v-for="conn in connections"
                    :key="conn.id"
                    :href="route('tools.connectors.show', conn.id)"
                    class="flex items-center justify-between rounded border border-border px-2 py-1.5 text-sm hover:bg-muted transition-colors"
                >
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="h-2 w-2 rounded-full shrink-0" :class="healthIndicator(conn.health_score ?? 1)"></div>
                        <span class="font-medium text-foreground truncate">{{ conn.display_name || conn.name }}</span>
                    </div>
                    <span class="text-xs text-muted-foreground shrink-0">{{ healthLabel(conn.health_score ?? 1) }}</span>
                </Link>
            </div>
        </CardContent>
    </Card>
</template>
