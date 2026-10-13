<script setup>
import { ref, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import html2pdf from 'html2pdf.js';

const props = defineProps({
    assets: Array,
    summary: Object,
});

const isGeneratingPdf = ref(false);
const reportContent = ref(null);

// State Form Pengaturan Laporan (Live Update)
const doc = reactive({
    namaInstansi: 'PEMERINTAH KOTA / PROVINSI',
    namaSekolahPerusahaan: 'SMA NEGERI 1 UTAMA',
    alamat: 'Jl. Merdeka No. 123, Kel. Kebayoran, Kota Jakarta',
    telepon: '(021) 555-1234',
    email: 'info@sekolahutama.sch.id',
    logoUrl: 'https://via.placeholder.com/80?text=LOGO',
    judulLaporan: 'LAPORAN INVENTARIS ASET',
    periode: '2026',
    nomorDokumen: 'INV/2026/001',
    tanggalCetak: new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }),
    namaKepala: 'Drs. H. Ahmad Fauzi, M.Pd.',
    nipKepala: '19750812 200003 1 002',
    jabatanPengurus: 'Pengurus Barang Inventaris',
    namaPengurus: 'Budi Santoso, S.Kom.',
    nipPengurus: '19880415 201502 1 005',
});

const handleLogoUpload = (e) => {
    const file = e.target.files[0];
    if (file) {
        doc.logoUrl = URL.createObjectURL(file);
    }
};

// Fungsi Download PDF Otomatis Presisi 1 Halaman
const handleDownloadPdf = async () => {
    if (!reportContent.value) return;

    isGeneratingPdf.value = true;

    const tahun = doc.periode ? doc.periode.trim() : new Date().getFullYear();
    const fileName = `Laporan_Inventaris_Aset_${tahun}.pdf`;

    const options = {
        margin: [8, 8, 8, 8],
        filename: fileName,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { 
            scale: 2, 
            useCORS: true, 
            logging: false 
        },
        jsPDF: { 
            unit: 'mm', 
            format: 'a4', 
            orientation: 'portrait' 
        },
        pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
    };

    try {
        await html2pdf().set(options).from(reportContent.value).save();
    } catch (error) {
        console.error('Gagal mengunduh PDF:', error);
        alert('Terjadi kesalahan saat mengunduh PDF.');
    } finally {
        isGeneratingPdf.value = false;
    }
};

const backToDashboard = () => {
    router.visit(route('dashboard'));
};

const formatRupiah = (val) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0
    }).format(val || 0);
};
</script>

