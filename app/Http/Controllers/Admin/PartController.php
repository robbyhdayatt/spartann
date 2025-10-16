<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Part;
use App\Models\Brand;
use App\Models\Category;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\PartsImport;
use Maatwebsite\Excel\Validators\ValidationException;

class PartController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $parts = Part::with(['brand', 'category'])->latest()->get();
        $brands = Brand::where('is_active', true)->orderBy('nama_brand')->get();
        $categories = Category::where('is_active', true)->orderBy('nama_kategori')->get();

        return view('admin.parts.index', compact('parts', 'brands', 'categories'));
    }

    public function store(Request $request)
    {
        $this->authorize('is-super-admin');

        // PERBAIKAN: Validasi disesuaikan dengan kolom harga baru
        $validated = $request->validate([
            'kode_part' => 'required|string|max:50|unique:parts',
            'nama_part' => 'required|string|max:255',
            'brand_id' => 'required|exists:brands,id',
            'category_id' => 'required|exists:categories,id',
            'satuan' => 'required|string|max:20',
            'stok_minimum' => 'nullable|integer|min:0',
            'dpp' => 'required|numeric|min:0',
            'ppn' => 'required|numeric|min:0',
            'harga_satuan' => 'required|numeric|min:0',
        ]);

        Part::create($validated);

        return redirect()->route('admin.parts.index')->with('success', 'Part berhasil ditambahkan!');
    }

    public function update(Request $request, Part $part)
    {
        $this->authorize('is-super-admin');

        // PERBAIKAN: Validasi disesuaikan dengan kolom harga baru
        $validated = $request->validate([
            'kode_part' => 'required|string|max:50|unique:parts,kode_part,' . $part->id,
            'nama_part' => 'required|string|max:255',
            'brand_id' => 'required|exists:brands,id',
            'category_id' => 'required|exists:categories,id',
            'satuan' => 'required|string|max:20',
            'stok_minimum' => 'nullable|integer|min:0',
            'dpp' => 'required|numeric|min:0',
            'ppn' => 'required|numeric|min:0',
            'harga_satuan' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',
        ]);

        $part->update($validated);

        return redirect()->route('admin.parts.index')->with('success', 'Part berhasil diperbarui!');
    }

    public function destroy(Part $part)
    {
        $this->authorize('is-super-admin');

        if ($part->inventoryBatches()->exists() || $part->penjualanDetails()->exists() || $part->purchaseOrderDetails()->exists()) {
            return redirect()->route('admin.parts.index')->with('error', 'Part tidak dapat dihapus karena sudah memiliki riwayat transaksi.');
        }

        $part->delete();
        return redirect()->route('admin.parts.index')->with('success', 'Part berhasil dihapus!');
    }

    public function import(Request $request)
    {
        $this->authorize('is-super-admin');

        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
        ]);

        try {
            Excel::import(new PartsImport, $request->file('file'));
        } catch (ValidationException $e) {
            $failures = $e->failures();
            $errorMessages = [];
            foreach ($failures as $failure) {
                $errorMessages[] = 'Baris ' . $failure->row() . ': ' . implode(', ', $failure->errors());
            }
            return back()->with('import_errors', $errorMessages);
        }

        return redirect()->route('admin.parts.index')->with('success', 'Data part berhasil diimpor.');
    }
public function search(Request $request)
    {
        $query = $request->get('q');
        $lokasiId = $request->get('lokasi_id'); // Ambil ID lokasi dari request

        $parts = Part::where('is_active', true)
            ->where(function($q) use ($query) {
                $q->where('nama_part', 'like', "%{$query}%")
                  ->orWhere('kode_part', 'like', "%{$query}%");
            })
            // Tambahkan withSum dengan kondisi berdasarkan lokasi
            ->when($lokasiId, function ($q) use ($lokasiId) {
                $q->withSum(['inventoryBatches' => function ($query) use ($lokasiId) {
                    $query->where('gudang_id', $lokasiId);
                }], 'quantity');
            })
            ->limit(20)
            ->get();

        // Mengubah format output JSON agar sesuai dengan kebutuhan Select2
        return response()->json($parts->map(function($part) {
            return [
                'id' => $part->id,
                'text' => "{$part->kode_part} - {$part->nama_part}",
                'harga_satuan' => $part->harga_satuan,
                'satuan' => $part->satuan,
                // Kirim data stok ke frontend
                'total_stock' => (int) $part->inventory_batches_sum_quantity ?? 0,
            ];
        }));
    }
}
