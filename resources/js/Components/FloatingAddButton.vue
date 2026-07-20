<script setup>
import { Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';

/**
 * The phone's primary action, parked above the tab bar.
 *
 * A list's "add" belongs where the thumb already is. In the page header it sat
 * in the corner furthest from it, and on a long list it scrolled away entirely —
 * so adding a second expense meant scrolling back to the top first.
 *
 * Fixed, not sticky: sticky rides inside the scrolling column and would slide
 * off with the list. Phone only — from sm: up the header button is a short
 * mouse move away and a floating control would just cover content.
 *
 * The wrapper spans the width and does not catch clicks; only the button does.
 * That way the bar can be centred in the same max-w-6xl column the tab bar uses
 * — so on a wide phone the button tracks the content edge rather than the glass
 * — without a full-width invisible sheet swallowing taps on the list beneath.
 */
defineProps({
    href: { type: String, required: true },
    label: { type: String, required: true },
});
</script>

<template>
    <!--
        Bottom clears the tab bar (84px) plus its safe-area padding, so the two
        cannot overlap on a notched phone.
    -->
    <div
        class="pointer-events-none fixed inset-x-0 z-40 sm:hidden"
        style="bottom: calc(6rem + env(safe-area-inset-bottom))"
    >
        <div class="mx-auto flex max-w-6xl justify-end px-4">
            <Button
                :as="Link"
                :href="href"
                class="pointer-events-auto h-12 rounded-full pe-5 ps-4 shadow-[0_8px_24px_-8px_rgba(15,23,42,0.4)]"
            >
                <Plus class="size-5" aria-hidden="true" />
                {{ label }}
            </Button>
        </div>
    </div>
</template>
