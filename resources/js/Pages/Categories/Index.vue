<script setup>
import { ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CategoryBadge from '@/Components/CategoryBadge.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import SearchInput from '@/Components/SearchInput.vue';
import { CARD } from '@/lib/appStyles';
import CategoryStylePicker from '@/Components/CategoryStylePicker.vue';
import LocaleTabs from '@/Components/LocaleTabs.vue';
import { localized } from '@/lib/i18n';
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';

const props = defineProps({
    categories: { type: Array, required: true },
    can: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

// Seeded from the URL so a shared or reloaded link shows its own search term.
const search = ref(props.filters?.filter?.name ?? '');

watch(search, (value) => {
    router.get(
        route('categories.index'),
        value ? { filter: { name: value } } : {},
        // preserveState keeps the box focused mid-typing; replace keeps every
        // keystroke out of the back button.
        { preserveState: true, preserveScroll: true, replace: true },
    );
});

const showDialog = ref(false);
const editing = ref(null);

const form = useForm({
    name: { en: '', km: '' },
    color: 'slate',
    icon: null,
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showDialog.value = true;
}

function openEdit(category) {
    editing.value = category;
    // name is the raw JSON field, so both inputs read straight from it.
    form.name = {
        en: category.name?.en ?? '',
        km: category.name?.km ?? '',
    };
    form.color = category.color;
    form.icon = category.icon;
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
        form.put(route('categories.update', editing.value.uuid), options);
    } else {
        form.post(route('categories.store'), options);
    }
}

const deleting = ref(null);
const deleteForm = useForm({});

// The category awaiting confirmation. Holding the row itself, not just a flag,
// lets the prompt name what is about to go.
const confirming = ref(null);

function confirmDestroy(category) {
    confirming.value = category;
}

function destroy() {
    const category = confirming.value;

    if (!category) {
        return;
    }

    deleting.value = category.uuid;

    deleteForm.delete(route('categories.destroy', category.uuid), {
        preserveScroll: true,
        // Closed here rather than on click: a category still in use comes back
        // 409 with a flash explaining why, and dismissing the prompt first would
        // leave the row sitting there with no visible reason.
        onSuccess: () => {
            confirming.value = null;
        },
        onFinish: () => {
            deleting.value = null;
        },
    });
}
</script>

<template>
    <Head title="Categories" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-neutral-100">
                    {{ __('Categories') }}
                </h2>
                <Button v-if="can.manage" size="sm" @click="openCreate">
                    {{ __('Add category') }}
                </Button>
            </div>
        </template>

        <div class="py-8">
            <!-- Width and gutters come from the layout's one container, so the
                 column never resizes when navigating between pages. -->
            <div>
                <div class="mb-4 sm:max-w-sm">
                    <SearchInput
                        v-model="search"
                        :placeholder="__('Search categories…')"
                    />
                </div>

                <!--
                    The card is 28px-rounded, so a flush table crowds its corners.
                    Padding here plus roomier cells keeps the content clear of the
                    curve without the rows losing their full-width dividers.
                -->
                <div :class="[CARD, 'overflow-hidden p-2 sm:p-3']">
                    <Table class="[&_td]:px-3 [&_th]:px-3 [&_tr:last-child]:border-0">
                        <TableHeader>
                            <TableRow>
                                <TableHead>{{ __('Name') }}</TableHead>
                                <TableHead class="text-right">{{ __('Expenses') }}</TableHead>
                                <TableHead v-if="can.manage" class="w-32 text-right">
                                    {{ __('Actions') }}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="!categories.length">
                                <TableCell
                                    :colspan="can.manage ? 3 : 2"
                                    class="py-10 text-center text-sm text-gray-500 dark:text-neutral-400"
                                >
                                    {{ search ? __('No categories match your search.') : __('No categories yet.') }}
                                </TableCell>
                            </TableRow>
                            <TableRow v-for="category in categories" :key="category.uuid">
                                <TableCell>
                                    <CategoryBadge
                                        :name="localized(category.name)"
                                        :color="category.color"
                                        :icon="category.icon"
                                    />
                                </TableCell>
                                <TableCell class="text-right text-gray-500 dark:text-neutral-400">
                                    {{ category.expenses_count }}
                                </TableCell>
                                <TableCell v-if="can.manage" class="text-right">
                                    <div class="flex justify-end gap-1">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="openEdit(category)"
                                        >
                                            {{ __('Edit') }}
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300"
                                            :disabled="deleting === category.uuid"
                                            @click="confirmDestroy(category)"
                                        >
                                            {{ __('Delete') }}
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>
        </div>

        <ConfirmDialog
            :open="confirming !== null"
            :title="__('Delete this category?')"
            :description="
                confirming
                    ? __('&quot;:name&quot; will be removed for everyone. This cannot be undone.', {
                          name: localized(confirming.name),
                      })
                    : ''
            "
            :confirm-label="__('Delete')"
            :cancel-label="__('Cancel')"
            :processing="deleting !== null"
            :processing-label="__('Deleting…')"
            @update:open="confirming = $event ? confirming : null"
            @confirm="destroy"
        />

        <Dialog v-model:open="showDialog">
            <DialogContent class="sm:max-w-md">
                <form @submit.prevent="submit">
                    <DialogHeader>
                        <DialogTitle>
                            {{ editing ? __('Edit category') : __('New category') }}
                        </DialogTitle>
                        <DialogDescription>
                            {{ __('Categories are shared by everyone logging expenses.') }}
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-4 py-4">
                        <LocaleTabs
                            :form="form"
                            field="name"
                            :placeholders="{ en: 'e.g. Groceries', km: 'ឧ. គ្រឿងទេស' }"
                        >
                            <template #label>
                                <Label for="name_en">{{ __('Name') }}</Label>
                            </template>

                            <template #default="{ locale, placeholder, isRequired }">
                                <Input
                                    :id="`name_${locale}`"
                                    v-model="form.name[locale]"
                                    autocomplete="off"
                                    :placeholder="placeholder"
                                    :required="isRequired"
                                    :aria-invalid="!!form.errors[`name.${locale}`]"
                                />
                            </template>
                        </LocaleTabs>

                        <CategoryStylePicker :form="form" />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showDialog = false"
                        >
                            {{ __('Cancel') }}
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ editing ? __('Save') : __('Create') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>
