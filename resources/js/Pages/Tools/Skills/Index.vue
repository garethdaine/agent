<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardDescription from '@/Components/ui/CardDescription.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { Puzzle, Plus, BookOpen, Pause, Play, Trash2 } from 'lucide-vue-next';

const skills = ref([]);
const loading = ref(true);
const error = ref(null);

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

async function fetchSkills() {
    loading.value = true;
    error.value = null;
    try {
        const response = await fetch('/agent/api/v1/skills');
        if (!response.ok) throw new Error('Failed to load skills');
        const json = await response.json();
        skills.value = json.data;
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

async function toggleStatus(skill) {
    const action = skill.status === 'active' ? 'pause' : 'resume';
    try {
        const response = await fetch(`/agent/api/v1/skills/${skill.id}/${action}`, { method: 'POST' });
        if (!response.ok) throw new Error(`Failed to ${action} skill`);
        await fetchSkills();
    } catch (e) {
        error.value = e.message;
    }
}

async function removeSkill(skill) {
    if (!confirm(`Remove "${skill.name}"? This action cannot be undone.`)) return;
    try {
        const response = await fetch(`/agent/api/v1/skills/${skill.id}`, { method: 'DELETE' });
        if (!response.ok) throw new Error('Failed to remove skill');
        await fetchSkills();
    } catch (e) {
        error.value = e.message;
    }
}

onMounted(fetchSkills);
</script>

<template>
    <AppLayout title="Skills">
        <Head title="Skills" />

        <template #header>
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                    <Puzzle class="h-5 w-5 text-primary" />
                </div>
                <h2 class="text-base font-semibold text-foreground truncate">Agent Skills</h2>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mb-6 flex items-center justify-between">
                <p class="text-sm text-muted-foreground">Manage installed agent skills.</p>
                <div class="flex gap-2">
                    <Link :href="route('tools.skills.library')" class="inline-flex items-center gap-1.5 rounded-md border border-input bg-background px-3 py-2 text-sm font-medium hover:bg-accent">
                        <BookOpen class="h-4 w-4" />
                        Browse Library
                    </Link>
                    <Link :href="route('tools.skills.install')" class="inline-flex items-center gap-1.5 rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90">
                        <Plus class="h-4 w-4" />
                        Install Skill
                    </Link>
                </div>
            </div>

            <div v-if="error" class="mb-4 rounded-md bg-destructive/10 p-3 text-sm text-destructive">{{ error }}</div>

            <div v-if="loading" class="text-center py-12 text-muted-foreground">Loading skills...</div>

            <div v-else-if="skills.length === 0" class="text-center py-12">
                <Puzzle class="mx-auto h-12 w-12 text-muted-foreground/50" />
                <h3 class="mt-4 text-sm font-semibold text-foreground">No skills installed yet</h3>
                <p class="mt-1 text-sm text-muted-foreground">Browse the library to get started.</p>
                <Link :href="route('tools.skills.library')" class="mt-4 inline-flex items-center gap-1.5 rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90">
                    Browse Library
                </Link>
            </div>

            <div v-else class="overflow-hidden rounded-lg border border-border">
                <table class="min-w-full divide-y divide-border">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Name</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Version</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Risk</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Invocations</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">Last Invoked</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-muted-foreground">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border bg-background">
                        <tr v-for="skill in skills" :key="skill.id" class="hover:bg-muted/30">
                            <td class="px-4 py-3">
                                <Link :href="route('tools.skills.show', { id: skill.id })" class="text-sm font-medium text-foreground hover:text-primary">
                                    {{ skill.name }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 text-sm text-muted-foreground">{{ skill.version }}</td>
                            <td class="px-4 py-3">
                                <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', riskColors[skill.risk_level] || 'bg-gray-100 text-gray-800']">
                                    {{ skill.risk_level }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', statusColors[skill.status] || 'bg-gray-100 text-gray-800']">
                                    {{ skill.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-muted-foreground">{{ skill.invocation_count ?? 0 }}</td>
                            <td class="px-4 py-3 text-sm text-muted-foreground">{{ skill.last_invoked_at ?? 'Never' }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button @click="toggleStatus(skill)" class="rounded p-1 hover:bg-muted" :title="skill.status === 'active' ? 'Pause' : 'Resume'">
                                        <Pause v-if="skill.status === 'active'" class="h-4 w-4 text-muted-foreground" />
                                        <Play v-else class="h-4 w-4 text-muted-foreground" />
                                    </button>
                                    <button @click="removeSkill(skill)" class="rounded p-1 hover:bg-destructive/10" title="Remove">
                                        <Trash2 class="h-4 w-4 text-destructive" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
