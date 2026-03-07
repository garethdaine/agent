<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardDescription from '@/Components/ui/CardDescription.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Puzzle, Upload, Globe, BookOpen } from 'lucide-vue-next';

const activeTab = ref('upload');
const url = ref('');
const installing = ref(false);
const error = ref(null);
const validationResult = ref(null);
const confirmModal = ref(null);
const dragOver = ref(false);

async function installFromFile(file) {
    installing.value = true;
    error.value = null;
    validationResult.value = null;
    try {
        const formData = new FormData();
        formData.append('skill_file', file);
        const response = await fetch('/agent/api/v1/skills/install', {
            method: 'POST',
            body: formData,
        });
        const json = await response.json();
        if (!response.ok) {
            if (json.data?.requiresAdminConfirmation) {
                confirmModal.value = json.data;
                return;
            }
            throw new Error(json.error?.message || 'Installation failed');
        }
        validationResult.value = json.data;
        if (json.data?.skill?.id) {
            router.visit(route('tools.skills.show', { id: json.data.skill.id }));
        }
    } catch (e) {
        error.value = e.message;
    } finally {
        installing.value = false;
    }
}

async function installFromUrl() {
    if (!url.value) return;
    installing.value = true;
    error.value = null;
    validationResult.value = null;
    try {
        const response = await fetch('/agent/api/v1/skills/install', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ url: url.value }),
        });
        const json = await response.json();
        if (!response.ok) {
            if (json.data?.requiresAdminConfirmation) {
                confirmModal.value = json.data;
                return;
            }
            throw new Error(json.error?.message || 'Installation failed');
        }
        validationResult.value = json.data;
        if (json.data?.skill?.id) {
            router.visit(route('tools.skills.show', { id: json.data.skill.id }));
        }
    } catch (e) {
        error.value = e.message;
    } finally {
        installing.value = false;
    }
}

function handleDrop(event) {
    dragOver.value = false;
    const file = event.dataTransfer?.files?.[0];
    if (file) installFromFile(file);
}

function handleFileInput(event) {
    const file = event.target?.files?.[0];
    if (file) installFromFile(file);
}

function dismissConfirm() {
    confirmModal.value = null;
}
</script>

<template>
    <AppLayout title="Install Skill">
        <Head title="Install Skill" />

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
                        <span class="text-foreground">Install</span>
                    </nav>
                    <h2 class="text-base font-semibold text-foreground">Install Skill</h2>
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8 max-w-3xl mx-auto">
            <div class="mb-6 flex gap-1 rounded-lg bg-muted p-1">
                <button
                    @click="activeTab = 'upload'"
                    :class="['flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium transition-colors', activeTab === 'upload' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground']"
                >
                    <Upload class="h-4 w-4" />
                    Upload File
                </button>
                <button
                    @click="activeTab = 'url'"
                    :class="['flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium transition-colors', activeTab === 'url' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground']"
                >
                    <Globe class="h-4 w-4" />
                    Paste URL
                </button>
                <Link :href="route('tools.skills.library')" class="flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium text-muted-foreground hover:text-foreground transition-colors">
                    <BookOpen class="h-4 w-4" />
                    Browse Library
                </Link>
            </div>

            <div v-if="error" class="mb-4 rounded-md bg-destructive/10 p-3 text-sm text-destructive">{{ error }}</div>

            <Card v-if="activeTab === 'upload'">
                <CardHeader>
                    <CardTitle>Upload Skill File</CardTitle>
                    <CardDescription>Drag and drop a .skill file or click to browse.</CardDescription>
                </CardHeader>
                <div class="p-6 pt-0">
                    <div
                        @drop.prevent="handleDrop"
                        @dragover.prevent="dragOver = true"
                        @dragleave="dragOver = false"
                        :class="['flex flex-col items-center justify-center rounded-lg border-2 border-dashed p-12 transition-colors', dragOver ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/50']"
                    >
                        <Upload class="h-10 w-10 text-muted-foreground mb-3" />
                        <p class="text-sm text-muted-foreground mb-2">Drop your skill file here, or</p>
                        <label class="cursor-pointer rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90">
                            Choose File
                            <input type="file" class="hidden" @change="handleFileInput" accept=".skill,.zip,.tar.gz" />
                        </label>
                    </div>
                    <div v-if="installing" class="mt-4 text-center text-sm text-muted-foreground">
                        Installing and validating skill...
                    </div>
                </div>
            </Card>

            <Card v-if="activeTab === 'url'">
                <CardHeader>
                    <CardTitle>Install from URL</CardTitle>
                    <CardDescription>Provide a URL to a skill package or repository.</CardDescription>
                </CardHeader>
                <div class="p-6 pt-0">
                    <div class="flex gap-2">
                        <input
                            v-model="url"
                            type="url"
                            placeholder="https://example.com/skill-package.zip"
                            class="flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                        />
                        <button
                            @click="installFromUrl"
                            :disabled="!url || installing"
                            class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                        >
                            {{ installing ? 'Installing...' : 'Install' }}
                        </button>
                    </div>
                </div>
            </Card>

            <!-- Confirmation Modal -->
            <div v-if="confirmModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                <Card class="w-full max-w-md">
                    <CardHeader>
                        <CardTitle>Admin Confirmation Required</CardTitle>
                        <CardDescription>This skill has elevated risk and requires admin approval.</CardDescription>
                    </CardHeader>
                    <div class="p-6 pt-0">
                        <p class="text-sm text-muted-foreground mb-4">{{ confirmModal.message }}</p>
                        <div class="flex justify-end gap-2">
                            <button @click="dismissConfirm" class="rounded-md border border-input px-3 py-2 text-sm font-medium hover:bg-accent">Cancel</button>
                            <button @click="dismissConfirm" class="rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90">Confirm Install</button>
                        </div>
                    </div>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
