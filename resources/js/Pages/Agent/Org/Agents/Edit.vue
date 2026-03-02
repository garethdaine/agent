<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import Button from '@/Components/ui/Button.vue';
import Form from '@/Pages/Agent/Org/Agents/Form.vue';
import { ArrowLeft } from 'lucide-vue-next';

defineProps({
    agentId: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <AppLayout title="Edit Agent">
        <Head title="Edit Agent" />

        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('org.agents.index')">
                    <Button variant="ghost" size="sm">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                </Link>
                <h2 class="text-xl font-semibold leading-tight text-foreground">Edit Agent</h2>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl">
                <Form :agent-id="agentId" />
            </div>
        </div>
    </AppLayout>
</template>
