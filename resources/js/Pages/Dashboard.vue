<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    assets: { type: Array, default: () => [] },
    tabelHargaAset: { type: [Object, Array], default: () => null },
    totalNilaiTabelHarga: { type: Number, default: 0 },
    stats: {
        type: Object,
        default: () => ({
            total_jenis_aset: 0,
            total_kuantitas: 0,
            total_kuantitas_aset: 0,
            total_nilai: 0,
            total_nilai_aset: 0,
            total_barang_masuk: 0,
            total_barang_keluar: 0,
            total_barang_dipinjam: 0,
            total_belum_dikembalikan: 0,
            kondisi_baik: 0,
            aset_kondisi_baik: 0,
            kondisi_rusak: 0,
            aset_kondisi_rusak: 0,
            stok_menipis: 0,
        })
    },
    grafikKeluarMasuk: {
        type: Object,
        default: () => ({ labels: [], masuk: [], keluar: [], dipinjam: [], dikembalikan: [] })
    },
    grafikNilaiKategori: { type: Array, default: () => [] },
    aktivitasTerbaru: { type: Array, default: () => [] },
    notifikasi: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    kategoriList: { type: Array, default: () => [] },
    periode: { type: String, default: '' },
    filters: { type: Object, default: () => ({}) },
});

const searchHarga = ref('');
const selectedKategoriHarga = ref('Semua');

const activePeriode = ref(
    props.periode ||
    props.filters?.periode ||
    new URLSearchParams(window.location.search).get('periode') ||
    'bulanan'
);

watch(
    () => props.periode || props.filters?.periode,
    (newVal) => {
        if (newVal) activePeriode.value = newVal;
    }
);

const changePeriode = (p) => {
    activePeriode.value = p;
    router.get('/dashboard', { periode: p }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0
    }).format(val || 0);
};

const allAssetsList = computed(() => {
    if (Array.isArray(props.assets) && props.assets.length > 0) {
        return props.assets;
    }
    if (Array.isArray(props.tabelHargaAset)) {
        return props.tabelHargaAset;
    }
    if (props.tabelHargaAset && Array.isArray(props.tabelHargaAset.data)) {
        return props.tabelHargaAset.data;
    }
    return [];
});

const categoryOptions = computed(() => {
    if (Array.isArray(props.categories) && props.categories.length > 0) return props.categories;
    if (Array.isArray(props.kategoriList) && props.kategoriList.length > 0) return props.kategoriList;
    return (props.grafikNilaiKategori || []).map((k) => k.kategori).filter(Boolean);
});

const getHargaSatuan = (asset) => Number(asset.harga_per_benda ?? asset.harga_perolehan ?? 0);
const getHargaTotal = (asset) => {
    const hitung = (Number(asset.jumlah) || 0) * getHargaSatuan(asset);
    return hitung > 0 ? hitung : Number(asset.harga_total ?? 0);
};

const filteredHargaAssets = computed(() => {
    return allAssetsList.value.filter((item) => {
        const matchSearch =
            !searchHarga.value ||
            item.nama_barang?.toLowerCase().includes(searchHarga.value.toLowerCase()) ||
            item.kode_aset?.toLowerCase().includes(searchHarga.value.toLowerCase());
        const matchKategori =
            selectedKategoriHarga.value === 'Semua' || item.kategori === selectedKategoriHarga.value;
        return matchSearch && matchKategori;
    });
});

const totalUnitTerfilter = computed(() => {
    return filteredHargaAssets.value.reduce((acc, item) => acc + (Number(item.jumlah) || 0), 0);
});

const fallbackTotalUnitKategori = computed(() => {
    return (props.grafikNilaiKategori || []).reduce((sum, k) => sum + (Number(k.total_unit) || 0), 0);
});

const fallbackTotalNilaiKategori = computed(() => {
    return (props.grafikNilaiKategori || []).reduce((sum, k) => sum + (Number(k.total_nilai) || 0), 0);
});

const totalNilaiTabelHargaComputed = computed(() => {
    const sumRows = filteredHargaAssets.value.reduce((acc, item) => acc + getHargaTotal(item), 0);
    if (sumRows > 0) return sumRows;
    if (props.totalNilaiTabelHarga > 0 && !searchHarga.value && selectedKategoriHarga.value === 'Semua') {
        return props.totalNilaiTabelHarga;
    }
    return sumRows;
});

