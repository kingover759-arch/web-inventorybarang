<script setup>
import { ref, reactive, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    assets: { type: Array, default: () => [] },
    stats: {
        type: Object,
        default: () => ({
            total_kuantitas: 0,
            total_tersedia: 0,
            total_dipinjam: 0,
            total_nilai: 0,
            kondisi_baik: 0,
            kondisi_rusak: 0,
        })
    },
    filters: {
        type: Object,
        default: () => ({ search: '', kondisi: 'Semua', kategori: 'Semua', lokasi: 'Semua' })
    },
    categories: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
});

// State Pencarian & Filter
const filterForm = reactive({
    search: props.filters?.search || '',
    kondisi: props.filters?.kondisi || 'Semua',
    kategori: props.filters?.kategori || 'Semua',
    lokasi: props.filters?.lokasi || 'Semua',
});

const applyFilters = () => {
    router.get(route('assets.index'), filterForm, { preserveState: true, preserveScroll: true, replace: true });
};

const resetFilters = () => {
    filterForm.search = '';
    filterForm.kondisi = 'Semua';
    filterForm.kategori = 'Semua';
    filterForm.lokasi = 'Semua';
    applyFilters();
};

// Format Rupiah
const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0
    }).format(val || 0);
};

const getHargaSatuan = (asset) => {
    return Number(asset.harga_per_benda ?? asset.harga_perolehan ?? 0);
};

const getHargaTotal = (asset) => {
    return (Number(asset.jumlah) || 0) * getHargaSatuan(asset);
};

// Format Tanggal DD-MM-YYYY
const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const parts = String(dateStr).split('T')[0].split('-');
    if (parts.length === 3) {
        return `${parts[2]}-${parts[1]}-${parts[0]}`;
    }
    return dateStr;
};

// Generator URL QR Code & Barcode
const getQrCodeUrl = (asset) => {
    const dataText = `Kode: ${asset.kode_aset}\nNama: ${asset.nama_barang}\nMerek: ${asset.merek || '-'}\nLokasi: ${asset.lokasi}`;
    return `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(dataText)}`;
};

const getBarcodeUrl = (asset) => {
    return `https://bwipjs-api.metafloor.com/?bcid=code128&text=${encodeURIComponent(asset.kode_aset || 'ASSET')}&scale=2&height=10&includetext`;
};

// Modals State
const showAddModal = ref(false);
const showEditModal = ref(false);
const showDetailModal = ref(false);
const showDeleteModal = ref(false);

const selectedAsset = ref(null);
const imagePreview = ref(null);

const form = useForm({
    id: null,
    foto: null,
    kode_aset: '',
    nama_barang: '',
    merek: '',
    kategori: 'Peralatan',
    lokasi: 'Gudang Utama',
    jumlah: 1,
    harga_perolehan: 0,
    harga_per_benda: 0,
    kondisi: 'Baik',
    status: 'Tersedia',
    tanggal_perolehan: new Date().toISOString().substr(0, 10),
    supplier: '',
    nomor_invoice: '',
    keterangan: '',
});

const calculatedHargaTotal = computed(() => {
    return (Number(form.jumlah) || 0) * (Number(form.harga_perolehan) || 0);
});

const openAddModal = () => {
    form.reset();
    form.clearErrors();
    form.tanggal_perolehan = new Date().toISOString().substr(0, 10);
    imagePreview.value = null;
    showEditModal.value = false;
    showAddModal.value = true;
};

const openEditModal = (asset) => {
    form.reset();
    form.clearErrors();
    form.id = asset.id;
    form.foto = null;
    form.kode_aset = asset.kode_aset || '';
    form.nama_barang = asset.nama_barang || '';
    form.merek = asset.merek || '';
    form.kategori = asset.kategori || 'Peralatan';
    form.lokasi = asset.lokasi || 'Gudang Utama';
    form.jumlah = Number(asset.jumlah ?? 0);
    form.harga_perolehan = getHargaSatuan(asset);
    form.harga_per_benda = getHargaSatuan(asset);
    form.kondisi = asset.kondisi || 'Baik';
    form.status = asset.status || 'Tersedia';
    form.tanggal_perolehan = asset.tanggal_perolehan ? String(asset.tanggal_perolehan).substr(0, 10) : '';
    form.supplier = asset.supplier || '';
    form.nomor_invoice = asset.nomor_invoice || '';
    form.keterangan = asset.keterangan || '';
    imagePreview.value = asset.foto || null;
    showAddModal.value = false;
    showEditModal.value = true;
};

