<script setup>
import { Head } from '@inertiajs/vue3';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm.vue';
import { trans } from '@/lib/i18n';
</script>

<template>
    <Head title="Password" />

    <SettingsLayout
        :heading="trans('Password')"
        :description="trans('Use a long, random password to keep your account secure.')"
    >
        <UpdatePasswordForm class="max-w-xl" />
    </SettingsLayout>
</template>
