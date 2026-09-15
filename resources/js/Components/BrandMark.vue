<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * The 28px mark beside the app name.
 *
 * It sits in two places — the phone header and the head of the desktop
 * sidebar — which is why it is a component rather than the same markup in the
 * layout twice. The name is left to the caller, since each place shows and
 * hides it at a different width.
 *
 * An uploaded logo replaces the lettermark; without one we fall back to the
 * app name's initial.
 */
const page = usePage();

// Shared from HandleInertiaRequests, so every page has it without a prop.
const branding = computed(() => page.props.branding ?? { name: 'SpendLog', logo: null });
const initial = computed(() => (branding.value.name || 'S').charAt(0).toUpperCase());
</script>

<template>
    <img
        v-if="branding.logo"
        :src="branding.logo"
        :alt="branding.name"
        class="size-7 shrink-0 rounded-lg object-contain"
    />
    <!-- The mark wears the brand colour: it stands in for the logo, so it is
         the one thing that should obviously be the admin's colour. Theme-aware
         by default, like every other use of the token. -->
    <span
        v-else
        class="bg-primary text-primary-foreground grid size-7 shrink-0 place-items-center rounded-lg text-[13px] font-extrabold"
    >
        {{ initial }}
    </span>
</template>
