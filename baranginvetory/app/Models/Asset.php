<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'jumlah',
        'harga_perolehan',
        'kondisi',
        'status',
        'tanggal_perolehan',
        'keterangan',
    ];

    protected $casts = [
        'harga_perolehan' => 'decimal:2',
        'jumlah' => 'integer',
        'tanggal_perolehan' => 'date',
    ];
}