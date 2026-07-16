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

const form = useForm({ name: '' });

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showDialog.value = true;
}

function openEdit(category) {
    editing.value = category;
    form.name = category.name;
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
                                    {{ category.name }}
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

                    <div class="py-4">
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
