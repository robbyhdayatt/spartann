<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ServiceDetail;
use Illuminate\Support\Str;

class DebugKsgAnalysis extends Command
{
    protected $signature = 'debug:ksg-analysis {date}';
    protected $description = 'Analisis detail KSG untuk menemukan selisih';

    public function handle()
    {
        $date = $this->argument('date');
        $this->info("=== ANALISIS DATA TANGGAL: $date ===");

        // Ambil semua detail service pada tanggal tersebut
        $details = ServiceDetail::with('service')->whereHas('service', function($q) use ($date) {
                $q->whereDate('reg_date', $date);
            })
            ->where('labor_cost_service', '>', 0)
            ->get()
            ->groupBy('service_id');

        $dataReport = [];
        $totalPotong = 0;

        foreach ($details as $serviceId => $items) {
            // Cek Kategori Invoice
            $isKsgCategory = false;
            foreach ($items as $item) {
                if (str_contains(strtoupper($item->service_category_code), 'KSG')) {
                    $isKsgCategory = true;
                    break; 
                }
            }

            // Cek Item Explicit
            $hasExplicitKsgItem = $items->contains(function ($item) {
                return preg_match('/KSG\s*[0-9]/i', $item->service_package_name);
            });

            foreach ($items as $item) {
                $pkgName = strtoupper($item->service_package_name ?? '');
                $status = "✅ RITEL (BAYAR)";
                $isDeducted = false;

                // LOGIKA DUPLIKASI DARI EXPORT
                if (preg_match('/KSG\s*[0-9]/i', $pkgName)) {
                    $status = "❌ POTONG (KSG MURNI)";
                    $isDeducted = true;
                } 
                elseif ($isKsgCategory && !$hasExplicitKsgItem) {
                     $blacklistKeywords = ['PRESS', 'GANTI', 'OVERHAUL', 'PASANG', 'STEL'];
                     $isBlacklisted = Str::contains($pkgName, $blacklistKeywords);
                     
                     if ($isBlacklisted) {
                         $status = "⚠️ SKIP (BLACKLIST: " . $pkgName . ")";
                     } else {
                         $status = "❌ POTONG (SALAH NAMA)";
                         $isDeducted = true;
                     }
                } elseif ($isKsgCategory && $hasExplicitKsgItem) {
                    $status = "✅ RITEL (ADD-ON KSG)";
                }

                if ($isDeducted) {
                    $totalPotong += $item->labor_cost_service;
                }

                // Tampilkan HANYA yang kategori KSG tapi TIDAK DIPOTONG (Biang Kerok)
                if ($isKsgCategory && !$isDeducted) {
                     $dataReport[] = [
                        $item->service->invoice_no,
                        $item->service_package_name,
                        number_format($item->labor_cost_service),
                        $status
                    ];
                }
            }
        }

        $this->info("\nDAFTAR ITEM KATEGORI 'KSG' YANG MASUK KE TOTAL RITEL (TIDAK DIPOTONG):");
        $this->table(['Invoice', 'Nama Paket', 'Harga', 'Alasan'], $dataReport);
        
        $this->info("\nTotal yang sudah dipotong sistem: Rp " . number_format($totalPotong));
    }
}