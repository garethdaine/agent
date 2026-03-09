<script setup>
import MarkdownRenderer from '@/Components/Markdown/MarkdownRenderer.vue';
import Spinner from '@/Components/ui/Spinner.vue';
import { ChevronDown, ChevronRight, FileCode, Minus, Plus } from 'lucide-vue-next';
import { useDiffViewer } from '@/Composables/Interrogation/useDiffViewer.js';
import { onMounted, computed } from 'vue';

const props = defineProps({
    sessionId: { type: Number, required: true },
    taskReviewSummary: { type: String, default: '' },
    taskReviewTaskId: { type: Number, required: true },
});

const {
    diffData,
    diffLoading,
    diffError,
    fetchDiff,
    toggleFile,
    isFileExpanded,
    expandAllFiles,
    collapseAllFiles,
} = useDiffViewer();

const hasFiles = computed(() => {
    return diffData.value?.available && diffData.value?.files?.length > 0;
});

const totalAdditions = computed(() => {
    if (!diffData.value?.files) return 0;
    return diffData.value.files.reduce((sum, f) => sum + (f.additions || 0), 0);
});

const totalDeletions = computed(() => {
    if (!diffData.value?.files) return 0;
    return diffData.value.files.reduce((sum, f) => sum + (f.deletions || 0), 0);
});

onMounted(() => {
    fetchDiff(props.sessionId, props.taskReviewTaskId);
});
</script>

<template>
    <div class="space-y-4 rounded-lg border border-amber-500/30 bg-amber-500/5 p-4">
        <div class="flex items-center gap-2 text-amber-600 dark:text-amber-400">
            <FileCode class="h-4 w-4" />
            <span class="text-sm font-semibold">Task Review</span>
        </div>

        <!-- Summary -->
        <div v-if="taskReviewSummary" class="prose prose-sm dark:prose-invert max-w-none">
            <MarkdownRenderer :content="taskReviewSummary" />
        </div>

        <!-- Diff Viewer -->
        <div class="space-y-2">
            <div v-if="diffLoading" class="flex items-center gap-2 py-4 text-sm text-muted-foreground">
                <Spinner class="h-4 w-4" />
                Loading file changes...
            </div>

            <div v-else-if="diffError" class="text-sm text-destructive">
                {{ diffError }}
            </div>

            <div v-else-if="diffData && !diffData.available" class="text-sm text-muted-foreground">
                Diff is not available for this task (no git baseline recorded).
            </div>

            <div v-else-if="hasFiles" class="space-y-2">
                <!-- Diff header -->
                <div class="flex items-center justify-between text-xs text-muted-foreground">
                    <span>
                        {{ diffData.files.length }} file{{ diffData.files.length !== 1 ? 's' : '' }} changed
                        <span v-if="totalAdditions > 0" class="text-emerald-600 dark:text-emerald-400">+{{ totalAdditions }}</span>
                        <span v-if="totalDeletions > 0" class="text-red-600 dark:text-red-400">-{{ totalDeletions }}</span>
                    </span>
                    <div class="flex items-center gap-2">
                        <button class="text-xs hover:underline" @click="expandAllFiles">Expand all</button>
                        <span class="text-muted-foreground/50">|</span>
                        <button class="text-xs hover:underline" @click="collapseAllFiles">Collapse all</button>
                    </div>
                </div>

                <!-- File list -->
                <div
                    v-for="file in diffData.files"
                    :key="file.path"
                    class="overflow-hidden rounded-md border border-border"
                >
                    <!-- File header -->
                    <button
                        class="flex w-full items-center gap-2 bg-muted/50 px-3 py-1.5 text-left text-sm hover:bg-muted"
                        @click="toggleFile(file.path)"
                    >
                        <component
                            :is="isFileExpanded(file.path) ? ChevronDown : ChevronRight"
                            class="h-3.5 w-3.5 shrink-0 text-muted-foreground"
                        />
                        <span class="truncate font-mono text-xs">{{ file.path }}</span>
                        <span
                            v-if="file.status !== 'modified'"
                            class="shrink-0 rounded px-1 py-0.5 text-[10px] font-medium uppercase"
                            :class="{
                                'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400': file.status === 'added',
                                'bg-red-500/10 text-red-600 dark:text-red-400': file.status === 'deleted',
                                'bg-blue-500/10 text-blue-600 dark:text-blue-400': file.status === 'renamed',
                            }"
                        >
                            {{ file.status }}
                        </span>
                        <span class="ml-auto flex shrink-0 items-center gap-1.5 text-xs">
                            <span v-if="file.additions > 0" class="text-emerald-600 dark:text-emerald-400">
                                <Plus class="inline h-3 w-3" />{{ file.additions }}
                            </span>
                            <span v-if="file.deletions > 0" class="text-red-600 dark:text-red-400">
                                <Minus class="inline h-3 w-3" />{{ file.deletions }}
                            </span>
                        </span>
                    </button>

                    <!-- File diff content -->
                    <div v-if="isFileExpanded(file.path)" class="max-h-96 overflow-auto">
                        <div
                            v-for="(hunk, hunkIdx) in file.hunks"
                            :key="hunkIdx"
                        >
                            <div class="bg-blue-500/10 px-3 py-0.5 font-mono text-xs text-blue-600 dark:text-blue-400">
                                {{ hunk.header }}
                            </div>
                            <table class="w-full border-collapse font-mono text-xs">
                                <tbody>
                                    <tr
                                        v-for="(line, lineIdx) in hunk.lines"
                                        :key="lineIdx"
                                        :class="{
                                            'bg-emerald-500/10': line.type === 'added',
                                            'bg-red-500/10': line.type === 'removed',
                                        }"
                                    >
                                        <td class="w-10 select-none px-2 text-right text-muted-foreground/50">
                                            {{ line.old_line ?? '' }}
                                        </td>
                                        <td class="w-10 select-none px-2 text-right text-muted-foreground/50">
                                            {{ line.new_line ?? '' }}
                                        </td>
                                        <td class="w-4 select-none px-1 text-center" :class="{
                                            'text-emerald-600 dark:text-emerald-400': line.type === 'added',
                                            'text-red-600 dark:text-red-400': line.type === 'removed',
                                        }">
                                            {{ line.type === 'added' ? '+' : line.type === 'removed' ? '-' : ' ' }}
                                        </td>
                                        <td class="whitespace-pre-wrap break-all px-2">{{ line.content }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else-if="diffData?.available && diffData?.files?.length === 0" class="text-sm text-muted-foreground">
                No file changes detected for this task.
            </div>
        </div>

        <p class="text-xs text-muted-foreground">
            Review the changes above, then click <strong>Resume Build</strong> to continue to the next task.
        </p>
    </div>
</template>
