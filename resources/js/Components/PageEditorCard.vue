<script setup>
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { CARD, FORM_ACTION, MUTED } from '@/lib/appStyles';

const props = defineProps({
    // { slug, name, title, body, published }
    page: { type: Object, required: true },
});

// Its own form so saving one page never touches another on the screen. Title and
// body are single values, stored under the fallback locale server-side.
const form = useForm({
    title: props.page.title ?? '',
    body: props.page.body ?? '',
    published: props.page.published,
});

function submit() {
    form.patch(route('pages.update', props.page.slug), { preserveScroll: true });
}
</script>

<template>
    <form :class="[CARD, 'space-y-5 p-5']" @submit.prevent="submit">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-neutral-100">{{ page.name }}</h3>

        <div>
            <Label :for="`${page.slug}_title`" class="text-xs" :class="MUTED">{{ __('Title') }}</Label>
            <Input
                :id="`${page.slug}_title`"
                v-model="form.title"
                class="mt-1"
                autocomplete="off"
                :required="form.published"
                :aria-invalid="!!form.errors.title"
            />
            <p v-if="form.errors.title" class="mt-1 text-sm text-red-600 dark:text-red-400">
                {{ form.errors.title }}
            </p>
        </div>

        <div>
            <Label :for="`${page.slug}_body`" class="text-xs" :class="MUTED">{{ __('Body') }}</Label>
            <Textarea
                :id="`${page.slug}_body`"
                v-model="form.body"
                class="mt-1"
                rows="8"
                :required="form.published"
                :aria-invalid="!!form.errors.body"
            />
            <p v-if="form.errors.body" class="mt-1 text-sm text-red-600 dark:text-red-400">
                {{ form.errors.body }}
            </p>
        </div>

        <!-- Save drops below the Published toggle on a phone rather than sharing
             the row with it: side by side, the toggle's two lines of help text
             squeezed the button down to about a third of the width. -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-3">
                <Checkbox
                    :id="`${page.slug}_published`"
                    :model-value="form.published"
                    class="mt-0.5"
                    @update:model-value="form.published = $event"
                />
                <div class="min-w-0">
                    <Label :for="`${page.slug}_published`" class="cursor-pointer text-sm font-medium">
                        {{ __('Published') }}
                    </Label>
                    <p class="text-xs" :class="MUTED">
                        {{ __('When off, the page is hidden and its footer link disappears.') }}
                    </p>
                </div>
            </div>

            <Button type="submit" :disabled="form.processing" :class="FORM_ACTION">
                {{ form.processing ? __('Saving…') : __('Save') }}
            </Button>
        </div>
    </form>
</template>
