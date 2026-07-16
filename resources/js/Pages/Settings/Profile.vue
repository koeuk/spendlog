<script setup>
import { Head } from '@inertiajs/vue3';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import UpdateProfileInformationForm from '@/Pages/Profile/Partials/UpdateProfileInformationForm.vue';
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm.vue';
import { trans } from '@/lib/i18n';

defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
});
</script>

<template>
    <Head title="Profile" />

    <SettingsLayout
        :heading="trans('Profile')"
        :description="trans('Your name and email address.')"
    >
        <UpdateProfileInformationForm
            :must-verify-email="mustVerifyEmail"
            :status="status"
            class="max-w-xl"
        />

        <!--
            Deleting the account is destructive and unrelated to editing it, so
            it sits in its own card rather than a stray button in this form.
        -->
        <template #after>
            <div class="mt-4 rounded-lg bg-white p-6 shadow-sm sm:p-8 dark:bg-neutral-900">
                <DeleteUserForm class="max-w-xl" />
            </div>
        </template>
    </SettingsLayout>
</template>
