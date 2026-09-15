<script setup>
import { computed } from 'vue';

/**
 * A person's photo, or their initial while they have none.
 *
 * One component for the account menu, the phone's More sheet and the profile
 * form, so a face looks the same everywhere it appears and the no-photo
 * fallback is decided once. The caller sets the size — and the initial's text
 * size to match — through the class attribute, which Vue merges onto whichever
 * of the two roots renders.
 *
 * The initial wears the brand tint rather than a grey: a grey disc beside a
 * name reads as a missing image, a tinted letter reads as a deliberate mark.
 *
 * alt="" throughout. Everywhere this appears the name is printed beside it, so
 * announcing "photo of Koeuk" next to "Koeuk" would say it twice.
 */
const props = defineProps({
    // The shared auth user, or anything shaped like it: { name, avatar_url }.
    user: { type: Object, required: true },
});

const initial = computed(() => (props.user.name || '?').trim().charAt(0).toUpperCase());
</script>

<template>
    <img
        v-if="user.avatar_url"
        :src="user.avatar_url"
        alt=""
        class="shrink-0 rounded-full object-cover"
    />
    <span
        v-else
        class="grid shrink-0 place-items-center rounded-full bg-primary/10 font-bold leading-none text-primary"
        aria-hidden="true"
    >
        {{ initial }}
    </span>
</template>
