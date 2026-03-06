<script setup>
import { ref, computed, nextTick, onBeforeUnmount, onMounted } from 'vue';
import Button from '@/Components/ui/Button.vue';
import Spinner from '@/Components/ui/Spinner.vue';
import ChatAttachmentPreviewModal from './ChatAttachmentPreviewModal.vue';
import ChatCommandMenu from './ChatCommandMenu.vue';
import { Send, Paperclip, X, FileText, Image as ImageIcon } from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps({
    disabled: { type: Boolean, default: false },
    sendLoading: { type: Boolean, default: false },
    sessionStatus: { type: String, default: 'active' },
});

const emit = defineEmits(['send', 'upload-files']);

const content = ref('');
const textareaRef = ref(null);
const fileInputRef = ref(null);
const queuedFiles = ref([]);

// Preview modal state
const previewOpen = ref(false);
const previewSrc = ref(null);
const previewFilename = ref('');
const previewMimeType = ref('');

const isActive = props.sessionStatus === 'active';

// Slash command menu
const commandMenuRef = ref(null);
const slashCommands = ref([]);
const showCommandMenu = ref(false);
const commandFilter = computed(() => {
    const text = content.value;
    if (!text.startsWith('/')) return '';
    const firstSpace = text.indexOf(' ');
    // Only show menu if we're still typing the command name (no space yet)
    if (firstSpace > 0) return null;
    return text.slice(1).toLowerCase();
});

const loadCommands = async () => {
    if (slashCommands.value.length > 0) return;
    try {
        const { data } = await axios.get('/agent/api/v1/chat/commands');
        slashCommands.value = data.data;
    } catch {
        // Silently fail - commands just won't autocomplete
    }
};

const handleSelectCommand = (cmd) => {
    content.value = `/${cmd.name} `;
    showCommandMenu.value = false;
    nextTick(() => {
        textareaRef.value?.focus();
        autoResize();
    });
};

const handleKeydown = (event) => {
    // Command menu navigation
    if (showCommandMenu.value && commandMenuRef.value?.hasResults()) {
        if (event.key === 'ArrowUp') {
            event.preventDefault();
            commandMenuRef.value.moveUp();
            return;
        }
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            commandMenuRef.value.moveDown();
            return;
        }
        if (event.key === 'Tab' || (event.key === 'Enter' && !event.shiftKey)) {
            event.preventDefault();
            commandMenuRef.value.selectCurrent();
            return;
        }
        if (event.key === 'Escape') {
            event.preventDefault();
            showCommandMenu.value = false;
            return;
        }
    }

    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        send();
    }
};

const send = () => {
    const text = content.value.trim();
    if (!text && queuedFiles.value.length === 0) return;
    if (props.sendLoading || props.disabled) return;

    emit('send', text, queuedFiles.value);
    content.value = '';
    showCommandMenu.value = false;
    revokeAllPreviews();
    queuedFiles.value = [];

    nextTick(() => {
        autoResize();
        textareaRef.value?.focus();
    });
};

const autoResize = () => {
    const el = textareaRef.value;
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 160) + 'px';
};

const triggerFileUpload = () => {
    fileInputRef.value?.click();
};

const handleFileSelect = (event) => {
    const files = Array.from(event.target.files ?? []);
    for (const file of files) {
        const entry = {
            id: crypto.randomUUID(),
            file,
            name: file.name,
            size: file.size,
            type: file.type,
            previewUrl: null,
        };

        // Generate thumbnail preview for images
        if (file.type?.startsWith('image/')) {
            entry.previewUrl = URL.createObjectURL(file);
        }

        queuedFiles.value.push(entry);
    }
    event.target.value = '';
};

const removeQueuedFile = (id) => {
    const file = queuedFiles.value.find((f) => f.id === id);
    if (file?.previewUrl) {
        URL.revokeObjectURL(file.previewUrl);
    }
    queuedFiles.value = queuedFiles.value.filter((f) => f.id !== id);
};

const revokeAllPreviews = () => {
    for (const file of queuedFiles.value) {
        if (file.previewUrl) URL.revokeObjectURL(file.previewUrl);
    }
};

const openPreview = (file) => {
    if (file.previewUrl) {
        previewSrc.value = file.previewUrl;
        previewFilename.value = file.name;
        previewMimeType.value = file.type;
        previewTextContent.value = null;
        previewOpen.value = true;
    } else if (isRenderableFile(file.name)) {
        openTextPreview(file);
    }
};

const isImageFile = (type) => type?.startsWith('image/');

const renderableExtensions = new Set([
    'js', 'jsx', 'ts', 'tsx', 'py', 'rb', 'php', 'sh', 'bash', 'zsh',
    'css', 'sql', 'xml', 'html', 'vue', 'svelte', 'go', 'rs', 'java',
    'c', 'cpp', 'h', 'hpp', 'swift', 'kt', 'scala', 'r', 'lua', 'pl',
    'json', 'yaml', 'yml', 'md', 'txt', 'log', 'env', 'ini', 'conf',
    'cfg', 'csv', 'tsv', 'diff', 'patch',
]);

