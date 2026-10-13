<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;

class AssetController extends Controller
{
    public function index(Request $request)
    {
        return $this->dashboard($request);
    }

    public function dashboard(Request $request)
    {
        // Pengaman jika tabel assets belum terbentuk di database agar tidak crash putih polos
        if (!Schema::hasTable('assets')) {
            return Inertia::render('Dashboard', [
                'assets' => [],
                'stats' => [
                    'total_kuantitas' => 0,
                    'total_nilai' => 0,
                    'kondisi_baik' => 0,
                    'kondisi_rusak' => 0,
                ],
                'filters' => ['search' => '', 'kondisi' => 'Semua', 'kategori' => 'Semua', 'lokasi' => 'Semua'],
                'categories' => [],
                'locations' => [],
            ]);
        }

        $query = Asset::query();

        // Pencarian (Nama Barang, Kode Aset, Merek)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_barang', 'like', "%{$search}%")
                  ->orWhere('kode_aset', 'like', "%{$search}%")
                  ->orWhere('merek', 'like', "%{$search}%");
            });
        }

        // Filter Kondisi
        if ($request->filled('kondisi') && $request->kondisi !== 'Semua') {
            $query->where('kondisi', $request->kondisi);
        }

        // Filter Kategori
        if ($request->filled('kategori') && $request->kategori !== 'Semua') {
            $query->where('kategori', $request->kategori);
        }

        // Filter Lokasi
        if ($request->filled('lokasi') && $request->lokasi !== 'Semua') {
            $query->where('lokasi', $request->lokasi);
        }

        $assets = $query->latest()->get();

        // Hitung Statistik Dinamis dari Database
        $stats = [
            'total_kuantitas' => (int) Asset::sum('jumlah'),
            'total_nilai' => (float) (Asset::selectRaw('SUM(jumlah * harga_perolehan) as total')->value('total') ?? 0),
            'kondisi_baik' => (int) Asset::where('kondisi', 'Baik')->sum('jumlah'),
            'kondisi_rusak' => (int) Asset::where('kondisi', 'Rusak')->sum('jumlah'),
        ];

        // Opsi Kategori & Lokasi unik untuk dropdown filter
        $categories = Asset::distinct()->whereNotNull('kategori')->pluck('kategori')->toArray();
        $locations = Asset::distinct()->whereNotNull('lokasi')->pluck('lokasi')->toArray();

        return Inertia::render('Dashboard', [
            'assets' => $assets,
            'stats' => $stats,
            'filters' => $request->only(['search', 'kondisi', 'kategori', 'lokasi']),
            'categories' => $categories,
            'locations' => $locations,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'foto' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'kode_aset' => 'required|string|unique:assets,kode_aset',
            'nama_barang' => 'required|string|max:255',
            'merek' => 'nullable|string|max:255',
            'kategori' => 'required|string|max:255',
            'lokasi' => 'required|string|max:255',
            'jumlah' => 'required|integer|min:1',
            'harga_perolehan' => 'required|numeric|min:0',
            'kondisi' => 'required|in:Baik,Rusak,Perlu Perbaikan',
            'status' => 'required|string|max:255',
            'tanggal_perolehan' => 'nullable|date',
            'keterangan' => 'nullable|string',
        ]);

        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->store('assets', 'public');
            $validated['foto'] = '/storage/' . $path;
        }

        Asset::create($validated);

        return redirect()->back()->with('success', 'Data aset berhasil ditambahkan.');
    }

    public function update(Request $request, Asset $asset)
    {
        $validated = $request->validate([
            'foto' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'kode_aset' => 'required|string|unique:assets,kode_aset,' . $asset->id,
            'nama_barang' => 'required|string|max:255',
            'merek' => 'nullable|string|max:255',
            'kategori' => 'required|string|max:255',
            'lokasi' => 'required|string|max:255',
            'jumlah' => 'required|integer|min:1',
            'harga_perolehan' => 'required|numeric|min:0',
            'kondisi' => 'required|in:Baik,Rusak,Perlu Perbaikan',
            'status' => 'required|string|max:255',
            'tanggal_perolehan' => 'nullable|date',
            'keterangan' => 'nullable|string',
        ]);

        if ($request->hasFile('foto')) {
            if ($asset->foto && Storage::disk('public')->exists(str_replace('/storage/', '', $asset->foto))) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $asset->foto));
            }
            $path = $request->file('foto')->store('assets', 'public');
            $validated['foto'] = '/storage/' . $path;
        }

        $asset->update($validated);

        return redirect()->back()->with('success', 'Data aset berhasil diperbarui.');
    }

    public function destroy(Asset $asset)
    {
        if ($asset->foto && Storage::disk('public')->exists(str_replace('/storage/', '', $asset->foto))) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $asset->foto));
        }

        $asset->delete();

        return redirect()->back()->with('success', 'Data aset berhasil dihapus.');
    }

    public function cetakLaporan()
    {
        $assets = Asset::latest()->get();
        $totalKuantitas = (int) Asset::sum('jumlah');
        $totalNilai = (float) (Asset::selectRaw('SUM(jumlah * harga_perolehan) as total')->value('total') ?? 0);

        return Inertia::render('CetakLaporan', [
            'assets' => $assets,
            'summary' => [
                'total_kuantitas' => $totalKuantitas,
                'total_nilai' => $totalNilai,
            ]
        ]);
    }
}