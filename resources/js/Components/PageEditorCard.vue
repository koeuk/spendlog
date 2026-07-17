<script setup>
import { useForm } from '@inertiajs/vue3';
import LocaleTabs from '@/Components/LocaleTabs.vue';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { CARD, MUTED } from '@/lib/appStyles';

const props = defineProps({
    // { slug, name, title: {en, km}, body: {en, km}, published }
    page: { type: Object, required: true },
});

// Its own form so saving one page never touches another on the screen.
const form = useForm({
    title: { en: props.page.title?.en ?? '', km: props.page.title?.km ?? '' },
    body: { en: props.page.body?.en ?? '', km: props.page.body?.km ?? '' },
    published: props.page.published,
});

function submit() {
    form.patch(route('pages.update', props.page.slug), { preserveScroll: true });
}
</script>

<template>
    <form :class="[CARD, 'space-y-5 p-5']" @submit.prevent="submit">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-neutral-100">{{ page.name }}</h3>

        <LocaleTabs :form="form" field="title">
            <template #label>
                <Label class="text-xs" :class="MUTED">{{ __('Title') }}</Label>
            </template>
            <template #default="{ locale, isRequired }">
                <Input
                    :id="`${page.slug}_title_${locale}`"
                    v-model="form.title[locale]"
                    autocomplete="off"
                    :required="isRequired && form.published"
                    :aria-invalid="!!form.errors[`title.${locale}`]"
                />
            </template>
        </LocaleTabs>

        <LocaleTabs :form="form" field="body">
            <template #label>
                <Label class="text-xs" :class="MUTED">{{ __('Body') }}</Label>
            </template>
            <template #default="{ locale, isRequired }">
                <Textarea
                    :id="`${page.slug}_body_${locale}`"
                    v-model="form.body[locale]"
                    rows="8"
                    :required="isRequired && form.published"
                    :aria-invalid="!!form.errors[`body.${locale}`]"
                />
            </template>
        </LocaleTabs>

        <div class="flex items-center justify-between gap-4">
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

            <Button type="submit" :disabled="form.processing">
                {{ form.processing ? __('Saving…') : __('Save') }}
            </Button>
        </div>
    </form>
</template>
