<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CreateTeamForm from '@/Pages/Teams/Partials/CreateTeamForm.vue';
import { Users } from 'lucide-vue-next';
</script>

<template>
    <AppLayout title="Create Team">
        <template #header>
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                    <Users class="h-5 w-5 text-primary" />
                </div>
                <h2 class="text-base font-semibold text-foreground truncate">
                    Create Team
                </h2>
            </div>
        </template>

        <div>
            <div class="py-10 sm:px-6 lg:px-8">
                <CreateTeamForm />
            </div>
        </div>
    </AppLayout>
</template>
