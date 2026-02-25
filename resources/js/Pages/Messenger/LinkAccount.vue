<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { computed } from 'vue';

const props = defineProps({
    token: String,
    provider: String,
    providerUserId: String,
    error: String,
});

const page = usePage();
const isAuthenticated = computed(() => !!page.props.auth?.user);

const form = useForm({});

const providerLabel = computed(() => {
    const labels = {
        slack: 'Slack',
        telegram: 'Telegram',
        discord: 'Discord',
        whatsapp: 'WhatsApp',
    };
    return labels[props.provider] || props.provider || 'Messenger';
});

const submit = () => {
    form.post(route('messenger.link.store', { token: props.token }));
};
</script>

<template>
    <Head :title="error ? 'Link Error' : 'Link Account'" />

    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />
        </template>

        <!-- Error State -->
        <div v-if="error" class="text-center">
            <div class="mb-4">
                <svg class="mx-auto h-12 w-12 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Link Failed</h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ error }}</p>
        </div>

        <!-- Valid Token State -->
        <div v-else class="text-center">
            <div class="mb-4">
                <svg class="mx-auto h-12 w-12 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                </svg>
            </div>

            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Link Your {{ providerLabel }} Account
            </h2>

            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                You're about to link your {{ providerLabel }} account to this Agent installation.
                This will allow you to control Agent through {{ providerLabel }}.
            </p>

            <!-- Not authenticated -->
            <div v-if="!isAuthenticated" class="mt-6">
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    Please log in to continue.
                </p>
                <a
                    :href="route('login', { redirect: route('messenger.link.show', { token }) })"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                >
                    Log In
                </a>
            </div>

            <!-- Authenticated -->
            <form v-else @submit.prevent="submit" class="mt-6">
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    Logged in as <strong>{{ page.props.auth.user.email }}</strong>
                </p>

                <PrimaryButton
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing">Linking...</span>
                    <span v-else>Link Account</span>
                </PrimaryButton>
            </form>
        </div>
    </AuthenticationCard>
</template>
