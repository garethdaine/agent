<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';

defineProps({
    entries: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <AppLayout title="Docs">
        <Head title="Docs" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-semibold text-xl text-foreground leading-tight">Docs Center</h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-[1440px] px-4 sm:px-6 lg:px-8 space-y-4">
                <p class="text-sm text-muted-foreground">
                    Internal documentation is available to authenticated users.
                </p>

                <Card v-if="entries.length === 0">
                    <CardContent class="py-8">
                        <p class="text-sm text-muted-foreground">No docs are currently available.</p>
                    </CardContent>
                </Card>

                <div v-else class="space-y-3">
                    <Card v-for="entry in entries" :key="entry.slug">
                        <CardHeader class="pb-2">
                            <CardTitle class="text-base">
                                <Link
                                    :href="route('docs.show', { slug: entry.slug })"
                                    class="text-primary hover:underline"
                                >
                                    {{ entry.title }}
                                </Link>
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-2 pt-0">
                            <p class="text-sm text-muted-foreground">{{ entry.summary }}</p>
                            <p class="text-xs text-muted-foreground uppercase tracking-wide">
                                {{ entry.domain }} · {{ entry.section }}
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
