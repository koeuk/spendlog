<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Clock } from 'lucide-vue-next';
import { CARD, EYEBROW, MUTED } from '@/lib/appStyles';

/**
 * The time of day, for a page you look at mid-session with your hands full.
 */
const page = usePage();

/*
 * Re-reads the clock every second rather than incrementing a counter. A
 * background tab throttles setInterval to about once a minute, and a counter
 * would drift by exactly the time spent away — the clock has to be right the
 * moment you look back at it.
 */
const now = ref(new Date());
const handle = setInterval(() => (now.value = new Date()), 1000);

onBeforeUnmount(() => clearInterval(handle));

const locale = computed(() => (page.props.locale === 'km' ? 'km-KH' : 'en-GB'));

// Built once per locale, not once per tick: Intl formatters are expensive to
// construct and this renders every second.
const timeFormat = computed(
    () => new Intl.DateTimeFormat(locale.value, {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }),
);

const dateFormat = computed(
    () => new Intl.DateTimeFormat(locale.value, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    }),
);

const time = computed(() => timeFormat.value.format(now.value));
const date = computed(() => dateFormat.value.format(now.value));
</script>

<template>
    <div :class="[CARD, 'anim flex items-center gap-4 p-6']">
        <span class="grid size-12 shrink-0 place-items-center rounded-full bg-primary/15">
            <Clock class="size-6 text-primary" aria-hidden="true" />
        </span>

        <div class="min-w-0">
            <p :class="EYEBROW">{{ __('Now') }}</p>
            <p class="mt-0.5 text-3xl font-extrabold tabular-nums tracking-tight">
                {{ time }}
            </p>
            <p class="mt-0.5 truncate text-xs font-semibold" :class="MUTED">{{ date }}</p>
        </div>
    </div>
</template>
