<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { KeyRound, MoreHorizontal, Pencil, Plus, ShieldCheck, Trash2 } from 'lucide-vue-next';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import Pagination from '@/Components/Pagination.vue';
import UserStatusDialog from '@/Components/UserStatusDialog.vue';
import { Button } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { MUTED, SETTINGS_ACTION } from '@/lib/appStyles';
import { trans } from '@/lib/i18n';

const props = defineProps({
    users: { type: Array, required: true },
    pagination: { type: Object, required: true },
    statuses: { type: Array, required: true },
    can: { type: Object, required: true },
});

const busy = ref(null);

// The row whose status dialog is open, or null.
const statusFor = ref(null);

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

// An empty menu is worse than no menu — the policy can hide every entry.
function hasActions(user) {
    return (
        user.can.update ||
        user.can.manage_permissions ||
        user.can.suspend ||
        user.can.delete
    );
}

</script>

<template>
    <Head title="Users" />

    <SettingsLayout
        :heading="trans('Users')"
        :description="trans('Who can sign in, and what they can do.')"
    >
        <template #actions>
            <Button v-if="can.create" :as="Link" :href="route('users.create')" size="sm" :class="SETTINGS_ACTION">
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
                            <!-- Labelled server-side: `capitalize` on the raw
                                 value would render "Super_admin". -->
                            <span class="text-xs font-medium">{{ user.role_label }}</span>
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
                        <td class="px-2 py-3 text-end">
                            <!--
                                One menu rather than a row of icons: four glyphs
                                per row read as noise, and the actions differ per
                                row anyway (the policy hides some), so a fixed
                                strip of buttons never lines up between rows.
                            -->
                            <DropdownMenu v-if="hasActions(user)">
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        :disabled="busy === user.uuid"
                                        :aria-label="__('Actions for :name', { name: user.name })"
                                    >
                                        <MoreHorizontal class="size-4" />
                                    </Button>
                                </DropdownMenuTrigger>

                                <DropdownMenuContent align="end" class="w-48">
                                    <DropdownMenuItem
                                        v-if="user.can.update"
                                        @select="router.get(route('users.edit', user.uuid))"
                                    >
                                        <Pencil class="size-4" />
                                        {{ __('Edit') }}
                                    </DropdownMenuItem>

                                    <DropdownMenuItem
                                        v-if="user.can.manage_permissions"
                                        @select="router.get(route('users.permissions.edit', user.uuid))"
                                    >
                                        <KeyRound class="size-4" />
                                        {{ __('Permissions') }}
                                    </DropdownMenuItem>

                                    <DropdownMenuItem
                                        v-if="user.can.suspend"
                                        @select="statusFor = user"
                                    >
                                        <ShieldCheck class="size-4" />
                                        {{ __('Change status') }}
                                    </DropdownMenuItem>

                                    <template v-if="user.can.delete">
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            class="text-red-600 focus:text-red-700 dark:text-red-400"
                                            @select="confirming = user"
                                        >
                                            <Trash2 class="size-4" />
                                            {{ __('Delete') }}
                                        </DropdownMenuItem>
                                    </template>
                                </DropdownMenuContent>
                            </DropdownMenu>

                            <!-- Your own row: every action is blocked by policy,
                                 so say why instead of showing an empty menu. -->
                            <span v-else class="text-xs" :class="MUTED">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Flush to the card's edges: cancels the panel's p-6/p-8 so the
             pager's hairline runs the full card width and the card ends at the
             pager instead of a band of padding under it. -->
        <div class="-mx-6 py-6 sm:-mx-8 sm:-mb-8">
            <Pagination :meta="pagination" />
        </div>

        <UserStatusDialog
            :user="statusFor"
            :statuses="statuses"
            @close="statusFor = null"
        />

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

    </SettingsLayout>
</template>