const openDetailModal = (asset) => {
    selectedAsset.value = asset;
    showDetailModal.value = true;
};

const openDeleteModal = (asset) => {
    selectedAsset.value = asset;
    showDeleteModal.value = true;
};

const handleImageChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.foto = file;
        imagePreview.value = URL.createObjectURL(file);
    }
};

const submitAdd = () => {
    form.harga_per_benda = form.harga_perolehan;
    form.post(route('assets.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showAddModal.value = false;
            form.reset();
            imagePreview.value = null;
        },
    });
};

const submitEdit = () => {
    form.harga_per_benda = form.harga_perolehan;
    form.transform((data) => ({
        ...data,
        _method: 'put',
    })).post(route('assets.update', form.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            form.reset();
            imagePreview.value = null;
        },
    });
};

const confirmDelete = () => {
    if (selectedAsset.value) {
        router.delete(route('assets.destroy', selectedAsset.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                showDeleteModal.value = false;
                selectedAsset.value = null;
            },
        });
    }
};

const goToPrintPage = () => {
    router.visit('/cetak-laporan');
};
</script>

<template>
    <Head title="Asset" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <p class="text-xs text-slate-500 font-medium">Inventory / Asset</p>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">
                        Asset
                    </h2>
                </div>
                <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                    <button 
                        @click="openAddModal"
                        class="inline-flex items-center px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm rounded-xl shadow-sm transition gap-2 cursor-pointer">
                        + Tambah Aset
                    </button>
                    <button 
                        @click="goToPrintPage"
                        class="inline-flex items-center px-4 py-2.5 bg-slate-800 hover:bg-slate-900 text-white font-semibold text-sm rounded-xl shadow-sm transition gap-2 cursor-pointer">
                        Cetak Laporan
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8 bg-slate-50/50 min-h-[calc(100vh-65px)]">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
                
                <!-- Alert Notifikasi -->
                <div v-if="$page.props.flash?.success" class="p-4 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-800 rounded-r-xl shadow-sm flex justify-between items-center">
                    <span class="font-medium text-sm">{{ $page.props.flash.success }}</span>
                </div>

                <!-- CONTAINER STYLED "ASSET" -->
                <div class="relative bg-white border-2 border-slate-200/90 rounded-2xl p-6 sm:p-8 shadow-sm">
                    <div class="absolute top-2 left-6 bg-slate-700 text-white px-4 py-1 rounded-full text-xs font-bold tracking-widest uppercase shadow-sm">
                        ASSET
                    </div>

                    <!-- 4 Cards Statistik -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mt-2">
                        <div class="bg-gradient-to-br from-blue-50 to-indigo-50/30 p-5 rounded-xl border border-blue-100 flex flex-col justify-between hover:shadow-md transition">
                            <div class="flex items-center justify-between text-blue-700">
                                <span class="text-xs font-bold uppercase tracking-wider">Total Kuantitas Aset</span>
                                <div class="p-2 bg-blue-500 text-white rounded-lg">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                </div>
                            </div>
                            <div class="mt-4">
                                <h3 class="text-3xl font-extrabold text-slate-900">
                                    {{ stats?.total_kuantitas ?? 0 }} <span class="text-base font-semibold text-slate-500">Unit</span>
                                </h3>
                                <p v-if="stats?.total_dipinjam > 0" class="text-xs text-amber-600 font-semibold mt-1">
                                    Tersedia: {{ stats.total_tersedia }} | Dipinjam: {{ stats.total_dipinjam }}
                                </p>
                            </div>
                        </div>

                        <div class="bg-gradient-to-br from-emerald-50 to-teal-50/30 p-5 rounded-xl border border-emerald-100 flex flex-col justify-between hover:shadow-md transition">
                            <div class="flex items-center justify-between text-emerald-700">
                                <span class="text-xs font-bold uppercase tracking-wider">Total Nilai Aset</span>
                                <div class="p-2 bg-emerald-500 text-white rounded-lg">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                            </div>
                            <div class="mt-4">
                                <h3 class="text-2xl font-extrabold text-slate-900 tracking-tight">
                                    {{ formatRupiah(stats?.total_nilai) }}
                                </h3>
                            </div>
                        </div>

                        <div class="bg-gradient-to-br from-sky-50 to-cyan-50/30 p-5 rounded-xl border border-sky-100 flex flex-col justify-between hover:shadow-md transition">
                            <div class="flex items-center justify-between text-sky-700">
                                <span class="text-xs font-bold uppercase tracking-wider">Aset Kondisi Baik</span>
                                <div class="p-2 bg-sky-500 text-white rounded-lg">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                            </div>
                            <div class="mt-4">
                                <h3 class="text-3xl font-extrabold text-slate-900">
                                    {{ stats?.kondisi_baik ?? 0 }} <span class="text-base font-semibold text-slate-500">Unit</span>
                                </h3>
                            </div>
                        </div>

                        <div class="bg-gradient-to-br from-rose-50 to-red-50/30 p-5 rounded-xl border border-rose-100 flex flex-col justify-between hover:shadow-md transition">
                            <div class="flex items-center justify-between text-rose-700">
                                <span class="text-xs font-bold uppercase tracking-wider">Aset Kondisi Rusak</span>
                                <div class="p-2 bg-rose-500 text-white rounded-lg">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                </div>
                            </div>
                            <div class="mt-4">
                                <h3 class="text-3xl font-extrabold text-slate-900">
                                    {{ stats?.kondisi_rusak ?? 0 }} <span class="text-base font-semibold text-slate-500">Unit</span>
                                </h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PENCARIAN & FILTER -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
                        <div class="relative">
                            <input 
                                v-model="filterForm.search"
                                @keyup.enter="applyFilters"
                                type="text" 
                                placeholder="Cari Barang / Kode / Merek..." 
                                class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-300 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 transition" />
                            <svg class="w-5 h-5 text-slate-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>

                        <div>
                            <select v-model="filterForm.kondisi" @change="applyFilters" class="w-full py-2.5 px-3 bg-slate-50 border border-slate-300 text-slate-800 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 transition">
                                <option value="Semua">- Semua Kondisi -</option>
                                <option value="Baik">Baik</option>
                                <option value="Rusak">Rusak</option>
                                <option value="Perlu Perbaikan">Perlu Perbaikan</option>
                            </select>
                        </div>

                        <div>
                            <select v-model="filterForm.kategori" @change="applyFilters" class="w-full py-2.5 px-3 bg-slate-50 border border-slate-300 text-slate-800 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 transition">
                                <option value="Semua">- Semua Kategori -</option>
                                <option v-for="cat in (categories || [])" :key="cat" :value="cat">{{ cat }}</option>
                            </select>
                        </div>

                        <div>
                            <select v-model="filterForm.lokasi" @change="applyFilters" class="w-full py-2.5 px-3 bg-slate-50 border border-slate-300 text-slate-800 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 transition">
                                <option value="Semua">- Semua Lokasi -</option>
                                <option v-for="loc in (locations || [])" :key="loc" :value="loc">{{ loc }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 justify-end">
                        <button @click="applyFilters" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold text-sm rounded-xl transition shadow-sm cursor-pointer">
                            Cari Data
                        </button>
                        <button @click="resetFilters" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-sm rounded-xl transition cursor-pointer">
                            Reset
                        </button>
                    </div>
                </div>

                <!-- TABEL DATA ASET -->
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-900 text-white text-xs font-bold uppercase tracking-wider">
                                    <th class="p-4 text-center">Foto</th>
                                    <th class="p-4 text-center">QR / Barcode</th>
                                    <th class="p-4">Kode Aset</th>
                                    <th class="p-4">Nama Barang</th>
                                    <th class="p-4">Merek</th>
                                    <th class="p-4">Kategori</th>
                                    <th class="p-4 text-center">Tanggal</th>
                                    <th class="p-4">Lokasi</th>
                                    <th class="p-4 text-center">Jumlah</th>
                                    <th class="p-4 text-right">Harga Per Benda</th>
                                    <th class="p-4 text-right">Harga Total</th>
                                    <th class="p-4 text-center">Kondisi</th>
                                    <th class="p-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 text-sm text-slate-700">
                                <tr v-if="!assets || assets.length === 0">
                                    <td colspan="13" class="p-8 text-center text-slate-500">
                                        Tidak ada data aset ditemukan.
                                    </td>
                                </tr>
                                <tr v-else v-for="asset in assets" :key="asset.id" class="hover:bg-slate-50/80 transition">
                                    <td class="p-4 text-center">
                                        <img :src="asset.foto || '/images/default-asset.png'" alt="Foto Aset" class="w-12 h-12 rounded-lg object-cover mx-auto border border-slate-200" />
                                    </td>
                                    <td class="p-4 text-center">
                                        <div class="flex flex-col items-center gap-1">
                                            <img :src="getQrCodeUrl(asset)" alt="QR Code" title="Scan QR Code" class="w-10 h-10 rounded border border-slate-200" />
                                            <img :src="getBarcodeUrl(asset)" alt="Barcode" class="h-5 object-contain" />
                                        </div>
                                    </td>
                                    <td class="p-4 font-mono font-bold text-slate-900">{{ asset.kode_aset }}</td>
                                    <td class="p-4 font-semibold text-slate-900">{{ asset.nama_barang }}</td>
                                    <td class="p-4 text-slate-600">{{ asset.merek || '-' }}</td>
                                    <td class="p-4">
                                        <span class="px-2.5 py-1 bg-slate-100 text-slate-700 text-xs font-medium rounded-full">
                                            {{ asset.kategori }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-center text-xs font-medium text-slate-600">
                                        {{ formatDate(asset.tanggal_perolehan) }}
                                    </td>
                                    <td class="p-4 text-slate-700 font-medium">{{ asset.lokasi }}</td>
                                    <td class="p-4 text-center whitespace-nowrap">
                                        <div class="font-bold text-slate-900">{{ asset.jumlah }} Unit</div>
                                        <div class="text-[11px] text-emerald-600 font-semibold">
                                            Tersedia: {{ asset.stok_tersedia ?? asset.jumlah }}
                                        </div>
                                        <div v-if="(asset.jumlah - (asset.stok_tersedia ?? asset.jumlah)) > 0" class="text-[11px] text-amber-600 font-semibold">
                                            Dipinjam: {{ asset.jumlah - (asset.stok_tersedia ?? asset.jumlah) }}
                                        </div>
                                    </td>
                                    <td class="p-4 text-right font-medium text-slate-800 whitespace-nowrap">
                                        {{ formatRupiah(getHargaSatuan(asset)) }}
                                    </td>
                                    <td class="p-4 text-right font-bold text-emerald-700 whitespace-nowrap">
                                        {{ formatRupiah(getHargaTotal(asset)) }}
                                    </td>
                                    <td class="p-4 text-center">
                                        <span 
                                            :class="{
                                                'bg-emerald-100 text-emerald-800 border border-emerald-200': asset.kondisi === 'Baik',
                                                'bg-rose-100 text-rose-800 border border-rose-200': asset.kondisi === 'Rusak',
                                                'bg-amber-100 text-amber-800 border border-amber-200': asset.kondisi === 'Perlu Perbaikan'
                                            }"
                                            class="px-2.5 py-1 rounded-full text-xs font-semibold">
                                            {{ asset.kondisi }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-center">
                                        <div class="inline-flex items-center gap-1.5">
                                            <button @click="openDetailModal(asset)" class="px-2.5 py-1.5 bg-blue-50 text-blue-600 hover:bg-blue-100 rounded-lg text-xs font-medium transition cursor-pointer">
                                                Detail
                                            </button>
                                            <button @click="openEditModal(asset)" class="px-2.5 py-1.5 bg-amber-50 text-amber-600 hover:bg-amber-100 rounded-lg text-xs font-medium transition cursor-pointer">
                                                Edit
                                            </button>
                                            <button @click="openDeleteModal(asset)" class="px-2.5 py-1.5 bg-rose-50 text-rose-600 hover:bg-rose-100 rounded-lg text-xs font-medium transition cursor-pointer">
                                                Hapus
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <!-- MODAL TAMBAH / EDIT ASET -->
        <div v-if="showAddModal || showEditModal" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-2xl space-y-6 my-8">
                <div class="flex justify-between items-center border-b pb-4">
                    <h3 class="text-xl font-bold text-slate-900">
                        {{ showAddModal ? 'Tambah Aset Baru' : 'Edit Data Aset' }}
                    </h3>
                    <button @click="showAddModal = showEditModal = false" class="text-slate-400 hover:text-slate-600 font-bold text-xl cursor-pointer">&times;</button>
                </div>

                <!-- Ringkasan Error Validasi -->
                <div v-if="Object.keys(form.errors).length > 0" class="p-3.5 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-700 space-y-1">
                    <p class="font-bold">Mohon periksa kembali data yang diinput:</p>
                    <ul class="list-disc list-inside">
                        <li v-for="(err, key) in form.errors" :key="key">{{ err }}</li>
                    </ul>
                </div>

                <form @submit.prevent="showAddModal ? submitAdd() : submitEdit()" class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Kode Aset *</label>
                        <input v-model="form.kode_aset" type="text" class="w-full rounded-xl border-slate-300" required />
                        <span v-if="form.errors.kode_aset" class="text-xs text-rose-500">{{ form.errors.kode_aset }}</span>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Nama Barang *</label>
                        <input v-model="form.nama_barang" type="text" class="w-full rounded-xl border-slate-300" required />
                        <span v-if="form.errors.nama_barang" class="text-xs text-rose-500">{{ form.errors.nama_barang }}</span>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Merek</label>
                        <input v-model="form.merek" type="text" class="w-full rounded-xl border-slate-300" />
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Kategori *</label>
                        <input v-model="form.kategori" type="text" class="w-full rounded-xl border-slate-300" required />
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Lokasi *</label>
                        <input v-model="form.lokasi" type="text" class="w-full rounded-xl border-slate-300" required />
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Tanggal Perolehan</label>
                        <input v-model="form.tanggal_perolehan" type="date" class="w-full rounded-xl border-slate-300" />
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Jumlah *</label>
                        <input v-model="form.jumlah" type="number" :min="showAddModal ? 1 : 0" class="w-full rounded-xl border-slate-300" required />
                        <span v-if="form.errors.jumlah" class="text-xs text-rose-500">{{ form.errors.jumlah }}</span>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Harga Per Benda (Rp) *</label>
                        <input v-model="form.harga_perolehan" type="number" min="0" class="w-full rounded-xl border-slate-300" required />
                    </div>

                    <div class="md:col-span-2 bg-emerald-50 border border-emerald-200 rounded-xl p-3 flex justify-between items-center">
                        <span class="text-xs font-semibold text-emerald-800">
                            Harga Total Otomatis (Harga Per Benda × Jumlah):
                        </span>
                        <span class="text-base font-extrabold text-emerald-700">
                            {{ formatRupiah(calculatedHargaTotal) }}
                        </span>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Kondisi *</label>
                        <select v-model="form.kondisi" class="w-full rounded-xl border-slate-300">
                            <option value="Baik">Baik</option>
                            <option value="Rusak">Rusak</option>
                            <option value="Perlu Perbaikan">Perlu Perbaikan</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Supplier (Opsional)</label>
                        <input v-model="form.supplier" type="text" placeholder="Nama Toko / Supplier" class="w-full rounded-xl border-slate-300" />
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Nomor Invoice (Opsional)</label>
                        <input v-model="form.nomor_invoice" type="text" placeholder="INV-..." class="w-full rounded-xl border-slate-300" />
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Foto Aset</label>
                        <input type="file" @change="handleImageChange" accept="image/*" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200" />
                        <img v-if="imagePreview" :src="imagePreview" class="w-20 h-20 rounded-xl object-cover mt-2 border" />
                    </div>

                    <div class="md:col-span-2">
                        <label class="block font-medium text-slate-700 mb-1">Keterangan</label>
                        <textarea v-model="form.keterangan" rows="2" class="w-full rounded-xl border-slate-300"></textarea>
                    </div>

                    <div class="md:col-span-2 flex justify-end gap-3 pt-4 border-t">
                        <button type="button" @click="showAddModal = showEditModal = false" class="px-4 py-2 bg-slate-100 rounded-xl font-semibold text-slate-700 cursor-pointer">
                            Batal
                        </button>
                        <button type="submit" :disabled="form.processing" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-xl font-semibold shadow-sm cursor-pointer">
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Aset' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL DETAIL ASET -->
        <div v-if="showDetailModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4">
                <div class="flex justify-between items-center border-b pb-3">
                    <h3 class="text-lg font-bold text-slate-900">Detail Aset</h3>
                    <button @click="showDetailModal = false" class="text-slate-400 hover:text-slate-600 font-bold text-xl cursor-pointer">&times;</button>
                </div>
                <div v-if="selectedAsset" class="space-y-3 text-sm">
                    <div class="flex items-center gap-4 border-b pb-3">
                        <img :src="getQrCodeUrl(selectedAsset)" class="w-24 h-24 rounded-lg border p-1" />
                        <div class="space-y-1">
                            <h4 class="font-bold text-base text-slate-900">{{ selectedAsset.nama_barang }}</h4>
                            <p class="text-slate-500 font-mono">{{ selectedAsset.kode_aset }}</p>
                            <img :src="getBarcodeUrl(selectedAsset)" class="h-8 object-contain" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-slate-600">
                        <p><strong>Merek:</strong> {{ selectedAsset.merek || '-' }}</p>
                        <p><strong>Kategori:</strong> {{ selectedAsset.kategori }}</p>
                        <p><strong>Tanggal:</strong> {{ formatDate(selectedAsset.tanggal_perolehan) }}</p>
                        <p><strong>Lokasi:</strong> {{ selectedAsset.lokasi }}</p>
                        <p><strong>Jumlah Total:</strong> {{ selectedAsset.jumlah }} Unit</p>
                        <p><strong>Stok Tersedia:</strong> {{ selectedAsset.stok_tersedia ?? selectedAsset.jumlah }} Unit</p>
                        <p><strong>Harga Per Benda:</strong> {{ formatRupiah(getHargaSatuan(selectedAsset)) }}</p>
                        <p><strong>Harga Total:</strong> {{ formatRupiah(getHargaTotal(selectedAsset)) }}</p>
                        <p><strong>Kondisi:</strong> {{ selectedAsset.kondisi }}</p>
                        <p><strong>Supplier:</strong> {{ selectedAsset.supplier || '-' }}</p>
                        <p><strong>No. Invoice:</strong> {{ selectedAsset.nomor_invoice || '-' }}</p>
                    </div>
                    <p class="text-slate-600"><strong>Keterangan:</strong> {{ selectedAsset.keterangan || '-' }}</p>
                </div>
            </div>
        </div>

        <!-- MODAL KONFIRMASI HAPUS -->
        <div v-if="showDeleteModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl text-center space-y-4">
                <div class="w-12 h-12 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mx-auto">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900">Hapus Data Aset?</h3>
                <p class="text-sm text-slate-600">Apakah Anda yakin ingin menghapus aset <strong>{{ selectedAsset?.nama_barang }}</strong>?</p>
                <div class="flex justify-center gap-3 pt-2">
                    <button @click="showDeleteModal = false" class="px-4 py-2 bg-slate-100 text-slate-700 font-semibold rounded-xl text-sm cursor-pointer">
                        Batal
                    </button>
                    <button @click="confirmDelete" class="px-5 py-2 bg-rose-600 text-white font-semibold rounded-xl text-sm shadow-sm hover:bg-rose-700 cursor-pointer">
                        Hapus
                    </button>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>