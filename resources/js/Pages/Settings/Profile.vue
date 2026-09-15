<script setup>
import { Head } from '@inertiajs/vue3';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import UpdateProfilePhotoForm from '@/Pages/Profile/Partials/UpdateProfilePhotoForm.vue';
import UpdateProfileInformationForm from '@/Pages/Profile/Partials/UpdateProfileInformationForm.vue';
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm.vue';
import { CARD } from '@/lib/appStyles';
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
        :description="trans('Your photo, name and email address.')"
    >
        <div class="space-y-6">
            <UpdateProfilePhotoForm />

            <!-- A hairline between the two: the photo lands on its own, the
                 fields wait for Save, and the rule says where one stops. -->
            <UpdateProfileInformationForm
                class="border-t border-border pt-6"
                :must-verify-email="mustVerifyEmail"
                :status="status"
            />
        </div>

        <!--
            Deleting the account is destructive and unrelated to editing it, so
            it sits in its own card rather than a stray button in this form.
        -->
        <template #after>
            <div :class="[CARD, 'anim p-6 sm:p-8']" style="--d: 120ms">
                <DeleteUserForm />
            </div>
        </template>
    </SettingsLayout>
</template>
