<script setup>
import { computed, ref, watch } from 'vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import { Markdown } from '@tiptap/markdown';

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
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M7 5h7a4 4 0 0 1 0 8H7z" />
                    <path d="M7 13h8a4 4 0 0 1 0 8H7z" />
                </svg>
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
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="5" cy="7" r="1.2" />
                    <circle cx="5" cy="12" r="1.2" />
                    <circle cx="5" cy="17" r="1.2" />
                    <path d="M10 7h9M10 12h9M10 17h9" />
                </svg>
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
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M4 7h1.5v4M10 7h10M10 12h10M10 17h10" />
                    <path d="M4 16c0-1 1-1.5 2-1.5s2 .5 2 1.5-1 1.5-2 2.5h2.2" />
                </svg>
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
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M10 13a5 5 0 0 0 7.07 0l1.41-1.41a5 5 0 0 0-7.07-7.07L10 5.93" />
                    <path d="M14 11a5 5 0 0 0-7.07 0l-1.41 1.41a5 5 0 0 0 7.07 7.07L14 18.07" />
                </svg>
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
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="m9 18-6-6 6-6" />
                    <path d="m15 6 6 6-6 6" />
                </svg>
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
