<?php

namespace App\Imports;

use App\Models\Service;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ServiceImport implements ToCollection
{
    private $currentService = null;
    private $importedCount = 0;
    private $skippedCount = 0;

    public function collection(Collection $rows)
    {
        $dataRows = $rows->slice(2);
        $headers = [
            1 => 'yss', 2 => 'dealer', 3 => 'point', 4 => 'reg_date', 5 => 'service_order',
            6 => 'plate_no', 7 => 'no_work_order', 8 => 'status_work_order', 9 => 'invoice_no',
            10 => 'nama', 11 => 'ktp', 12 => 'no_npwp', 13 => 'name_npwp', 14 => 'telepon_no',
            15 => 'brand', 16 => 'model_name', 17 => 'frame_no', 18 => 'service_category',
            21 => 'service_package', 22 => 'labor_cost_service', 23 => 'parts_no',
            24 => 'parts_name', 25 => 'parts_qty', 26 => 'parts_price', 27 => 'payment_type',
            28 => 'transaction_code', 29 => 'amount', 30 => 'e_payment_amount',
            31 => 'cash_amount', 32 => 'debit_amount', 33 => 'down_payment_dp',
            34 => 'total_labor', 35 => 'total_part_service', 36 => 'total_oil_service',
            37 => 'total_retail_parts', 38 => 'total_retail_oil', 39 => 'total_amount',
            40 => 'benefit_amount', 41 => 'total_payment', 42 => 'balance', 43 => 'technician_name'
        ];

        foreach ($dataRows as $row) {
            $rowData = new Collection();
            foreach ($headers as $index => $headerName) {
                if (isset($row[$index])) {
                    $rowData->put($headerName, $row[$index]);
                }
            }

            if ($rowData->filter()->isEmpty()) {
                continue;
            }

            if (!empty($rowData->get('invoice_no')) && !empty($rowData->get('dealer'))) {
                $exists = Service::where('invoice_no', $rowData->get('invoice_no'))
                                 ->where('dealer_code', $rowData->get('dealer'))
                                 ->exists();

                if ($exists) {
                    $this->currentService = null;
                    $this->skippedCount++;
                    continue;
                }

                $regDate = is_numeric($rowData->get('reg_date')) ? Date::excelToDateTimeObject($rowData->get('reg_date'))->format('Y-m-d') : null;

                $this->currentService = Service::create([
                    'invoice_no' => $rowData->get('invoice_no'),
                    'dealer_code' => $rowData->get('dealer'),
                    'yss' => $rowData->get('yss'),
                    'point' => $rowData->get('point'),
                    'reg_date' => $regDate,
                    'service_order' => $rowData->get('service_order'),
                    'plate_no' => $rowData->get('plate_no'),
                    'work_order_no' => $rowData->get('no_work_order'),
                    'work_order_status' => $rowData->get('status_work_order'),
                    'customer_name' => $rowData->get('nama'),
                    'customer_ktp' => $rowData->get('ktp'),
                    'customer_npwp_no' => $rowData->get('no_npwp'),
                    'customer_npwp_name' => $rowData->get('name_npwp'),
                    'customer_phone' => $rowData->get('telepon_no'),
                    'mc_brand' => $rowData->get('brand'),
                    'mc_model_name' => $rowData->get('model_name'),
                    'mc_frame_no' => $rowData->get('frame_no'),
                    'technician_name' => $rowData->get('technician_name'),
                    'payment_type' => $rowData->get('payment_type'),
                    'transaction_code' => $rowData->get('transaction_code'),
                    'e_payment_amount' => $rowData->get('e_payment_amount', 0),
                    'cash_amount' => $rowData->get('cash_amount', 0),
                    'debit_amount' => $rowData->get('debit_amount', 0),
                    'total_down_payment' => $rowData->get('down_payment_dp', 0),
                    'total_labor' => $rowData->get('total_labor', 0),
                    'total_part_service' => $rowData->get('total_part_service', 0),
                    'total_oil_service' => $rowData->get('total_oil_service', 0),
                    'total_retail_parts' => $rowData->get('total_retail_parts', 0),
                    'total_retail_oil' => $rowData->get('total_retail_oil', 0),
                    'total_amount' => $rowData->get('total_amount', 0),
                    'benefit_amount' => $rowData->get('benefit_amount', 0),
                    'total_payment' => $rowData->get('total_payment', 0),
                    'balance' => $rowData->get('balance', 0),
                ]);

                $this->importedCount++;
            }

            if (!$this->currentService) {
                continue;
            }

            if (!empty($rowData->get('labor_cost_service')) && is_numeric($rowData->get('labor_cost_service'))) {
                $this->currentService->details()->updateOrCreate(
                    ['item_category' => 'JASA', 'item_name' => $rowData->get('service_package')],
                    ['service_category_code' => $rowData->get('service_category'), 'service_package_name' => $rowData->get('service_package'), 'quantity' => 1, 'price' => $rowData->get('labor_cost_service')]
                );
            }

            if (!empty($rowData->get('parts_no')) && !empty($rowData->get('parts_name'))) {
                $category = (stripos($rowData->get('parts_name'), 'oil') !== false || stripos($rowData->get('parts_name'), 'lube') !== false) ? 'OLI' : 'PART';
                $this->currentService->details()->updateOrCreate(
                    ['item_category' => $category, 'item_code' => $rowData->get('parts_no')],
                    ['item_name' => $rowData->get('parts_name'), 'quantity' => $rowData->get('parts_qty'), 'price' => $rowData->get('parts_price')]
                );
            }
        }
    }

    public function getImportedCount()
    {
        return $this->importedCount;
    }

    public function getSkippedCount()
    {
        return $this->skippedCount;
    }
}
