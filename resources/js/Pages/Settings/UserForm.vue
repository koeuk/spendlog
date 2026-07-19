<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import FormScreenLayout from '@/Layouts/FormScreenLayout.vue';
import FormActions from '@/Components/FormActions.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { MUTED } from '@/lib/appStyles';
import { trans } from '@/lib/i18n';

/**
 * Create/edit a person on its own screen.
 *
 * Seven fields, two of them selects that open portalled popovers. In a dialog
 * that was some 700px before the confirm-password field appeared and before any
 * validation line, so it scrolled on a phone from the moment it opened.
 *
 * Not SettingsLayout: that renders the nine-tab settings nav, which is nine ways
 * out of a half-filled form.
 */
const props = defineProps({
    // The person being edited; absent when adding.
    user: { type: Object, default: null },
    roles: { type: Array, required: true },
    statuses: { type: Array, required: true },
    can: { type: Object, default: () => ({ change_role: true }) },
});

const editing = !!props.user;

const form = useForm({
    name: props.user?.name ?? '',
    username: props.user?.username ?? '',
    email: props.user?.email ?? '',
    password: '',
    password_confirmation: '',
    role: props.user?.role ?? 'user',
    status: props.user?.status ?? 'active',
});

// Editing, a blank password means "leave it alone" — worth saying, because an
// empty required-looking box otherwise reads as unfinished.
const passwordHint = computed(() =>
    editing ? trans('Leave blank to keep the current password.') : '',
);

const backHref = route('users.index');

function submit() {
    if (editing) {
        form.patch(route('users.update', props.user.uuid));
    } else {
        form.post(route('users.store'));
    }
}
</script>

<template>
    <Head :title="editing ? trans('Edit user') : trans('Add user')" />

    <FormScreenLayout
        :back-href="backHref"
        :title="editing ? __('Edit user') : __('Add user')"
        :back-label="__('Back to users')"
    >
        <form class="flex flex-1 flex-col" @submit.prevent="submit">
            <p class="mb-4 text-sm" :class="MUTED">
                {{ __('Admins can manage categories, branding and other users.') }}
            </p>

            <div class="grid gap-4">
                <div>
                    <Label for="user_name">{{ __('Name') }}</Label>
                    <Input id="user_name" v-model="form.name" class="mt-1" autocomplete="off" />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ form.errors.name }}
                    </p>
                </div>

                <div>
                    <Label for="user_username">{{ __('Username') }}</Label>
                    <Input
                        id="user_username"
                        v-model="form.username"
                        class="mt-1"
                        autocomplete="off"
                        autocapitalize="none"
                        spellcheck="false"
                        placeholder="koeuk"
                        :aria-invalid="!!form.errors.username"
                    />
                    <p class="mt-1 text-xs" :class="MUTED">
                        {{ __('Optional. Lowercase letters, numbers, underscores and hyphens. Can also be used to sign in.') }}
                    </p>
                    <p v-if="form.errors.username" class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ form.errors.username }}
                    </p>
                </div>

                <div>
                    <Label for="user_email">{{ __('Email') }}</Label>
                    <Input id="user_email" v-model="form.email" type="email" class="mt-1" autocomplete="off" />
                    <p v-if="form.errors.email" class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ form.errors.email }}
                    </p>
                </div>

                <!-- Side by side only once there is room. At 320px two columns
                     are 112px each, and the triggers are whitespace-nowrap with
                     a line-clamp, so "Super admin" and "Deactivated" collapsed
                     to a stub that read the same as its neighbours in the closed
                     state — the one state you pick from. -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <!-- A span, not a Label: the trigger is a button, which
                             a label cannot forward a click to. Same reasoning as
                             the movement form. -->
                        <span class="text-sm font-medium">{{ __('Role') }}</span>
                        <!-- Disabled where the last-admin rule forbids a change,
                             rather than hidden: the current role is still worth
                             showing. -->
                        <SearchableSelect
                            v-model="form.role"
                            :options="roles"
                            :disabled="!can.change_role"
                            :label="__('Role')"
                            :searchable="false"
                            align="start"
                            trigger-class="mt-1 h-10 w-full rounded-xl border border-input bg-background px-3 text-sm max-sm:h-11"
                            content-class="w-[--reka-popover-trigger-width]"
                        />
                        <p v-if="form.errors.role" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ form.errors.role }}
                        </p>
                    </div>

                    <div>
                        <span class="text-sm font-medium">{{ __('Status') }}</span>
                        <SearchableSelect
                            v-model="form.status"
                            :options="statuses"
                            :label="__('Status')"
                            :searchable="false"
                            align="start"
                            trigger-class="mt-1 h-10 w-full rounded-xl border border-input bg-background px-3 text-sm max-sm:h-11"
                            content-class="w-[--reka-popover-trigger-width]"
                        />
                        <p v-if="form.errors.status" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ form.errors.status }}
                        </p>
                    </div>
                </div>

                <div>
                    <Label for="user_password">{{ __('Password') }}</Label>
                    <Input
                        id="user_password"
                        v-model="form.password"
                        type="password"
                        class="mt-1"
                        autocomplete="new-password"
                    />
                    <p v-if="passwordHint" class="mt-1 text-xs" :class="MUTED">
                        {{ passwordHint }}
                    </p>
                    <p v-if="form.errors.password" class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ form.errors.password }}
                    </p>
                </div>

                <div v-if="form.password">
                    <Label for="user_password_confirmation">{{ __('Confirm password') }}</Label>
                    <Input
                        id="user_password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        class="mt-1"
                        autocomplete="new-password"
                    />
                </div>
            </div>

            <FormActions>
                <template #cancel>
                    <Button :as="Link" :href="backHref" variant="outline" class="w-full max-sm:h-12 sm:w-auto">
                        {{ __('Cancel') }}
                    </Button>
                </template>

                <template #submit>
                    <Button type="submit" :disabled="form.processing" class="w-full max-sm:h-12 sm:w-auto">
                        {{ form.processing ? __('Saving…') : editing ? __('Save') : __('Create') }}
                    </Button>
                </template>
            </FormActions>
        </form>
    </FormScreenLayout>
</template>
