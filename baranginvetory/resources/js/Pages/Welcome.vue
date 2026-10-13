<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
});
</script>

<template>
    <Head title="Selamat Datang - Sistem Inventaris Aset" />

    <div class="relative min-h-screen flex flex-col lg:flex-row overflow-hidden bg-slate-900 text-white font-sans">
        
        <!-- BAGIAN KIRI: Gambar Gudang dengan Overlap Gradien & Blur -->
        <div class="relative flex-1 min-h-[300px] lg:min-h-screen bg-cover bg-center" style="background-image: url('/images/warehouse.jpg');">
            <!-- Overlay Gelap Kiri -->
            <div class="absolute inset-0 bg-slate-950/30"></div>

            <!-- Transisi Blur & Gradien Menyatu ke Kanan (Desktop) / ke Bawah (Mobile) -->
            <div class="absolute inset-0 bg-gradient-to-b lg:bg-gradient-to-r from-transparent via-slate-900/70 to-slate-900 backdrop-blur-[2px]"></div>
        </div>

        <!-- BAGIAN KANAN: Form / Konten Utama Halaman Welcome -->
        <div class="relative z-10 flex-1 flex flex-col justify-center items-center p-8 lg:p-16 text-center bg-slate-900/90 lg:bg-transparent">
            
            <div class="max-w-md w-full space-y-6">
                <!-- Branding / Header -->
                <div class="space-y-2">
                    <div class="inline-flex items-center justify-center p-3 bg-blue-600/20 text-blue-400 rounded-2xl mb-2 border border-blue-500/30">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                    </div>
                    <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-white">
                        Sistem Inventaris Aset
                    </h1>
                    <p class="text-sm text-slate-400">
                        Kelola data barang, lokasi, dan rekapitulasi laporan aset secara terstruktur dan efisien.
                    </p>
                </div>

                <!-- Tombol Navigasi Login & Register -->
                <div v-if="canLogin" class="pt-4 space-y-3">
                    <template v-if="$page.props.auth.user">
                        <Link
                            :href="route('dashboard')"
                            class="block w-full py-3.5 px-6 rounded-xl bg-blue-600 hover:bg-blue-500 font-semibold text-white shadow-lg shadow-blue-600/30 transition duration-200"
                        >
                            Buka Dashboard
                        </Link>
                    </template>

                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="block w-full py-3.5 px-6 rounded-xl bg-blue-600 hover:bg-blue-500 font-semibold text-white shadow-lg shadow-blue-600/30 transition duration-200"
                        >
                            Masuk / Login
                        </Link>

                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="block w-full py-3.5 px-6 rounded-xl bg-slate-800 hover:bg-slate-700 font-semibold text-slate-200 border border-slate-700 transition duration-200"
                        >
                            Daftar Akun Baru
                        </Link>
                    </template>
                </div>

                <!-- Footer Informasi -->
                <p class="text-xs text-slate-500 pt-6">
                    &copy; 2026 Aplikasi Manajemen Barang Inventaris.
                </p>
            </div>

        </div>

    </div>
</template>