const isRenderableFile = (name) => {
    const ext = name?.split('.').pop()?.toLowerCase();
    return ext && renderableExtensions.has(ext);
};

const openTextPreview = (file) => {
    const reader = new FileReader();
    reader.onload = () => {
        previewSrc.value = null;
        previewFilename.value = file.name;
        previewMimeType.value = file.type || 'text/plain';
        previewTextContent.value = reader.result;
        previewOpen.value = true;
    };
    reader.readAsText(file.file);
};

const previewTextContent = ref(null);

const formatFileSize = (bytes) => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / 1048576).toFixed(1)} MB`;
};

const handleInput = () => {
    autoResize();
    // Show command menu when typing a slash command
    if (content.value.startsWith('/') && commandFilter.value !== null) {
        loadCommands();
        showCommandMenu.value = true;
    } else {
        showCommandMenu.value = false;
    }
};

onMounted(() => {
    // Pre-load commands so they're ready when the user types /
    loadCommands();
});

onBeforeUnmount(() => {
    revokeAllPreviews();
});
</script>

<template>
    <div class="shrink-0 border-t border-border bg-card">
        <!-- Queued files -->
        <div v-if="queuedFiles.length" class="flex flex-wrap gap-2 px-4 pt-3">
            <div
                v-for="file in queuedFiles"
                :key="file.id"
                class="relative group rounded-lg border border-border bg-muted/30 overflow-hidden"
                :class="file.previewUrl ? 'w-16 h-16' : 'flex items-center gap-1.5 px-2.5 py-1.5'"
            >
                <!-- Image thumbnail -->
                <template v-if="file.previewUrl">
                    <img
                        :src="file.previewUrl"
                        :alt="file.name"
                        class="w-full h-full object-cover cursor-pointer"
                        :title="file.name"
                        @click="openPreview(file)"
                    />
                    <button
                        type="button"
                        class="absolute top-0.5 right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-black/60 text-white opacity-0 group-hover:opacity-100 transition-opacity"
                        @click.stop="removeQueuedFile(file.id)"
                    >
                        <X class="h-2.5 w-2.5" />
                    </button>
                </template>

                <!-- Non-image file chip -->
                <template v-else>
                    <FileText class="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                    <span
                        class="truncate max-w-[100px] font-medium text-xs"
                        :class="isRenderableFile(file.name) ? 'cursor-pointer hover:underline' : ''"
                        @click.stop="isRenderableFile(file.name) ? openTextPreview(file) : null"
                    >
                        {{ file.name }}
                    </span>
                    <span class="text-muted-foreground text-xs">{{ formatFileSize(file.size) }}</span>
                    <button
                        type="button"
                        class="rounded-full p-0.5 hover:bg-muted transition-colors"
                        @click="removeQueuedFile(file.id)"
                    >
                        <X class="h-3 w-3" />
                    </button>
                </template>
            </div>
        </div>

        <!-- Slash command menu -->
        <div class="relative">
            <ChatCommandMenu
                ref="commandMenuRef"
                :commands="slashCommands"
                :visible="showCommandMenu"
                :filter="commandFilter ?? ''"
                @select="handleSelectCommand"
            />
        </div>

        <!-- Input area -->
        <div class="flex items-center gap-2 px-4 py-3">
            <!-- File upload button -->
            <button
                v-if="isActive"
                type="button"
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                title="Attach files"
                @click="triggerFileUpload"
            >
                <Paperclip class="h-4 w-4" />
            </button>
            <input
                ref="fileInputRef"
                type="file"
                multiple
                accept="image/*,.pdf,.txt,.md,.json,.csv,.log,.xml,.yaml,.yml"
                class="hidden"
                @change="handleFileSelect"
            />

            <!-- Textarea -->
            <div class="flex-1 min-w-0">
                <textarea
                    ref="textareaRef"
                    v-model="content"
                    :disabled="!isActive || sendLoading || disabled"
                    :placeholder="isActive ? 'Type a message... (Enter to send, Shift+Enter for new line)' : 'This session is archived.'"
                    rows="1"
                    class="w-full resize-none rounded-2xl border border-border/60 bg-muted/40 px-4 py-2.5 text-sm leading-snug ring-offset-background placeholder:text-muted-foreground/60 focus:outline-none focus:border-primary/40 focus:ring-2 focus:ring-primary/20 focus:bg-background disabled:cursor-not-allowed disabled:opacity-50 transition-colors"
                    style="max-height: 160px"
                    @input="handleInput"
                    @keydown="handleKeydown"
                />
            </div>

            <!-- Send button -->
            <Button
                size="icon"
                class="h-10 w-10 shrink-0 rounded-xl"
                :disabled="(!content.trim() && queuedFiles.length === 0) || sendLoading || !isActive || disabled"
                @click="send"
            >
                <Spinner v-if="sendLoading" class="h-4 w-4" />
                <Send v-else class="h-4 w-4" />
            </Button>
        </div>
    </div>

    <!-- Preview modal -->
    <ChatAttachmentPreviewModal
        :open="previewOpen"
        :src="previewSrc"
        :filename="previewFilename"
        :mime-type="previewMimeType"
        :text-content="previewTextContent"
        @close="previewOpen = false"
    />
</template>
