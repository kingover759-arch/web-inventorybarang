<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $periode = $request->input('periode', 'bulanan'); // harian, mingguan, bulanan, tahunan

        // Ambil seluruh data aset untuk Tabel Harga Aset & Perhitungan Statistik
        $assets = Asset::orderBy('nama_barang')->get()->map(function ($item) {
            $hargaSatuan = (int) ($item->harga_per_benda ?: $item->harga_perolehan ?: 0);
            $jumlah = (int) $item->jumlah;
            $item->harga_per_benda = $hargaSatuan;
            $item->harga_perolehan = $hargaSatuan;
            $item->harga_total = $jumlah * $hargaSatuan;
            return $item;
        });

        // 1. Kartu Statistik (10 Kartu Sinkron dari Database)
        $totalJenisAset = $assets->count();
        $totalKuantitas = (int) $assets->sum('jumlah');
        $totalNilai = (int) $assets->sum('harga_total');

        // Barang Masuk dihitung dari transaksi masuk + total pengadaan aset awal jika transaksi masuk masih 0
        $transaksiMasuk = (int) AssetTransaction::where('arah_transaksi', 'Masuk')->sum('jumlah');
        $totalBarangMasuk = $transaksiMasuk > 0 ? $transaksiMasuk : $totalKuantitas;

        $totalBarangKeluar = (int) AssetTransaction::where('arah_transaksi', 'Keluar')->sum('jumlah');
        $totalBarangDipinjam = (int) AssetTransaction::where('jenis_transaksi', 'Peminjaman Barang')->sum('jumlah');

        $totalBelumDikembalikan = (int) AssetTransaction::where('jenis_transaksi', 'Peminjaman Barang')
            ->whereIn('status', ['Dipinjam', 'Pengembalian Sebagian'])
            ->selectRaw('COALESCE(SUM(jumlah - jumlah_dikembalikan), 0) as sisa')
            ->value('sisa');

        $kondisiBaik = (int) Asset::where('kondisi', 'Baik')->sum('jumlah');
        $kondisiRusak = (int) Asset::whereIn('kondisi', ['Rusak', 'Rusak Ringan', 'Rusak Berat', 'Perlu Perbaikan'])->sum('jumlah');
        $stokMenipis = Asset::where('jumlah', '>', 0)->where('jumlah', '<=', 5)->count();

        // 2. Grafik Keluar-Masuk (Sinkron dengan transaksi & perolehan aset)
        $grafikKeluarMasuk = $this->buildTransactionChart($periode);

        // 3. Grafik Nilai Aset per Kategori
        $grafikNilaiKategori = Asset::select(
                'kategori',
                DB::raw('SUM(jumlah * COALESCE(NULLIF(harga_per_benda, 0), harga_perolehan, 0)) as total_nilai'),
                DB::raw('SUM(jumlah) as total_unit')
            )
            ->groupBy('kategori')
            ->orderByDesc('total_nilai')
            ->get();

        // 4. Aktivitas Terbaru
        $aktivitasTerbaru = AssetTransaction::with('asset:id,kode_aset,nama_barang')
            ->latest('tanggal_transaksi')
            ->latest('id')
            ->take(8)
            ->get();

        // 5. Notifikasi Sistem
        $notifikasi = $this->buildNotifications();

        // 6. Daftar Kategori untuk Filter Tabel Harga
        $categories = Asset::select('kategori')->distinct()->pluck('kategori');

        return Inertia::render('Dashboard', [
            'assets' => $assets,
            'periode' => $periode,
            'categories' => $categories,
            'stats' => [
                'total_jenis_aset' => $totalJenisAset,
                'total_kuantitas' => $totalKuantitas,
                'total_nilai' => $totalNilai,
                'total_barang_masuk' => $totalBarangMasuk,
                'total_barang_keluar' => $totalBarangKeluar,
                'total_barang_dipinjam' => $totalBarangDipinjam,
                'total_belum_dikembalikan' => $totalBelumDikembalikan,
                'kondisi_baik' => $kondisiBaik,
                'kondisi_rusak' => $kondisiRusak,
                'stok_menipis' => $stokMenipis,
            ],
            'grafikKeluarMasuk' => $grafikKeluarMasuk,
            'grafikNilaiKategori' => $grafikNilaiKategori,
            'aktivitasTerbaru' => $aktivitasTerbaru,
            'notifikasi' => $notifikasi,
        ]);
    }

    private function buildTransactionChart(string $periode): array
    {
        $now = Carbon::now();
        $labels = [];
        $masuk = [];
        $keluar = [];
        $dipinjam = [];
        $dikembalikan = [];

        if ($periode === 'harian') {
            for ($i = 6; $i >= 0; $i--) {
                $date = $now->copy()->subDays($i)->format('Y-m-d');
                $labels[] = Carbon::parse($date)->translatedFormat('d M Y');
                $txMasuk = (int) AssetTransaction::whereDate('tanggal_transaksi', $date)->where('arah_transaksi', 'Masuk')->sum('jumlah');
                $asetBaru = (int) Asset::whereDate('tanggal_perolehan', $date)->sum('jumlah');

                $masuk[] = $txMasuk + $asetBaru;
                $keluar[] = (int) AssetTransaction::whereDate('tanggal_transaksi', $date)->where('arah_transaksi', 'Keluar')->sum('jumlah');
                $dipinjam[] = (int) AssetTransaction::whereDate('tanggal_transaksi', $date)->where('jenis_transaksi', 'Peminjaman Barang')->sum('jumlah');
                $dikembalikan[] = (int) AssetTransaction::whereDate('tanggal_transaksi', $date)->where('jenis_transaksi', 'Pengembalian Barang')->sum('jumlah');
            }
        } elseif ($periode === 'mingguan') {
            for ($i = 3; $i >= 0; $i--) {
                $start = $now->copy()->subWeeks($i)->startOfWeek()->format('Y-m-d');
                $end = $now->copy()->subWeeks($i)->endOfWeek()->format('Y-m-d');
                $labels[] = Carbon::parse($start)->format('d M') . ' - ' . Carbon::parse($end)->format('d M');

                $txMasuk = (int) AssetTransaction::whereBetween('tanggal_transaksi', [$start, $end])->where('arah_transaksi', 'Masuk')->sum('jumlah');
                $asetBaru = (int) Asset::whereBetween('tanggal_perolehan', [$start, $end])->sum('jumlah');

                $masuk[] = $txMasuk + $asetBaru;
                $keluar[] = (int) AssetTransaction::whereBetween('tanggal_transaksi', [$start, $end])->where('arah_transaksi', 'Keluar')->sum('jumlah');
                $dipinjam[] = (int) AssetTransaction::whereBetween('tanggal_transaksi', [$start, $end])->where('jenis_transaksi', 'Peminjaman Barang')->sum('jumlah');
                $dikembalikan[] = (int) AssetTransaction::whereBetween('tanggal_transaksi', [$start, $end])->where('jenis_transaksi', 'Pengembalian Barang')->sum('jumlah');
            }
        } elseif ($periode === 'tahunan') {
            for ($i = 4; $i >= 0; $i--) {
                $year = $now->copy()->subYears($i)->year;
                $labels[] = (string) $year;

                $txMasuk = (int) AssetTransaction::whereYear('tanggal_transaksi', $year)->where('arah_transaksi', 'Masuk')->sum('jumlah');
                $asetBaru = (int) Asset::whereYear('tanggal_perolehan', $year)->sum('jumlah');

                $masuk[] = $txMasuk + $asetBaru;
                $keluar[] = (int) AssetTransaction::whereYear('tanggal_transaksi', $year)->where('arah_transaksi', 'Keluar')->sum('jumlah');
                $dipinjam[] = (int) AssetTransaction::whereYear('tanggal_transaksi', $year)->where('jenis_transaksi', 'Peminjaman Barang')->sum('jumlah');
                $dikembalikan[] = (int) AssetTransaction::whereYear('tanggal_transaksi', $year)->where('jenis_transaksi', 'Pengembalian Barang')->sum('jumlah');
            }
        } else {
            // Bulanan (12 bulan terakhir)
            for ($i = 11; $i >= 0; $i--) {
                $month = $now->copy()->subMonths($i);
                $labels[] = $month->translatedFormat('M Y');

                $txMasuk = (int) AssetTransaction::whereYear('tanggal_transaksi', $month->year)->whereMonth('tanggal_transaksi', $month->month)->where('arah_transaksi', 'Masuk')->sum('jumlah');
                $asetBaru = (int) Asset::whereYear('tanggal_perolehan', $month->year)->whereMonth('tanggal_perolehan', $month->month)->sum('jumlah');

                $masuk[] = $txMasuk + $asetBaru;
                $keluar[] = (int) AssetTransaction::whereYear('tanggal_transaksi', $month->year)->whereMonth('tanggal_transaksi', $month->month)->where('arah_transaksi', 'Keluar')->sum('jumlah');
                $dipinjam[] = (int) AssetTransaction::whereYear('tanggal_transaksi', $month->year)->whereMonth('tanggal_transaksi', $month->month)->where('jenis_transaksi', 'Peminjaman Barang')->sum('jumlah');
                $dikembalikan[] = (int) AssetTransaction::whereYear('tanggal_transaksi', $month->year)->whereMonth('tanggal_transaksi', $month->month)->where('jenis_transaksi', 'Pengembalian Barang')->sum('jumlah');
            }
        }

        return compact('labels', 'masuk', 'keluar', 'dipinjam', 'dikembalikan');
    }

    private function buildNotifications(): array
    {
        $alerts = [];

        $habis = Asset::where('stok_tersedia', '<=', 0)->where('jumlah', '<=', 0)->get(['kode_aset', 'nama_barang']);
        foreach ($habis as $item) {
            $alerts[] = [
                'type' => 'danger',
                'category' => 'Barang Habis',
                'message' => "Stok untuk {$item->nama_barang} ({$item->kode_aset}) sudah habis (0 unit).",
            ];
        }

        $menipis = Asset::whereBetween('jumlah', [1, 5])->get(['kode_aset', 'nama_barang', 'jumlah']);
        foreach ($menipis as $item) {
            $alerts[] = [
                'type' => 'warning',
                'category' => 'Stok Menipis',
                'message' => "{$item->nama_barang} tersisa {$item->jumlah} unit tersedia.",
            ];
        }

        $rusak = Asset::whereIn('kondisi', ['Rusak', 'Rusak Ringan', 'Rusak Berat', 'Perlu Perbaikan'])->get(['kode_aset', 'nama_barang', 'kondisi']);
        foreach ($rusak as $item) {
            $alerts[] = [
                'type' => 'danger',
                'category' => 'Barang Rusak',
                'message' => "{$item->nama_barang} ({$item->kode_aset}) tercatat dalam kondisi {$item->kondisi}.",
            ];
        }

        return array_slice($alerts, 0, 10);
    }
}