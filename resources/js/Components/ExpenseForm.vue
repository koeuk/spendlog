<script setup>
import { computed } from 'vue';
import {
    CalendarDate,
    DateFormatter,
    getLocalTimeZone,
    today,
} from '@internationalized/date';
import LocaleTabs from '@/Components/LocaleTabs.vue';
import { categoryColor, categoryIcon } from '@/lib/categoryStyles';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Calendar } from '@/Components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/Components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';

const props = defineProps({
    form: { type: Object, required: true },
    categories: { type: Array, required: true },
});

const formatter = new DateFormatter('en-US', { dateStyle: 'medium' });

// The form holds a plain 'YYYY-MM-DD' string; the Calendar needs a CalendarDate.
const spentOn = computed({
    get() {
        if (!props.form.spent_on) {
            return undefined;
        }
        const [year, month, day] = props.form.spent_on.split('-').map(Number);
        return new CalendarDate(year, month, day);
    },
    set(value) {
        props.form.spent_on = value ? value.toString() : '';
    },
});

const spentOnLabel = computed(() =>
    spentOn.value
        ? formatter.format(spentOn.value.toDate(getLocalTimeZone()))
        : 'Pick a date',
);

// Logging a future expense is rejected server-side; block it in the picker too.
const maxDate = today(getLocalTimeZone());
</script>

<template>
    <div class="grid gap-4">
        <LocaleTabs
            :form="form"
            field="item"
            :placeholders="{ en: 'e.g. Coffee', km: 'ឧ. កាហ្វេ' }"
        >
            <template #label>
                <Label for="item_en">{{ __('Item') }}</Label>
            </template>

            <template #default="{ locale, placeholder, isRequired }">
                <Input
                    :id="`item_${locale}`"
                    v-model="form.item[locale]"
                    autocomplete="off"
                    :placeholder="placeholder"
                    :required="isRequired"
                    :aria-invalid="!!form.errors[`item.${locale}`]"
                />
            </template>
        </LocaleTabs>

        <p v-if="form.errors.item" class="-mt-2 text-sm text-red-600 dark:text-red-400">
            {{ form.errors.item }}
        </p>

        <!--
            Three across once there is room: the dialog is as wide as the list
            behind it, and a stack of full-width fields looks lost in it.
        -->
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <div>
                <Label for="price">{{ __('Price') }}</Label>
                <Input
                    id="price"
                    v-model="form.price"
                    class="mt-1"
                    type="number"
                    step="0.01"
                    min="0"
                    inputmode="decimal"
                    placeholder="0.00"
                />
                <p v-if="form.errors.price" class="mt-1 text-sm text-red-600">
                    {{ form.errors.price }}
                </p>
            </div>

            <div>
                <Label for="category">{{ __('Category') }}</Label>
                <Select v-model="form.category_uuid">
                    <SelectTrigger id="category" class="mt-1 w-full">
                        <SelectValue :placeholder="__('Choose')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="category in categories"
                            :key="category.uuid"
                            :value="category.uuid"
                        >
                            <span class="flex items-center gap-2">
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
                                {{ category.name }}
                            </span>
                        </SelectItem>
                    </SelectContent>
                </Select>
                <p v-if="form.errors.category_uuid" class="mt-1 text-sm text-red-600">
                    {{ form.errors.category_uuid }}
                </p>
            </div>

            <div class="col-span-2 sm:col-span-1">
                <Label>{{ __('Date') }}</Label>
                <Popover>
                    <PopoverTrigger as-child>
                        <Button
                            type="button"
                            variant="outline"
                            class="mt-1 w-full justify-start font-normal"
                        >
                            {{ spentOnLabel }}
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent class="w-auto p-0">
                        <Calendar v-model="spentOn" :max-value="maxDate" initial-focus />
                    </PopoverContent>
                </Popover>
                <p v-if="form.errors.spent_on" class="mt-1 text-sm text-red-600">
                    {{ form.errors.spent_on }}
                </p>
            </div>
        </div>
    </div>
</template>