const syncedStats = computed(() => {
    const kuantitas =
        props.stats?.total_kuantitas ||
        props.stats?.total_kuantitas_aset ||
        fallbackTotalUnitKategori.value ||
        0;

    const nilai =
        props.stats?.total_nilai ||
        props.stats?.total_nilai_aset ||
        fallbackTotalNilaiKategori.value ||
        0;

    return {
        total_jenis_aset: props.stats?.total_jenis_aset ?? allAssetsList.value.length,
        total_kuantitas: kuantitas,
        total_nilai: nilai,
        total_barang_masuk: props.stats?.total_barang_masuk || kuantitas,
        total_barang_keluar: props.stats?.total_barang_keluar ?? 0,
        total_barang_dipinjam: props.stats?.total_barang_dipinjam ?? 0,
        total_belum_dikembalikan: props.stats?.total_belum_dikembalikan ?? 0,
        kondisi_baik: props.stats?.kondisi_baik || props.stats?.aset_kondisi_baik || kuantitas,
        kondisi_rusak: props.stats?.kondisi_rusak ?? props.stats?.aset_kondisi_rusak ?? 0,
        stok_menipis: props.stats?.stok_menipis ?? 0,
    };
});

const maxKategoriValue = computed(() => {
    if (!props.grafikNilaiKategori.length) return 1;
    return Math.max(...props.grafikNilaiKategori.map((k) => Number(k.total_nilai) || 0), 1);
});

const rasioKondisiBaik = computed(() => {
    if (!syncedStats.value.total_kuantitas) return 100;
    return Math.round((syncedStats.value.kondisi_baik / syncedStats.value.total_kuantitas) * 100);
});

// Helper visual untuk log aktivitas (Anti-Slop semantic styling)
const getActivityMeta = (jenis = '') => {
    const lower = jenis.toLowerCase();
    if (lower.includes('hapus') || lower.includes('keluar') || lower.includes('rusak')) {
        return {
            bg: 'bg-rose-50 text-rose-600 border-rose-200/80',
            dot: 'bg-rose-500'
        };
    }
    if (lower.includes('pinjam')) {
        return {
            bg: 'bg-indigo-50 text-indigo-600 border-indigo-200/80',
            dot: 'bg-indigo-500'
        };
    }
    if (lower.includes('kembali') || lower.includes('masuk') || lower.includes('tambah')) {
        return {
            bg: 'bg-emerald-50 text-emerald-600 border-emerald-200/80',
            dot: 'bg-emerald-500'
        };
    }
    if (lower.includes('pindah') || lower.includes('mutasi')) {
        return {
            bg: 'bg-sky-50 text-sky-600 border-sky-200/80',
            dot: 'bg-sky-500'
        };
    }
    return {
        bg: 'bg-amber-50 text-amber-600 border-amber-200/80',
        dot: 'bg-amber-500'
    };
};

