<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: true,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Log in" />

    <div class="relative min-h-screen flex flex-col lg:flex-row overflow-hidden bg-slate-900 text-white font-sans">
        <!-- BAGIAN KIRI: Gambar Gudang & Effect Blur Transisi -->
        <div class="relative flex-1 min-h-[250px] lg:min-h-screen bg-cover bg-center" style="background-image: url('/images/warehouse.jpg');">
            <div class="absolute inset-0 bg-slate-950/30"></div>
            <div class="absolute inset-0 bg-gradient-to-b lg:bg-gradient-to-r from-transparent via-slate-900/70 to-slate-900 backdrop-blur-[2px]"></div>
        </div>

        <!-- BAGIAN KANAN: Form Login -->
        <div class="relative z-10 flex-1 flex flex-col justify-center items-center p-6 sm:p-12 bg-slate-900/90 lg:bg-transparent">
            <div class="max-w-md w-full space-y-6">
                <div>
                    <h2 class="text-2xl font-bold text-white">Log in</h2>
                    <p class="text-sm text-slate-400">Masukkan akun Anda untuk melanjutkan.</p>
                </div>

                <div v-if="status" class="mb-4 text-sm font-medium text-green-400">
                    {{ status }}
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <InputLabel for="email" value="Email" class="text-slate-300" />

                        <TextInput
                            id="email"
                            type="email"
                            class="mt-1 block w-full bg-slate-800 border-slate-700 text-white focus:border-blue-500 focus:ring-blue-500"
                            v-model="form.email"
                            required
                            autofocus
                            autocomplete="username"
                        />

                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div>
                        <InputLabel for="password" value="Password" class="text-slate-300" />

                        <TextInput
                            id="password"
                            type="password"
                            class="mt-1 block w-full bg-slate-800 border-slate-700 text-white focus:border-blue-500 focus:ring-blue-500"
                            v-model="form.password"
                            required
                            autocomplete="current-password"
                        />

                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center cursor-pointer">
                            <Checkbox name="remember" v-model:checked="form.remember" class="bg-slate-800 border-slate-700 text-blue-600 focus:ring-blue-500" />
                            <span class="ms-2 text-sm text-slate-400">Remember me</span>
                        </label>

                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="text-xs text-slate-400 hover:text-white underline"
                        >
                            Forgot your password?
                        </Link>
                    </div>

                    <div class="pt-2">
                        <PrimaryButton
                            type="submit"
                            class="w-full justify-center py-3 bg-blue-600 hover:bg-blue-500 font-bold rounded-xl shadow-lg shadow-blue-600/30 transition"
                            :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing"
                        >
                            Log in
                        </PrimaryButton>
                    </div>
                </form>

                <p class="text-center text-xs text-slate-400">
                    Belum punya akun? 
                    <Link :href="route('register')" class="text-blue-400 hover:underline font-semibold">Daftar Akun Baru</Link>
                </p>
            </div>
        </div>
    </div>
</template>