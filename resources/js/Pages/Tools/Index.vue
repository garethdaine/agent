<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <AppLayout title="Tools">
        <Head title="Tools" />

        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Tools</h2>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <Link
                        :href="route('tools.discovery.index')"
                        class="rounded-lg border border-gray-200 bg-white p-5 transition hover:border-indigo-300 hover:shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Requirements Discovery</p>
                        <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">Interactive discovery wizard</h3>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Guide AI-led requirements interrogation and generate summaries/plans.</p>
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
