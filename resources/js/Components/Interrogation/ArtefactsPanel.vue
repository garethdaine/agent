<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { FileText, FileJson, FileCode, File, X } from 'lucide-vue-next';
import DialogModal from '@/Components/DialogModal.vue';
import MarkdownRenderer from '@/Components/Markdown/MarkdownRenderer.vue';
import Spinner from '@/Components/ui/Spinner.vue';

const props = defineProps({
    sessionId: {
        type: Number,
        required: true,
    },
    artefacts: {
        type: Array,
        default: () => [],
    },
});

const previewOpen = ref(false);
const previewArtefact = ref(null);
const previewContent = ref('');
const previewLoading = ref(false);
const previewError = ref('');
const previewTruncated = ref(false);

const iconForType = (type) => {
    switch (type) {
        case 'document': return FileText;
        case 'config': return FileJson;
        case 'markup': return FileCode;
        case 'data': return FileCode;
        default: return File;
    }
};

const badgeClasses = (type) => {
    switch (type) {
        case 'document': return 'bg-primary/10 text-primary';
        case 'config': return 'bg-warning/10 text-warning';
        case 'markup': return 'bg-info/10 text-info';
        case 'data': return 'bg-success/10 text-success';
        default: return 'bg-muted text-muted-foreground';
    }
};

const isMarkdownType = (type) => ['document'].includes(type);

const openPreview = async (artefact) => {
    previewArtefact.value = artefact;
    previewContent.value = '';
    previewError.value = '';
    previewTruncated.value = false;
    previewLoading.value = true;
    previewOpen.value = true;

    try {
        const { data } = await axios.get(
            `/agent/api/v1/interrogation/sessions/${props.sessionId}/build-tasks/${artefact.task_id}/artefact-content`,
            { params: { path: artefact.path } }
        );

        previewContent.value = data?.data?.content ?? '';
        previewTruncated.value = data?.data?.truncated ?? false;
    } catch (e) {
        previewError.value = e?.response?.data?.error?.message ?? 'Failed to load artefact content.';
    } finally {
        previewLoading.value = false;
    }
};

const closePreview = () => {
    previewOpen.value = false;
    previewArtefact.value = null;
    previewContent.value = '';
    previewError.value = '';
};
</script>

<template>
    <div class="space-y-3 rounded-lg border border-border bg-card p-4">
        <h3 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Artefacts</h3>

        <p v-if="artefacts.length === 0" class="text-xs text-muted-foreground">
            No artefacts generated yet.
        </p>

        <div v-else class="space-y-1.5">
            <button
                v-for="artefact in artefacts"
                :key="`${artefact.task_id}-${artefact.path}`"
                type="button"
                class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-xs transition-colors hover:bg-muted/50"
                @click="openPreview(artefact)"
            >
                <component :is="iconForType(artefact.type)" class="h-3.5 w-3.5 flex-shrink-0 text-muted-foreground" />
                <span class="flex-1 truncate text-foreground">{{ artefact.name }}</span>
                <span
                    class="flex-shrink-0 rounded px-1.5 py-0.5 text-[10px] font-medium"
                    :class="badgeClasses(artefact.type)"
                >
                    {{ artefact.type }}
                </span>
            </button>
        </div>
    </div>

    <DialogModal :show="previewOpen" max-width="2xl" @close="closePreview">
        <template #title>
            <div class="flex items-center gap-2">
                <component
                    v-if="previewArtefact"
                    :is="iconForType(previewArtefact.type)"
                    class="h-4 w-4 text-muted-foreground"
                />
                <span>{{ previewArtefact?.name ?? 'Artefact' }}</span>
            </div>
        </template>

        <template #content>
            <div class="max-h-[60vh] overflow-y-auto">
                <div v-if="previewLoading" class="flex items-center justify-center py-8">
                    <Spinner class="h-5 w-5" />
                    <span class="ml-2 text-sm text-muted-foreground">Loading content...</span>
                </div>

                <div v-else-if="previewError" class="rounded border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
                    {{ previewError }}
                </div>

                <div v-else>
                    <div
                        v-if="previewTruncated"
                        class="mb-3 rounded border border-warning/30 bg-warning/10 p-2 text-xs text-warning"
                    >
                        This file was truncated because it exceeds the maximum preview size.
                    </div>

                    <div v-if="previewArtefact && isMarkdownType(previewArtefact.type)" class="prose prose-sm prose-neutral dark:prose-invert max-w-none">
                        <MarkdownRenderer :markdown="previewContent" />
                    </div>

                    <pre
                        v-else
                        class="overflow-x-auto rounded border border-border bg-muted/30 p-3 text-xs leading-relaxed text-foreground"
                    ><code>{{ previewContent }}</code></pre>
                </div>
            </div>
        </template>

        <template #footer>
            <button
                type="button"
                class="rounded bg-muted px-3 py-2 text-xs font-semibold text-foreground hover:bg-muted/80"
                @click="closePreview"
            >
                Close
            </button>
        </template>
    </DialogModal>
</template>