<template>
    <Head title="Cetak Laporan Aset" />

    <div class="min-h-screen bg-slate-100 flex flex-col">
        <!-- Topbar Aksi -->
        <header class="bg-slate-900 text-white px-6 py-4 flex justify-between items-center shadow-md">
            <div class="flex items-center gap-3">
                <button @click="backToDashboard" class="p-2 hover:bg-slate-800 rounded-lg transition text-slate-300 hover:text-white">
                    ← Kembali ke Dashboard
                </button>
                <h1 class="font-bold text-lg border-l border-slate-700 pl-3">Pengaturan & Preview Cetak Laporan</h1>
            </div>
            
            <button 
                @click="handleDownloadPdf"
                :disabled="isGeneratingPdf"
                class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-500 disabled:bg-emerald-800 disabled:cursor-not-allowed text-white font-bold text-sm rounded-xl shadow-md transition flex items-center gap-2">
                <svg v-if="isGeneratingPdf" class="animate-spin -ml-1 mr-2 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span>{{ isGeneratingPdf ? 'Membuat PDF...' : 'Cetak Laporan' }}</span>
            </button>
        </header>

        <!-- Container Utama: Left Form | Right Preview -->
        <div class="flex-1 max-w-7xl w-full mx-auto p-4 md:p-6 grid grid-cols-1 lg:grid-cols-12 gap-6">
            
            <!-- FORM PENGATURAN LAPORAN -->
            <div class="lg:col-span-5 bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-6 h-fit overflow-y-auto max-h-[calc(100vh-100px)]">
                <h2 class="font-bold text-slate-900 text-base border-b pb-3 uppercase tracking-wider text-xs">Informasi Template Laporan</h2>

                <!-- Identitas Instansi -->
                <div class="space-y-3">
                    <h3 class="font-semibold text-xs text-blue-600 uppercase">1. Identitas Instansi</h3>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Logo Instansi</label>
                        <input @change="handleLogoUpload" type="file" accept="image/*" class="w-full text-xs text-slate-500" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Nama Instansi Atasan</label>
                        <input v-model="doc.namaInstansi" type="text" class="w-full text-xs rounded-lg border-slate-300" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Nama Sekolah / Instansi</label>
                        <input v-model="doc.namaSekolahPerusahaan" type="text" class="w-full text-xs rounded-lg border-slate-300" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Alamat Lengkap</label>
                        <textarea v-model="doc.alamat" rows="2" class="w-full text-xs rounded-lg border-slate-300"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Telepon</label>
                            <input v-model="doc.telepon" type="text" class="w-full text-xs rounded-lg border-slate-300" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Email</label>
                            <input v-model="doc.email" type="text" class="w-full text-xs rounded-lg border-slate-300" />
                        </div>
                    </div>
                </div>

                <!-- Informasi Dokumen -->
                <div class="space-y-3 border-t pt-4">
                    <h3 class="font-semibold text-xs text-blue-600 uppercase">2. Informasi Dokumen</h3>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Judul Laporan</label>
                        <input v-model="doc.judulLaporan" type="text" class="w-full text-xs rounded-lg border-slate-300" />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Periode / Tahun</label>
                            <input v-model="doc.periode" type="text" class="w-full text-xs rounded-lg border-slate-300" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">No. Dokumen</label>
                            <input v-model="doc.nomorDokumen" type="text" class="w-full text-xs rounded-lg border-slate-300" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Tanggal Cetak</label>
                        <input v-model="doc.tanggalCetak" type="text" class="w-full text-xs rounded-lg border-slate-300" />
                    </div>
                </div>

                <!-- Penanggung Jawab Lengkap -->
                <div class="space-y-3 border-t pt-4">
                    <h3 class="font-semibold text-xs text-blue-600 uppercase">3. Penanggung Jawab & Tanda Tangan</h3>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Nama Kepala / Pimpinan</label>
                        <input v-model="doc.namaKepala" type="text" class="w-full text-xs rounded-lg border-slate-300" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">NIP / NIK Kepala</label>
                        <input v-model="doc.nipKepala" type="text" class="w-full text-xs rounded-lg border-slate-300" />
                    </div>
                    <div class="border-t border-dashed pt-3 space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Jabatan Pengurus</label>
                            <input v-model="doc.jabatanPengurus" type="text" class="w-full text-xs rounded-lg border-slate-300" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Nama Pengurus Barang</label>
                            <input v-model="doc.namaPengurus" type="text" class="w-full text-xs rounded-lg border-slate-300" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">NIP / NIK Pengurus</label>
                            <input v-model="doc.nipPengurus" type="text" class="w-full text-xs rounded-lg border-slate-300" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- LIVE PREVIEW DOKUMEN A4 -->
            <div class="lg:col-span-7 flex justify-center">
                <div ref="reportContent" class="bg-white p-6 border border-slate-300 shadow-xl w-full max-w-[210mm] text-black font-sans text-xs leading-tight">
                    
                    <!-- KOP SURAT -->
                    <div class="relative flex items-center border-b-4 border-black pb-3 mb-3">
                        <div class="absolute left-0">
                            <img :src="doc.logoUrl" class="w-16 h-16 object-contain" />
                        </div>
                        <div class="w-full text-center px-16">
                            <h3 class="font-bold text-xs uppercase tracking-wide">{{ doc.namaInstansi }}</h3>
                            <h2 class="font-extrabold text-sm uppercase tracking-wider">{{ doc.namaSekolahPerusahaan }}</h2>
                            <p class="text-[10px]">{{ doc.alamat }}</p>
                            <p class="text-[9px]">Telp: {{ doc.telepon }} | Email: {{ doc.email }}</p>
                        </div>
                    </div>

                    <!-- JUDUL LAPORAN -->
                    <div class="text-center my-4 space-y-0.5">
                        <h1 class="font-bold text-xs uppercase underline tracking-wider">{{ doc.judulLaporan }}</h1>
                        <p class="text-[10px]">Periode / Tahun: {{ doc.periode }}</p>
                        <p class="text-[9px] text-gray-600">No. Dokumen: {{ doc.nomorDokumen }}</p>
                    </div>

                    <!-- TABEL LAPORAN ASET -->
                    <table class="w-full border-collapse border border-black text-[10px] my-3">
                        <thead>
                            <tr class="bg-gray-100 font-bold border-b border-black text-center">
                                <th class="border border-black p-1.5 w-6">No</th>
                                <th class="border border-black p-1.5">Kode Aset</th>
                                <th class="border border-black p-1.5">Nama Barang</th>
                                <th class="border border-black p-1.5">Merek</th>
                                <th class="border border-black p-1.5">Kategori</th>
                                <th class="border border-black p-1.5">Lokasi</th>
                                <th class="border border-black p-1.5 w-10">Jml</th>
                                <th class="border border-black p-1.5">Harga Perolehan</th>
                                <th class="border border-black p-1.5">Kondisi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, idx) in assets" :key="item.id" class="border-b border-black">
                                <td class="border border-black p-1.5 text-center">{{ idx + 1 }}</td>
                                <td class="border border-black p-1.5 font-mono font-bold">{{ item.kode_aset }}</td>
                                <td class="border border-black p-1.5 font-semibold">{{ item.nama_barang }}</td>
                                <td class="border border-black p-1.5">{{ item.merek || '-' }}</td>
                                <td class="border border-black p-1.5">{{ item.kategori }}</td>
                                <td class="border border-black p-1.5">{{ item.lokasi }}</td>
                                <td class="border border-black p-1.5 text-center font-bold">{{ item.jumlah }}</td>
                                <td class="border border-black p-1.5 text-right">{{ formatRupiah(item.harga_perolehan) }}</td>
                                <td class="border border-black p-1.5 text-center">{{ item.kondisi }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- RINGKASAN REKAPITULASI -->
                    <div class="mt-2 p-2 border border-black bg-gray-50 flex justify-between font-bold text-[10px]">
                        <span>TOTAL KUANTITAS: {{ summary?.total_kuantitas ?? 0 }} UNIT</span>
                        <span>TOTAL NILAI PEROLEHAN: {{ formatRupiah(summary?.total_nilai) }}</span>
                    </div>

                    <!-- BAGIAN TANDA TANGAN (Dikunci agar menyatu di Halaman 1) -->
                    <div class="mt-8 pt-2 page-break-inside-avoid">
                        <div class="flex justify-between items-start text-[11px]">
                            <div class="text-center w-56">
                                <p class="mb-1">Mengetahui,</p>
                                <p class="font-bold">Kepala / Pimpinan</p>
                                <div class="h-14"></div>
                                <p class="font-bold underline">{{ doc.namaKepala }}</p>
                                <p>NIP: {{ doc.nipKepala }}</p>
                            </div>
                            <div class="text-center w-56">
                                <p class="mb-1">{{ doc.tanggalCetak }}</p>
                                <p class="font-bold">{{ doc.jabatanPengurus }}</p>
                                <div class="h-14"></div>
                                <p class="font-bold underline">{{ doc.namaPengurus }}</p>
                                <p>NIP: {{ doc.nipPengurus }}</p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</template>