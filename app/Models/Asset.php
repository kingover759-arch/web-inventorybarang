<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'foto',
        'kode_aset',
        'nama_barang',
        'merek',
        'kategori',
        'lokasi',
        'tanggal_perolehan',
        'jumlah',
        'stok_tersedia',
        'harga_perolehan',
        'harga_per_benda',
        'harga_total',
        'kondisi',
        'status',
        'supplier',
        'nomor_invoice',
        'keterangan',
    ];

    protected static function booted(): void
    {
        static::saving(function (Asset $asset) {
            // Sinkronkan harga_perolehan dan harga_per_benda
            $hargaSatuan = (int) ($asset->harga_per_benda ?: $asset->harga_perolehan ?: 0);
            $asset->harga_per_benda = $hargaSatuan;
            $asset->harga_perolehan = $hargaSatuan;

            // Rumus wajib: Harga Total = Harga Per Benda x Jumlah
            $asset->harga_total = (int) $asset->jumlah * $hargaSatuan;
        });
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(AssetTransaction::class, 'asset_id');
    }

    public function locationStocks(): HasMany
    {
        return $this->hasMany(AssetLocationStock::class, 'asset_id');
    }
}