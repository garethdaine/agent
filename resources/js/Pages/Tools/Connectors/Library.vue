<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpHint from '@/Components/HelpHint.vue';
import { Head, Link } from '@inertiajs/vue3';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import { Plug, Search, ArrowLeft, Key, ExternalLink } from 'lucide-vue-next';
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';

const loading = ref(true);
const error = ref('');
const connectors = ref([]);
const searchQuery = ref('');
const selectedCategory = ref('');

const categories = [
    { value: '', label: 'All' },
    { value: 'accounting', label: 'Accounting' },
    { value: 'crm', label: 'CRM' },
    { value: 'communication', label: 'Communication' },
    { value: 'productivity', label: 'Productivity' },
    { value: 'payments', label: 'Payments' },
    { value: 'hr', label: 'HR' },
    { value: 'support', label: 'Support' },
    { value: 'marketing', label: 'Marketing' },
    { value: 'project_management', label: 'Project Management' },
    { value: 'e_commerce', label: 'E-Commerce' },
    { value: 'document_management', label: 'Documents' },
    { value: 'it_msp', label: 'IT/MSP' },
    { value: 'facilities', label: 'Facilities' },
    { value: 'logistics', label: 'Logistics' },
    { value: 'construction', label: 'Construction' },
    { value: 'property', label: 'Property' },
    { value: 'recruitment', label: 'Recruitment' },
    { value: 'legal', label: 'Legal' },
    { value: 'insurance', label: 'Insurance' },
];

const load = async () => {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await axios.get('/agent/api/v1/connectors');
        connectors.value = data.data ?? [];
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load connectors.';
    } finally {
        loading.value = false;
    }
};

const filtered = computed(() => {
    let list = connectors.value;

    if (selectedCategory.value) {
        list = list.filter(c => c.category === selectedCategory.value);
    }

    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(c =>
            (c.display_name || c.name || '').toLowerCase().includes(q)
            || (c.description || '').toLowerCase().includes(q)
        );
    }

    return list;
});

const authLabel = (type) => {
    if (type === 'oauth2') return 'OAuth';
    if (type === 'api_key') return 'API Key';
    if (type === 'api_key_secret') return 'API Key + Secret';
    if (type === 'basic') return 'Basic Auth';
    if (type === 'custom_header') return 'Custom Header';
    return type ?? 'Unknown';
};

const categoryColor = (category) => {
    const colors = {
        accounting: 'bg-blue-100 text-blue-800',
        crm: 'bg-purple-100 text-purple-800',
        communication: 'bg-green-100 text-green-800',
        productivity: 'bg-indigo-100 text-indigo-800',
        payments: 'bg-yellow-100 text-yellow-800',
        hr: 'bg-pink-100 text-pink-800',
        support: 'bg-orange-100 text-orange-800',
        marketing: 'bg-teal-100 text-teal-800',
    };
    return colors[category] || 'bg-gray-100 text-gray-800';
};

const connectingId = ref(null);

const connect = async (connector) => {
    if (connector.status === 'partnership_pending') return;

    connectingId.value = connector.id;
    try {
        if (connector.auth_type === 'oauth2') {
            const { data } = await axios.post(`/agent/api/v1/connectors/${connector.id}/connect`);
            if (data.data?.redirect_url) {
                window.open(data.data.redirect_url, '_blank');
            }
        } else {
            const apiKey = prompt('Enter your API key:');
            if (!apiKey) return;

            await axios.post(`/agent/api/v1/connectors/${connector.id}/connect`, {
                api_key: apiKey,
            });
            await load();
        }
    } catch (e) {
        alert(e?.response?.data?.error?.message ?? 'Connection failed.');
    } finally {
        connectingId.value = null;
    }
};

onMounted(load);
</script>

<template>
    <AppLayout title="Connector Library">
        <Head title="Connector Library" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <Plug class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-semibold text-foreground truncate">Connector Library</h2>
                        <HelpHint
                            ui-key="connectors.library"
                            short-text="Browse and connect external services."
                            learn-more-href="/docs/connectors"
                        />
                    </div>
                </div>
                <Link
                    :href="route('tools.connectors.index')"
                    class="inline-flex items-center gap-1.5 rounded-md border border-border px-3 py-2 text-sm hover:bg-muted"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Connected Services
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-6 lg:flex-row">
                    <!-- Category sidebar -->
                    <div class="w-full lg:w-48 shrink-0">
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Categories</h3>
                        <nav class="space-y-0.5">
                            <button
                                v-for="cat in categories"
                                :key="cat.value"
                                class="block w-full rounded-md px-2 py-1.5 text-left text-sm transition-colors"
                                :class="selectedCategory === cat.value ? 'bg-primary/10 text-primary font-medium' : 'text-muted-foreground hover:text-foreground hover:bg-muted'"
                                @click="selectedCategory = cat.value"
                            >
                                {{ cat.label }}
                            </button>
                        </nav>
                    </div>

                    <!-- Main content -->
                    <div class="flex-1 min-w-0 space-y-4">
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                v-model="searchQuery"
                                placeholder="Search connectors..."
                                class="pl-9"
                            />
                        </div>

                        <div v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                            {{ error }}
                        </div>

                        <div v-if="loading" class="text-center py-12 text-muted-foreground">Loading connectors...</div>

                        <div v-else-if="filtered.length === 0" class="text-center py-12">
                            <Plug class="mx-auto h-12 w-12 text-muted-foreground/50" />
                            <h3 class="mt-4 text-sm font-semibold text-foreground">No connectors found</h3>
                            <p class="mt-1 text-sm text-muted-foreground">Try adjusting your search or category filter.</p>
                        </div>

                        <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            <Card v-for="connector in filtered" :key="connector.id">
                                <CardHeader>
                                    <div class="flex items-center justify-between">
                                        <CardTitle class="text-sm font-medium">{{ connector.display_name || connector.name }}</CardTitle>
                                        <span v-if="connector.category" :class="['inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium', categoryColor(connector.category)]">
                                            {{ connector.category }}
                                        </span>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <p class="text-sm text-muted-foreground line-clamp-2 mb-3">
                                        {{ connector.description || 'No description available.' }}
                                    </p>

                                    <div class="flex items-center gap-2 mb-3">
                                        <div class="flex items-center gap-1 text-xs text-muted-foreground">
                                            <Key class="h-3 w-3" />
                                            {{ authLabel(connector.auth_type) }}
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <Button
                                            v-if="connector.status !== 'partnership_pending'"
                                            size="sm"
                                            :disabled="connectingId === connector.id"
                                            @click="connect(connector)"
                                        >
                                            {{ connectingId === connector.id ? 'Connecting...' : 'Connect' }}
                                        </Button>
                                        <Badge v-else variant="outline" class="text-xs">Coming Soon</Badge>
                                        <Link
                                            :href="route('tools.connectors.show', connector.id)"
                                            class="inline-flex items-center rounded-md border border-border px-2.5 py-1.5 text-xs hover:bg-muted"
                                        >
                                            Details
                                        </Link>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
