<script setup>
import ApiTokenManager from '@/Pages/API/Partials/ApiTokenManager.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpHint from '@/Components/HelpHint.vue';

defineProps({
    tokens: Array,
    availablePermissions: Array,
    defaultPermissions: Array,
});
</script>

<template>
    <AppLayout title="API Tokens">
        <template #header>
            <div class="flex items-center gap-2">
                <h2 class="font-semibold text-xl text-foreground leading-tight">
                    API Tokens
                </h2>
                <HelpHint
                    ui-key="api.tokens"
                    short-text="Create and rotate API tokens with least-privilege permissions."
                    learn-more-href="/docs/api-contracts"
                />
            </div>
        </template>

        <div>
            <div class="max-w-[1440px] mx-auto py-10 sm:px-6 lg:px-8">
                <ApiTokenManager
                    :tokens="tokens"
                    :available-permissions="availablePermissions"
                    :default-permissions="defaultPermissions"
                />
            </div>
        </div>
    </AppLayout>
</template>
