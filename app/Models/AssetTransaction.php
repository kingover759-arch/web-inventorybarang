<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'parent_transaction_id',
        'tanggal_transaksi',
        'arah_transaksi',
        'jenis_transaksi',
        'jumlah',
        'jumlah_dikembalikan',
        'lokasi_asal',
        'tujuan',
        'nama_peminjam',
        'kontak_peminjam',
        'tanggal_pengembalian_rencana',
        'tanggal_pengembalian_aktual',
        'kondisi_sebelum',
        'kondisi_setelah',
        'alasan',
        'stok_sebelumnya',
        'stok_baru',
        'selisih_stok',
        'bukti_dokumen',
        'penanggung_jawab',
        'status',
        'keterangan',
    ];

    protected $casts = [
        'tanggal_transaksi' => 'date:Y-m-d',
        'tanggal_pengembalian_rencana' => 'date:Y-m-d',
        'tanggal_pengembalian_aktual' => 'date:Y-m-d',
        'jumlah' => 'integer',
        'jumlah_dikembalikan' => 'integer',
        'stok_sebelumnya' => 'integer',
        'stok_baru' => 'integer',
        'selisih_stok' => 'integer',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function parentLoan(): BelongsTo
    {
        return $this->belongsTo(AssetTransaction::class, 'parent_transaction_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(AssetTransaction::class, 'parent_transaction_id');
    }
}