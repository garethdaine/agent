<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import DirectoryPickerInput from '@/Components/ui/DirectoryPickerInput.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Search } from 'lucide-vue-next';
import HelpHint from '@/Components/HelpHint.vue';
import axios from 'axios';
import { reactive, ref, watch } from 'vue';

const form = reactive({
    name: '',
    runner_type: 'claude',
    model: '',
    project_directory: '/Users/garethdaine/Code/agent',
    interrogation_type: 'feature',
    feature_brief: '',
});

const submitting = ref(false);
const error = ref('');
const validation = ref({});
const availableModels = ref([]);
const loadingModels = ref(false);
const defaultModel = ref('');

const fetchModels = async (runnerType) => {
    loadingModels.value = true;
    availableModels.value = [];
    form.model = '';

    try {
        const { data } = await axios.get('/agent/api/v1/interrogation/runner-models', {
            params: { runner_type: runnerType },
        });
        availableModels.value = data?.data ?? [];
        defaultModel.value = data?.default ?? '';
        form.model = defaultModel.value;
    } catch {
        availableModels.value = [];
        defaultModel.value = '';
    } finally {
        loadingModels.value = false;
    }
};

watch(() => form.runner_type, (runnerType) => {
    fetchModels(runnerType);
}, { immediate: true });

const submit = async () => {
    submitting.value = true;
    error.value = '';
    validation.value = {};

    try {
        const { data } = await axios.post('/agent/api/v1/interrogation/sessions', form);
        const id = data?.data?.id;

        if (id) {
            router.visit(route('tools.discovery.wizard', id));
            return;
        }

        router.visit(route('tools.discovery.index'));
    } catch (e) {
        const payload = e?.response?.data ?? {};
        const envelope = payload?.error ?? null;

        if (envelope) {
            validation.value = envelope?.details ?? {};
            error.value = envelope?.message ?? 'Failed to create session.';
        } else if (payload?.errors && typeof payload.errors === 'object') {
            validation.value = payload.errors;
            error.value = payload?.message ?? 'The given data was invalid.';
        } else {
            validation.value = {};
            error.value = payload?.message ?? 'Failed to create session.';
        }
    } finally {
        submitting.value = false;
    }
};
</script>

<template>
    <AppLayout title="New Discovery Session">
        <Head title="New Discovery Session" />

        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <Search class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-semibold text-foreground truncate">New Discovery Session</h2>
                        <HelpHint
                            ui-key="discovery.create"
                            short-text="Start a new requirements discovery session."
                            learn-more-href="/docs/overview"
                        />
                    </div>
                </div>
                <Link :href="route('tools.discovery.index')">
                    <Button variant="outline" size="sm">
                        <ArrowLeft class="h-4 w-4" />
                        Back
                    </Button>
                </Link>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-4xl">
                <Card>
                    <CardHeader>
                        <CardTitle>Create Session</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">{{ error }}</div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium">Name (optional)</label>
                                <Input v-model="form.name" type="text" class="mt-1" :error="!!validation.name" />
                                <p v-if="validation.name" class="mt-1 text-sm text-destructive">{{ validation.name[0] }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium">Runner</label>
                                <select
                                    v-model="form.runner_type"
                                    class="mt-1 flex h-9 w-full rounded-md border border-input bg-input-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    <option value="claude">claude</option>
                                    <option value="codex">codex</option>
                                </select>
                                <p v-if="validation.runner_type" class="mt-1 text-sm text-destructive">{{ validation.runner_type[0] }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium">Model</label>
                            <select
                                v-model="form.model"
                                :disabled="loadingModels"
                                class="mt-1 flex h-9 w-full rounded-md border border-input bg-input-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50"
                            >
                                <option v-if="loadingModels" value="">Loading models...</option>
                                <option v-for="m in availableModels" :key="m.id" :value="m.id">
                                    {{ m.name }}{{ m.id === defaultModel ? ' (default)' : '' }}
                                </option>
                            </select>
                            <p class="mt-1 text-xs text-muted-foreground">Model used for discovery and build runs.</p>
                            <p v-if="validation.model" class="mt-1 text-sm text-destructive">{{ validation.model[0] }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium">Project Directory</label>
                            <DirectoryPickerInput v-model="form.project_directory" class="mt-1" :error="!!validation.project_directory" />
                            <p class="mt-1 text-xs text-muted-foreground">Absolute path where discovery commands run.</p>
                            <p v-if="validation.project_directory" class="mt-1 text-sm text-destructive">{{ validation.project_directory[0] }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium">Interrogation Type</label>
                            <select
                                v-model="form.interrogation_type"
                                class="mt-1 flex h-9 w-full rounded-md border border-input bg-input-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                <option value="feature">feature</option>
                                <option value="general">general</option>
                            </select>
                            <p v-if="validation.interrogation_type" class="mt-1 text-sm text-destructive">{{ validation.interrogation_type[0] }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium">Feature Brief</label>
                            <Textarea
                                v-model="form.feature_brief"
                                :rows="10"
                                class="mt-1 font-mono"
                                placeholder="Describe the feature scope, users, and constraints."
                                :error="!!validation.feature_brief"
                            />
                            <p class="mt-1 text-xs text-muted-foreground">Required for feature sessions.</p>
                            <p v-if="validation.feature_brief" class="mt-1 text-sm text-destructive">{{ validation.feature_brief[0] }}</p>
                        </div>

                        <div class="flex justify-end">
                            <Button :disabled="submitting" @click="submit">
                                {{ submitting ? 'Creating...' : 'Create Session' }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
