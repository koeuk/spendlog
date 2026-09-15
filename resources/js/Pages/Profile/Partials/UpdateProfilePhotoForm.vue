<script setup>
import { computed, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { ImageUp, Trash2 } from 'lucide-vue-next';
import UserAvatar from '@/Components/UserAvatar.vue';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { MUTED } from '@/lib/appStyles';

/**
 * The profile photo, above the name and email on the Profile page.
 *
 * Its own form with its own two routes rather than a file field on the
 * profile form: a photo is picked and lands, the way it does everywhere else
 * people have one, while name and email are typed and then saved. Sharing one
 * Save would make the photo wait on the fields, and a multipart PATCH carrying
 * the email is a stranger thing to test than two small routes.
 *
 * Reads the user through a computed, unlike the info form's one-time snapshot:
 * the upload's redirect re-shares auth.user with the new avatar_url, and this
 * is what shows it.
 */
const page = usePage();
const user = computed(() => page.props.auth.user);

const form = useForm({ avatar: null });

const input = ref(null);

// An upload is in flight — as opposed to a removal, which also sets
// processing but has no file to speak of.
const uploading = computed(() => form.processing && form.avatar !== null);

function pick(event) {
    const file = event.target.files?.[0];

    if (!file) {
        return;
    }

    form.avatar = file;

    // forceFormData: file uploads cannot go as JSON. preserveScroll: this sits
    // at the top of the card, and a jump to the top on every pick would be felt.
    form.post(route('profile.avatar.store'), {
        forceFormData: true,
        preserveScroll: true,
        // Cleared either way, so picking the same file again after a rejected
        // upload still fires change.
        onFinish: () => {
            form.reset();
            if (input.value) input.value.value = '';
        },
    });
}

function remove() {
    form.delete(route('profile.avatar.destroy'), { preserveScroll: true });
}
</script>

<template>
    <div>
        <Label>{{ __('Photo') }}</Label>
        <div class="mt-2 flex items-center gap-4">
            <UserAvatar :user="user" class="size-16 text-xl" />

            <div class="flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="form.processing"
                    @click="input?.click()"
                >
                    <ImageUp class="size-4" />
                    {{ uploading ? __('Uploading…') : user.avatar_url ? __('Change photo') : __('Upload photo') }}
                </Button>
                <Button
                    v-if="user.avatar_url"
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="text-red-600 hover:text-red-700 dark:text-red-400"
                    :disabled="form.processing"
                    @click="remove"
                >
                    <Trash2 class="size-4" />
                    {{ __('Remove photo') }}
                </Button>
            </div>
        </div>

        <input
            ref="input"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            class="sr-only"
            @change="pick"
        />

        <p class="mt-2 text-xs" :class="MUTED">
            {{ __('JPEG, PNG or WebP, up to 4 MB. Shown beside your name in the menu.') }}
        </p>
        <p v-if="form.errors.avatar" class="mt-1 text-sm text-red-600 dark:text-red-400">
            {{ form.errors.avatar }}
        </p>
    </div>
</template>
