<script setup>
import { ref, reactive, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    transactions: { type: Array, default: () => [] },
    assetsList: { type: Array, default: () => [] },
    activeLoans: { type: Array, default: () => [] },
    topStats: {
        type: Object,
        default: () => ({
            total_barang_keluar: 0,
            total_barang_masuk: 0,
            total_barang_dipinjam: 0,
            total_sedang_dipinjam: 0,
            total_transaksi: 0,
            barang_belum_dikembalikan: 0,
        })
    },
    bottomSummary: {
        type: Object,
        default: () => ({
            total_keluar: 0,
            total_masuk: 0,
            total_dipindahkan: 0,
            total_dipinjam: 0,
            total_dikembalikan: 0,
            total_belum_dikembalikan: 0,
        })
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            jenis_transaksi: 'Semua',
            arah_transaksi: 'Semua',
            status: 'Semua',
        })
    }
});

const showModal = ref(false);

const filterForm = reactive({
    search: props.filters?.search || '',
    jenis_transaksi: props.filters?.jenis_transaksi || 'Semua',
    arah_transaksi: props.filters?.arah_transaksi || 'Semua',
    status: props.filters?.status || 'Semua',
});

const applyFilters = () => {
    router.get(route('transactions.index'), filterForm, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const resetFilters = () => {
    filterForm.search = '';
    filterForm.jenis_transaksi = 'Semua';
    filterForm.arah_transaksi = 'Semua';
    filterForm.status = 'Semua';
    applyFilters();
};

const form = useForm({
    tanggal_transaksi: new Date().toISOString().substr(0, 10),
    arah_transaksi: 'Keluar',
    asset_id: '',
    parent_transaction_id: '',
    jumlah: 1,
    jenis_transaksi: 'Pemindahan Barang',
    lokasi_asal: '',
    tujuan: '',
    nama_peminjam: '',
    kontak_peminjam: '',
    tanggal_pengembalian_rencana: '',
    kondisi_sebelum: 'Baik',
    kondisi_setelah: 'Baik',
    alasan: '',
    stok_baru: 0,
    penanggung_jawab: '',
    keterangan: '',
});

const selectedAsset = computed(() => {
    return props.assetsList.find((a) => String(a.id) === String(form.asset_id)) || null;
});

const selectedLoan = computed(() => {
    return props.activeLoans.find((l) => String(l.id) === String(form.parent_transaction_id)) || null;
});

const maxAvailableForAction = computed(() => {
    if (form.jenis_transaksi === 'Pengembalian Barang' && selectedLoan.value) {
        return Number(selectedLoan.value.sisa_pinjaman ?? (selectedLoan.value.jumlah - (selectedLoan.value.jumlah_dikembalikan || 0)));
    }
    if (selectedAsset.value) {
        return Number(selectedAsset.value.stok_tersedia ?? selectedAsset.value.jumlah ?? 0);
    }
    return null;
});

const selisihStokPreview = computed(() => {
    if (!selectedAsset.value) return 0;
    return Number(form.stok_baru || 0) - Number(selectedAsset.value.jumlah || 0);
});

const openCreateModal = () => {
    form.reset();
    form.clearErrors();
    form.tanggal_transaksi = new Date().toISOString().substr(0, 10);
    form.jenis_transaksi = 'Pemindahan Barang';
    form.arah_transaksi = 'Keluar';
    showModal.value = true;
};

const onSelectAsset = () => {
    if (selectedAsset.value) {
        form.lokasi_asal = selectedAsset.value.lokasi || '';
        form.kondisi_sebelum = selectedAsset.value.kondisi || 'Baik';
        form.kondisi_setelah = selectedAsset.value.kondisi || 'Baik';
        form.stok_baru = selectedAsset.value.jumlah ?? 0;
    }
};

const onSelectLoan = () => {
    if (selectedLoan.value) {
        form.asset_id = selectedLoan.value.asset_id;
        form.nama_peminjam = selectedLoan.value.nama_peminjam || '';
        form.kontak_peminjam = selectedLoan.value.kontak_peminjam || '';
        form.lokasi_asal = selectedLoan.value.tujuan || '';
        form.tujuan = selectedLoan.value.asset?.lokasi || selectedLoan.value.lokasi_asal || '';
        form.jumlah = selectedLoan.value.sisa_pinjaman ?? (selectedLoan.value.jumlah - (selectedLoan.value.jumlah_dikembalikan || 0));
        form.kondisi_setelah = selectedLoan.value.asset?.kondisi || 'Baik';
    }
};

const onSelectJenis = () => {
    form.clearErrors();
    if (['Pemindahan Barang', 'Peminjaman Barang', 'Penghapusan Barang'].includes(form.jenis_transaksi)) {
        form.arah_transaksi = 'Keluar';
    } else if (form.jenis_transaksi === 'Pengembalian Barang') {
        form.arah_transaksi = 'Masuk';
        if (props.activeLoans.length === 1) {
            form.parent_transaction_id = props.activeLoans[0].id;
            onSelectLoan();
        }
    } else if (form.jenis_transaksi === 'Penyesuaian Stok' && selectedAsset.value) {
        form.stok_baru = selectedAsset.value.jumlah;
    }
};

const openReturnModal = (loanRow) => {
    form.reset();
    form.clearErrors();
    form.tanggal_transaksi = new Date().toISOString().substr(0, 10);
    form.jenis_transaksi = 'Pengembalian Barang';
    form.arah_transaksi = 'Masuk';
    form.parent_transaction_id = loanRow.id;
    form.asset_id = loanRow.asset_id;
    form.nama_peminjam = loanRow.nama_peminjam || '';
    form.kontak_peminjam = loanRow.kontak_peminjam || '';
    form.lokasi_asal = loanRow.tujuan || '';
    form.tujuan = loanRow.asset?.lokasi || loanRow.lokasi_asal || '';
    form.jumlah = loanRow.jumlah - (loanRow.jumlah_dikembalikan || 0);
    form.penanggung_jawab = loanRow.penanggung_jawab || '';
    form.kondisi_setelah = loanRow.asset?.kondisi || 'Baik';
    showModal.value = true;
};

const submitTransaction = () => {
    if (form.jenis_transaksi === 'Penyesuaian Stok') {
        form.arah_transaksi = selisihStokPreview.value >= 0 ? 'Masuk' : 'Keluar';
        form.jumlah = Math.max(1, Math.abs(selisihStokPreview.value));
    }

    form.post(route('transactions.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false;
            form.reset();
        }
    });
};

const deleteTransaction = (id) => {
    if (confirm('Hapus riwayat transaksi ini dan kembalikan perubahan stok aset?')) {
        router.delete(route('transactions.destroy', id), {
            preserveScroll: true,
        });
    }
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const parts = String(dateStr).split('T')[0].split('-');
    if (parts.length === 3) {
        return `${parts[2]}-${parts[1]}-${parts[0]}`;
    }
    return dateStr;
};

const getStatusBadgeClass = (status) => {
    switch (status) {
        case 'Selesai':
        case 'Dikembalikan':
            return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
        case 'Sedang Dipinjam':
        case 'Dipinjam':
            return 'bg-amber-50 text-amber-700 border border-amber-200';
        case 'Pengembalian Sebagian':
            return 'bg-blue-50 text-blue-700 border border-blue-200';
        default:
            return 'bg-slate-100 text-slate-700 border border-slate-200';
    }
};

const getJenisBadgeClass = (jenis) => {
    switch (jenis) {
        case 'Pemindahan Barang':
            return 'bg-indigo-50 text-indigo-700 border border-indigo-200';
        case 'Peminjaman Barang':
            return 'bg-amber-50 text-amber-700 border border-amber-200';
        case 'Pengembalian Barang':
            return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
        case 'Penghapusan Barang':
            return 'bg-rose-50 text-rose-700 border border-rose-200';
        case 'Penyesuaian Stok':
            return 'bg-purple-50 text-purple-700 border border-purple-200';
        default:
            return 'bg-slate-100 text-slate-700 border border-slate-200';
    }
};
</script>

<template>
    <Head title="Keluar-Masuk (Peminjaman)" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <p class="text-xs text-slate-500 font-medium tracking-wide">Inventory / Keluar-Masuk (Peminjaman)</p>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight mt-0.5">
                        Keluar-Masuk (Peminjaman)
                    </h2>
                </div>
                <div class="flex items-center gap-2.5">
                    <button
                        @click="openCreateModal"
                        class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm rounded-xl shadow-sm transition cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                        </svg>
                        Tambah Transaksi
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8 bg-slate-50/60 min-h-[calc(100vh-65px)]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- Alert Notifikasi Flash -->
                <div
                    v-if="$page.props.flash?.success"
                    class="p-4 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-800 rounded-r-xl shadow-xs flex justify-between items-center"
                >
                    <div class="flex items-center gap-2.5">
                        <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="font-medium text-sm">{{ $page.props.flash.success }}</span>
                    </div>
                </div>

                <!-- A. RINGKASAN TRANSAKSI (6 KARTU MODERN) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4">
                    <!-- Total Barang Keluar -->
                    <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs hover:shadow-md transition flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] text-slate-500 font-bold uppercase tracking-wider">Total Barang Keluar</span>
                            <div class="p-2 bg-rose-50 text-rose-600 rounded-xl">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-3 flex items-baseline gap-1.5">
                            <span class="text-2xl font-extrabold text-rose-600">{{ topStats.total_barang_keluar }}</span>
                            <span class="text-xs font-medium text-slate-400">Unit</span>
                        </div>
                    </div>

                    <!-- Total Barang Masuk -->
                    <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs hover:shadow-md transition flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] text-slate-500 font-bold uppercase tracking-wider">Total Barang Masuk</span>
                            <div class="p-2 bg-emerald-50 text-emerald-600 rounded-xl">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-3 flex items-baseline gap-1.5">
                            <span class="text-2xl font-extrabold text-emerald-600">{{ topStats.total_barang_masuk }}</span>
                            <span class="text-xs font-medium text-slate-400">Unit</span>
                        </div>
                    </div>

                    <!-- Total Dipinjam -->
                    <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs hover:shadow-md transition flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] text-slate-500 font-bold uppercase tracking-wider">Total Dipinjam</span>
                            <div class="p-2 bg-indigo-50 text-indigo-600 rounded-xl">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-3 flex items-baseline gap-1.5">
                            <span class="text-2xl font-extrabold text-indigo-600">{{ topStats.total_barang_dipinjam }}</span>
                            <span class="text-xs font-medium text-slate-400">Unit</span>
                        </div>
                    </div>

                    <!-- Sedang Dipinjam -->
                    <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs hover:shadow-md transition flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] text-slate-500 font-bold uppercase tracking-wider">Sedang Dipinjam</span>
                            <div class="p-2 bg-amber-50 text-amber-600 rounded-xl">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-3 flex items-baseline gap-1.5">
                            <span class="text-2xl font-extrabold text-amber-600">{{ topStats.total_sedang_dipinjam }}</span>
                            <span class="text-xs font-medium text-slate-400">Unit</span>
                        </div>
                    </div>

                    <!-- Total Transaksi -->
                    <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs hover:shadow-md transition flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] text-slate-500 font-bold uppercase tracking-wider">Total Transaksi</span>
                            <div class="p-2 bg-slate-100 text-slate-700 rounded-xl">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-3 flex items-baseline gap-1.5">
                            <span class="text-2xl font-extrabold text-slate-900">{{ topStats.total_transaksi }}</span>
                            <span class="text-xs font-medium text-slate-400">Riwayat</span>
                        </div>
                    </div>

                    <!-- Belum Dikembalikan -->
                    <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs hover:shadow-md transition flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] text-slate-500 font-bold uppercase tracking-wider">Belum Dikembalikan</span>
                            <div class="p-2 bg-red-50 text-red-600 rounded-xl">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-3 flex items-baseline gap-1.5">
                            <span class="text-2xl font-extrabold text-red-600">{{ topStats.barang_belum_dikembalikan }}</span>
                            <span class="text-xs font-medium text-slate-400">Unit</span>
                        </div>
                    </div>
                </div>

                <!-- FILTER & PENCARIAN TRANSAKSI -->
                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-xs flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                    <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
                        <div class="relative">
                            <input
                                v-model="filterForm.search"
                                @keyup.enter="applyFilters"
                                type="text"
                                placeholder="Cari kode, barang, peminjam..."
                                class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-300 text-slate-900 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 transition"
                            />
                            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>

                        <div>
                            <select
                                v-model="filterForm.jenis_transaksi"
                                @change="applyFilters"
                                class="w-full py-2 px-3 bg-slate-50 border border-slate-300 text-slate-800 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 transition"
                            >
                                <option value="Semua">- Semua Jenis Transaksi -</option>
                                <option value="Pemindahan Barang">Pemindahan Barang</option>
                                <option value="Peminjaman Barang">Peminjaman Barang</option>
                                <option value="Pengembalian Barang">Pengembalian Barang</option>
                                <option value="Penghapusan Barang">Penghapusan Barang</option>
                                <option value="Penyesuaian Stok">Penyesuaian Stok</option>
                            </select>
                        </div>

                        <div>
                            <select
                                v-model="filterForm.arah_transaksi"
                                @change="applyFilters"
                                class="w-full py-2 px-3 bg-slate-50 border border-slate-300 text-slate-800 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 transition"
                            >
                                <option value="Semua">- Semua Arah -</option>
                                <option value="Keluar">Barang Keluar</option>
                                <option value="Masuk">Barang Masuk</option>
                            </select>
                        </div>

                        <div>
                            <select
                                v-model="filterForm.status"
                                @change="applyFilters"
                                class="w-full py-2 px-3 bg-slate-50 border border-slate-300 text-slate-800 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 transition"
                            >
                                <option value="Semua">- Semua Status -</option>
                                <option value="Sedang Dipinjam">Sedang Dipinjam</option>
                                <option value="Pengembalian Sebagian">Pengembalian Sebagian</option>
                                <option value="Dikembalikan">Dikembalikan</option>
                                <option value="Selesai">Selesai</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 justify-end">
                        <button
                            @click="applyFilters"
                            class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white font-semibold text-sm rounded-xl transition shadow-xs cursor-pointer"
                        >
                            Filter
                        </button>
                        <button
                            @click="resetFilters"
                            class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-sm rounded-xl transition cursor-pointer"
                        >
                            Reset
                        </button>
                    </div>
                </div>

                <!-- B. TABEL RIWAYAT TRANSAKSI KELUAR-MASUK -->
                <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div>
                            <h3 class="font-bold text-slate-800 text-base">Daftar Riwayat Transaksi</h3>
                            <p class="text-xs text-slate-500">Menampilkan seluruh aktivitas pemindahan, peminjaman, pengembalian, penghapusan, dan penyesuaian stok.</p>
                        </div>
                        <span class="text-xs font-semibold text-slate-500 bg-slate-100 px-3 py-1 rounded-full self-start sm:self-auto">
                            {{ transactions.length }} Data Ditampilkan
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-sm">
                            <thead>
                                <tr class="bg-slate-900 text-white text-[11px] uppercase tracking-wider font-bold">
                                    <th class="py-3.5 px-4">Tanggal</th>
                                    <th class="py-3.5 px-4">Kode Aset</th>
                                    <th class="py-3.5 px-4">Nama Barang</th>
                                    <th class="py-3.5 px-4">Jenis Transaksi</th>
                                    <th class="py-3.5 px-4 text-center">Arah</th>
                                    <th class="py-3.5 px-4 text-center">Jumlah</th>
                                    <th class="py-3.5 px-4">Lokasi Asal</th>
                                    <th class="py-3.5 px-4">Tujuan / Ket.</th>
                                    <th class="py-3.5 px-4">Nama Peminjam</th>
                                    <th class="py-3.5 px-4">Penanggung Jawab</th>
                                    <th class="py-3.5 px-4 text-center">Status</th>
                                    <th class="py-3.5 px-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200/80 text-slate-700">
                                <tr v-if="!transactions.length">
                                    <td colspan="12" class="py-12 px-4 text-center text-slate-400">
                                        <div class="flex flex-col items-center justify-center gap-2">
                                            <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            <p class="font-medium text-sm text-slate-500">Belum ada transaksi tercatat.</p>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-else v-for="row in transactions" :key="row.id" class="hover:bg-slate-50/90 transition">
                                    <td class="py-3.5 px-4 whitespace-nowrap text-xs font-medium text-slate-600">
                                        {{ formatDate(row.tanggal_transaksi) }}
                                    </td>
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <span class="font-mono font-bold text-xs bg-slate-100 text-slate-800 px-2 py-1 rounded-md">
                                            {{ row.asset?.kode_aset || '-' }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 font-semibold text-slate-900">
                                        {{ row.asset?.nama_barang || 'Aset Terhapus' }}
                                    </td>
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <span :class="getJenisBadgeClass(row.jenis_transaksi)" class="px-2.5 py-1 rounded-lg text-xs font-semibold inline-block">
                                            {{ row.jenis_transaksi }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                        <span
                                            :class="row.arah_transaksi === 'Masuk'
                                                ? 'bg-emerald-100 text-emerald-800 border border-emerald-200'
                                                : 'bg-rose-100 text-rose-800 border border-rose-200'"
                                            class="px-2.5 py-1 rounded-full text-xs font-bold"
                                        >
                                            {{ row.arah_transaksi }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                        <div class="font-extrabold text-slate-900">{{ row.jumlah }}</div>
                                        <div
                                            v-if="row.jenis_transaksi === 'Peminjaman Barang' && row.jumlah_dikembalikan > 0"
                                            class="text-[11px] text-emerald-600 font-semibold"
                                        >
                                            Kembali: {{ row.jumlah_dikembalikan }}/{{ row.jumlah }}
                                        </div>
                                        <div
                                            v-if="row.jenis_transaksi === 'Penyesuaian Stok' && row.stok_sebelumnya !== null"
                                            class="text-[11px] text-slate-500"
                                        >
                                            {{ row.stok_sebelumnya }} &rarr; {{ row.stok_baru }}
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-600 text-xs">{{ row.lokasi_asal || '-' }}</td>
                                    <td class="py-3.5 px-4 text-slate-700 text-xs">
                                        <div class="font-medium">{{ row.tujuan || '-' }}</div>
                                        <div v-if="row.alasan" class="text-[11px] text-slate-500 mt-0.5">
                                            Alasan: {{ row.alasan }}
                                        </div>
                                        <div v-else-if="row.keterangan" class="text-[11px] text-slate-400 truncate max-w-[180px]" :title="row.keterangan">
                                            {{ row.keterangan }}
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 text-xs">
                                        <div class="font-medium text-slate-800">{{ row.nama_peminjam || '-' }}</div>
                                        <div v-if="row.tanggal_pengembalian_rencana" class="text-[11px] text-slate-400">
                                            Rencana: {{ formatDate(row.tanggal_pengembalian_rencana) }}
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 text-xs font-medium text-slate-700">{{ row.penanggung_jawab }}</td>
                                    <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                        <span :class="getStatusBadgeClass(row.status)" class="px-2.5 py-1 rounded-full text-xs font-semibold">
                                            {{ row.status }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                        <div class="inline-flex items-center gap-1.5">
                                            <button
                                                v-if="row.jenis_transaksi === 'Peminjaman Barang' && ['Sedang Dipinjam', 'Dipinjam', 'Pengembalian Sebagian'].includes(row.status) && (row.jumlah > (row.jumlah_dikembalikan || 0))"
                                                @click="openReturnModal(row)"
                                                class="px-2.5 py-1.5 bg-emerald-600 text-white rounded-lg text-xs font-semibold hover:bg-emerald-700 transition shadow-2xs cursor-pointer"
                                            >
                                                Kembalikan
                                            </button>
                                            <button
                                                @click="deleteTransaction(row.id)"
                                                class="px-2.5 py-1.5 bg-rose-50 text-rose-600 border border-rose-200/60 rounded-lg text-xs font-semibold hover:bg-rose-100 transition cursor-pointer"
                                            >
                                                Hapus
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- E. RINGKASAN KELUAR-MASUK DI BAWAH TABEL -->
                <div class="bg-white rounded-2xl border border-slate-200/90 p-6 shadow-xs">
                    <h3 class="font-bold text-slate-800 text-sm uppercase tracking-wider mb-4">Ringkasan Aktivitas Stok</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 text-center">
                        <div class="p-3.5 bg-slate-50 border border-slate-200/60 rounded-xl">
                            <p class="text-xs text-slate-500 font-medium">Barang Keluar</p>
                            <p class="text-xl font-extrabold text-slate-800 mt-1">{{ bottomSummary.total_keluar }}</p>
                        </div>
                        <div class="p-3.5 bg-slate-50 border border-slate-200/60 rounded-xl">
                            <p class="text-xs text-slate-500 font-medium">Barang Masuk</p>
                            <p class="text-xl font-extrabold text-emerald-600 mt-1">{{ bottomSummary.total_masuk }}</p>
                        </div>
                        <div class="p-3.5 bg-slate-50 border border-slate-200/60 rounded-xl">
                            <p class="text-xs text-slate-500 font-medium">Dipindahkan</p>
                            <p class="text-xl font-extrabold text-indigo-600 mt-1">{{ bottomSummary.total_dipindahkan }}</p>
                        </div>
                        <div class="p-3.5 bg-slate-50 border border-slate-200/60 rounded-xl">
                            <p class="text-xs text-slate-500 font-medium">Total Dipinjam</p>
                            <p class="text-xl font-extrabold text-amber-600 mt-1">{{ bottomSummary.total_dipinjam }}</p>
                        </div>
                        <div class="p-3.5 bg-slate-50 border border-slate-200/60 rounded-xl">
                            <p class="text-xs text-slate-500 font-medium">Sudah Dikembalikan</p>
                            <p class="text-xl font-extrabold text-teal-600 mt-1">{{ bottomSummary.total_dikembalikan }}</p>
                        </div>
                        <div class="p-3.5 bg-rose-50/60 border border-rose-200/60 rounded-xl">
                            <p class="text-xs text-rose-600 font-semibold">Belum Kembali</p>
                            <p class="text-xl font-extrabold text-rose-600 mt-1">{{ bottomSummary.total_belum_dikembalikan }}</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- MODAL FORM TAMBAH TRANSAKSI DINAMIS -->
        <div v-if="showModal" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-2xl space-y-5 my-8 border border-slate-200">
                <div class="flex justify-between items-center border-b border-slate-200 pb-3.5">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Form Transaksi Aset</h3>
                        <p class="text-xs text-slate-500">Perubahan akan langsung memperbarui stok & lokasi pada Master Aset.</p>
                    </div>
                    <button @click="showModal = false" class="text-slate-400 hover:text-slate-600 font-bold text-2xl leading-none cursor-pointer">&times;</button>
                </div>

                <!-- Error Summary Box -->
                <div v-if="Object.keys(form.errors).length > 0" class="p-3.5 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-700 space-y-1">
                    <p class="font-bold">Gagal menyimpan transaksi:</p>
                    <ul class="list-disc list-inside">
                        <li v-for="(err, key) in form.errors" :key="key">{{ err }}</li>
                    </ul>
                </div>

                <form @submit.prevent="submitTransaction" class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Tanggal Transaksi *</label>
                        <input v-model="form.tanggal_transaksi" type="date" class="w-full rounded-xl border-slate-300 text-sm" required />
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Jenis Transaksi *</label>
                        <select v-model="form.jenis_transaksi" @change="onSelectJenis" class="w-full rounded-xl border-slate-300 text-sm">
                            <option value="Pemindahan Barang">1. Pemindahan Barang</option>
                            <option value="Peminjaman Barang">2. Peminjaman Barang</option>
                            <option value="Pengembalian Barang">3. Pengembalian Barang</option>
                            <option value="Penghapusan Barang">4. Penghapusan Barang</option>
                            <option value="Penyesuaian Stok">5. Penyesuaian Stok</option>
                        </select>
                    </div>

                    <!-- KHUSUS PENGEMBALIAN BARANG: Pilih dari Peminjaman Aktif -->
                    <div v-if="form.jenis_transaksi === 'Pengembalian Barang'" class="md:col-span-2">
                        <label class="block font-semibold text-slate-700 mb-1">Pilih Data Peminjaman Aktif *</label>
                        <select v-model="form.parent_transaction_id" @change="onSelectLoan" class="w-full rounded-xl border-slate-300 text-sm" required>
                            <option value="">-- Pilih Transaksi Peminjaman yang Akan Dikembalikan --</option>
                            <option v-for="loan in activeLoans" :key="loan.id" :value="loan.id">
                                [{{ loan.asset?.kode_aset }}] {{ loan.asset?.nama_barang }} — Peminjam: {{ loan.nama_peminjam }} (Sisa Dipinjam: {{ loan.sisa_pinjaman }} Unit)
                            </option>
                        </select>
                        <p v-if="!activeLoans.length" class="text-xs text-amber-600 mt-1 font-medium">
                            Saat ini tidak ada barang dengan status sedang dipinjam.
                        </p>
                    </div>

                    <!-- PILIH BARANG MASTER ASET -->
                    <div v-if="form.jenis_transaksi !== 'Pengembalian Barang'" class="md:col-span-2">
                        <label class="block font-semibold text-slate-700 mb-1">Pilih Barang *</label>
                        <select v-model="form.asset_id" @change="onSelectAsset" class="w-full rounded-xl border-slate-300 text-sm" required>
                            <option value="">-- Pilih Barang dari Master Asset --</option>
                            <option v-for="a in assetsList" :key="a.id" :value="a.id">
                                [{{ a.kode_aset }}] {{ a.nama_barang }} — Lokasi: {{ a.lokasi }} (Stok Total: {{ a.jumlah }} | Tersedia: {{ a.stok_tersedia ?? a.jumlah }})
                            </option>
                        </select>
                    </div>

                    <!-- Info Ringkas Aset Terpilih -->
                    <div v-if="selectedAsset" class="md:col-span-2 bg-slate-50 p-3.5 rounded-xl border border-slate-200 text-xs grid grid-cols-2 sm:grid-cols-4 gap-2">
                        <div><span class="text-slate-500 block">Kode Aset</span><strong class="text-slate-900 font-mono">{{ selectedAsset.kode_aset }}</strong></div>
                        <div><span class="text-slate-500 block">Lokasi Saat Ini</span><strong class="text-slate-900">{{ selectedAsset.lokasi }}</strong></div>
                        <div><span class="text-slate-500 block">Total Stok</span><strong class="text-slate-900">{{ selectedAsset.jumlah }} Unit</strong></div>
                        <div><span class="text-slate-500 block">Stok Tersedia</span><strong class="text-emerald-700">{{ selectedAsset.stok_tersedia ?? selectedAsset.jumlah }} Unit</strong></div>
                    </div>

                    <!-- JUMLAH TRANSAKSI (Selain Penyesuaian Stok) -->
                    <div v-if="form.jenis_transaksi !== 'Penyesuaian Stok'">
                        <label class="block font-semibold text-slate-700 mb-1">
                            Jumlah Unit *
                            <span v-if="maxAvailableForAction !== null" class="text-xs font-normal text-slate-500">
                                (Maks: {{ maxAvailableForAction }})
                            </span>
                        </label>
                        <input
                            v-model="form.jumlah"
                            type="number"
                            min="1"
                            :max="maxAvailableForAction || undefined"
                            class="w-full rounded-xl border-slate-300 text-sm"
                            required
                        />
                    </div>

                    <!-- PENYESUAIAN STOK -->
                    <template v-else>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Stok Fisik Sebenarnya (Stok Baru) *</label>
                            <input v-model="form.stok_baru" type="number" min="0" class="w-full rounded-xl border-slate-300 text-sm" required />
                        </div>
                        <div class="flex flex-col justify-center bg-purple-50 border border-purple-200 rounded-xl px-4 py-2 text-xs">
                            <span class="text-purple-700 font-semibold">Selisih Penyesuaian Stok:</span>
                            <span class="text-base font-extrabold" :class="selisihStokPreview >= 0 ? 'text-emerald-700' : 'text-rose-700'">
                                {{ selisihStokPreview > 0 ? `+${selisihStokPreview}` : selisihStokPreview }} Unit
                            </span>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block font-semibold text-slate-700 mb-1">Alasan Penyesuaian Stok *</label>
                            <input
                                v-model="form.alasan"
                                type="text"
                                placeholder="Contoh: Hasil Stock Opname Bulanan / Penambahan Unit Baru"
                                class="w-full rounded-xl border-slate-300 text-sm"
                                required
                            />
                        </div>
                    </template>

                    <!-- PEMINDAHAN BARANG -->
                    <template v-if="form.jenis_transaksi === 'Pemindahan Barang'">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Lokasi Asal *</label>
                            <input v-model="form.lokasi_asal" type="text" class="w-full rounded-xl border-slate-300 bg-slate-100 text-sm" readonly required />
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Lokasi Tujuan *</label>
                            <input
                                v-model="form.tujuan"
                                type="text"
                                placeholder="Contoh: Gedung Utama"
                                class="w-full rounded-xl border-slate-300 text-sm"
                                required
                            />
                        </div>
                    </template>

                    <!-- PEMINJAMAN BARANG -->
                    <template v-if="form.jenis_transaksi === 'Peminjaman Barang'">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Nama Peminjam *</label>
                            <input v-model="form.nama_peminjam" type="text" placeholder="Nama lengkap peminjam" class="w-full rounded-xl border-slate-300 text-sm" required />
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Kontak Peminjam</label>
                            <input v-model="form.kontak_peminjam" type="text" placeholder="No. HP / Divisi" class="w-full rounded-xl border-slate-300 text-sm" />
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Rencana Tanggal Kembali *</label>
                            <input v-model="form.tanggal_pengembalian_rencana" type="date" class="w-full rounded-xl border-slate-300 text-sm" required />
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Tujuan / Lokasi Peminjaman *</label>
                            <input v-model="form.tujuan" type="text" placeholder="Digunakan di ruang / kegiatan apa" class="w-full rounded-xl border-slate-300 text-sm" required />
                        </div>
                    </template>

                    <!-- PENGEMBALIAN BARANG -->
                    <template v-if="form.jenis_transaksi === 'Pengembalian Barang'">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Nama Peminjam</label>
                            <input v-model="form.nama_peminjam" type="text" class="w-full rounded-xl border-slate-300 bg-slate-100 text-sm" readonly />
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Kondisi Barang Saat Dikembalikan *</label>
                            <select v-model="form.kondisi_setelah" class="w-full rounded-xl border-slate-300 text-sm">
                                <option value="Baik">Baik</option>
                                <option value="Perlu Perbaikan">Perlu Perbaikan</option>
                                <option value="Rusak">Rusak</option>
                            </select>
                        </div>
                    </template>

                    <!-- PENGHAPUSAN BARANG -->
                    <template v-if="form.jenis_transaksi === 'Penghapusan Barang'">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Alasan Penghapusan *</label>
                            <input
                                v-model="form.alasan"
                                type="text"
                                placeholder="Contoh: Rusak Berat / Hilang / Kadaluarsa"
                                class="w-full rounded-xl border-slate-300 text-sm"
                                required
                            />
                        </div>
                    </template>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Penanggung Jawab / Petugas *</label>
                        <input v-model="form.penanggung_jawab" type="text" placeholder="Nama petugas" class="w-full rounded-xl border-slate-300 text-sm" required />
                    </div>

                    <div class="md:col-span-2">
                        <label class="block font-semibold text-slate-700 mb-1">Keterangan / Catatan Tambahan</label>
                        <textarea v-model="form.keterangan" rows="2" placeholder="Catatan transaksi..." class="w-full rounded-xl border-slate-300 text-sm"></textarea>
                    </div>

                    <div class="md:col-span-2 flex justify-end gap-2.5 pt-4 border-t border-slate-200">
                        <button
                            type="button"
                            @click="showModal = false"
                            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 rounded-xl font-semibold text-slate-700 transition cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-xl font-semibold shadow-sm transition cursor-pointer"
                        >
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Transaksi' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>