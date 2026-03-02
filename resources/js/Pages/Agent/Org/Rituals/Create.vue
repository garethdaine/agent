<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import Button from '@/Components/ui/Button.vue';
import Form from '@/Pages/Agent/Org/Rituals/Form.vue';
import { ArrowLeft } from 'lucide-vue-next';
</script>

<template>
    <AppLayout title="Create Ritual">
        <Head title="Create Ritual" />

        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('org.rituals.index')">
                    <Button variant="ghost" size="sm">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                </Link>
                <h2 class="text-xl font-semibold leading-tight text-foreground">Create Ritual</h2>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-5xl">
                <Form />
            </div>
        </div>
    </AppLayout>
</template>
