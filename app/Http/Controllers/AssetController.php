<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetLocationStock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AssetController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search', '');
        $kondisi = $request->input('kondisi', 'Semua');
        $kategori = $request->input('kategori', 'Semua');
        $lokasi = $request->input('lokasi', 'Semua');

        $query = Asset::query()
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('nama_barang', 'like', "%{$search}%")
                        ->orWhere('kode_aset', 'like', "%{$search}%")
                        ->orWhere('merek', 'like', "%{$search}%");
                });
            })
            ->when($kondisi && $kondisi !== 'Semua', fn($q) => $q->where('kondisi', $kondisi))
            ->when($kategori && $kategori !== 'Semua', fn($q) => $q->where('kategori', $kategori))
            ->when($lokasi && $lokasi !== 'Semua', fn($q) => $q->where('lokasi', $lokasi));

        $assets = $query->latest('updated_at')->latest('id')->get()->map(function (Asset $item) {
            $hargaSatuan = (int) ($item->harga_per_benda ?: $item->harga_perolehan ?: 0);
            $jumlahTotal = (int) $item->jumlah;
            $stokTersedia = $item->stok_tersedia !== null ? (int) $item->stok_tersedia : $jumlahTotal;

            $item->harga_per_benda = $hargaSatuan;
            $item->harga_perolehan = $hargaSatuan;
            $item->harga_total = $jumlahTotal * $hargaSatuan;
            $item->stok_tersedia = max(0, min($jumlahTotal, $stokTersedia));
            $item->sedang_dipinjam = max(0, $jumlahTotal - $item->stok_tersedia);

            return $item;
        });

        $allAssets = Asset::all();
        $totalNilai = $allAssets->sum(function ($a) {
            $harga = (int) ($a->harga_per_benda ?: $a->harga_perolehan ?: 0);
            return (int) $a->jumlah * $harga;
        });

        $totalKuantitas = (int) $allAssets->sum('jumlah');
        $totalTersedia = (int) $allAssets->sum(function ($a) {
            return $a->stok_tersedia !== null ? (int) $a->stok_tersedia : (int) $a->jumlah;
        });

        return Inertia::render('Asset', [
            'assets' => $assets,
            'stats' => [
                'total_kuantitas' => $totalKuantitas,
                'total_tersedia' => max(0, $totalTersedia),
                'total_dipinjam' => max(0, $totalKuantitas - $totalTersedia),
                'total_nilai' => (int) $totalNilai,
                'kondisi_baik' => (int) Asset::where('kondisi', 'Baik')->sum('jumlah'),
                'kondisi_rusak' => (int) Asset::whereIn('kondisi', ['Rusak', 'Perlu Perbaikan', 'Rusak Ringan', 'Rusak Berat'])->sum('jumlah'),
            ],
            'filters' => [
                'search' => $search,
                'kondisi' => $kondisi,
                'kategori' => $kategori,
                'lokasi' => $lokasi,
            ],
            'categories' => Asset::select('kategori')->whereNotNull('kategori')->distinct()->pluck('kategori'),
            'locations' => Asset::select('lokasi')->whereNotNull('lokasi')->distinct()->pluck('lokasi'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kode_aset' => ['required', 'string', 'max:100', 'unique:assets,kode_aset'],
            'nama_barang' => ['required', 'string', 'max:255'],
            'merek' => ['nullable', 'string', 'max:150'],
            'kategori' => ['required', 'string', 'max:150'],
            'lokasi' => ['required', 'string', 'max:150'],
            'tanggal_perolehan' => ['nullable', 'date'],
            'jumlah' => ['required', 'integer', 'min:1'],
            'harga_perolehan' => ['nullable', 'numeric', 'min:0'],
            'harga_per_benda' => ['nullable', 'numeric', 'min:0'],
            'kondisi' => ['required', 'string'],
            'status' => ['nullable', 'string'],
            'supplier' => ['nullable', 'string', 'max:150'],
            'nomor_invoice' => ['nullable', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string'],
            'foto' => ['nullable', 'image', 'max:3072'],
        ]);

        DB::transaction(function () use ($request, $validated) {
            if ($request->hasFile('foto')) {
                $path = $request->file('foto')->store('assets', 'public');
                $validated['foto'] = '/storage/' . $path;
            }

            $hargaSatuan = (int) ($validated['harga_per_benda'] ?? $validated['harga_perolehan'] ?? 0);
            $jumlah = (int) $validated['jumlah'];

            $validated['harga_perolehan'] = $hargaSatuan;
            $validated['harga_per_benda'] = $hargaSatuan;
            $validated['harga_total'] = $jumlah * $hargaSatuan;
            $validated['stok_tersedia'] = $jumlah;
            $validated['status'] = $validated['status'] ?? 'Tersedia';

            $asset = Asset::create($validated);

            AssetLocationStock::updateOrCreate(
                ['asset_id' => $asset->id, 'lokasi' => $asset->lokasi],
                ['jumlah' => $asset->jumlah]
            );
        });

        return redirect()->back()->with('success', 'Aset baru berhasil ditambahkan.');
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $rules = [
            'kode_aset' => ['required', 'string', 'max:100', 'unique:assets,kode_aset,' . $asset->id],
            'nama_barang' => ['required', 'string', 'max:255'],
            'merek' => ['nullable', 'string', 'max:150'],
            'kategori' => ['required', 'string', 'max:150'],
            'lokasi' => ['required', 'string', 'max:150'],
            'tanggal_perolehan' => ['nullable', 'date'],
            'jumlah' => ['required', 'integer', 'min:0'],
            'harga_perolehan' => ['nullable', 'numeric', 'min:0'],
            'harga_per_benda' => ['nullable', 'numeric', 'min:0'],
            'kondisi' => ['required', 'string'],
            'status' => ['nullable', 'string'],
            'supplier' => ['nullable', 'string', 'max:150'],
            'nomor_invoice' => ['nullable', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string'],
        ];

        if ($request->hasFile('foto')) {
            $rules['foto'] = ['nullable', 'image', 'max:3072'];
        }

        $validated = $request->validate($rules);

        DB::transaction(function () use ($request, $asset, $validated) {
            $lockedAsset = Asset::lockForUpdate()->findOrFail($asset->id);

            $stokTersediaLama = $lockedAsset->stok_tersedia !== null
                ? (int) $lockedAsset->stok_tersedia
                : (int) $lockedAsset->jumlah;
            $sedangDipinjam = max(0, (int) $lockedAsset->jumlah - $stokTersediaLama);
            $jumlahBaru = (int) $validated['jumlah'];

            if ($jumlahBaru < $sedangDipinjam) {
                throw ValidationException::withMessages([
                    'jumlah' => "Jumlah stok ({$jumlahBaru}) tidak boleh lebih kecil dari jumlah barang yang sedang dipinjam ({$sedangDipinjam} unit).",
                ]);
            }

            if ($request->hasFile('foto')) {
                $path = $request->file('foto')->store('assets', 'public');
                $validated['foto'] = '/storage/' . $path;
            } else {
                unset($validated['foto']);
            }

            $hargaSatuan = (int) ($validated['harga_per_benda'] ?? $validated['harga_perolehan'] ?? 0);

            $validated['harga_perolehan'] = $hargaSatuan;
            $validated['harga_per_benda'] = $hargaSatuan;
            $validated['harga_total'] = $jumlahBaru * $hargaSatuan;
            $validated['stok_tersedia'] = max(0, $jumlahBaru - $sedangDipinjam);

            if ($validated['stok_tersedia'] > 0 && ($lockedAsset->status === 'Habis' || $lockedAsset->status === 'Dipinjam')) {
                $validated['status'] = 'Tersedia';
            } elseif ($jumlahBaru === 0) {
                $validated['status'] = 'Habis';
            }

            $lockedAsset->update($validated);

            // Sinkronkan lokasi stok
            AssetLocationStock::where('asset_id', $lockedAsset->id)
                ->where('lokasi', '!=', $lockedAsset->lokasi)
                ->delete();

            AssetLocationStock::updateOrCreate(
                ['asset_id' => $lockedAsset->id, 'lokasi' => $lockedAsset->lokasi],
                ['jumlah' => (int) $lockedAsset->jumlah]
            );
        });

        return redirect()->back()->with('success', 'Data aset berhasil diperbarui.');
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        DB::transaction(function () use ($asset) {
            AssetLocationStock::where('asset_id', $asset->id)->delete();
            $asset->delete();
        });

        return redirect()->back()->with('success', 'Data aset berhasil dihapus.');
    }

    public function cetakLaporan(): Response
    {
        $assets = Asset::orderBy('nama_barang')->get();
        return Inertia::render('CetakLaporan', [
            'assets' => $assets,
            'summary' => [
                'total_kuantitas' => (int) $assets->sum('jumlah'),
                'total_nilai' => (int) $assets->sum(fn($a) => (int) $a->jumlah * (int) ($a->harga_per_benda ?: $a->harga_perolehan ?: 0)),
            ],
        ]);
    }
}