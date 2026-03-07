<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardDescription from '@/Components/ui/CardDescription.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { Puzzle, Search, Download } from 'lucide-vue-next';

const librarySkills = ref([]);
const loading = ref(true);
const error = ref(null);
const searchQuery = ref('');
const selectedCategory = ref('');
const selectedIndustry = ref('');
const installingSlug = ref(null);

const riskColors = {
    low: 'bg-green-100 text-green-800',
    standard: 'bg-blue-100 text-blue-800',
    elevated: 'bg-yellow-100 text-yellow-800',
    critical: 'bg-red-100 text-red-800',
};

const categories = computed(() => {
    const cats = new Set(librarySkills.value.map(s => s.category).filter(Boolean));
    return [...cats].sort();
});

const industries = computed(() => {
    const inds = new Set(librarySkills.value.flatMap(s => s.industries || []).filter(Boolean));
    return [...inds].sort();
});

const filtered = computed(() => {
    return librarySkills.value.filter(skill => {
        if (searchQuery.value && !skill.name.toLowerCase().includes(searchQuery.value.toLowerCase())
            && !skill.description?.toLowerCase().includes(searchQuery.value.toLowerCase())) {
            return false;
        }
        if (selectedCategory.value && skill.category !== selectedCategory.value) return false;
        if (selectedIndustry.value && !(skill.industries || []).includes(selectedIndustry.value)) return false;
        return true;
    });
});

async function fetchLibrary() {
    loading.value = true;
    error.value = null;
    try {
        const response = await fetch('/agent/api/v1/skills/library');
        if (!response.ok) throw new Error('Failed to load library');
        const json = await response.json();
        librarySkills.value = json.data;
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

async function installSkill(skill) {
    installingSlug.value = skill.slug;
    error.value = null;
    try {
        const response = await fetch(`/agent/api/v1/skills/library/${skill.slug}/install`, { method: 'POST' });
        const json = await response.json();
        if (!response.ok) throw new Error(json.error?.message || 'Installation failed');
        if (json.data?.skill?.id) {
            router.visit(route('tools.skills.show', { id: json.data.skill.id }));
        }
    } catch (e) {
        error.value = e.message;
    } finally {
        installingSlug.value = null;
    }
}

onMounted(fetchLibrary);
</script>

<template>
    <AppLayout title="Skill Library">
        <Head title="Skill Library" />

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
                        <span class="text-foreground">Library</span>
                    </nav>
                    <h2 class="text-base font-semibold text-foreground">Skill Library</h2>
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mb-6 flex flex-wrap items-center gap-3">
                <div class="relative flex-1 min-w-[200px]">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search skills..."
                        class="w-full rounded-md border border-input bg-background pl-9 pr-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                </div>
                <select v-model="selectedCategory" class="rounded-md border border-input bg-background px-3 py-2 text-sm">
                    <option value="">All Categories</option>
                    <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                </select>
                <select v-model="selectedIndustry" class="rounded-md border border-input bg-background px-3 py-2 text-sm">
                    <option value="">All Industries</option>
                    <option v-for="ind in industries" :key="ind" :value="ind">{{ ind }}</option>
                </select>
            </div>

            <div v-if="error" class="mb-4 rounded-md bg-destructive/10 p-3 text-sm text-destructive">{{ error }}</div>

            <div v-if="loading" class="text-center py-12 text-muted-foreground">Loading library...</div>

            <div v-else-if="filtered.length === 0" class="text-center py-12 text-muted-foreground">
                No skills found matching your criteria.
            </div>

            <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Card v-for="skill in filtered" :key="skill.slug" class="flex flex-col">
                    <CardHeader class="flex-1">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <CardTitle class="text-sm">{{ skill.name }}</CardTitle>
                                <CardDescription class="mt-1">{{ skill.description }}</CardDescription>
                            </div>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            <span v-if="skill.category" class="inline-flex rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
                                {{ skill.category }}
                            </span>
                            <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', riskColors[skill.risk_level] || 'bg-gray-100 text-gray-800']">
                                {{ skill.risk_level }}
                            </span>
                        </div>
                    </CardHeader>
                    <div class="p-4 pt-0">
                        <button
                            @click="installSkill(skill)"
                            :disabled="installingSlug === skill.slug"
                            class="flex w-full items-center justify-center gap-1.5 rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                        >
                            <Download class="h-4 w-4" />
                            {{ installingSlug === skill.slug ? 'Installing...' : 'Install' }}
                        </button>
                    </div>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
