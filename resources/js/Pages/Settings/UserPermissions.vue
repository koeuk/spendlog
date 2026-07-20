<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Check, Info, Minus } from 'lucide-vue-next';
import FormScreenLayout from '@/Layouts/FormScreenLayout.vue';
import FormActions from '@/Components/FormActions.vue';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Label } from '@/Components/ui/label';
import { CARD_LIFT, FORM_ACTION, MUTED } from '@/lib/appStyles';
import { trans } from '@/lib/i18n';

/**
 * The permissions editor, promoted from a right-side sheet to its own screen.
 *
 * Twenty-nine checkboxes across seven groups is roughly 1900px of content. As a
 * sheet that was a scroller inside a modal, with the granted count and the Save
 * button pinned in a footer the list slid under — on a phone the only way to
 * check your work was to scroll a panel inside a page.
 *
 * Seeded straight from props rather than through a watch: a route hands over one
 * user per visit, so there is no case where the record changes underneath.
 */
const props = defineProps({
    user: { type: Object, required: true },
    permission_groups: { type: Object, required: true },
});

const form = useForm({
    permissions: [...(props.user.direct_permissions ?? [])],
});

function toggle(value, checked) {
    form.permissions = checked
        ? [...new Set([...form.permissions, value])]
        : form.permissions.filter((p) => p !== value);
}

function isChecked(value) {
    return form.permissions.includes(value);
}

const grantedCount = computed(() => form.permissions.length);

// Per-group, for the count on each panel header. Reads form.permissions, so it
// re-evaluates as boxes are ticked without needing a computed per group.
function groupGrantedCount(items) {
    return items.filter((item) => form.permissions.includes(item.value)).length;
}

/**
 * The group's own tick: on when every item is granted, 'indeterminate' when only
 * some are.
 *
 * The middle state is the point. Without it a half-granted group shows an empty
 * box, which reads as "nothing here is on" — the opposite of the truth, and the
 * one state a permissions screen must not misreport.
 */
function groupState(items) {
    const granted = groupGrantedCount(items);

    if (granted === 0) {
        return false;
    }

    return granted === items.length ? true : 'indeterminate';
}

/**
 * Grant or revoke a whole group.
 *
 * Reka hands back `true` from an indeterminate box, so a partly-granted group
 * fills rather than empties — which is what someone reaching for a half-ticked
 * box means by it.
 */
function toggleGroup(items, checked) {
    const values = items.map((item) => item.value);

    form.permissions = checked
        ? [...new Set([...form.permissions, ...values])]
        : form.permissions.filter((p) => !values.includes(p));
}
const totalCount = computed(() =>
    Object.values(props.permission_groups).reduce((n, items) => n + items.length, 0),
);

const backHref = route('users.index');

function submit() {
    form.put(route('users.permissions', props.user.uuid));
}
</script>

<template>
    <Head :title="trans('Permissions')" />

    <FormScreenLayout
        :back-href="backHref"
        :title="__('Permissions')"
        :back-label="__('Back to users')"
    >
        <form class="flex flex-1 flex-col" @submit.prevent="submit">
            <div
                class="mb-5 flex items-start gap-2 rounded-2xl bg-neutral-50 p-3 text-xs dark:bg-neutral-800/60"
                :class="MUTED"
            >
                <Info class="mt-0.5 size-3.5 shrink-0" />
                <p>
                    {{ __('Ticked by default from the :role role. Change any of them for this person only — the role is just the starting point.', { role: user.role }) }}
                </p>
            </div>

            <!-- The count moves out of the footer and into the body: the action
                 bar is fixed to the viewport now, and a running total belongs
                 with the list it counts rather than under the buttons. -->
            <p class="mb-4 text-sm font-medium">
                {{ __('What :name can do beyond their own records.', { name: user.name }) }}
                <span class="ms-1 text-xs font-normal" :class="MUTED">
                    {{ __(':count of :total granted', { count: grantedCount, total: totalCount }) }}
                </span>
            </p>

            <!--
                Two columns of panels, not one long list.

                Twenty-nine checkboxes in seven groups ran about 1900px, so the
                last group sat three screens below the first and the only thing
                separating one from the next was a line of small grey caps.
                Boxing each group gives the header something to title, and the
                second column halves the scroll.

                CSS columns rather than a grid: the groups are 2 to 6 items, so
                fixed grid cells would leave a tall gap under every short one.
                Columns fill by height and balance themselves — with
                break-inside-avoid, since a permission group split across a
                column boundary would read as two unrelated half-groups.
            -->
            <div class="gap-4 lg:columns-2">
                <div
                    v-for="(items, group) in permission_groups"
                    :key="group"
                    :class="[
                        CARD_LIFT,
                        'mb-4 break-inside-avoid rounded-2xl border border-border bg-card/40 p-4',
                    ]"
                >
                    <!--
                        A panel heading now, not a label floating above a list,
                        so it carries the group's own count: with the groups
                        boxed, "3 of 6" answers "did I miss one in here?" without
                        counting ticks.
                    -->
                    <div class="mb-3 flex items-center gap-2">
                        <Checkbox
                            :id="`group_${group}`"
                            :model-value="groupState(items)"
                            :aria-label="__('Grant everything in :group', { group })"
                            @update:model-value="toggleGroup(items, $event)"
                        >
                            <!-- A dash for the middle state. The default slot is
                                 the tick, so without this a partly-granted group
                                 would wear the same mark as a fully granted one. -->
                            <Minus v-if="groupState(items) === 'indeterminate'" class="size-3.5" />
                            <Check v-else class="size-3.5" />
                        </Checkbox>

                        <Label
                            :for="`group_${group}`"
                            class="cursor-pointer text-[11px] font-semibold uppercase tracking-[0.12em] text-muted-foreground"
                        >
                            {{ group }}
                        </Label>

                        <span class="ms-auto text-[11px] font-medium tabular-nums" :class="MUTED">
                            {{ groupGrantedCount(items) }}/{{ items.length }}
                        </span>
                    </div>

                    <div class="space-y-3">
                        <div v-for="item in items" :key="item.value" class="flex items-start gap-3">
                            <Checkbox
                                :id="`perm_${item.value}`"
                                :model-value="isChecked(item.value)"
                                class="mt-0.5"
                                @update:model-value="toggle(item.value, $event)"
                            />
                            <div class="min-w-0">
                                <Label
                                    :for="`perm_${item.value}`"
                                    class="cursor-pointer text-sm font-medium"
                                >
                                    {{ item.label }}
                                </Label>
                                <p class="text-xs" :class="MUTED">{{ item.description }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <FormActions>
                <template #cancel>
                    <Button :as="Link" :href="backHref" variant="outline" :class="FORM_ACTION">
                        {{ __('Cancel') }}
                    </Button>
                </template>

                <template #submit>
                    <Button type="submit" :disabled="form.processing" :class="FORM_ACTION">
                        {{ form.processing ? __('Saving…') : __('Save') }}
                    </Button>
                </template>
            </FormActions>
        </form>
    </FormScreenLayout>
</template>
