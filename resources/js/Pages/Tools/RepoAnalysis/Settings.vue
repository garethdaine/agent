<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardDescription from '@/Components/ui/CardDescription.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { onMounted, reactive, ref } from 'vue';

const props = defineProps({
    sessionId: {
        type: Number,
        required: true,
    },
});

const loading = ref(false);
const saving = ref(false);
const error = ref('');
const notice = ref('');
const validation = ref({});

const form = reactive({
    name: '',
    project_directory: '',
    analyzer_profile: 'default',
});

const load = async () => {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get(`/agent/api/v1/repo-analysis/sessions/${props.sessionId}`);
        form.name = data?.data?.name ?? '';
        form.project_directory = data?.data?.project_directory ?? '';
        form.analyzer_profile = data?.data?.analyzer_profile ?? 'default';
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load settings.';
    } finally {
        loading.value = false;
    }
};

const save = async () => {
    saving.value = true;
    error.value = '';
    notice.value = '';
    validation.value = {};

    try {
        await axios.patch(`/agent/api/v1/repo-analysis/sessions/${props.sessionId}`, {
            name: form.name,
            project_directory: form.project_directory,
            analyzer_profile: form.analyzer_profile,
        });

        notice.value = 'Settings saved.';
        await load();
    } catch (e) {
        const payload = e?.response?.data ?? {};
        validation.value = payload?.error?.details ?? payload?.errors ?? {};
        error.value = payload?.error?.message ?? payload?.message ?? 'Failed to save settings.';
    } finally {
        saving.value = false;
    }
};

onMounted(load);
</script>

<template>
    <AppLayout title="Repo Analysis Settings">
        <Head title="Repo Analysis Settings" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-foreground">Repo Analysis Settings</h2>
                <div class="flex items-center gap-2">
                    <Link :href="route('tools.repo-analysis.wizard', sessionId)">
                        <Button variant="outline" size="sm">Back to Wizard</Button>
                    </Link>
                    <Link :href="route('tools.repo-analysis.index')">
                        <Button variant="outline" size="sm">Session List</Button>
                    </Link>
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <Card>
                    <CardHeader>
                        <CardTitle>Session Configuration</CardTitle>
                        <CardDescription>Update display name, repository path, and analyzer profile.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">{{ error }}</div>
                        <div v-if="notice" class="rounded-md border border-emerald-500/40 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-700 dark:text-emerald-300">{{ notice }}</div>

                        <div>
                            <label class="block text-sm font-medium">Name</label>
                            <Input v-model="form.name" class="mt-1" type="text" :error="!!validation.name" :disabled="loading || saving" />
                            <p v-if="validation.name" class="mt-1 text-sm text-destructive">{{ validation.name[0] }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium">Project Directory</label>
                            <Input v-model="form.project_directory" class="mt-1" type="text" :error="!!validation.project_directory" :disabled="loading || saving" />
                            <p v-if="validation.project_directory" class="mt-1 text-sm text-destructive">{{ validation.project_directory[0] }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium">Analyzer Profile</label>
                            <Input v-model="form.analyzer_profile" class="mt-1" type="text" :error="!!validation.analyzer_profile" :disabled="loading || saving" />
                            <p v-if="validation.analyzer_profile" class="mt-1 text-sm text-destructive">{{ validation.analyzer_profile[0] }}</p>
                        </div>

                        <div class="flex justify-end">
                            <Button :disabled="saving || loading" @click="save">
                                {{ saving ? 'Saving…' : 'Save Settings' }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
