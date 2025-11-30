<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\StockMovement;
use App\Models\InventoryBatch;
use Illuminate\Support\Facades\DB;

class FixStockHistory extends Command
{
    protected $signature = 'fix:stock-history';
    protected $description = 'Mengurutkan ulang ID dan memperbaiki running balance stok';

    public function handle()
    {
        if (!$this->confirm('PERINGATAN: Command ini akan MENGOSONGKAN tabel stock_movements dan mengisinya ulang agar ID berurutan. Pastikan tidak ada transaksi berlangsung. Lanjutkan?')) {
            return;
        }

        $this->info("⏳ Mengambil dan mengurutkan data...");
        
        // 1. Ambil semua data, urutkan mutlak berdasarkan Created At
        // Jika created_at sama, urutkan berdasarkan ID lama untuk menjaga konsistensi
        $allMovements = StockMovement::orderBy('created_at', 'asc')
                                     ->orderBy('id', 'asc') 
                                     ->get();

        if ($allMovements->isEmpty()) {
            $this->info("Tidak ada data pergerakan stok.");
            return;
        }

        $this->info("🗑️  Mereset tabel stock_movements (Truncate)...");
        
        // 2. Matikan foreign key check sementara untuk Truncate
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        StockMovement::truncate(); // Ini mereset ID kembali ke 1
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->info("🔄 Menyusun ulang ID dan memperbaiki saldo stok...");
        
        $bar = $this->output->createProgressBar($allMovements->count());
        $bar->start();

        // Array untuk melacak saldo berjalan (Running Balance)
        // Format Key: "barang_id-lokasi_id-rak_id" => Qty Terakhir
        $balances = []; 

        foreach ($allMovements as $mov) {
            // Buat key unik kombinasi Barang + Lokasi + Rak
            $key = "{$mov->barang_id}-{$mov->lokasi_id}-{$mov->rak_id}";
            
            // Ambil saldo terakhir (atau 0 jika belum ada history)
            $currentBalance = $balances[$key] ?? 0;
            
            // Hitung posisi stok yang benar
            $stokSebelumBaru = $currentBalance;
            $stokSesudahBaru = $currentBalance + $mov->jumlah;
            
            // Update tracker saldo untuk iterasi berikutnya
            $balances[$key] = $stokSesudahBaru;

            // 3. Insert Ulang Data
            // Karena tabel kosong, data pertama akan dapat ID 1, kedua ID 2, dst.
            StockMovement::create([
                'barang_id'      => $mov->barang_id,
                'lokasi_id'      => $mov->lokasi_id,
                'rak_id'         => $mov->rak_id,
                'jumlah'         => $mov->jumlah,
                'stok_sebelum'   => $stokSebelumBaru,   // Nilai yang sudah dikoreksi
                'stok_sesudah'   => $stokSesudahBaru,   // Nilai yang sudah dikoreksi
                'referensi_type' => $mov->referensi_type,
                'referensi_id'   => $mov->referensi_id,
                'keterangan'     => $mov->keterangan,
                'user_id'        => $mov->user_id,
                'created_at'     => $mov->created_at, // Pertahankan waktu asli
                'updated_at'     => $mov->updated_at, // Pertahankan waktu asli
            ]);

            $bar->advance();
        }

        $bar->finish();
        $this->info("\n\n💾 Sinkronisasi stok akhir ke Master Data (InventoryBatch)...");

        // 4. Update Tabel Master Stok agar sesuai dengan history akhir
        foreach ($balances as $key => $finalQty) {
            // Pecah key kembali
            $parts = explode('-', $key);
            $barangId = $parts[0];
            $lokasiId = $parts[1];
            $rakId = isset($parts[2]) && $parts[2] !== '' ? $parts[2] : null;
            
            // Cari Batch Stok
            $batch = InventoryBatch::where('barang_id', $barangId)
                ->where('lokasi_id', $lokasiId)
                ->where('rak_id', $rakId)
                ->orderBy('created_at', 'desc') // Ambil batch terbaru jika ada duplikat
                ->first();

            if ($batch) {
                // Koreksi jika ada selisih
                if ($batch->quantity != $finalQty) {
                    $batch->quantity = $finalQty;
                    $batch->save();
                }
            } else {
                 // Jika batch tidak ditemukan tapi history ada sisa stok (jarang terjadi)
                 if ($finalQty > 0) {
                     InventoryBatch::create([
                         'barang_id' => $barangId,
                         'lokasi_id' => $lokasiId,
                         'rak_id'    => $rakId,
                         'quantity'  => $finalQty
                     ]);
                 }
            }
        }

        $this->info("✅ Selesai! Tabel stock_movements sudah rapi (ID urut & Saldo konsisten).");
    }
}