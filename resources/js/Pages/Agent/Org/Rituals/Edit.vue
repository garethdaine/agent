<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import Button from '@/Components/ui/Button.vue';
import Form from '@/Pages/Agent/Org/Rituals/Form.vue';
import { ArrowLeft, Bot } from 'lucide-vue-next';
import HelpHint from '@/Components/HelpHint.vue';

defineProps({
    ritualId: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <AppLayout title="Edit Ritual">
        <Head title="Edit Ritual" />

        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('org.rituals.index')">
                    <Button variant="ghost" size="sm">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                </Link>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                    <Bot class="h-5 w-5 text-primary" />
                </div>
                <div class="flex items-center gap-2">
                    <h2 class="text-base font-semibold text-foreground truncate">Edit Ritual</h2>
                    <HelpHint
                        ui-key="org.rituals.edit"
                        short-text="Modify an existing agent ritual."
                        learn-more-href="/docs/overview"
                    />
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-5xl">
                <Form :ritual-id="ritualId" />
            </div>
        </div>
    </AppLayout>
</template>
