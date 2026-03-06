<script setup>
import { ref } from 'vue';
import Badge from '@/Components/ui/Badge.vue';
import ChatAttachmentPreviewModal from './ChatAttachmentPreviewModal.vue';
import { Paperclip, FileText, Image, AlertTriangle, Eye } from 'lucide-vue-next';

const props = defineProps({
    attachments: { type: Array, default: () => [] },
    attachmentIds: { type: Array, default: () => [] },
});

const previewOpen = ref(false);
const previewSrc = ref(null);
const previewFilename = ref('');
const previewMimeType = ref('');

const isImage = (mime) => mime?.startsWith('image/');

const isPreviewable = (mime) => {
    if (!mime) return false;
    return mime.startsWith('image/') || mime === 'application/pdf' || mime.startsWith('text/');
};

const formatSize = (bytes) => {
    if (!bytes) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1048576).toFixed(1)} MB`;
};

const scanStatusConfig = {
    pending: { label: 'Scanning', class: 'bg-warning/20 text-warning' },
    clean: { label: 'Clean', class: 'bg-success/20 text-success' },
    infected: { label: 'Infected', class: 'bg-destructive/20 text-destructive' },
    skipped: { label: 'Skipped', class: 'bg-muted text-muted-foreground' },
};

const openPreview = (attachment) => {
    const url = attachment.url || attachment.download_url || attachment.preview_url;
    if (!url) return;
    previewSrc.value = url;
    previewFilename.value = attachment.filename || 'Attachment';
    previewMimeType.value = attachment.mime_type || '';
    previewOpen.value = true;
};
</script>

<template>
    <!-- Full attachment data available -->
    <div v-if="attachments.length" class="flex flex-wrap gap-2 mt-2">
        <div
            v-for="attachment in attachments"
            :key="attachment.id"
            class="group relative rounded-lg border border-border bg-muted/30 overflow-hidden"
            :class="isImage(attachment.mime_type) && (attachment.url || attachment.preview_url)
                ? 'w-20 h-20 cursor-pointer'
                : 'flex items-center gap-2 px-2.5 py-1.5 text-xs'"
            @click="isPreviewable(attachment.mime_type) ? openPreview(attachment) : null"
        >
            <!-- Image thumbnail -->
            <template v-if="isImage(attachment.mime_type) && (attachment.url || attachment.preview_url)">
                <img
                    :src="attachment.preview_url || attachment.url"
                    :alt="attachment.filename"
                    class="w-full h-full object-cover"
                />
                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-colors flex items-center justify-center">
                    <Eye class="h-4 w-4 text-white opacity-0 group-hover:opacity-100 transition-opacity" />
                </div>
            </template>

            <!-- Non-image attachment -->
            <template v-else>
                <FileText class="h-3.5 w-3.5 text-muted-foreground shrink-0" />

                <span
                    class="truncate max-w-[120px] text-foreground font-medium"
                    :class="isPreviewable(attachment.mime_type) ? 'cursor-pointer hover:underline' : ''"
                >
                    {{ attachment.filename }}
                </span>

                <span v-if="attachment.size_bytes" class="text-muted-foreground">
                    {{ formatSize(attachment.size_bytes) }}
                </span>

                <span
                    v-if="attachment.scan_status && attachment.scan_status !== 'clean'"
                    class="inline-flex items-center gap-0.5 rounded px-1 py-0.5 text-[10px] font-medium"
                    :class="scanStatusConfig[attachment.scan_status]?.class"
                >
                    <AlertTriangle v-if="attachment.scan_status === 'infected'" class="h-2.5 w-2.5" />
                    {{ scanStatusConfig[attachment.scan_status]?.label }}
                </span>
            </template>
        </div>
    </div>

    <!-- Only attachment IDs available (no full data yet) -->
    <div v-else-if="attachmentIds.length" class="flex items-center gap-1.5 mt-2 text-xs text-muted-foreground">
        <Paperclip class="h-3 w-3" />
        <span>{{ attachmentIds.length }} attachment{{ attachmentIds.length !== 1 ? 's' : '' }}</span>
    </div>

    <!-- Preview modal -->
    <ChatAttachmentPreviewModal
        :open="previewOpen"
        :src="previewSrc"
        :filename="previewFilename"
        :mime-type="previewMimeType"
        @close="previewOpen = false"
    />
</template>
