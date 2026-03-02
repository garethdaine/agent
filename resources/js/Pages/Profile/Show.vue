<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpHint from '@/Components/HelpHint.vue';
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm.vue';
import LogoutOtherBrowserSessionsForm from '@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.vue';
import TwoFactorAuthenticationForm from '@/Pages/Profile/Partials/TwoFactorAuthenticationForm.vue';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from '@/Pages/Profile/Partials/UpdateProfileInformationForm.vue';

defineProps({
    confirmsTwoFactorAuthentication: Boolean,
    sessions: Array,
});
</script>

<template>
    <AppLayout title="Profile">
        <template #header>
            <div class="flex items-center gap-2">
                <h2 class="font-semibold text-xl text-foreground leading-tight">
                    Profile
                </h2>
                <HelpHint
                    ui-key="profile.security"
                    short-text="Manage account profile, password, two-factor auth, and active sessions."
                    learn-more-href="/docs/overview"
                />
            </div>
        </template>

        <div>
            <div class="max-w-[1440px] mx-auto py-10 sm:px-6 lg:px-8">
                <div v-if="$page.props.jetstream.canUpdateProfileInformation">
                    <UpdateProfileInformationForm :user="$page.props.auth.user" />

                    <div class="hidden sm:block">
                        <div class="py-8">
                            <div class="border-t border-border" />
                        </div>
                    </div>
                </div>

                <div v-if="$page.props.jetstream.canUpdatePassword">
                    <UpdatePasswordForm class="mt-10 sm:mt-0" />

                    <div class="hidden sm:block">
                        <div class="py-8">
                            <div class="border-t border-border" />
                        </div>
                    </div>
                </div>

                <div v-if="$page.props.jetstream.canManageTwoFactorAuthentication">
                    <TwoFactorAuthenticationForm
                        :requires-confirmation="confirmsTwoFactorAuthentication"
                        class="mt-10 sm:mt-0"
                    />

                    <div class="hidden sm:block">
                        <div class="py-8">
                            <div class="border-t border-border" />
                        </div>
                    </div>
                </div>

                <LogoutOtherBrowserSessionsForm :sessions="sessions" class="mt-10 sm:mt-0" />

                <template v-if="$page.props.jetstream.hasAccountDeletionFeatures">
                    <div class="hidden sm:block">
                        <div class="py-8">
                            <div class="border-t border-border" />
                        </div>
                    </div>

                    <DeleteUserForm class="mt-10 sm:mt-0" />
                </template>
            </div>
        </div>
    </AppLayout>
</template>
