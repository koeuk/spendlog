<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
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
});

const showDialog = ref(false);
const editing = ref(null);

const form = useForm({ name: '', color: 'slate', icon: null });

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showDialog.value = true;
}

function openEdit(category) {
    editing.value = category;
    form.name = category.name;
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

function destroy(category) {
    deleting.value = category.uuid;
    deleteForm.delete(route('categories.destroy', category.uuid), {
        preserveScroll: true,
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
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Categories
                </h2>
                <Button v-if="can.manage" size="sm" @click="openCreate">
                    Add category
                </Button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead class="text-right">Expenses</TableHead>
                                <TableHead v-if="can.manage" class="w-32 text-right">
                                    Actions
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="!categories.length">
                                <TableCell
                                    :colspan="can.manage ? 3 : 2"
                                    class="py-10 text-center text-sm text-gray-500"
                                >
                                    No categories yet.
                                </TableCell>
                            </TableRow>
                            <TableRow v-for="category in categories" :key="category.uuid">
                                <TableCell class="font-medium">
                                    <span class="flex items-center gap-2.5">
                                        <span
                                            class="flex size-7 shrink-0 items-center justify-center rounded-full ring-1 ring-inset"
                                            :class="categoryColor(category.color).badge"
                                        >
                                            <component
                                                :is="categoryIcon(category.icon)"
                                                v-if="categoryIcon(category.icon)"
                                                class="size-4"
                                            />
                                            <span
                                                v-else
                                                class="size-2 rounded-full"
                                                :class="categoryColor(category.color).dot"
                                            />
                                        </span>
                                        {{ category.name }}
                                    </span>
                                </TableCell>
                                <TableCell class="text-right text-gray-500">
                                    {{ category.expenses_count }}
                                </TableCell>
                                <TableCell v-if="can.manage" class="text-right">
                                    <div class="flex justify-end gap-1">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="openEdit(category)"
                                        >
                                            Edit
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="text-red-600 hover:text-red-700"
                                            :disabled="deleting === category.uuid"
                                            @click="destroy(category)"
                                        >
                                            Delete
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>
        </div>

        <Dialog v-model:open="showDialog">
            <DialogContent class="sm:max-w-md">
                <form @submit.prevent="submit">
                    <DialogHeader>
                        <DialogTitle>
                            {{ editing ? 'Edit category' : 'New category' }}
                        </DialogTitle>
                        <DialogDescription>
                            Categories are shared by everyone logging expenses.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-5 py-4">
                        <div>
                            <Label for="name">Name</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                class="mt-1"
                                autocomplete="off"
                                placeholder="e.g. Groceries"
                            />
                            <p v-if="form.errors.name" class="mt-2 text-sm text-red-600">
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <div>
                            <Label>Colour</Label>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button
                                    v-for="name in CATEGORY_COLOR_NAMES"
                                    :key="name"
                                    type="button"
                                    class="size-7 rounded-full ring-offset-2 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400"
                                    :class="[
                                        categoryColor(name).dot,
                                        form.color === name
                                            ? 'ring-2 ring-gray-900'
                                            : 'ring-1 ring-black/10 hover:ring-gray-400',
                                    ]"
                                    :aria-label="name"
                                    :aria-pressed="form.color === name"
                                    @click="form.color = name"
                                />
                            </div>
                            <p v-if="form.errors.color" class="mt-2 text-sm text-red-600">
                                {{ form.errors.color }}
                            </p>
                        </div>

                        <div>
                            <Label>
                                Icon
                                <span class="font-normal text-gray-500">(optional)</span>
                            </Label>
                            <div class="mt-2 grid grid-cols-8 gap-1.5">
                                <button
                                    v-for="name in CATEGORY_ICON_NAMES"
                                    :key="name"
                                    type="button"
                                    class="flex aspect-square items-center justify-center rounded-md border transition focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400"
                                    :class="
                                        form.icon === name
                                            ? 'border-gray-900 bg-gray-900 text-white'
                                            : 'border-gray-200 text-gray-600 hover:border-gray-400 hover:bg-gray-50'
                                    "
                                    :aria-label="name"
                                    :aria-pressed="form.icon === name"
                                    @click="toggleIcon(name)"
                                >
                                    <component :is="categoryIcon(name)" class="size-4" />
                                </button>
                            </div>
                            <p v-if="form.errors.icon" class="mt-2 text-sm text-red-600">
                                {{ form.errors.icon }}
                            </p>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showDialog = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ editing ? 'Save' : 'Create' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>
