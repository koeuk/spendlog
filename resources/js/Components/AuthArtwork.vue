<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useTheme } from '@/composables/useTheme';

const props = defineProps({
    // The dots below are real controls, so every page passes its own copy.
    slides: { type: Array, required: true },
});

const { isDark } = useTheme();

const slide = ref(0);
let timer = null;

function start() {
    stop();
    timer = setInterval(() => {
        slide.value = (slide.value + 1) % props.slides.length;
    }, 5000);
}

function stop() {
    if (timer) clearInterval(timer);
    timer = null;
}

function goTo(index) {
    slide.value = index;
    start();
}

onMounted(start);
onBeforeUnmount(stop);

const rows = [
    { dot: 'bg-orange-400', name: 'Lunch', cat: 'Food', amt: '$12.50' },
    { dot: 'bg-blue-400', name: 'Tuk-tuk', cat: 'Transport', amt: '$3.70' },
    { dot: 'bg-purple-400', name: 'Headphones', cat: 'Shopping', amt: '$18.00' },
];
</script>

<template>
    <div
        class="relative hidden overflow-hidden rounded-[28px] bg-[#f1f7ef] lg:flex lg:flex-col lg:items-center lg:justify-center dark:bg-[#0f1a12]"
    >
        <svg
            class="pointer-events-none absolute inset-0 size-full"
            viewBox="0 0 400 400"
            fill="none"
            aria-hidden="true"
        >
            <circle cx="330" cy="80" r="52" :fill="isDark ? '#16281a' : '#e6f2e3'" />
            <circle cx="64" cy="312" r="66" :fill="isDark ? '#16281a' : '#e6f2e3'" />
            <path
                d="M112 128C112 88 160 68 192 92c32 24 80 4 80-36"
                :stroke="isDark ? '#2f5c3a' : '#bcdcc0'"
                stroke-width="2"
                stroke-linecap="round"
            />
        </svg>

        <div class="relative flex flex-col items-center px-10">
            <div class="w-[280px] rotate-[-3deg] rounded-3xl bg-white p-5 shadow-[0_18px_40px_-12px_rgba(31,64,38,0.18)] dark:bg-neutral-900 dark:shadow-[0_18px_40px_-12px_rgba(0,0,0,0.6)]">
                <div class="flex items-baseline justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-[0.14em] text-neutral-400 dark:text-neutral-500">
                        Today
                    </span>
                    <span class="text-lg font-extrabold tracking-tight tabular-nums">$34.20</span>
                </div>

                <div class="mt-4 space-y-3">
                    <div v-for="row in rows" :key="row.name" class="flex items-center gap-3">
                        <span class="size-2 shrink-0 rounded-full" :class="row.dot" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[13px] font-semibold leading-tight">
                                {{ row.name }}
                            </p>
                            <p class="text-[11px] text-neutral-400 dark:text-neutral-500">{{ row.cat }}</p>
                        </div>
                        <span class="text-[13px] font-bold tabular-nums">{{ row.amt }}</span>
                    </div>
                </div>
            </div>

            <div class="-mt-6 ml-40 flex items-center gap-3 rounded-2xl bg-white px-4 py-3 shadow-[0_14px_30px_-10px_rgba(31,64,38,0.22)] dark:bg-neutral-900 dark:shadow-[0_14px_30px_-10px_rgba(0,0,0,0.6)]">
                <div class="relative grid size-11 place-items-center">
                    <svg class="size-11 -rotate-90" viewBox="0 0 44 44" aria-hidden="true">
                        <circle cx="22" cy="22" r="18" fill="none" :stroke="isDark ? '#25352a' : '#e8f0e6'" stroke-width="5" />
                        <circle
                            cx="22"
                            cy="22"
                            r="18"
                            fill="none"
                            stroke="#5fae72"
                            stroke-width="5"
                            stroke-linecap="round"
                            stroke-dasharray="113.1"
                            stroke-dashoffset="18.1"
                        />
                    </svg>
                    <span class="absolute text-[10px] font-extrabold">84%</span>
                </div>
                <div>
                    <p class="text-[12px] font-bold leading-tight">Food budget</p>
                    <p class="text-[11px] text-neutral-400 tabular-nums dark:text-neutral-500">$168 of $200</p>
                </div>
            </div>

            <div class="mt-14 flex h-16 max-w-[340px] items-start justify-center">
                <Transition name="fade" mode="out-in">
                    <p :key="slide" class="text-center text-[22px] font-bold leading-snug tracking-[-0.02em]">
                        {{ slides[slide] }}
                    </p>
                </Transition>
            </div>

            <div class="mt-2 flex items-center gap-1.5">
                <button
                    v-for="(s, i) in slides"
                    :key="i"
                    type="button"
                    :aria-label="`Slide ${i + 1}`"
                    :aria-current="slide === i"
                    class="h-1.5 rounded-full transition-all duration-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900"
                    :class="slide === i ? 'w-6 bg-neutral-900 dark:bg-neutral-100' : 'w-1.5 bg-neutral-300 hover:bg-neutral-400 dark:bg-neutral-700 dark:hover:bg-neutral-600'"
                    @click="goTo(i)"
                />
            </div>
        </div>
    </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.35s ease, transform 0.35s ease;
}

.fade-enter-from {
    opacity: 0;
    transform: translateY(6px);
}

.fade-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}

@media (prefers-reduced-motion: reduce) {
    .fade-enter-active,
    .fade-leave-active {
        transition: none;
    }
}
</style>
