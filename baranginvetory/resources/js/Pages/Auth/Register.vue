<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Register" />

    <div class="relative min-h-screen flex flex-col lg:flex-row overflow-hidden bg-slate-900 text-white font-sans">
        <!-- BAGIAN KIRI: Gambar Gudang & Effect Blur Transisi -->
        <div class="relative flex-1 min-h-[250px] lg:min-h-screen bg-cover bg-center" style="background-image: url('/images/warehouse.jpg');">
            <div class="absolute inset-0 bg-slate-950/30"></div>
            <div class="absolute inset-0 bg-gradient-to-b lg:bg-gradient-to-r from-transparent via-slate-900/70 to-slate-900 backdrop-blur-[2px]"></div>
        </div>

        <!-- BAGIAN KANAN: Form Register -->
        <div class="relative z-10 flex-1 flex flex-col justify-center items-center p-6 sm:p-12 bg-slate-900/90 lg:bg-transparent">
            <div class="max-w-md w-full space-y-6">
                <div>
                    <h2 class="text-2xl font-bold text-white">Register</h2>
                    <p class="text-sm text-slate-400">Lengkapi data untuk mendaftar akun baru.</p>
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <InputLabel for="name" value="Name" class="text-slate-300" />

                        <TextInput
                            id="name"
                            type="text"
                            class="mt-1 block w-full bg-slate-800 border-slate-700 text-white focus:border-blue-500 focus:ring-blue-500"
                            v-model="form.name"
                            required
                            autofocus
                            autocomplete="name"
                        />

                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div>
                        <InputLabel for="email" value="Email" class="text-slate-300" />

                        <TextInput
                            id="email"
                            type="email"
                            class="mt-1 block w-full bg-slate-800 border-slate-700 text-white focus:border-blue-500 focus:ring-blue-500"
                            v-model="form.email"
                            required
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
                            autocomplete="new-password"
                        />

                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <div>
                        <InputLabel
                            for="password_confirmation"
                            value="Confirm Password"
                            class="text-slate-300"
                        />

                        <TextInput
                            id="password_confirmation"
                            type="password"
                            class="mt-1 block w-full bg-slate-800 border-slate-700 text-white focus:border-blue-500 focus:ring-blue-500"
                            v-model="form.password_confirmation"
                            required
                            autocomplete="new-password"
                        />

                        <InputError
                            class="mt-2"
                            :message="form.errors.password_confirmation"
                        />
                    </div>

                    <div class="pt-2">
                        <PrimaryButton
                            class="w-full justify-center py-3 bg-blue-600 hover:bg-blue-500 font-bold rounded-xl shadow-lg shadow-blue-600/30 transition"
                            :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing"
                        >
                            Register
                        </PrimaryButton>
                    </div>
                </form>

                <p class="text-center text-xs text-slate-400">
                    Sudah punya akun?
                    <Link
                        :href="route('login')"
                        class="text-blue-400 hover:underline font-semibold"
                    >
                        Already registered?
                    </Link>
                </p>
            </div>
        </div>
    </div>
</template>