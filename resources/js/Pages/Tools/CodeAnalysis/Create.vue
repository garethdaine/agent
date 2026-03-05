<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import DirectoryPickerInput from '@/Components/ui/DirectoryPickerInput.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, FileCode } from 'lucide-vue-next';
import HelpHint from '@/Components/HelpHint.vue';
import axios from 'axios';
import { reactive, ref } from 'vue';

const props = defineProps({
    defaultProjectDirectory: {
        type: String,
        default: '',
    },
});

const form = reactive({
    name: '',
    project_directory: props.defaultProjectDirectory || '',
    analyzer_profile: 'default',
    runner_type: 'claude',
});

const submitting = ref(false);
const error = ref('');
const validation = ref({});

const submit = async () => {
    submitting.value = true;
    error.value = '';
    validation.value = {};

    try {
        const { data } = await axios.post('/agent/api/v1/code-analysis/sessions', form);
        const sessionId = data?.data?.id;

        if (sessionId) {
            router.visit(`${route('tools.code-analysis.wizard', sessionId)}?autostart=1`);
            return;
        }

        router.visit(route('tools.code-analysis.index'));
    } catch (e) {
        const payload = e?.response?.data ?? {};
        validation.value = payload?.error?.details ?? payload?.errors ?? {};
        error.value = payload?.error?.message ?? payload?.message ?? 'Failed to create code analysis session.';
    } finally {
        submitting.value = false;
    }
};
</script>

<template>
    <AppLayout title="New Code Analysis Session">
        <Head title="New Code Analysis Session" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <FileCode class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-semibold text-foreground truncate">New Code Analysis Session</h2>
                        <HelpHint
                            ui-key="code-analysis.create"
                            short-text="Start a new code analysis session."
                            learn-more-href="/docs/overview"
                        />
                    </div>
                </div>
                <Link :href="route('tools.code-analysis.index')">
                    <Button variant="outline" size="sm">
                        <ArrowLeft class="h-4 w-4" />
                        Back
                    </Button>
                </Link>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <Card>
                    <CardHeader>
                        <CardTitle>Create Session</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                            {{ error }}
                        </div>

                        <div>
                            <label class="block text-sm font-medium">Name (optional)</label>
                            <Input v-model="form.name" class="mt-1" type="text" :error="!!validation.name" />
                            <p v-if="validation.name" class="mt-1 text-sm text-destructive">{{ validation.name[0] }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium">Project Directory</label>
                            <DirectoryPickerInput v-model="form.project_directory" class="mt-1" :error="!!validation.project_directory" :disabled="submitting" />
                            <p class="mt-1 text-xs text-muted-foreground">Absolute path to the repository root to analyze.</p>
                            <p v-if="validation.project_directory" class="mt-1 text-sm text-destructive">{{ validation.project_directory[0] }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium">Analyzer Profile</label>
                            <Input v-model="form.analyzer_profile" class="mt-1" type="text" :error="!!validation.analyzer_profile" />
                            <p v-if="validation.analyzer_profile" class="mt-1 text-sm text-destructive">{{ validation.analyzer_profile[0] }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium">AI Runner</label>
                            <select v-model="form.runner_type" class="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                <option value="claude">Claude</option>
                                <option value="codex">Codex</option>
                            </select>
                            <p class="mt-1 text-xs text-muted-foreground">Runner used for AI analysis tasks and final report generation.</p>
                            <p v-if="validation.runner_type" class="mt-1 text-sm text-destructive">{{ validation.runner_type[0] }}</p>
                        </div>

                        <div class="flex justify-end">
                            <Button :disabled="submitting" @click="submit">
                                {{ submitting ? 'Creating…' : 'Create Session' }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