const getStatusBadgeClass = (status = '') => {
    const s = status.toLowerCase();
    if (s === 'selesai' || s === 'dikembalikan' || s === 'tersedia') {
        return 'bg-emerald-50 text-emerald-700 border-emerald-200';
    }
    if (s.includes('pinjam') || s.includes('proses')) {
        return 'bg-amber-50 text-amber-700 border-amber-200';
    }
    return 'bg-slate-100 text-slate-700 border-slate-200';
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <p class="text-xs text-slate-500 font-medium">Inventory / Dashboard</p>
                    <h2 class="font-bold text-2xl text-slate-900 tracking-tight leading-tight">
                        Dashboard
                    </h2>
                </div>
            </div>
        </template>


        <!-- Background Abu-Abu Bersih Standar -->
        <div class="py-8 bg-slate-100/80 min-h-[calc(100vh-65px)]">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- A. 10 KARTU STATISTIK -->
                <div class="bg-white border border-slate-200/90 rounded-xl p-6 shadow-xs">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                            Ringkasan Statistik Inventaris
                        </h3>
                        <span class="text-xs text-slate-400 font-medium">Data Real-time</span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3.5 tabular-nums">
                        <div class="bg-slate-50/70 p-4 rounded-xl border border-slate-200/80 transition-all duration-150 hover:-translate-y-0.5 active:scale-[0.99] hover:border-slate-300 hover:shadow-xs">
                            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wide">1. Total Jenis Aset</span>
                            <p class="text-2xl font-extrabold text-slate-900 mt-1.5">{{ syncedStats.total_jenis_aset }}</p>
                        </div>
                        <div class="bg-blue-50/40 p-4 rounded-xl border border-blue-200/70 transition-all duration-150 hover:-translate-y-0.5 active:scale-[0.99] hover:border-blue-300 hover:shadow-xs">
                            <span class="text-[11px] font-bold text-blue-700 uppercase tracking-wide">2. Total Kuantitas</span>
                            <p class="text-2xl font-extrabold text-slate-900 mt-1.5">{{ syncedStats.total_kuantitas }} <span class="text-xs font-semibold text-slate-500">Unit</span></p>
                        </div>
                        <div class="bg-emerald-50/40 p-4 rounded-xl border border-emerald-200/70 transition-all duration-150 hover:-translate-y-0.5 active:scale-[0.99] hover:border-emerald-300 hover:shadow-xs">
                            <span class="text-[11px] font-bold text-emerald-700 uppercase tracking-wide">3. Total Nilai Aset</span>
                            <p class="text-lg font-extrabold text-slate-900 mt-2 truncate" :title="formatRupiah(syncedStats.total_nilai)">
                                {{ formatRupiah(syncedStats.total_nilai) }}
                            </p>
                        </div>
                        <div class="bg-teal-50/40 p-4 rounded-xl border border-teal-200/70 transition-all duration-150 hover:-translate-y-0.5 active:scale-[0.99] hover:border-teal-300 hover:shadow-xs">
                            <span class="text-[11px] font-bold text-teal-700 uppercase tracking-wide">4. Barang Masuk</span>
                            <p class="text-2xl font-extrabold text-slate-900 mt-1.5">{{ syncedStats.total_barang_masuk }}</p>
                        </div>
                        <div class="bg-rose-50/40 p-4 rounded-xl border border-rose-200/70 transition-all duration-150 hover:-translate-y-0.5 active:scale-[0.99] hover:border-rose-300 hover:shadow-xs">
                            <span class="text-[11px] font-bold text-rose-700 uppercase tracking-wide">5. Barang Keluar</span>
                            <p class="text-2xl font-extrabold text-slate-900 mt-1.5">{{ syncedStats.total_barang_keluar }}</p>
                        </div>
                        <div class="bg-indigo-50/40 p-4 rounded-xl border border-indigo-200/70 transition-all duration-150 hover:-translate-y-0.5 active:scale-[0.99] hover:border-indigo-300 hover:shadow-xs">
                            <span class="text-[11px] font-bold text-indigo-700 uppercase tracking-wide">6. Sedang Dipinjam</span>
                            <p class="text-2xl font-extrabold text-slate-900 mt-1.5">{{ syncedStats.total_barang_dipinjam }}</p>
                        </div>
                        <div class="bg-amber-50/40 p-4 rounded-xl border border-amber-200/70 transition-all duration-150 hover:-translate-y-0.5 active:scale-[0.99] hover:border-amber-300 hover:shadow-xs">
                            <span class="text-[11px] font-bold text-amber-700 uppercase tracking-wide">7. Belum Kembali</span>
                            <p class="text-2xl font-extrabold text-amber-700 mt-1.5">{{ syncedStats.total_belum_dikembalikan }}</p>
                        </div>
                        <div class="bg-sky-50/40 p-4 rounded-xl border border-sky-200/70 transition-all duration-150 hover:-translate-y-0.5 active:scale-[0.99] hover:border-sky-300 hover:shadow-xs">
                            <span class="text-[11px] font-bold text-sky-700 uppercase tracking-wide">8. Kondisi Baik</span>
                            <p class="text-2xl font-extrabold text-slate-900 mt-1.5">{{ syncedStats.kondisi_baik }} <span class="text-xs font-semibold text-slate-500">Unit</span></p>
                        </div>
                        <div class="bg-red-50/40 p-4 rounded-xl border border-red-200/70 transition-all duration-150 hover:-translate-y-0.5 active:scale-[0.99] hover:border-red-300 hover:shadow-xs">
                            <span class="text-[11px] font-bold text-red-700 uppercase tracking-wide">9. Kondisi Rusak</span>
                            <p class="text-2xl font-extrabold text-slate-900 mt-1.5">{{ syncedStats.kondisi_rusak }} <span class="text-xs font-semibold text-slate-500">Unit</span></p>
                        </div>
                        <div class="bg-orange-50/40 p-4 rounded-xl border border-orange-200/70 transition-all duration-150 hover:-translate-y-0.5 active:scale-[0.99] hover:border-orange-300 hover:shadow-xs">
                            <span class="text-[11px] font-bold text-orange-700 uppercase tracking-wide">10. Stok Menipis</span>
                            <p class="text-2xl font-extrabold text-orange-700 mt-1.5">{{ syncedStats.stok_menipis }} <span class="text-xs font-semibold text-orange-600/80">Jenis</span></p>
                        </div>
                    </div>
                </div>

                <!-- B & C. GRAFIK KELUAR-MASUK & GRAFIK NILAI ASET -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- B. Grafik Keluar-Masuk -->
                    <div class="bg-white rounded-xl border border-slate-200/90 p-6 shadow-xs">
                        <div class="flex flex-wrap justify-between items-center gap-2 mb-5">
                            <div>
                                <h3 class="font-bold text-slate-900 text-base tracking-tight">Ringkasan Arus Barang</h3>
                                <p class="text-xs text-slate-500">Rekapitulasi transaksi masuk, keluar, dan peminjaman</p>
                            </div>
                            <div class="inline-flex p-1 bg-slate-100 rounded-lg border border-slate-200/60">
                                <button
                                    v-for="p in ['harian', 'mingguan', 'bulanan', 'tahunan']"
                                    :key="p"
                                    type="button"
                                    @click="changePeriode(p)"
                                    :class="activePeriode === p ? 'bg-white text-slate-900 shadow-2xs border border-slate-200/80' : 'text-slate-600 hover:text-slate-900'"
                                    class="px-2.5 py-1 rounded-md text-xs font-semibold capitalize transition-all duration-150 active:scale-95 cursor-pointer"
                                >
                                    {{ p }}
                                </button>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left border-collapse tabular-nums">
                                <thead>
                                    <tr class="border-y border-slate-200 bg-slate-50/70 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                                        <th class="py-2.5 px-3">Periode</th>
                                        <th class="py-2.5 px-3 text-right text-emerald-700">Masuk</th>
                                        <th class="py-2.5 px-3 text-right text-rose-700">Keluar</th>
                                        <th class="py-2.5 px-3 text-right text-indigo-700">Dipinjam</th>
                                        <th class="py-2.5 px-3 text-right text-sky-700">Kembali</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr
                                        v-for="(label, i) in (grafikKeluarMasuk.labels || [])"
                                        :key="i"
                                        class="hover:bg-slate-50/80 transition-colors"
                                    >
                                        <td class="py-2.5 px-3 font-semibold text-slate-800">{{ label }}</td>
                                        <td class="py-2.5 px-3 text-right font-bold text-emerald-600">{{ grafikKeluarMasuk.masuk?.[i] ?? 0 }}</td>
                                        <td class="py-2.5 px-3 text-right font-bold text-rose-600">{{ grafikKeluarMasuk.keluar?.[i] ?? 0 }}</td>
                                        <td class="py-2.5 px-3 text-right font-bold text-indigo-600">{{ grafikKeluarMasuk.dipinjam?.[i] ?? 0 }}</td>
                                        <td class="py-2.5 px-3 text-right font-bold text-sky-600">{{ grafikKeluarMasuk.dikembalikan?.[i] ?? 0 }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- C. Grafik Nilai Aset Berdasarkan Kategori -->
                    <div class="bg-white rounded-xl border border-slate-200/90 p-6 shadow-xs flex flex-col justify-between">
                        <div>
                            <div class="mb-5">
                                <h3 class="font-bold text-slate-900 text-base tracking-tight">Distribusi Nilai per Kategori</h3>
                                <p class="text-xs text-slate-500">Proporsi valuasi aset berdasarkan kelompok barang</p>
                            </div>

                            <div v-if="!grafikNilaiKategori.length" class="text-sm text-slate-400 py-10 text-center border border-dashed border-slate-200 rounded-xl">
                                Belum ada data kategori aset.
                            </div>
                            <div v-else class="space-y-4 tabular-nums">
                                <div
                                    v-for="kat in grafikNilaiKategori"
                                    :key="kat.kategori"
                                    class="p-3 rounded-lg border border-slate-100 bg-slate-50/40 hover:bg-slate-50 transition-colors"
                                >
                                    <div class="flex justify-between items-baseline text-xs mb-2">
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-slate-800">{{ kat.kategori }}</span>
                                            <span class="px-2 py-0.5 bg-slate-200/70 text-slate-700 rounded text-[11px] font-semibold">
                                                {{ kat.total_unit }} unit
                                            </span>
                                        </div>
                                        <span class="text-slate-900 font-extrabold">{{ formatRupiah(kat.total_nilai) }}</span>
                                    </div>
                                    <div class="w-full h-2 bg-slate-200/70 rounded-full overflow-hidden">
                                        <div
                                            class="h-full bg-emerald-600 rounded-full transition-all duration-500"
                                            :style="{ width: `${Math.max(Math.round((Number(kat.total_nilai) / maxKategoriValue) * 100), 4)}%` }"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- D. TABEL HARGA ASET (ANTI-SLOP DATA TABLE) -->
                <div class="bg-white rounded-xl border border-slate-200/90 shadow-xs overflow-hidden">
                    <!-- Table Header Bar -->
                    <div class="p-6 pb-5 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div>
                            <div class="flex items-center gap-2.5">
                                <h3 class="font-bold text-slate-900 text-base tracking-tight">Tabel Valuasi Harga Aset</h3>
                                <span class="px-2.5 py-0.5 text-xs font-semibold bg-slate-100 text-slate-700 rounded-full border border-slate-200 tabular-nums">
                                    {{ filteredHargaAssets.length }} Item
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">Daftar rincian harga satuan dan nilai total per kode aset</p>
                        </div>

                        <!-- Filter Controls -->
                        <div class="flex flex-wrap items-center gap-2.5 w-full sm:w-auto">
                            <div class="relative flex-1 sm:flex-initial sm:w-64">
                                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                <input
                                    v-model="searchHarga"
                                    type="text"
                                    placeholder="Cari kode atau nama barang..."
                                    class="w-full pl-9 pr-8 py-2 bg-slate-50 hover:bg-slate-100/70 focus:bg-white border border-slate-200 focus:border-slate-400 rounded-lg text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-0 transition-colors"
                                />
                                <button
                                    v-if="searchHarga"
                                    type="button"
                                    @click="searchHarga = ''"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-sm font-bold leading-none cursor-pointer"
                                    title="Hapus pencarian"
                                >
                                    &times;
                                </button>
                            </div>

                            <select
                                v-model="selectedKategoriHarga"
                                class="py-2 pl-3 pr-8 bg-slate-50 hover:bg-slate-100/70 focus:bg-white border border-slate-200 focus:border-slate-400 rounded-lg text-xs font-medium text-slate-700 focus:outline-none focus:ring-0 transition-colors cursor-pointer"
                            >
                                <option value="Semua">Semua Kategori</option>
                                <option v-for="c in categoryOptions" :key="c" :value="c">{{ c }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- Data Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-sm tabular-nums">
                            <thead>
                                <tr class="bg-slate-50/90 border-y border-slate-200 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                                    <th class="py-3 px-6">Kode Aset</th>
                                    <th class="py-3 px-4">Nama Barang</th>
                                    <th class="py-3 px-4">Kategori</th>
                                    <th class="py-3 px-4 text-right">Jumlah</th>
                                    <th class="py-3 px-4 text-right">Harga Satuan</th>
                                    <th class="py-3 px-6 text-right">Harga Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-if="!filteredHargaAssets.length">
                                    <td colspan="6" class="py-10 px-6 text-center">
                                        <p class="text-sm font-medium text-slate-600">Tidak ada aset yang cocok dengan filter</p>
                                        <p class="text-xs text-slate-400 mt-1">Coba gunakan kata kunci lain atau pilih Semua Kategori.</p>
                                    </td>
                                </tr>
                                <tr
                                    v-else
                                    v-for="item in filteredHargaAssets"
                                    :key="item.id || item.kode_aset"
                                    class="group hover:bg-slate-50/90 transition-colors"
                                >
                                    <td class="py-3.5 px-6 whitespace-nowrap">
                                        <span class="inline-block font-mono text-xs font-semibold text-slate-700 bg-slate-100 group-hover:bg-white px-2.5 py-1 rounded-md border border-slate-200/80 transition-colors">
                                            {{ item.kode_aset }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 font-semibold text-slate-900">
                                        {{ item.nama_barang }}
                                    </td>
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-slate-100 text-slate-600">
                                            {{ item.kategori }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-semibold text-slate-800 whitespace-nowrap">
                                        {{ item.jumlah }} <span class="text-xs font-normal text-slate-400">unit</span>
                                    </td>
                                    <td class="py-3.5 px-4 text-right text-slate-600 font-medium whitespace-nowrap">
                                        {{ formatRupiah(getHargaSatuan(item)) }}
                                    </td>
                                    <td class="py-3.5 px-6 text-right font-bold text-slate-900 whitespace-nowrap">
                                        {{ formatRupiah(getHargaTotal(item)) }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="bg-slate-50/90 border-t-2 border-slate-200 text-slate-900">
                                    <td colspan="3" class="py-4 px-6 text-xs font-bold uppercase tracking-wider text-slate-500">
                                        Total Valuasi Aset {{ (searchHarga || selectedKategoriHarga !== 'Semua') ? '(Terfilter)' : 'Keseluruhan' }}
                                    </td>
                                    <td class="py-4 px-4 text-right text-xs font-bold text-slate-700 whitespace-nowrap">
                                        {{ totalUnitTerfilter }} unit
                                    </td>
                                    <td class="py-4 px-4"></td>
                                    <td class="py-4 px-6 text-right text-base font-extrabold text-emerald-700 whitespace-nowrap">
                                        {{ formatRupiah(totalNilaiTabelHargaComputed) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- E & F. AKTIVITAS TERBARU & NOTIFIKASI SISTEM -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">
                    
                    <!-- E. Aktivitas Terbaru (7 Kolom di layar besar) -->
                    <div class="lg:col-span-7 bg-white rounded-xl border border-slate-200/90 p-6 shadow-xs flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-5">
                                <div>
                                    <h3 class="font-bold text-slate-900 text-base tracking-tight">Aktivitas Terbaru</h3>
                                    <p class="text-xs text-slate-500">Riwayat mutasi, peminjaman, dan penyesuaian stok terakhir</p>
                                </div>
                                <span class="px-2.5 py-1 bg-slate-100 text-slate-600 border border-slate-200 rounded-md text-xs font-semibold tabular-nums">
                                    {{ aktivitasTerbaru.length }} Log
                                </span>
                            </div>

                            <div v-if="!aktivitasTerbaru.length" class="text-sm text-slate-400 text-center py-12 border border-dashed border-slate-200 rounded-xl">
                                Belum ada aktivitas keluar-masuk tercatat.
                            </div>

                            <div v-else class="divide-y divide-slate-100">
                                <div
                                    v-for="act in aktivitasTerbaru"
                                    :key="act.id"
                                    class="py-3.5 px-2 -mx-2 rounded-lg hover:bg-slate-50/80 transition-colors flex justify-between items-center gap-4"
                                >
                                    <div class="flex items-start gap-3.5 min-w-0">
                                        <!-- Indikator Warna Transaksi -->
                                        <div
                                            :class="getActivityMeta(act.jenis_transaksi).bg"
                                            class="w-8 h-8 rounded-lg border flex items-center justify-center shrink-0 mt-0.5"
                                        >
                                            <span :class="getActivityMeta(act.jenis_transaksi).dot" class="w-2 h-2 rounded-full"></span>
                                        </div>

                                        <div class="min-w-0">
                                            <p class="font-semibold text-sm text-slate-900 truncate">
                                                {{ act.asset?.nama_barang || '-' }}
                                            </p>
                                            <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-slate-500 mt-0.5 tabular-nums">
                                                <span>{{ act.tanggal_transaksi }}</span>
                                                <span class="text-slate-300">•</span>
                                                <span class="font-medium text-slate-700">{{ act.jenis_transaksi }}</span>
                                                <span class="text-slate-400">({{ act.jumlah }} unit)</span>
                                            </div>
                                        </div>
                                    </div>

                                    <span
                                        :class="getStatusBadgeClass(act.status)"
                                        class="px-2.5 py-1 rounded-md text-xs font-semibold border shrink-0"
                                    >
                                        {{ act.status }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- F. Notifikasi Sistem & Status Inventaris (5 Kolom di layar besar) -->
                    <div class="lg:col-span-5 bg-white rounded-xl border border-slate-200/90 p-6 shadow-xs flex flex-col justify-between gap-6">
                        <div>
                            <div class="flex items-center justify-between mb-5">
                                <div>
                                    <h3 class="font-bold text-slate-900 text-base tracking-tight">Notifikasi Sistem</h3>
                                    <p class="text-xs text-slate-500">Peringatan stok dan pemantauan kondisi aset</p>
                                </div>
                                <span
                                    :class="notifikasi.length > 0 ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'"
                                    class="px-2.5 py-1 rounded-md text-xs font-semibold border tabular-nums"
                                >
                                    {{ notifikasi.length > 0 ? `${notifikasi.length} Perhatian` : 'Aman' }}
                                </span>
                            </div>

                            <!-- Daftar Alert Notifikasi -->
                            <div v-if="!notifikasi.length" class="p-4 rounded-xl bg-emerald-50/50 border border-emerald-200/80 flex items-center gap-3 text-xs text-emerald-800">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 shrink-0"></span>
                                <div>
                                    <p class="font-bold">Semua Terkendali</p>
                                    <p class="text-emerald-700/80 mt-0.5">Tidak ada stok kritis atau keterlambatan pengembalian.</p>
                                </div>
                            </div>

                            <div v-else class="space-y-2.5">
                                <div
                                    v-for="(notif, idx) in notifikasi"
                                    :key="idx"
                                    :class="{
                                        'bg-rose-50/60 border-rose-200 border-l-4 border-l-rose-500 text-rose-900': notif.type === 'danger',
                                        'bg-amber-50/60 border-amber-200 border-l-4 border-l-amber-500 text-amber-900': notif.type === 'warning',
                                        'bg-blue-50/60 border-blue-200 border-l-4 border-l-blue-500 text-blue-900': notif.type === 'info'
                                    }"
                                    class="p-3.5 rounded-lg border text-xs leading-relaxed"
                                >
                                    <p class="font-bold uppercase tracking-wider text-[11px]">
                                        {{ notif.category }}
                                    </p>
                                    <p class="mt-0.5 text-slate-700 font-medium">
                                        {{ notif.message }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Panel Ringkasan Kesehatan Inventaris (Pengisi Ruang Kosong Agar Seimbang) -->
                        <div class="pt-5 border-t border-slate-100">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">
                                    Indikator Kesehatan Aset
                                </span>
                                <span class="text-xs font-bold text-slate-700 tabular-nums">
                                    {{ rasioKondisiBaik }}% Layak Pakai
                                </span>
                            </div>

                            <!-- Progress Bar Rasio Kondisi Baik -->
                            <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden mb-4">
                                <div
                                    class="h-full bg-emerald-600 rounded-full transition-all duration-500"
                                    :style="{ width: `${rasioKondisiBaik}%` }"
                                ></div>
                            </div>

                            <div class="grid grid-cols-3 gap-2.5 text-center tabular-nums">
                                <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-200/60">
                                    <span class="block text-[11px] font-medium text-slate-500">Kondisi Baik</span>
                                    <span class="text-sm font-bold text-slate-900 mt-0.5 block">{{ syncedStats.kondisi_baik }} Unit</span>
                                </div>
                                <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-200/60">
                                    <span class="block text-[11px] font-medium text-slate-500">Perlu Cek/Rusak</span>
                                    <span class="text-sm font-bold text-rose-600 mt-0.5 block">{{ syncedStats.kondisi_rusak }} Unit</span>
                                </div>
                                <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-200/60">
                                    <span class="block text-[11px] font-medium text-slate-500">Stok Menipis</span>
                                    <span class="text-sm font-bold text-amber-600 mt-0.5 block">{{ syncedStats.stok_menipis }} Jenis</span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </div>
        
    </AuthenticatedLayout>
</template>