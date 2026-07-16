<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Ban, CircleCheck, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { MUTED } from '@/lib/appStyles';
import { trans } from '@/lib/i18n';

const props = defineProps({
    users: { type: Array, required: true },
    roles: { type: Array, required: true },
    statuses: { type: Array, required: true },
    can: { type: Object, required: true },
});

const showDialog = ref(false);
const editing = ref(null);

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'user',
    status: 'active',
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showDialog.value = true;
}

function openEdit(user) {
    editing.value = user;
    form.name = user.name;
    form.email = user.email;
    // Left blank on edit: the server keeps the existing password unless one is typed.
    form.password = '';
    form.password_confirmation = '';
    form.role = user.role;
    form.status = user.status;
    form.clearErrors();
    showDialog.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showDialog.value = false;
        },
    };

    if (editing.value) {
        form.patch(route('users.update', editing.value.uuid), options);
    } else {
        form.post(route('users.store'), options);
    }
}

const busy = ref(null);

function toggleStatus(user) {
    busy.value = user.uuid;
    router.patch(route('users.status', user.uuid), {}, {
        preserveScroll: true,
        onFinish: () => (busy.value = null),
    });
}

// Holds the row itself so the prompt can name what is about to go, and say how
// many expenses go with it.
const confirming = ref(null);

function destroy() {
    if (!confirming.value) {
        return;
    }

    busy.value = confirming.value.uuid;

    router.delete(route('users.destroy', confirming.value.uuid), {
        preserveScroll: true,
        onSuccess: () => (confirming.value = null),
        onFinish: () => (busy.value = null),
    });
}

const passwordHint = computed(() =>
    editing.value ? trans('Leave blank to keep the current password.') : '',
);
</script>

<template>
    <Head title="Users" />

    <SettingsLayout
        :heading="trans('Users')"
        :description="trans('Who can sign in, and what they can do.')"
    >
        <template #actions>
            <Button v-if="can.create" size="sm" @click="openCreate">
                <Plus class="size-4" />
                {{ __('Add user') }}
            </Button>
        </template>

        <div class="-mx-2 overflow-x-auto">
            <table class="w-full min-w-[34rem] text-sm">
                <thead>
                    <tr class="border-b border-neutral-100 dark:border-neutral-800">
                        <th class="px-2 pb-2 text-start font-semibold">{{ __('Name') }}</th>
                        <th class="px-2 pb-2 text-start font-semibold">{{ __('Role') }}</th>
                        <th class="px-2 pb-2 text-start font-semibold">{{ __('Status') }}</th>
                        <th class="px-2 pb-2 text-end font-semibold">{{ __('Expenses') }}</th>
                        <th class="w-px px-2 pb-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                    <tr v-for="user in users" :key="user.uuid">
                        <td class="px-2 py-3">
                            <div class="flex items-center gap-2">
                                <span class="font-medium">{{ user.name }}</span>
                                <span
                                    v-if="user.is_self"
                                    class="rounded-full bg-neutral-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400"
                                >
                                    {{ __('You') }}
                                </span>
                            </div>
                            <p class="text-xs" :class="MUTED">
                                {{ user.email }}
                                <span v-if="!user.verified" class="text-amber-600 dark:text-amber-400">
                                    · {{ __('unverified') }}
                                </span>
                            </p>
                        </td>
                        <td class="px-2 py-3">
                            <span class="text-xs font-medium capitalize">{{ user.role }}</span>
                        </td>
                        <td class="px-2 py-3">
                            <span
                                class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                :class="user.status_classes"
                            >
                                {{ user.status_label }}
                            </span>
                        </td>
                        <td class="px-2 py-3 text-end tabular-nums" :class="MUTED">
                            {{ user.expenses_count }}
                        </td>
                        <td class="px-2 py-3">
                            <div class="flex items-center justify-end gap-0.5">
                                <Button
                                    v-if="user.can.update"
                                    variant="ghost"
                                    size="icon-sm"
                                    :aria-label="__('Edit')"
                                    :title="__('Edit')"
                                    @click="openEdit(user)"
                                >
                                    <Pencil class="size-4" />
                                </Button>

                                <!--
                                    Absent rather than disabled when not allowed:
                                    the policy blocks acting on yourself and on the
                                    last admin, and a dead button invites clicking.
                                -->
                                <Button
                                    v-if="user.can.suspend"
                                    variant="ghost"
                                    size="icon-sm"
                                    :disabled="busy === user.uuid"
                                    :aria-label="user.status === 'suspended' ? __('Activate') : __('Suspend')"
                                    :title="user.status === 'suspended' ? __('Activate') : __('Suspend')"
                                    @click="toggleStatus(user)"
                                >
                                    <component
                                        :is="user.status === 'suspended' ? CircleCheck : Ban"
                                        class="size-4"
                                    />
                                </Button>

                                <Button
                                    v-if="user.can.delete"
                                    variant="ghost"
                                    size="icon-sm"
                                    class="text-red-600 hover:text-red-700 dark:text-red-400"
                                    :disabled="busy === user.uuid"
                                    :aria-label="__('Delete')"
                                    :title="__('Delete')"
                                    @click="confirming = user"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ConfirmDialog
            :open="confirming !== null"
            :title="__('Delete this user?')"
            :description="
                confirming
                    ? __(':name and their :count expenses will be permanently deleted. This cannot be undone.', {
                          name: confirming.name,
                          count: confirming.expenses_count,
                      })
                    : ''
            "
            :confirm-label="__('Delete')"
            :processing="busy !== null"
            @confirm="destroy"
            @update:open="(v) => !v && (confirming = null)"
        />

        <Dialog v-model:open="showDialog">
            <DialogContent class="sm:max-w-md">
                <form @submit.prevent="submit">
                    <DialogHeader>
                        <DialogTitle>
                            {{ editing ? __('Edit user') : __('Add user') }}
                        </DialogTitle>
                        <DialogDescription>
                            {{ __('Admins can manage categories, branding and other users.') }}
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-4 py-4">
                        <div>
                            <Label for="user_name">{{ __('Name') }}</Label>
                            <Input id="user_name" v-model="form.name" class="mt-1" autocomplete="off" />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <div>
                            <Label for="user_email">{{ __('Email') }}</Label>
                            <Input id="user_email" v-model="form.email" type="email" class="mt-1" autocomplete="off" />
                            <p v-if="form.errors.email" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.email }}
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <Label for="user_role">{{ __('Role') }}</Label>
                                <Select v-model="form.role">
                                    <SelectTrigger id="user_role" class="mt-1 w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="r in roles" :key="r.value" :value="r.value">
                                            {{ r.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p v-if="form.errors.role" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                    {{ form.errors.role }}
                                </p>
                            </div>

                            <div>
                                <Label for="user_status">{{ __('Status') }}</Label>
                                <Select v-model="form.status">
                                    <SelectTrigger id="user_status" class="mt-1 w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="s in statuses" :key="s.value" :value="s.value">
                                            {{ s.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
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

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showDialog = false">
                            {{ __('Cancel') }}
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ editing ? __('Save') : __('Create') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </SettingsLayout>
</template>
