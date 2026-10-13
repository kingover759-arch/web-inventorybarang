<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('foto')->nullable();
            $table->string('kode_aset')->unique();
            $table->string('nama_barang');
            $table->string('merek')->nullable();
            $table->string('kategori');
            $table->string('lokasi');
            $table->integer('jumlah')->default(1);
            $table->decimal('harga_perolehan', 15, 2)->default(0);
            $table->enum('kondisi', ['Baik', 'Rusak', 'Perlu Perbaikan'])->default('Baik');
            $table->string('status')->default('Tersedia');
            $table->date('tanggal_perolehan')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};