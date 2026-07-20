<script setup>
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { FORM_ACTION } from '@/lib/appStyles';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        // Put the cursor back on the field that was rejected.
        onError: () => passwordInput.value?.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="space-y-6">
        <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-neutral-100">
                {{ __('Delete Account') }}
            </h2>

            <p class="mt-1 text-sm text-gray-600 dark:text-neutral-400">
                {{
                    __(
                        'Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.',
                    )
                }}
            </p>
        </header>

        <Button
            variant="destructive"
            size="sm"
            :class="FORM_ACTION"
            @click="confirmingUserDeletion = true"
        >
            {{ __('Delete Account') }}
        </Button>

        <ConfirmDialog
            v-model:open="confirmingUserDeletion"
            :title="__('Are you sure you want to delete your account?')"
            :description="
                __(
                    'Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.',
                )
            "
            :confirm-label="__('Delete Account')"
            :cancel-label="__('Cancel')"
            :processing="form.processing"
            :processing-label="__('Deleting...')"
            @update:open="(open) => !open && closeModal()"
            @confirm="deleteUser"
        >
            <div>
                <InputLabel
                    for="password"
                    :value="__('Password')"
                    class="sr-only"
                />

                <Input
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    :placeholder="__('Password')"
                    :aria-invalid="Boolean(form.errors.password)"
                    @keyup.enter="deleteUser"
                />

                <InputError :message="form.errors.password" class="mt-2" />
            </div>
        </ConfirmDialog>
    </section>
</template>
