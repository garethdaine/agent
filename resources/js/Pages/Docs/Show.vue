<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';

defineProps({
    entry: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <AppLayout :title="entry.title ?? 'Docs'">
        <Head :title="entry.title ?? 'Docs'" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-semibold text-xl text-foreground leading-tight">{{ entry.title }}</h2>
                <Link :href="route('docs.index')" class="text-sm text-primary hover:underline">Back to Docs</Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-[1440px] px-4 sm:px-6 lg:px-8 space-y-4">
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-base">{{ entry.title }}</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3 pt-0">
                        <p class="text-sm text-muted-foreground">{{ entry.summary }}</p>
                        <p class="text-xs text-muted-foreground uppercase tracking-wide">
                            {{ entry.domain }} · {{ entry.section }}
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
