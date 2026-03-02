<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import Button from '@/Components/ui/Button.vue';
import { computed } from 'vue';
import { AlertTriangle, Link2 } from 'lucide-vue-next';

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
                <AlertTriangle class="mx-auto h-12 w-12 text-red-500" />
            </div>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Link Failed</h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ error }}</p>
        </div>

        <!-- Valid Token State -->
        <div v-else class="text-center">
            <div class="mb-4">
                <Link2 class="mx-auto h-12 w-12 text-indigo-500" />
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

                <Button
                    type="submit"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing">Linking...</span>
                    <span v-else>Link Account</span>
                </Button>
            </form>
        </div>
    </AuthenticationCard>
</template>
