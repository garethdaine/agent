<script setup>
import { ref } from 'vue';
import Badge from '@/Components/ui/Badge.vue';
import ChatAttachmentPreviewModal from './ChatAttachmentPreviewModal.vue';
import { Paperclip, FileText, Image, AlertTriangle, Eye } from 'lucide-vue-next';

const props = defineProps({
    messages: { type: Array, default: () => [] },
});

const previewOpen = ref(false);
const previewSrc = ref(null);
const previewFilename = ref('');
const previewMimeType = ref('');

// Collect all attachments from messages
const allAttachments = () => {
    const attachments = [];

    for (const msg of props.messages) {
        if (msg.attachments?.length) {
            for (const att of msg.attachments) {
                attachments.push(att);
            }
        } else if (msg.attachment_ids?.length) {
            for (const id of msg.attachment_ids) {
                attachments.push({ id, filename: null, placeholder: true });
            }
        }
    }

    return attachments;
};

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

const openPreview = (att) => {
    const url = att.url || att.download_url || att.preview_url;
    if (!url) return;
    previewSrc.value = url;
    previewFilename.value = att.filename || 'Attachment';
    previewMimeType.value = att.mime_type || '';
    previewOpen.value = true;
};
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                <Paperclip class="h-3 w-3" />
                Attachments
            </h4>
            <Badge v-if="allAttachments().length" variant="outline" class="text-[10px]">
                {{ allAttachments().length }}
            </Badge>
        </div>

        <div v-if="allAttachments().length === 0" class="text-xs text-muted-foreground">
            No attachments in this session.
        </div>

        <div v-else class="space-y-1.5">
            <div
                v-for="att in allAttachments()"
                :key="att.id"
                class="group flex items-center gap-2 rounded-lg border border-border px-2.5 py-2 text-xs transition-colors"
                :class="isPreviewable(att.mime_type) ? 'cursor-pointer hover:bg-muted/50' : ''"
                @click="isPreviewable(att.mime_type) ? openPreview(att) : null"
            >
                <!-- Image thumbnail or icon -->
                <template v-if="isImage(att.mime_type) && (att.url || att.preview_url)">
                    <div class="relative h-8 w-8 shrink-0 rounded overflow-hidden">
                        <img
                            :src="att.preview_url || att.url"
                            :alt="att.filename"
                            class="h-full w-full object-cover"
                        />
                    </div>
                </template>
                <template v-else>
                    <Image v-if="isImage(att.mime_type)" class="h-3.5 w-3.5 text-primary shrink-0" />
                    <FileText v-else class="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                </template>

                <div class="min-w-0 flex-1">
                    <div class="truncate font-medium text-foreground">
                        {{ att.filename ?? att.id }}
                    </div>
                    <div v-if="att.size_bytes" class="text-[10px] text-muted-foreground">
                        {{ formatSize(att.size_bytes) }}
                    </div>
                </div>

                <Eye
                    v-if="isPreviewable(att.mime_type)"
                    class="h-3 w-3 text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity shrink-0"
                />

                <span
                    v-if="att.scan_status"
                    class="inline-flex items-center gap-0.5 rounded px-1 py-0.5 text-[10px] font-medium shrink-0"
                    :class="scanStatusConfig[att.scan_status]?.class"
                >
                    <AlertTriangle v-if="att.scan_status === 'infected'" class="h-2.5 w-2.5" />
                    {{ scanStatusConfig[att.scan_status]?.label }}
                </span>
            </div>
        </div>
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
