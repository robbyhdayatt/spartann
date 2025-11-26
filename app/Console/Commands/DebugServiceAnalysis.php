<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ServiceDetail;
use Carbon\Carbon;

class DebugServiceAnalysis extends Command
{
    protected $signature = 'debug:service-analysis {dealerCode} {startDate} {endDate}';
    protected $description = 'Analisis detail baris service untuk menemukan selisih KSG';

    public function handle()
    {
        $dealerCode = $this->argument('dealerCode');
        $startDate = $this->argument('startDate');
        $endDate = $this->argument('endDate');

        $this->info("=== ANALISIS DETAIL BARIS ===");
        
        $query = ServiceDetail::with('service')
            ->whereHas('service', function($q) use ($dealerCode, $startDate, $endDate) {
                $q->whereBetween('reg_date', [$startDate, $endDate]);
                if ($dealerCode !== 'all') $q->where('dealer_code', $dealerCode);
            });

        // 1. Cek Item yang Kategori-nya KSG tapi Paket-nya BUKAN KSG (Kemungkinan False Positive)
        $suspiciousItems = (clone $query)->where(function($q) {
            $q->where('service_category_code', 'LIKE', 'KSG%'); // Kategori Invoice KSG
        })
        ->where('service_package_name', 'NOT LIKE', 'KSG%') // Tapi Nama Paket BUKAN KSG
        ->where('labor_cost_service', '>', 0) // Dan ada nilai uangnya
        ->get();

        $this->warn("\n[!] ITEM BERBAYAR YANG TERSANDERA KATEGORI KSG:");
        if ($suspiciousItems->count() == 0) {
            $this->info("Tidak ditemukan item mencurigakan. Filter kategori aman?");
        } else {
            $headers = ['Invoice', 'Nama Item', 'Paket', 'Kategori', 'Biaya Jasa (Labor)'];
            $data = [];
            $totalSalahPotong = 0;

            foreach ($suspiciousItems as $item) {
                $data[] = [
                    $item->service->invoice_no,
                    $item->item_name ?: '-',
                    $item->service_package_name ?: '-',
                    $item->service_category_code,
                    number_format($item->labor_cost_service)
                ];
                $totalSalahPotong += $item->labor_cost_service;
            }
            $this->table($headers, $data);
            $this->error("TOTAL YANG SALAH DIKURANGI: Rp " . number_format($totalSalahPotong));
            $this->info("^^ Nilai ini yang menyebabkan selisih pada Total Tanpa KSG ^^");
        }
    }
}