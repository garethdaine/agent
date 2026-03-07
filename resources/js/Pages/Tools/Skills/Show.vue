<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardDescription from '@/Components/ui/CardDescription.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { Puzzle, Pause, Play, Trash2, RefreshCw, Code } from 'lucide-vue-next';

const props = defineProps({
    skillId: { type: String, required: true },
});

const skill = ref(null);
const loading = ref(true);
const error = ref(null);
const showSource = ref(false);

const riskColors = {
    low: 'bg-green-100 text-green-800',
    standard: 'bg-blue-100 text-blue-800',
    elevated: 'bg-yellow-100 text-yellow-800',
    critical: 'bg-red-100 text-red-800',
};

const statusColors = {
    active: 'bg-green-100 text-green-800',
    paused: 'bg-yellow-100 text-yellow-800',
    failed: 'bg-red-100 text-red-800',
};

async function fetchSkill() {
    loading.value = true;
    error.value = null;
    try {
        const response = await fetch(`/agent/api/v1/skills/${props.skillId}`);
        if (!response.ok) throw new Error('Failed to load skill');
        const json = await response.json();
        skill.value = json.data;
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

async function toggleStatus() {
    const action = skill.value.status === 'active' ? 'pause' : 'resume';
    try {
        const response = await fetch(`/agent/api/v1/skills/${props.skillId}/${action}`, { method: 'POST' });
        if (!response.ok) throw new Error(`Failed to ${action} skill`);
        await fetchSkill();
    } catch (e) {
        error.value = e.message;
    }
}

async function removeSkill() {
    if (!confirm(`Remove "${skill.value.name}"? This action cannot be undone.`)) return;
    try {
        const response = await fetch(`/agent/api/v1/skills/${props.skillId}`, { method: 'DELETE' });
        if (!response.ok) throw new Error('Failed to remove skill');
        router.visit(route('tools.skills.index'));
    } catch (e) {
        error.value = e.message;
    }
}

async function revalidate() {
    try {
        const response = await fetch(`/agent/api/v1/skills/${props.skillId}/validate`, { method: 'POST' });
        if (!response.ok) throw new Error('Revalidation failed');
        await fetchSkill();
    } catch (e) {
        error.value = e.message;
    }
}

onMounted(fetchSkill);
</script>

<template>
    <AppLayout title="Skill Detail">
        <Head :title="skill?.name ?? 'Skill Detail'" />

        <template #header>
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                    <Puzzle class="h-5 w-5 text-primary" />
                </div>
                <div>
                    <nav class="flex items-center gap-1 text-xs text-muted-foreground">
                        <Link :href="route('tools.index')" class="hover:text-foreground">Tools</Link>
                        <span>/</span>
                        <Link :href="route('tools.skills.index')" class="hover:text-foreground">Skills</Link>
                        <span>/</span>
                        <span class="text-foreground">{{ skill?.name ?? 'Loading...' }}</span>
                    </nav>
                    <h2 class="text-base font-semibold text-foreground truncate">{{ skill?.name ?? 'Skill Detail' }}</h2>
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8 max-w-4xl mx-auto">
            <div v-if="error" class="mb-4 rounded-md bg-destructive/10 p-3 text-sm text-destructive">{{ error }}</div>

            <div v-if="loading" class="text-center py-12 text-muted-foreground">Loading skill details...</div>

            <template v-else-if="skill">
                <!-- Metadata -->
                <Card class="mb-6">
                    <CardHeader>
                        <div class="flex items-start justify-between">
                            <div>
                                <CardTitle>{{ skill.name }}</CardTitle>
                                <CardDescription class="mt-1">{{ skill.description }}</CardDescription>
                            </div>
                            <div class="flex items-center gap-2">
                                <span :class="['inline-flex rounded-full px-2.5 py-1 text-xs font-medium', statusColors[skill.status] || 'bg-gray-100 text-gray-800']">
                                    {{ skill.status }}
                                </span>
                                <span :class="['inline-flex rounded-full px-2.5 py-1 text-xs font-medium', riskColors[skill.risk_level] || 'bg-gray-100 text-gray-800']">
                                    {{ skill.risk_level }}
                                </span>
                            </div>
                        </div>
                    </CardHeader>
                    <div class="px-6 pb-6">
                        <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <div>
                                <dt class="text-xs font-medium text-muted-foreground">Version</dt>
                                <dd class="mt-1 text-sm text-foreground">{{ skill.version }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-muted-foreground">Author</dt>
                                <dd class="mt-1 text-sm text-foreground">{{ skill.author ?? 'Unknown' }}</dd>
                            </div>
                            <div v-if="skill.industries?.length">
                                <dt class="text-xs font-medium text-muted-foreground">Industries</dt>
                                <dd class="mt-1 flex flex-wrap gap-1">
                                    <span v-for="ind in skill.industries" :key="ind" class="inline-flex rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                                        {{ ind }}
                                    </span>
                                </dd>
                            </div>
                            <div v-if="skill.category">
                                <dt class="text-xs font-medium text-muted-foreground">Category</dt>
                                <dd class="mt-1 text-sm text-foreground">{{ skill.category }}</dd>
                            </div>
                        </dl>
                    </div>
                </Card>

                <!-- Validation -->
                <Card v-if="skill.validation" class="mb-6">
                    <CardHeader>
                        <CardTitle class="text-sm">Validation Results</CardTitle>
                    </CardHeader>
                    <div class="px-6 pb-6">
                        <div v-for="(stage, index) in (skill.validation?.stages || [])" :key="index" class="mb-2 last:mb-0">
                            <div class="flex items-center gap-2 text-sm">
                                <span :class="stage.passed ? 'text-green-600' : 'text-red-600'">
                                    {{ stage.passed ? 'Pass' : 'Fail' }}
                                </span>
                                <span class="font-medium text-foreground">{{ stage.name }}</span>
                            </div>
                            <p v-if="stage.message" class="ml-6 text-xs text-muted-foreground">{{ stage.message }}</p>
                        </div>
                        <div v-if="skill.validation?.risk_score != null" class="mt-4">
                            <div class="flex items-center justify-between text-xs text-muted-foreground mb-1">
                                <span>Risk Score</span>
                                <span>{{ skill.validation.risk_score }}/100</span>
                            </div>
                            <div class="h-2 rounded-full bg-muted overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="skill.validation.risk_score > 70 ? 'bg-red-500' : skill.validation.risk_score > 40 ? 'bg-yellow-500' : 'bg-green-500'"
                                    :style="{ width: `${skill.validation.risk_score}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </Card>

                <!-- Stats -->
                <Card class="mb-6">
                    <CardHeader>
                        <CardTitle class="text-sm">Usage Statistics</CardTitle>
                    </CardHeader>
                    <div class="px-6 pb-6">
                        <dl class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                            <div>
                                <dt class="text-xs font-medium text-muted-foreground">Invocations</dt>
                                <dd class="mt-1 text-2xl font-semibold text-foreground">{{ skill.invocation_count ?? 0 }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-muted-foreground">Last Invoked</dt>
                                <dd class="mt-1 text-sm text-foreground">{{ skill.last_invoked_at ?? 'Never' }}</dd>
                            </div>
                        </dl>
                    </div>
                </Card>

                <!-- Controls -->
                <div class="flex flex-wrap gap-2">
                    <button @click="toggleStatus" class="inline-flex items-center gap-1.5 rounded-md border border-input bg-background px-3 py-2 text-sm font-medium hover:bg-accent">
                        <Pause v-if="skill.status === 'active'" class="h-4 w-4" />
                        <Play v-else class="h-4 w-4" />
                        {{ skill.status === 'active' ? 'Pause' : 'Resume' }}
                    </button>
                    <button @click="revalidate" class="inline-flex items-center gap-1.5 rounded-md border border-input bg-background px-3 py-2 text-sm font-medium hover:bg-accent">
                        <RefreshCw class="h-4 w-4" />
                        Re-validate
                    </button>
                    <button @click="showSource = true" class="inline-flex items-center gap-1.5 rounded-md border border-input bg-background px-3 py-2 text-sm font-medium hover:bg-accent">
                        <Code class="h-4 w-4" />
                        View Source
                    </button>
                    <button @click="removeSkill" class="inline-flex items-center gap-1.5 rounded-md border border-destructive bg-background px-3 py-2 text-sm font-medium text-destructive hover:bg-destructive/10">
                        <Trash2 class="h-4 w-4" />
                        Remove
                    </button>
                </div>

                <!-- Source Modal -->
                <div v-if="showSource" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showSource = false">
                    <Card class="w-full max-w-3xl max-h-[80vh] overflow-auto">
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <CardTitle>SKILL.md Source</CardTitle>
                                <button @click="showSource = false" class="text-muted-foreground hover:text-foreground">&times;</button>
                            </div>
                        </CardHeader>
                        <div class="p-6 pt-0">
                            <pre class="overflow-auto rounded-lg bg-muted p-4 text-xs text-foreground">{{ skill.source_content ?? 'Source not available.' }}</pre>
                        </div>
                    </Card>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
