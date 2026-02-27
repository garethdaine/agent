<script setup>
import { computed, ref, watch } from 'vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import { Markdown } from '@tiptap/markdown';
import { Bold, List, ListOrdered, Link2, Code } from 'lucide-vue-next';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Write markdown notes...',
    },
});

const emit = defineEmits(['update:modelValue']);

const isClient = typeof window !== 'undefined';

const editor = isClient
    ? useEditor({
          immediatelyRender: false,
          content: String(props.modelValue ?? ''),
          contentType: 'markdown',
          extensions: [
              StarterKit,
              Link.configure({
                  openOnClick: false,
                  autolink: true,
                  defaultProtocol: 'https',
              }),
              Markdown.configure({
                  breaks: true,
                  transformCopiedText: true,
                  transformPastedText: true,
              }),
              Placeholder.configure({
                  placeholder: props.placeholder,
              }),
          ],
          onUpdate: ({ editor: currentEditor }) => {
              emit('update:modelValue', currentEditor.getMarkdown());
          },
      })
    : ref(null);

watch(
    () => props.modelValue,
    (value) => {
        if (!editor.value) {
            return;
        }

        const normalized = String(value ?? '');
        if (editor.value.getMarkdown() === normalized) {
            return;
        }

        editor.value.commands.setContent(normalized, {
            contentType: 'markdown',
        });
    }
);

const canEdit = computed(() => editor.value !== null);

const isActive = (name) => editor.value?.isActive(name) ?? false;
const toolbarButtonClass = (active) => [
    'inline-flex h-8 w-8 items-center justify-center rounded transition-colors',
    active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
];

const setLink = () => {
    if (!editor.value || typeof window === 'undefined') {
        return;
    }

    const currentUrl = editor.value.getAttributes('link').href ?? '';
    const nextUrl = window.prompt('Enter URL', currentUrl);

    if (nextUrl === null) {
        return;
    }

    const normalized = nextUrl.trim();
    if (normalized === '') {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
        return;
    }

    editor.value.chain().focus().extendMarkRange('link').setLink({ href: normalized }).run();
};
</script>

<template>
    <div class="space-y-2">
        <div class="flex flex-wrap items-center gap-2 rounded-md border border-gray-300 bg-gray-50 p-2 dark:border-gray-700 dark:bg-gray-900/50">
            <button
                type="button"
                class="text-xs font-medium"
                :class="toolbarButtonClass(isActive('bold'))"
                aria-label="Bold"
                title="Bold"
                :disabled="!canEdit"
                @click="editor?.chain().focus().toggleBold().run()"
            >
                <Bold class="size-4" aria-hidden="true" />
            </button>
            <button
                type="button"
                class="text-xs font-medium"
                :class="toolbarButtonClass(isActive('bulletList'))"
                aria-label="Bullet list"
                title="Bullet list"
                :disabled="!canEdit"
                @click="editor?.chain().focus().toggleBulletList().run()"
            >
                <List class="size-4" aria-hidden="true" />
            </button>
            <button
                type="button"
                class="text-xs font-medium"
                :class="toolbarButtonClass(isActive('orderedList'))"
                aria-label="Ordered list"
                title="Ordered list"
                :disabled="!canEdit"
                @click="editor?.chain().focus().toggleOrderedList().run()"
            >
                <ListOrdered class="size-4" aria-hidden="true" />
            </button>
            <button
                type="button"
                class="text-xs font-medium"
                :class="toolbarButtonClass(isActive('link'))"
                aria-label="Link"
                title="Link"
                :disabled="!canEdit"
                @click="setLink"
            >
                <Link2 class="size-4" aria-hidden="true" />
            </button>
            <button
                type="button"
                class="text-xs font-medium"
                :class="toolbarButtonClass(isActive('code'))"
                aria-label="Inline code"
                title="Inline code"
                :disabled="!canEdit"
                @click="editor?.chain().focus().toggleCode().run()"
            >
                <Code class="size-4" aria-hidden="true" />
            </button>
        </div>

        <div class="rounded-md border border-gray-300 bg-white dark:border-gray-700 dark:bg-gray-900">
            <EditorContent v-if="editor" :editor="editor" class="markdown-editor-content" />
            <textarea
                v-else
                :value="modelValue"
                :placeholder="placeholder"
                rows="8"
                class="w-full rounded-md border-0 bg-transparent p-3 font-mono text-sm focus:outline-none focus:ring-0 dark:text-gray-100"
                @input="emit('update:modelValue', $event.target.value)"
            />
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400">Formatting toolbar edits markdown and stores plain markdown text.</p>
    </div>
</template>

<style scoped>
:deep(.markdown-editor-content .tiptap) {
    min-height: 14rem;
    padding: 0.75rem;
    font-size: 0.925rem;
    line-height: 1.5;
    outline: none;
    white-space: pre-wrap;
    color: #111827;
}

:deep(.dark .markdown-editor-content .tiptap) {
    color: #f3f4f6;
}

@media (prefers-color-scheme: dark) {
    :deep(.markdown-editor-content .tiptap) {
        color: #f3f4f6;
    }
}

:deep(.markdown-editor-content .tiptap p) {
    margin: 0.5rem 0;
}

:deep(.markdown-editor-content .tiptap ul),
:deep(.markdown-editor-content .tiptap ol) {
    margin: 0.5rem 0;
    padding-left: 1.25rem;
}

:deep(.markdown-editor-content .tiptap code) {
    border-radius: 0.25rem;
    background: #f3f4f6;
    padding: 0.125rem 0.25rem;
    font-size: 0.8125rem;
}

:deep(.dark .markdown-editor-content .tiptap code) {
    background: #1f2937;
}

@media (prefers-color-scheme: dark) {
    :deep(.markdown-editor-content .tiptap code) {
        background: #1f2937;
    }
}

:deep(.markdown-editor-content .tiptap a) {
    color: #4f46e5;
    text-decoration: underline;
}

:deep(.dark .markdown-editor-content .tiptap a) {
    color: #818cf8;
}

@media (prefers-color-scheme: dark) {
    :deep(.markdown-editor-content .tiptap a) {
        color: #818cf8;
    }
}

:deep(.markdown-editor-content .tiptap p.is-editor-empty:first-child::before) {
    content: attr(data-placeholder);
    color: #9ca3af;
    float: left;
    height: 0;
    pointer-events: none;
}

@media (prefers-color-scheme: dark) {
    :deep(.markdown-editor-content .tiptap p.is-editor-empty:first-child::before) {
        color: #6b7280;
    }
}
</style>
