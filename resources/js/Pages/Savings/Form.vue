<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import FormScreenLayout from '@/Layouts/FormScreenLayout.vue';
import AmountField from '@/Components/AmountField.vue';
import DateField from '@/Components/DateField.vue';
import FormActions from '@/Components/FormActions.vue';
import { categoryColor } from '@/lib/categoryStyles';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { trans } from '@/lib/i18n';

/**
 * Create/edit a savings goal on its own screen — the deadline opens a
 * calendar, which a dialog cannot hold on a phone.
 */
const props = defineProps({
    // The goal being edited; absent when creating.
    goal: { type: Object, default: null },
    // The palette names, mirroring App\Enums\CategoryColor.
    colors: { type: Array, required: true },
});

const editing = !!props.goal;

const defaultCurrency = usePage().props.default_currency ?? 'USD';

const form = useForm({
    name: props.goal?.name ?? '',
    target_amount: editing ? String(props.goal.target_amount) : '',
    // Stored in USD whatever it was typed in, so editing starts from USD.
    currency: editing ? 'USD' : defaultCurrency,
    deadline: props.goal?.deadline ?? '',
    color: props.goal?.color ?? props.colors[0],
});

// Back to the goal when editing it, to the list when there is no goal yet.
const backHref = editing ? route('savings.show', props.goal.uuid) : route('savings.index');

function submit() {
    if (editing) {
        form.put(route('savings.update', props.goal.uuid));
    } else {
        form.post(route('savings.store'));
    }
}
</script>

<template>
    <Head :title="editing ? trans('Edit goal') : trans('New goal')" />

    <FormScreenLayout
        :back-href="backHref"
        :title="editing ? __('Edit goal') : __('New goal')"
        :back-label="editing ? __('Back to goal') : __('Back to savings')"
    >
        <form class="flex flex-1 flex-col" @submit.prevent="submit">
            <div class="grid gap-4">
                <div>
                    <Label for="name">{{ __('Goal name') }}</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        class="mt-1"
                        autocomplete="off"
                        :placeholder="__('e.g. Emergency fund')"
                        required
                        :aria-invalid="!!form.errors.name"
                    />
                    <p
                        v-if="form.errors.name"
                        class="mt-1 text-sm text-red-600 dark:text-red-400"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>

                <AmountField :form="form" field="target_amount" :label="__('Target')" />

                <div>
                    <div class="flex items-center justify-between gap-2">
                        <Label>
                            {{ __('Deadline') }}
                            <span class="font-normal text-muted-foreground">({{ __('optional') }})</span>
                        </Label>
                        <!-- Deadlines are optional, and a calendar has no
                             "none" cell, so clearing is its own control. -->
                        <button
                            v-if="form.deadline"
                            type="button"
                            class="text-xs font-medium text-muted-foreground hover:text-foreground"
                            @click="form.deadline = ''"
                        >
                            {{ __('No deadline') }}
                        </button>
                    </div>
                    <!-- A new goal cannot already be overdue; an existing one
                         may be, so the edit form leaves the past open. -->
                    <DateField v-model="form.deadline" :no-past="!editing" :placeholder="__('No deadline')" />
                    <p
                        v-if="form.errors.deadline"
                        class="mt-1 text-sm text-red-600 dark:text-red-400"
                    >
                        {{ form.errors.deadline }}
                    </p>
                </div>

                <div>
                    <Label>{{ __('Colour') }}</Label>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button
                            v-for="name in colors"
                            :key="name"
                            type="button"
                            class="size-7 rounded-full ring-offset-2 ring-offset-background transition focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            :class="[
                                categoryColor(name).dot,
                                form.color === name
                                    ? 'ring-2 ring-foreground'
                                    : 'ring-1 ring-border hover:ring-foreground/40',
                            ]"
                            :aria-label="name"
                            :aria-pressed="form.color === name"
                            @click="form.color = name"
                        />
                    </div>
                    <p
                        v-if="form.errors.color"
                        class="mt-2 text-sm text-red-600 dark:text-red-400"
                    >
                        {{ form.errors.color }}
                    </p>
                </div>
            </div>

            <FormActions>
                <template #cancel>
                    <Button
                        :as="Link"
                        :href="backHref"
                        variant="outline"
                        class="w-full rounded-xl max-sm:h-12 sm:w-auto"
                    >
                        {{ __('Cancel') }}
                    </Button>
                </template>

                <template #submit>
                    <Button type="submit" :disabled="form.processing" class="w-full rounded-xl max-sm:h-12 sm:w-auto">
                        {{ form.processing ? __('Saving…') : __('Save') }}
                    </Button>
                </template>
            </FormActions>
        </form>
    </FormScreenLayout>
</template>
