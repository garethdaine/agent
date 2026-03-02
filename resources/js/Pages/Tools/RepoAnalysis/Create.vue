<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
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
});

const submitting = ref(false);
const error = ref('');
const validation = ref({});

const submit = async () => {
    submitting.value = true;
    error.value = '';
    validation.value = {};

    try {
        const { data } = await axios.post('/agent/api/v1/repo-analysis/sessions', form);
        const sessionId = data?.data?.id;

        if (sessionId) {
            router.visit(`${route('tools.repo-analysis.wizard', sessionId)}?autostart=1`);
            return;
        }

        router.visit(route('tools.repo-analysis.index'));
    } catch (e) {
        const payload = e?.response?.data ?? {};
        validation.value = payload?.error?.details ?? payload?.errors ?? {};
        error.value = payload?.error?.message ?? payload?.message ?? 'Failed to create repo analysis session.';
    } finally {
        submitting.value = false;
    }
};
</script>

<template>
    <AppLayout title="New Repo Analysis Session">
        <Head title="New Repo Analysis Session" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-foreground">New Repo Analysis Session</h2>
                <Link :href="route('tools.repo-analysis.index')">
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
                            <Input v-model="form.project_directory" class="mt-1" type="text" :error="!!validation.project_directory" />
                            <p class="mt-1 text-xs text-muted-foreground">Absolute path to the repository root to analyze.</p>
                            <p v-if="validation.project_directory" class="mt-1 text-sm text-destructive">{{ validation.project_directory[0] }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium">Analyzer Profile</label>
                            <Input v-model="form.analyzer_profile" class="mt-1" type="text" :error="!!validation.analyzer_profile" />
                            <p v-if="validation.analyzer_profile" class="mt-1 text-sm text-destructive">{{ validation.analyzer_profile[0] }}</p>
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
