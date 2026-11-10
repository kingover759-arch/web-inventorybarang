<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetLocationStock;
use App\Models\AssetTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetTransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search', '');
        $jenis = $request->input('jenis_transaksi', 'Semua');
        $arah = $request->input('arah_transaksi', 'Semua');
        $status = $request->input('status', 'Semua');

        $query = AssetTransaction::with(['asset', 'parentLoan'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('nama_peminjam', 'like', "%{$search}%")
                        ->orWhere('penanggung_jawab', 'like', "%{$search}%")
                        ->orWhere('tujuan', 'like', "%{$search}%")
                        ->orWhere('lokasi_asal', 'like', "%{$search}%")
                        ->orWhereHas('asset', function ($assetQuery) use ($search) {
                            $assetQuery->where('nama_barang', 'like', "%{$search}%")
                                ->orWhere('kode_aset', 'like', "%{$search}%");
                        });
                });
            })
            ->when($jenis && $jenis !== 'Semua', fn($q) => $q->where('jenis_transaksi', $jenis))
            ->when($arah && $arah !== 'Semua', fn($q) => $q->where('arah_transaksi', $arah))
            ->when($status && $status !== 'Semua', fn($q) => $q->where('status', $status));

        $transactions = $query->latest('tanggal_transaksi')->latest('id')->get();

        // Daftar aset untuk dropdown transaksi
        $assetsList = Asset::orderBy('nama_barang')->get()->map(function (Asset $asset) {
            $stokTersedia = $asset->stok_tersedia !== null ? (int) $asset->stok_tersedia : (int) $asset->jumlah;
            $asset->stok_tersedia = max(0, $stokTersedia);
            $asset->sedang_dipinjam = max(0, (int) $asset->jumlah - $asset->stok_tersedia);
            return $asset;
        });

        // Daftar transaksi peminjaman yang masih aktif (belum dikembalikan seluruhnya)
        $activeLoans = AssetTransaction::with('asset')
            ->where('jenis_transaksi', 'Peminjaman Barang')
            ->whereIn('status', ['Sedang Dipinjam', 'Dipinjam', 'Pengembalian Sebagian'])
            ->latest('tanggal_transaksi')
            ->get()
            ->filter(function (AssetTransaction $loan) {
                $sisa = (int) $loan->jumlah - (int) ($loan->jumlah_dikembalikan ?? 0);
                return $sisa > 0;
            })
            ->values()
            ->map(function (AssetTransaction $loan) {
                $loan->sisa_pinjaman = max(0, (int) $loan->jumlah - (int) ($loan->jumlah_dikembalikan ?? 0));
                return $loan;
            });

        $totalKeluar = (int) AssetTransaction::where('arah_transaksi', 'Keluar')->sum('jumlah');
        $totalMasuk = (int) AssetTransaction::where('arah_transaksi', 'Masuk')->sum('jumlah');
        $totalDipinjam = (int) AssetTransaction::where('jenis_transaksi', 'Peminjaman Barang')->sum('jumlah');
        $totalDikembalikan = (int) AssetTransaction::where('jenis_transaksi', 'Pengembalian Barang')->sum('jumlah');
        $totalDipindahkan = (int) AssetTransaction::where('jenis_transaksi', 'Pemindahan Barang')->sum('jumlah');

        $sedangDipinjam = (int) $activeLoans->sum(function ($loan) {
            return max(0, (int) $loan->jumlah - (int) ($loan->jumlah_dikembalikan ?? 0));
        });

        $topStats = [
            'total_barang_keluar' => $totalKeluar,
            'total_barang_masuk' => $totalMasuk,
            'total_barang_dipinjam' => $totalDipinjam,
            'total_sedang_dipinjam' => $sedangDipinjam,
            'total_transaksi' => AssetTransaction::count(),
            'barang_belum_dikembalikan' => $sedangDipinjam,
        ];

        return Inertia::render('KeluarMasuk', [
            'transactions' => $transactions,
            'topStats' => $topStats,
            'bottomSummary' => [
                'total_keluar' => $totalKeluar,
                'total_masuk' => $totalMasuk,
                'total_dipindahkan' => $totalDipindahkan,
                'total_dipinjam' => $totalDipinjam,
                'total_dikembalikan' => $totalDikembalikan,
                'total_belum_dikembalikan' => $sedangDipinjam,
            ],
            'assetsList' => $assetsList,
            'activeLoans' => $activeLoans,
            'filters' => [
                'search' => $search,
                'jenis_transaksi' => $jenis,
                'arah_transaksi' => $arah,
                'status' => $status,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tanggal_transaksi' => ['required', 'date'],
            'arah_transaksi' => ['required', 'string', 'in:Keluar,Masuk'],
            'asset_id' => ['required', 'exists:assets,id'],
            'parent_transaction_id' => ['nullable', 'exists:asset_transactions,id'],
            'jenis_transaksi' => [
                'required',
                'string',
                'in:Pemindahan Barang,Peminjaman Barang,Pengembalian Barang,Penghapusan Barang,Penyesuaian Stok,Lainnya',
            ],
            'jumlah' => ['nullable', 'integer', 'min:1'],
            'stok_baru' => ['nullable', 'integer', 'min:0'],
            'lokasi_asal' => ['nullable', 'string', 'max:150'],
            'tujuan' => ['nullable', 'string', 'max:150'],
            'nama_peminjam' => ['nullable', 'string', 'max:150'],
            'kontak_peminjam' => ['nullable', 'string', 'max:100'],
            'tanggal_pengembalian_rencana' => ['nullable', 'date'],
            'kondisi_sebelum' => ['nullable', 'string', 'max:100'],
            'kondisi_setelah' => ['nullable', 'string', 'max:100'],
            'alasan' => ['nullable', 'string', 'max:255'],
            'penanggung_jawab' => ['required', 'string', 'max:150'],
            'keterangan' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($validated) {
            /** @var Asset $asset */
            $asset = Asset::lockForUpdate()->findOrFail($validated['asset_id']);

            $jenis = $validated['jenis_transaksi'];
            $stokTotalAsal = (int) $asset->jumlah;
            $stokTersediaAsal = $asset->stok_tersedia !== null ? (int) $asset->stok_tersedia : $stokTotalAsal;
            $sedangDipinjamAsal = max(0, $stokTotalAsal - $stokTersediaAsal);

            // Cegah duplikasi transaksi identik dalam waktu 5 detik terakhir
            $recentDuplicate = AssetTransaction::where('asset_id', $asset->id)
                ->where('jenis_transaksi', $jenis)
                ->where('penanggung_jawab', $validated['penanggung_jawab'])
                ->where('created_at', '>=', now()->subSeconds(5))
                ->exists();

            if ($recentDuplicate) {
                throw ValidationException::withMessages([
                    'jenis_transaksi' => 'Transaksi yang sama sedang diproses. Mohon tunggu beberapa saat.',
                ]);
            }

            // =========================================================================
            // A. PEMINDAHAN BARANG
            // =========================================================================
            if ($jenis === 'Pemindahan Barang') {
                $jumlah = (int) ($validated['jumlah'] ?? 0);
                $lokasiAsal = trim($validated['lokasi_asal'] ?? $asset->lokasi);
                $lokasiTujuan = trim($validated['tujuan'] ?? '');

                if ($jumlah < 1) {
                    throw ValidationException::withMessages(['jumlah' => 'Jumlah barang yang dipindahkan minimal 1 unit.']);
                }
                if ($lokasiTujuan === '') {
                    throw ValidationException::withMessages(['tujuan' => 'Lokasi tujuan wajib diisi untuk pemindahan barang.']);
                }
                if (mb_strtolower($lokasiAsal) === mb_strtolower($lokasiTujuan)) {
                    throw ValidationException::withMessages(['tujuan' => 'Lokasi tujuan tidak boleh sama dengan lokasi asal (' . $lokasiAsal . ').']);
                }
                if ($jumlah > $stokTersediaAsal) {
                    throw ValidationException::withMessages([
                        'jumlah' => "Jumlah pemindahan ({$jumlah}) melebihi stok tersedia di {$asset->lokasi} ({$stokTersediaAsal} unit).",
                    ]);
                }

                // 1. Kurangi stok di lokasi asal
                $asset->jumlah = $stokTotalAsal - $jumlah;
                $asset->stok_tersedia = $stokTersediaAsal - $jumlah;
                if ($asset->jumlah === 0) {
                    $asset->status = 'Dipindahkan';
                }
                $asset->save();

                $this->syncLocationStock($asset);

                // 2. Cari apakah aset yang sama sudah ada di lokasi tujuan
                $targetAsset = Asset::lockForUpdate()
                    ->where('id', '!=', $asset->id)
                    ->whereRaw('LOWER(TRIM(nama_barang)) = ?', [mb_strtolower(trim($asset->nama_barang))])
                    ->whereRaw('LOWER(TRIM(lokasi)) = ?', [mb_strtolower($lokasiTujuan)])
                    ->where('kategori', $asset->kategori)
                    ->first();

                if ($targetAsset) {
                    $targetStokTersedia = $targetAsset->stok_tersedia !== null ? (int) $targetAsset->stok_tersedia : (int) $targetAsset->jumlah;
                    $targetAsset->jumlah = (int) $targetAsset->jumlah + $jumlah;
                    $targetAsset->stok_tersedia = $targetStokTersedia + $jumlah;
                    $targetAsset->lokasi = $lokasiTujuan;
                    if ($targetAsset->status === 'Dipindahkan' || $targetAsset->status === 'Habis') {
                        $targetAsset->status = 'Tersedia';
                    }
                    $targetAsset->save();

                    $this->syncLocationStock($targetAsset);
                } else {
                    // Buat data aset baru di lokasi tujuan
                    $newKodeAset = $this->generateUniqueMovedAssetCode($asset->kode_aset, $lokasiTujuan);
                    $hargaSatuan = (int) ($asset->harga_per_benda ?: $asset->harga_perolehan ?: 0);

                    $targetAsset = Asset::create([
                        'foto' => $asset->foto,
                        'kode_aset' => $newKodeAset,
                        'nama_barang' => $asset->nama_barang,
                        'merek' => $asset->merek,
                        'kategori' => $asset->kategori,
                        'lokasi' => $lokasiTujuan,
                        'tanggal_perolehan' => $asset->tanggal_perolehan,
                        'jumlah' => $jumlah,
                        'stok_tersedia' => $jumlah,
                        'harga_perolehan' => $hargaSatuan,
                        'harga_per_benda' => $hargaSatuan,
                        'harga_total' => $jumlah * $hargaSatuan,
                        'kondisi' => $asset->kondisi,
                        'status' => 'Tersedia',
                        'supplier' => $asset->supplier,
                        'nomor_invoice' => $asset->nomor_invoice,
                        'keterangan' => $asset->keterangan,
                    ]);

                    $this->syncLocationStock($targetAsset);
                }

                // Jika seluruh stok di lokasi asal dipindahkan dan lokasi asal tidak punya riwayat transaksi lain
                // serta tidak ada barang yang sedang dipinjam, hapus baris asal yang bernilai 0 agar tidak menumpuk baris kosong
                if ($asset->jumlah === 0 && $sedangDipinjamAsal === 0 && $asset->transactions()->count() === 0) {
                    AssetLocationStock::where('asset_id', $asset->id)->delete();
                    // Arahkan transaksi ke targetAsset sebelum menghapus baris kosong
                    AssetTransaction::create([
                        'asset_id' => $targetAsset->id,
                        'tanggal_transaksi' => $validated['tanggal_transaksi'],
                        'arah_transaksi' => 'Keluar',
                        'jenis_transaksi' => 'Pemindahan Barang',
                        'jumlah' => $jumlah,
                        'lokasi_asal' => $lokasiAsal,
                        'tujuan' => $lokasiTujuan,
                        'kondisi_sebelum' => $validated['kondisi_sebelum'] ?? $asset->kondisi,
                        'kondisi_setelah' => $validated['kondisi_setelah'] ?? $asset->kondisi,
                        'stok_sebelumnya' => $stokTotalAsal,
                        'stok_baru' => $targetAsset->jumlah,
                        'selisih_stok' => 0,
                        'penanggung_jawab' => $validated['penanggung_jawab'],
                        'status' => 'Selesai',
                        'keterangan' => $validated['keterangan'] ?? "Pemindahan {$jumlah} unit dari {$lokasiAsal} ke {$lokasiTujuan}",
                    ]);
                    $asset->delete();
                    return;
                }

                AssetTransaction::create([
                    'asset_id' => $asset->id,
                    'tanggal_transaksi' => $validated['tanggal_transaksi'],
                    'arah_transaksi' => 'Keluar',
                    'jenis_transaksi' => 'Pemindahan Barang',
                    'jumlah' => $jumlah,
                    'lokasi_asal' => $lokasiAsal,
                    'tujuan' => $lokasiTujuan,
                    'kondisi_sebelum' => $validated['kondisi_sebelum'] ?? $asset->kondisi,
                    'kondisi_setelah' => $validated['kondisi_setelah'] ?? $asset->kondisi,
                    'stok_sebelumnya' => $stokTotalAsal,
                    'stok_baru' => $asset->jumlah,
                    'selisih_stok' => -$jumlah,
                    'penanggung_jawab' => $validated['penanggung_jawab'],
                    'status' => 'Selesai',
                    'keterangan' => $validated['keterangan'] ?? "Pemindahan {$jumlah} unit ke {$lokasiTujuan} (Kode tujuan: {$targetAsset->kode_aset})",
                ]);

                return;
            }

            // =========================================================================
            // B. PEMINJAMAN BARANG
            // =========================================================================
            if ($jenis === 'Peminjaman Barang') {
                $jumlah = (int) ($validated['jumlah'] ?? 0);
                $namaPeminjam = trim($validated['nama_peminjam'] ?? '');
                $tujuanPinjam = trim($validated['tujuan'] ?? '');

                if ($jumlah < 1) {
                    throw ValidationException::withMessages(['jumlah' => 'Jumlah barang yang dipinjam minimal 1 unit.']);
                }
                if ($namaPeminjam === '') {
                    throw ValidationException::withMessages(['nama_peminjam' => 'Nama peminjam wajib diisi.']);
                }
                if ($tujuanPinjam === '') {
                    throw ValidationException::withMessages(['tujuan' => 'Tujuan peminjaman wajib diisi.']);
                }
                if ($jumlah > $stokTersediaAsal) {
                    throw ValidationException::withMessages([
                        'jumlah' => "Jumlah peminjaman ({$jumlah}) melebihi stok tersedia ({$stokTersediaAsal} unit).",
                    ]);
                }

                // Kurangi stok_tersedia, tetapi pertahankan jumlah total kepemilikan aset
                $asset->stok_tersedia = $stokTersediaAsal - $jumlah;
                $asset->status = $asset->stok_tersedia === 0 ? 'Dipinjam' : 'Tersedia';
                $asset->save();

                AssetTransaction::create([
                    'asset_id' => $asset->id,
                    'tanggal_transaksi' => $validated['tanggal_transaksi'],
                    'arah_transaksi' => 'Keluar',
                    'jenis_transaksi' => 'Peminjaman Barang',
                    'jumlah' => $jumlah,
                    'jumlah_dikembalikan' => 0,
                    'lokasi_asal' => $validated['lokasi_asal'] ?: $asset->lokasi,
                    'tujuan' => $tujuanPinjam,
                    'nama_peminjam' => $namaPeminjam,
                    'kontak_peminjam' => $validated['kontak_peminjam'] ?? null,
                    'tanggal_pengembalian_rencana' => $validated['tanggal_pengembalian_rencana'] ?? null,
                    'kondisi_sebelum' => $validated['kondisi_sebelum'] ?? $asset->kondisi,
                    'stok_sebelumnya' => $stokTersediaAsal,
                    'stok_baru' => $asset->stok_tersedia,
                    'selisih_stok' => -$jumlah,
                    'penanggung_jawab' => $validated['penanggung_jawab'],
                    'status' => 'Sedang Dipinjam',
                    'keterangan' => $validated['keterangan'] ?? null,
                ]);

                return;
            }

            // =========================================================================
            // C. PENGEMBALIAN BARANG
            // =========================================================================
            if ($jenis === 'Pengembalian Barang') {
                $jumlahKembali = (int) ($validated['jumlah'] ?? 0);
                if ($jumlahKembali < 1) {
                    throw ValidationException::withMessages(['jumlah' => 'Jumlah pengembalian minimal 1 unit.']);
                }

                // Cari transaksi peminjaman induk (parent loan)
                $parentLoan = null;
                if (!empty($validated['parent_transaction_id'])) {
                    $parentLoan = AssetTransaction::lockForUpdate()
                        ->where('id', $validated['parent_transaction_id'])
                        ->where('jenis_transaksi', 'Peminjaman Barang')
                        ->first();
                } else {
                    $parentLoan = AssetTransaction::lockForUpdate()
                        ->where('asset_id', $asset->id)
                        ->where('jenis_transaksi', 'Peminjaman Barang')
                        ->whereIn('status', ['Sedang Dipinjam', 'Dipinjam', 'Pengembalian Sebagian'])
                        ->whereRaw('jumlah > COALESCE(jumlah_dikembalikan, 0)')
                        ->oldest('tanggal_transaksi')
                        ->first();
                }

                if (!$parentLoan) {
                    throw ValidationException::withMessages([
                        'parent_transaction_id' => 'Tidak ditemukan data peminjaman aktif untuk barang ini.',
                    ]);
                }

                // Pastikan aset yang dikembalikan merujuk ke aset pada peminjaman induk
                if ((int) $parentLoan->asset_id !== (int) $asset->id) {
                    $asset = Asset::lockForUpdate()->findOrFail($parentLoan->asset_id);
                    $stokTotalAsal = (int) $asset->jumlah;
                    $stokTersediaAsal = $asset->stok_tersedia !== null ? (int) $asset->stok_tersedia : $stokTotalAsal;
                }

                $sudahDikembalikan = (int) ($parentLoan->jumlah_dikembalikan ?? 0);
                $sisaPinjam = max(0, (int) $parentLoan->jumlah - $sudahDikembalikan);

                if ($jumlahKembali > $sisaPinjam) {
                    throw ValidationException::withMessages([
                        'jumlah' => "Jumlah pengembalian ({$jumlahKembali}) melebihi sisa barang yang sedang dipinjam ({$sisaPinjam} unit).",
                    ]);
                }

                // Tambahkan kembali ke stok_tersedia (maksimal sebesar jumlah total aset)
                $stokTersediaBaru = min($stokTotalAsal, $stokTersediaAsal + $jumlahKembali);
                $asset->stok_tersedia = $stokTersediaBaru;
                if (!empty($validated['kondisi_setelah'])) {
                    $asset->kondisi = $validated['kondisi_setelah'];
                }
                if ($asset->stok_tersedia > 0) {
                    $asset->status = 'Tersedia';
                }
                $asset->save();

                // Perbarui status pada transaksi peminjaman induk
                $totalKembaliBaru = $sudahDikembalikan + $jumlahKembali;
                $parentLoan->jumlah_dikembalikan = $totalKembaliBaru;
                $parentLoan->tanggal_pengembalian_aktual = $validated['tanggal_transaksi'];
                $parentLoan->status = $totalKembaliBaru >= (int) $parentLoan->jumlah
                    ? 'Dikembalikan'
                    : 'Pengembalian Sebagian';
                $parentLoan->save();

                // Catat riwayat transaksi pengembalian yang terhubung ke parent_transaction_id
                AssetTransaction::create([
                    'asset_id' => $asset->id,
                    'parent_transaction_id' => $parentLoan->id,
                    'tanggal_transaksi' => $validated['tanggal_transaksi'],
                    'arah_transaksi' => 'Masuk',
                    'jenis_transaksi' => 'Pengembalian Barang',
                    'jumlah' => $jumlahKembali,
                    'jumlah_dikembalikan' => $jumlahKembali,
                    'lokasi_asal' => $parentLoan->tujuan ?: ($validated['lokasi_asal'] ?? '-'),
                    'tujuan' => $asset->lokasi,
                    'nama_peminjam' => $validated['nama_peminjam'] ?: $parentLoan->nama_peminjam,
                    'kontak_peminjam' => $validated['kontak_peminjam'] ?: $parentLoan->kontak_peminjam,
                    'tanggal_pengembalian_rencana' => $parentLoan->tanggal_pengembalian_rencana,
                    'tanggal_pengembalian_aktual' => $validated['tanggal_transaksi'],
                    'kondisi_sebelum' => $parentLoan->kondisi_sebelum ?? $asset->kondisi,
                    'kondisi_setelah' => $validated['kondisi_setelah'] ?? $asset->kondisi,
                    'stok_sebelumnya' => $stokTersediaAsal,
                    'stok_baru' => $asset->stok_tersedia,
                    'selisih_stok' => $jumlahKembali,
                    'penanggung_jawab' => $validated['penanggung_jawab'],
                    'status' => 'Selesai',
                    'keterangan' => $validated['keterangan'] ?? "Pengembalian pinjaman oleh {$parentLoan->nama_peminjam}",
                ]);

                return;
            }

            // =========================================================================
            // D. PENGHAPUSAN BARANG
            // =========================================================================
            if ($jenis === 'Penghapusan Barang') {
                $jumlahHapus = (int) ($validated['jumlah'] ?? 0);
                if ($jumlahHapus < 1) {
                    throw ValidationException::withMessages(['jumlah' => 'Jumlah penghapusan minimal 1 unit.']);
                }
                if ($jumlahHapus > $stokTersediaAsal) {
                    throw ValidationException::withMessages([
                        'jumlah' => "Jumlah penghapusan ({$jumlahHapus}) tidak boleh melebihi stok tersedia ({$stokTersediaAsal} unit).",
                    ]);
                }

                $asset->jumlah = max(0, $stokTotalAsal - $jumlahHapus);
                $asset->stok_tersedia = max(0, $stokTersediaAsal - $jumlahHapus);
                if ($asset->jumlah === 0) {
                    $asset->status = 'Dihapus';
                }
                $asset->save();

                $this->syncLocationStock($asset);

                AssetTransaction::create([
                    'asset_id' => $asset->id,
                    'tanggal_transaksi' => $validated['tanggal_transaksi'],
                    'arah_transaksi' => 'Keluar',
                    'jenis_transaksi' => 'Penghapusan Barang',
                    'jumlah' => $jumlahHapus,
                    'lokasi_asal' => $asset->lokasi,
                    'tujuan' => $validated['tujuan'] ?? 'Penghapusan / Write-Off',
                    'kondisi_sebelum' => $validated['kondisi_sebelum'] ?? $asset->kondisi,
                    'alasan' => $validated['alasan'] ?? ($validated['keterangan'] ?? 'Penghapusan Aset'),
                    'stok_sebelumnya' => $stokTotalAsal,
                    'stok_baru' => $asset->jumlah,
                    'selisih_stok' => -$jumlahHapus,
                    'penanggung_jawab' => $validated['penanggung_jawab'],
                    'status' => 'Selesai',
                    'keterangan' => $validated['keterangan'] ?? $validated['alasan'] ?? null,
                ]);

                return;
            }

            // =========================================================================
            // E. PENYESUAIAN STOK
            // =========================================================================
            if ($jenis === 'Penyesuaian Stok') {
                if (!isset($validated['stok_baru']) || $validated['stok_baru'] === null) {
                    throw ValidationException::withMessages(['stok_baru' => 'Stok baru wajib diisi untuk penyesuaian stok.']);
                }

                $stokBaru = (int) $validated['stok_baru'];
                if ($stokBaru < 0) {
                    throw ValidationException::withMessages(['stok_baru' => 'Stok baru tidak boleh bernilai negatif.']);
                }
                if ($stokBaru < $sedangDipinjamAsal) {
                    throw ValidationException::withMessages([
                        'stok_baru' => "Stok baru ({$stokBaru}) tidak boleh lebih kecil dari jumlah barang yang sedang dipinjam saat ini ({$sedangDipinjamAsal} unit).",
                    ]);
                }

                $selisih = $stokBaru - $stokTotalAsal;
                if ($selisih === 0) {
                    throw ValidationException::withMessages([
                        'stok_baru' => "Stok baru ({$stokBaru}) sama dengan jumlah stok saat ini ({$stokTotalAsal}). Tidak ada perubahan stok.",
                    ]);
                }

                $arahPenyesuaian = $selisih > 0 ? 'Masuk' : 'Keluar';
                $jumlahPenyesuaian = abs($selisih);

                $asset->jumlah = $stokBaru;
                $asset->stok_tersedia = max(0, $stokBaru - $sedangDipinjamAsal);
                $asset->status = $asset->stok_tersedia > 0 ? 'Tersedia' : ($stokBaru === 0 ? 'Habis' : 'Dipinjam');
                $asset->save();

                $this->syncLocationStock($asset);

                AssetTransaction::create([
                    'asset_id' => $asset->id,
                    'tanggal_transaksi' => $validated['tanggal_transaksi'],
                    'arah_transaksi' => $arahPenyesuaian,
                    'jenis_transaksi' => 'Penyesuaian Stok',
                    'jumlah' => $jumlahPenyesuaian,
                    'lokasi_asal' => $asset->lokasi,
                    'tujuan' => $asset->lokasi,
                    'alasan' => $validated['alasan'] ?? 'Stock Opname / Penyesuaian Stok',
                    'stok_sebelumnya' => $stokTotalAsal,
                    'stok_baru' => $stokBaru,
                    'selisih_stok' => $selisih,
                    'penanggung_jawab' => $validated['penanggung_jawab'],
                    'status' => 'Selesai',
                    'keterangan' => $validated['keterangan'] ?? ($validated['alasan'] ?? "Penyesuaian stok dari {$stokTotalAsal} menjadi {$stokBaru}"),
                ]);

                return;
            }

            // =========================================================================
            // F. TRANSAKSI LAINNYA (MASUK / KELUAR UMUM)
            // =========================================================================
            $jumlahLain = (int) ($validated['jumlah'] ?? 0);
            if ($jumlahLain < 1) {
                throw ValidationException::withMessages(['jumlah' => 'Jumlah transaksi minimal 1 unit.']);
            }

            $arah = $validated['arah_transaksi'];
            if ($arah === 'Keluar') {
                if ($jumlahLain > $stokTersediaAsal) {
                    throw ValidationException::withMessages([
                        'jumlah' => "Jumlah keluar ({$jumlahLain}) melebihi stok tersedia ({$stokTersediaAsal} unit).",
                    ]);
                }
                $asset->jumlah = $stokTotalAsal - $jumlahLain;
                $asset->stok_tersedia = $stokTersediaAsal - $jumlahLain;
                $selisih = -$jumlahLain;
            } else {
                $asset->jumlah = $stokTotalAsal + $jumlahLain;
                $asset->stok_tersedia = $stokTersediaAsal + $jumlahLain;
                $selisih = $jumlahLain;
            }

            $asset->save();
            $this->syncLocationStock($asset);

            AssetTransaction::create([
                'asset_id' => $asset->id,
                'tanggal_transaksi' => $validated['tanggal_transaksi'],
                'arah_transaksi' => $arah,
                'jenis_transaksi' => $jenis,
                'jumlah' => $jumlahLain,
                'lokasi_asal' => $validated['lokasi_asal'] ?: $asset->lokasi,
                'tujuan' => $validated['tujuan'] ?? $asset->lokasi,
                'stok_sebelumnya' => $stokTotalAsal,
                'stok_baru' => $asset->jumlah,
                'selisih_stok' => $selisih,
                'penanggung_jawab' => $validated['penanggung_jawab'],
                'status' => 'Selesai',
                'keterangan' => $validated['keterangan'] ?? null,
            ]);
        });

        return redirect()->back()->with('success', 'Transaksi berhasil disimpan dan data aset telah diperbarui.');
    }

    public function destroy(AssetTransaction $transaction): RedirectResponse
    {
        DB::transaction(function () use ($transaction) {
            /** @var Asset|null $asset */
            $asset = Asset::lockForUpdate()->find($transaction->asset_id);
            $jenis = $transaction->jenis_transaksi;
            $jumlah = (int) $transaction->jumlah;

            if ($asset) {
                $stokTotal = (int) $asset->jumlah;
                $stokTersedia = $asset->stok_tersedia !== null ? (int) $asset->stok_tersedia : $stokTotal;

                if ($jenis === 'Peminjaman Barang') {
                    $belumKembali = max(0, $jumlah - (int) ($transaction->jumlah_dikembalikan ?? 0));
                    $asset->stok_tersedia = min($stokTotal, $stokTersedia + $belumKembali);
                    if ($asset->stok_tersedia > 0) {
                        $asset->status = 'Tersedia';
                    }
                    $asset->save();
                } elseif ($jenis === 'Pengembalian Barang') {
                    $asset->stok_tersedia = max(0, $stokTersedia - $jumlah);
                    $asset->save();

                    if ($transaction->parent_transaction_id) {
                        $parent = AssetTransaction::lockForUpdate()->find($transaction->parent_transaction_id);
                        if ($parent) {
                            $parent->jumlah_dikembalikan = max(0, (int) $parent->jumlah_dikembalikan - $jumlah);
                            $parent->status = $parent->jumlah_dikembalikan === 0
                                ? 'Sedang Dipinjam'
                                : 'Pengembalian Sebagian';
                            $parent->save();
                        }
                    }
                } elseif ($jenis === 'Penghapusan Barang') {
                    $asset->jumlah = $stokTotal + $jumlah;
                    $asset->stok_tersedia = $stokTersedia + $jumlah;
                    $asset->status = 'Tersedia';
                    $asset->save();
                    $this->syncLocationStock($asset);
                } elseif ($jenis === 'Penyesuaian Stok' && $transaction->stok_sebelumnya !== null) {
                    $sedangDipinjam = max(0, $stokTotal - $stokTersedia);
                    $asset->jumlah = (int) $transaction->stok_sebelumnya;
                    $asset->stok_tersedia = max(0, (int) $transaction->stok_sebelumnya - $sedangDipinjam);
                    $asset->save();
                    $this->syncLocationStock($asset);
                } elseif ($jenis === 'Pemindahan Barang') {
                    // Kembalikan stok ke aset asal dan kurangi dari lokasi tujuan jika ada
                    if (!empty($transaction->tujuan)) {
                        $targetAsset = Asset::lockForUpdate()
                            ->where('id', '!=', $asset->id)
                            ->whereRaw('LOWER(TRIM(nama_barang)) = ?', [mb_strtolower(trim($asset->nama_barang))])
                            ->whereRaw('LOWER(TRIM(lokasi)) = ?', [mb_strtolower(trim($transaction->tujuan))])
                            ->first();

                        if ($targetAsset && $targetAsset->jumlah >= $jumlah) {
                            $targetAsset->jumlah = (int) $targetAsset->jumlah - $jumlah;
                            $targetAsset->stok_tersedia = max(0, (int) $targetAsset->stok_tersedia - $jumlah);
                            $targetAsset->save();
                            $this->syncLocationStock($targetAsset);

                            if ($targetAsset->jumlah === 0 && $targetAsset->transactions()->count() === 0) {
                                AssetLocationStock::where('asset_id', $targetAsset->id)->delete();
                                $targetAsset->delete();
                            }
                        }
                    }
                    $asset->jumlah = $stokTotal + $jumlah;
                    $asset->stok_tersedia = $stokTersedia + $jumlah;
                    $asset->status = 'Tersedia';
                    $asset->save();
                    $this->syncLocationStock($asset);
                }
            }

            $transaction->delete();
        });

        return redirect()->back()->with('success', 'Riwayat transaksi berhasil dihapus dan stok aset telah disesuaikan.');
    }

    private function syncLocationStock(Asset $asset): void
    {
        AssetLocationStock::where('asset_id', $asset->id)
            ->where('lokasi', '!=', $asset->lokasi)
            ->delete();

        AssetLocationStock::updateOrCreate(
            ['asset_id' => $asset->id, 'lokasi' => $asset->lokasi],
            ['jumlah' => (int) $asset->jumlah]
        );
    }

    private function generateUniqueMovedAssetCode(string $baseCode, string $lokasiTujuan): string
    {
        // Ambil inisial lokasi tujuan (contoh: "Gedung Utama" -> "GU")
        $words = preg_split('/\s+/', trim($lokasiTujuan));
        $initials = '';
        foreach ($words as $w) {
            if ($w !== '') {
                $initials .= mb_strtoupper(mb_substr($w, 0, 1));
            }
        }
        $initials = $initials ?: 'LOC';

        $candidate = "{$baseCode}-{$initials}";
        $counter = 1;

        while (Asset::where('kode_aset', $candidate)->exists()) {
            $counter++;
            $candidate = "{$baseCode}-{$initials}{$counter}";
        }

        return $candidate;
    }

    public function exportExcel(): StreamedResponse
    {
        $transactions = AssetTransaction::with('asset:id,kode_aset,nama_barang')->latest('tanggal_transaksi')->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="laporan-keluar-masuk-' . date('Y-m-d') . '.csv"',
        ];

        return response()->stream(function () use ($transactions) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, ['Tanggal', 'Kode Aset', 'Nama Barang', 'Jenis Transaksi', 'Arah', 'Jumlah', 'Penanggung Jawab', 'Status']);

            foreach ($transactions as $row) {
                fputcsv($handle, [
                    $row->tanggal_transaksi,
                    $row->asset?->kode_aset,
                    $row->asset?->nama_barang,
                    $row->jenis_transaksi,
                    $row->arah_transaksi,
                    $row->jumlah,
                    $row->penanggung_jawab,
                    $row->status,
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }
}