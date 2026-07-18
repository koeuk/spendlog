<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
    CalendarDate,
    DateFormatter,
    getLocalTimeZone,
    today,
} from '@internationalized/date';
import CategoryPicker from '@/Components/CategoryPicker.vue';
import CurrencyToggle from '@/Components/CurrencyToggle.vue';
import LocaleTabs from '@/Components/LocaleTabs.vue';
import { categoryColor, categoryIcon } from '@/lib/categoryStyles';
import { MUTED } from '@/lib/appStyles';
import { trans } from '@/lib/i18n';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Calendar } from '@/Components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/Components/ui/popover';

const props = defineProps({
    form: { type: Object, required: true },
    categories: { type: Array, required: true },
    // Mirrors CategoryPolicy::create, passed down from the page.
    canCreateCategory: { type: Boolean, default: false },
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

const khrPerUsd = computed(() => Number(usePage().props.khr_per_usd) || 4100);

const usd = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
});

/**
 * What a riel amount will actually be stored as.
 *
 * Mirrors App\Enums\Currency::toUsd — same divisor, same rounding — so the hint
 * matches the row that gets written. Nothing to say for a USD price: it is
 * stored exactly as typed.
 */
const convertedPreview = computed(() => {
    if (props.form.currency !== 'KHR') {
        return '';
    }

    const amount = Number(props.form.price);

    if (!Number.isFinite(amount) || amount <= 0) {
        return trans('Entered in riel, stored in US dollars.');
    }

    return trans('Stored as :amount', {
        amount: usd.format(Math.round((amount / khrPerUsd.value) * 100) / 100),
    });
});
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

        <p
            v-if="form.errors.item"
            class="-mt-2 text-sm text-red-600 dark:text-red-400"
        >
            {{ form.errors.item }}
        </p>

        <!--
            Price gets its own row. Sharing one with Category and Date left it a
            third as wide while carrying the most in it — a label, a two-option
            currency toggle, the number and a conversion hint — so the toggle
            crowded the label and the hint wrapped.
        -->
        <div>
            <div class="flex items-center justify-between gap-2">
                <Label for="price">{{ __('Price') }}</Label>

                <CurrencyToggle v-model="form.currency" />
            </div>

            <Input
                id="price"
                v-model="form.price"
                class="mt-1"
                type="number"
                :step="form.currency === 'KHR' ? '100' : '0.01'"
                min="0"
                inputmode="decimal"
                :placeholder="form.currency === 'KHR' ? '0' : '0.00'"
                :aria-invalid="!!form.errors.price"
            />

            <!-- Only stored amounts are USD, so say what a riel figure will
                     become before it is saved rather than after. -->
            <p v-if="convertedPreview" class="mt-1 text-xs" :class="MUTED">
                {{ convertedPreview }}
            </p>

            <p
                v-if="form.errors.price"
                class="mt-1 text-sm text-red-600 dark:text-red-400"
            >
                {{ form.errors.price }}
            </p>
        </div>

        <!-- Category and Date still share a row: both are a single control with
             a short label, and neither grows. -->
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <Label for="category">{{ __('Category') }}</Label>
                <CategoryPicker
                    :form="form"
                    :categories="categories"
                    :can-create="canCreateCategory"
                />
            </div>

            <div>
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
                        <Calendar
                            v-model="spentOn"
                            :max-value="maxDate"
                            initial-focus
                        />
                    </PopoverContent>
                </Popover>
                <p
                    v-if="form.errors.spent_on"
                    class="mt-1 text-sm text-red-600"
                >
                    {{ form.errors.spent_on }}
                </p>
            </div>
        </div>
    </div>
</template>
