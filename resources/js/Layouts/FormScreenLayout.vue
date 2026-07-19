<script setup>
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import AmbientBackdrop from '@/Components/AmbientBackdrop.vue';
import { Toaster } from '@/Components/ui/sonner';
import { useTheme } from '@/composables/useTheme';
import { useBrandColors } from '@/composables/useBrandColors';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { APP_PAGE } from '@/lib/appStyles';

/**
 * A pushed screen: one task, one way out.
 *
 * Deliberately not AuthenticatedLayout. A create/edit form reached from a list
 * is a detour, and the app frame works against that — the tab bar offers five
 * ways to abandon a half-filled form, and the brand bar spends the top of a
 * phone screen on a workspace switcher nobody needs mid-task. Native apps push
 * these over the tab bar rather than inside it, so this does too.
 *
 * What that costs, and why it is still worth it: leaving this screen is Back or
 * Cancel, nothing else. That is the point — the form is modal in intent even
 * though it is a real route — but it does mean every screen using this layout
 * must give Back somewhere sensible to go.
 *
 * The Toaster comes along because flash messages are how a failed save reports
 * itself, and useBrandColors because the palette is applied per-layout rather
 * than globally.
 */
defineProps({
    backHref: { type: String, required: true },
    title: { type: String, required: true },
    backLabel: { type: String, default: 'Back' },
});

const page = usePage();
const { isDark } = useTheme();

useBrandColors(isDark);

const branding = computed(
    () => page.props.branding ?? { name: 'SpendLog', logo: null, plain_background: false },
);
</script>

<template>
    <div :class="APP_PAGE">
        <AmbientBackdrop v-if="!branding.plain_background" />

        <div class="mx-auto flex min-h-screen max-w-2xl flex-col px-3 lg:px-4">
            <!--
                The bar spans the screen rather than floating as a card: this is
                chrome, not content, and it is the only chrome here.

                Sticky and blurred so a long form scrolls under it — on a phone
                the title is what says which record you are in, and that is worth
                as much at the bottom of the form as at the top.
            -->
            <header
                class="sticky top-0 z-40 -mx-3 flex items-center gap-3 border-b border-border/60 bg-background/80 px-3 py-3 backdrop-blur-xl lg:-mx-4 lg:px-4"
            >
                <!-- A circle, not a bare glyph: it reads as a control at a
                     glance, and it is the 44px target the arrow alone was not. -->
                <Link
                    :href="backHref"
                    :aria-label="backLabel"
                    class="grid size-11 shrink-0 place-items-center rounded-full border border-border bg-card/70 text-foreground transition hover:bg-muted"
                >
                    <ArrowLeft class="size-5" aria-hidden="true" />
                </Link>

                <!-- min-w-0 so a long name truncates instead of pushing the row
                     wider than the viewport. -->
                <h1 class="min-w-0 flex-1 truncate text-center text-lg font-semibold leading-tight">
                    {{ title }}
                </h1>

                <!-- Balances the back button so the title is centred on the bar
                     rather than on the space left over beside it. Takes the
                     actions slot when there is one, and holds its width when
                     there is not — an aria-hidden spacer, not a control. -->
                <div class="flex size-11 shrink-0 items-center justify-end">
                    <slot name="actions" />
                </div>
            </header>

            <!-- A flex column, so the form inside can claim the full height and
                 push its action bar onto the bottom edge with mt-auto. Without
                 flex-1 reaching all the way down, a short form's buttons would
                 come to rest wherever the fields happened to end.

                 No footer and no tab bar: FormActions is the only thing below. -->
            <main class="flex flex-1 flex-col pt-5">
                <slot />
            </main>
        </div>

        <Toaster
            :theme="isDark ? 'dark' : 'light'"
            position="top-right"
            rich-colors
            close-button
        />
    </div>
</template>
