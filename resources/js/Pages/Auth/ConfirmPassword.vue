<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff, ShieldCheck } from 'lucide-vue-next';
import AuthCardLayout from '@/Layouts/AuthCardLayout.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { PILL_BUTTON, PILL_INPUT } from '@/lib/authStyles';

const form = useForm({
    password: '',
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <Head title="Confirm password" />

    <AuthCardLayout heading="Confirm your password">
        <template #icon>
            <ShieldCheck class="size-6 text-[#4b9d5f]" aria-hidden="true" />
        </template>

        <template #description>
            This is a secure area. Please re-enter your password to continue.
        </template>

        <form @submit.prevent="submit">
            <div class="relative">
                <Label for="password" class="sr-only">Password</Label>
                <Input
                    id="password"
                    v-model="form.password"
                    :type="showPassword ? 'text' : 'password'"
                    required
                    autofocus
                    autocomplete="current-password"
                    placeholder="Password"
                    :aria-invalid="!!form.errors.password"
                    :class="[PILL_INPUT, 'pr-12']"
                />
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    :aria-label="showPassword ? 'Hide password' : 'Show password'"
                    :aria-pressed="showPassword"
                    class="absolute right-1.5 top-1.5 size-[42px] rounded-full text-neutral-400 hover:bg-neutral-50 hover:text-neutral-700"
                    @click="showPassword = !showPassword"
                >
                    <component :is="showPassword ? Eye : EyeOff" class="size-[18px]" />
                </Button>
            </div>
            <p v-if="form.errors.password" class="mt-1.5 px-5 text-xs font-medium text-red-600">
                {{ form.errors.password }}
            </p>

            <Button type="submit" :disabled="form.processing" :class="[PILL_BUTTON, 'mt-4']">
                {{ form.processing ? 'Confirming…' : 'Confirm' }}
            </Button>
        </form>
    </AuthCardLayout>
</template>
