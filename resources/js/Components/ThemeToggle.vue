<script setup>
import { Moon, Sun } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { useTheme } from '@/composables/useTheme';

const { isDark, toggleTheme } = useTheme();
</script>

<template>
    <Button
        type="button"
        variant="ghost"
        size="icon"
        :aria-label="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
        :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
        :aria-pressed="isDark"
        class="rounded-full text-muted-foreground hover:text-foreground"
        @click="toggleTheme"
    >
        <!--
            Both icons are always mounted and cross-faded, so the button never
            reflows and the swap reads as one motion.
        -->
        <span class="relative grid size-4 place-items-center">
            <Sun
                class="absolute size-4 transition-all duration-300"
                :class="isDark ? 'scale-0 -rotate-90 opacity-0' : 'scale-100 rotate-0 opacity-100'"
                aria-hidden="true"
            />
            <Moon
                class="absolute size-4 transition-all duration-300"
                :class="isDark ? 'scale-100 rotate-0 opacity-100' : 'scale-0 rotate-90 opacity-0'"
                aria-hidden="true"
            />
        </span>
    </Button>
</template>
