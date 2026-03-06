<script setup>
import { computed, ref, watch, nextTick } from 'vue';
import { X, FileText, Download, ExternalLink, Copy, Check } from 'lucide-vue-next';
import MarkdownRenderer from '@/Components/Markdown/MarkdownRenderer.vue';
import { useCodeHighlighting } from '@/Composables/Chat/useCodeHighlighting';

const props = defineProps({
    open: { type: Boolean, default: false },
    src: { type: String, default: null },
    filename: { type: String, default: '' },
    mimeType: { type: String, default: '' },
    downloadUrl: { type: String, default: null },
    textContent: { type: String, default: null },
});

const emit = defineEmits(['close']);

const { highlightWithin } = useCodeHighlighting();
const codeContainerRef = ref(null);
const copied = ref(false);
let copyTimeout = null;

const extMap = {
    js: 'javascript', jsx: 'javascript', ts: 'typescript', tsx: 'typescript',
    py: 'python', rb: 'ruby', php: 'php', sh: 'bash', bash: 'bash', zsh: 'bash',
    json: 'json', xml: 'xml', html: 'html', css: 'css', sql: 'sql',
    yaml: 'yaml', yml: 'yaml', md: 'markdown',
};

const codeExtensions = new Set([
    'js', 'jsx', 'ts', 'tsx', 'py', 'rb', 'php', 'sh', 'bash', 'zsh',
    'css', 'sql', 'xml', 'html', 'vue', 'svelte', 'go', 'rs', 'java',
    'c', 'cpp', 'h', 'hpp', 'swift', 'kt', 'scala', 'r', 'lua', 'pl',
]);

const textExtensions = new Set([
    'txt', 'log', 'env', 'ini', 'conf', 'cfg', 'csv', 'tsv', 'diff', 'patch',
]);

const fileExtension = computed(() => {
    const name = props.filename || '';
    const parts = name.split('.');
    return parts.length > 1 ? parts.pop().toLowerCase() : '';
});

const isImage = computed(() => props.mimeType?.startsWith('image/'));
const isPdf = computed(() => props.mimeType === 'application/pdf');
const isMarkdown = computed(() =>
    fileExtension.value === 'md' || props.mimeType === 'text/markdown',
);
const isJson = computed(() =>
    fileExtension.value === 'json' || props.mimeType === 'application/json',
);
const isCode = computed(() => codeExtensions.has(fileExtension.value));
const isPlainText = computed(() =>
    textExtensions.has(fileExtension.value) ||
    props.mimeType?.startsWith('text/plain'),
);
const isRenderable = computed(() =>
    isMarkdown.value || isJson.value || isCode.value || isPlainText.value,
);

const languageClass = computed(() => {
    const lang = extMap[fileExtension.value];
    return lang ? `language-${lang}` : '';
});

const formattedJson = computed(() => {
    if (!isJson.value || !props.textContent) return '';
    try {
        return JSON.stringify(JSON.parse(props.textContent), null, 2);
    } catch {
        return props.textContent;
    }
});

const displayContent = computed(() => {
    if (isJson.value) return formattedJson.value;
    return props.textContent || '';
});

// Apply highlighting when code content renders
watch(
    () => props.open,
    async (val) => {
        if (val && (isCode.value || isJson.value)) {
            await nextTick();
            await nextTick();
            if (codeContainerRef.value) {
                highlightWithin(codeContainerRef.value);
            }
        }
    },
);

const handleBackdropClick = (e) => {
    if (e.target === e.currentTarget) emit('close');
};

