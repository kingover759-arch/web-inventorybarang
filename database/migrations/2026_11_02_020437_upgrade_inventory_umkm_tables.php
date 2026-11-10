<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Sesuaikan tabel assets yang sudah ada tanpa merusak kolom lama (foto, harga_perolehan, dll)
        if (Schema::hasTable('assets')) {
            Schema::table('assets', function (Blueprint $table) {
                if (!Schema::hasColumn('assets', 'stok_tersedia')) {
                    $table->integer('stok_tersedia')->default(0);
                }
                if (!Schema::hasColumn('assets', 'harga_per_benda')) {
                    $table->unsignedBigInteger('harga_per_benda')->default(0);
                }
                if (!Schema::hasColumn('assets', 'harga_total')) {
                    $table->unsignedBigInteger('harga_total')->default(0);
                }
                if (!Schema::hasColumn('assets', 'supplier')) {
                    $table->string('supplier')->nullable();
                }
                if (!Schema::hasColumn('assets', 'nomor_invoice')) {
                    $table->string('nomor_invoice')->nullable();
                }
            });

            // Sinkronisasi data lama ke kolom baru secara otomatis
            DB::statement('UPDATE assets SET stok_tersedia = jumlah WHERE stok_tersedia = 0 AND jumlah > 0');

            if (Schema::hasColumn('assets', 'harga_perolehan')) {
                DB::statement('UPDATE assets SET harga_per_benda = harga_perolehan WHERE harga_per_benda = 0 AND harga_perolehan > 0');
            }

            DB::statement('UPDATE assets SET harga_total = jumlah * harga_per_benda');
        }

        // 2. Tabel distribusi stok per lokasi
        if (!Schema::hasTable('asset_location_stocks')) {
            Schema::create('asset_location_stocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
                $table->string('lokasi');
                $table->integer('jumlah')->default(0);
                $table->timestamps();

                $table->unique(['asset_id', 'lokasi']);
            });
        }

        // 3. Tabel transaksi keluar-masuk & peminjaman
        if (!Schema::hasTable('asset_transactions')) {
            Schema::create('asset_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
                $table->foreignId('parent_transaction_id')->nullable()->constrained('asset_transactions')->nullOnDelete();
                $table->date('tanggal_transaksi');
                $table->enum('arah_transaksi', ['Masuk', 'Keluar']);
                $table->enum('jenis_transaksi', [
                    'Pemindahan Barang',
                    'Peminjaman Barang',
                    'Pengembalian Barang',
                    'Penghapusan Barang',
                    'Penyesuaian Stok',
                    'Lainnya'
                ]);
                $table->integer('jumlah');
                $table->integer('jumlah_dikembalikan')->default(0);

                // Lokasi & Tujuan
                $table->string('lokasi_asal')->nullable();
                $table->string('tujuan')->nullable();

                // Peminjaman & Pengembalian
                $table->string('nama_peminjam')->nullable();
                $table->string('kontak_peminjam')->nullable();
                $table->date('tanggal_pengembalian_rencana')->nullable();
                $table->date('tanggal_pengembalian_aktual')->nullable();
                $table->string('kondisi_sebelum')->nullable();
                $table->string('kondisi_setelah')->nullable();

                // Penghapusan & Penyesuaian Stok
                $table->string('alasan')->nullable();
                $table->integer('stok_sebelumnya')->nullable();
                $table->integer('stok_baru')->nullable();
                $table->integer('selisih_stok')->nullable();
                $table->string('bukti_dokumen')->nullable();

                // Umum
                $table->string('penanggung_jawab');
                $table->enum('status', [
                    'Selesai',
                    'Dipinjam',
                    'Pengembalian Sebagian',
                    'Dikembalikan',
                    'Dihapus'
                ])->default('Selesai');
                $table->text('keterangan')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_transactions');
        Schema::dropIfExists('asset_location_stocks');
    }
};