const copyContent = async () => {
    const text = displayContent.value || props.textContent;
    if (!text) return;
    try {
        await navigator.clipboard.writeText(text);
        copied.value = true;
        clearTimeout(copyTimeout);
        copyTimeout = setTimeout(() => { copied.value = false; }, 2000);
    } catch {}
};
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open && (src || textContent)"
                class="fixed inset-0 z-[100] flex items-center justify-center bg-black/70 backdrop-blur-sm"
                @click="handleBackdropClick"
                @keydown.esc="emit('close')"
                tabindex="0"
            >
                <!-- Header bar -->
                <div class="fixed top-0 inset-x-0 z-10 flex items-center justify-between bg-black/50 px-4 py-3">
                    <span class="text-sm font-medium text-white truncate max-w-[60%]">
                        {{ filename || 'Attachment' }}
                    </span>
                    <div class="flex items-center gap-2">
                        <!-- Copy button for text content -->
                        <button
                            v-if="textContent"
                            type="button"
                            class="flex h-8 w-8 items-center justify-center rounded-lg text-white/70 hover:bg-white/10 hover:text-white transition-colors"
                            :title="copied ? 'Copied!' : 'Copy content'"
                            @click="copyContent"
                        >
                            <Check v-if="copied" class="h-4 w-4 text-green-400" />
                            <Copy v-else class="h-4 w-4" />
                        </button>
                        <a
                            v-if="downloadUrl"
                            :href="downloadUrl"
                            target="_blank"
                            class="flex h-8 w-8 items-center justify-center rounded-lg text-white/70 hover:bg-white/10 hover:text-white transition-colors"
                            title="Open in new tab"
                        >
                            <ExternalLink class="h-4 w-4" />
                        </a>
                        <button
                            type="button"
                            class="flex h-8 w-8 items-center justify-center rounded-lg text-white/70 hover:bg-white/10 hover:text-white transition-colors"
                            @click="emit('close')"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <!-- Content -->
                <div class="max-h-[85vh] max-w-[90vw] overflow-auto rounded-lg mt-12">
                    <!-- Image preview -->
                    <img
                        v-if="isImage"
                        :src="src"
                        :alt="filename"
                        class="max-h-[85vh] max-w-[90vw] rounded-lg object-contain"
                    />

                    <!-- PDF preview -->
                    <iframe
                        v-else-if="isPdf"
                        :src="src"
                        class="h-[80vh] w-[70vw] rounded-lg bg-white"
                    />

                    <!-- Markdown preview -->
                    <div
                        v-else-if="isMarkdown && textContent"
                        class="w-[70vw] max-h-[80vh] overflow-auto rounded-lg bg-card border border-border p-6"
                    >
                        <div class="prose prose-sm dark:prose-invert max-w-none">
                            <MarkdownRenderer :markdown="textContent" :normalize="false" />
                        </div>
                    </div>

                    <!-- JSON preview -->
                    <div
                        v-else-if="isJson && textContent"
                        ref="codeContainerRef"
                        class="w-[70vw] max-h-[80vh] overflow-auto rounded-lg bg-card border border-border"
                    >
                        <pre class="p-4 text-sm leading-relaxed"><code class="language-json">{{ formattedJson }}</code></pre>
                    </div>

                    <!-- Code preview -->
                    <div
                        v-else-if="isCode && textContent"
                        ref="codeContainerRef"
                        class="w-[70vw] max-h-[80vh] overflow-auto rounded-lg bg-card border border-border"
                    >
                        <pre class="p-4 text-sm leading-relaxed"><code :class="languageClass">{{ textContent }}</code></pre>
                    </div>

                    <!-- Plain text preview -->
                    <div
                        v-else-if="isPlainText && textContent"
                        class="w-[70vw] max-h-[80vh] overflow-auto rounded-lg bg-card border border-border p-6"
                    >
                        <pre class="text-sm leading-relaxed text-foreground font-mono whitespace-pre-wrap break-words">{{ textContent }}</pre>
                    </div>

                    <!-- Unsupported type -->
                    <div v-else class="flex flex-col items-center gap-4 rounded-xl bg-card border border-border px-12 py-10">
                        <FileText class="h-12 w-12 text-muted-foreground" />
                        <div class="text-center">
                            <p class="text-sm font-medium text-foreground">{{ filename }}</p>
                            <p class="text-xs text-muted-foreground mt-1">Preview not available for this file type.</p>
                        </div>
                        <a
                            v-if="downloadUrl"
                            :href="downloadUrl"
                            target="_blank"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
                        >
                            <Download class="h-4 w-4" />
                            Download
                        </a>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
