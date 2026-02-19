<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class BarangController extends Controller
{
    /**
     * Menampilkan daftar barang.
     */
    public function index()
    {
        $this->authorize('view-barang');
        
        $barangs = Barang::latest()->get();
        return view('admin.barangs.index', compact('barangs'));
    }

    /**
     * Menyimpan barang baru.
     */
    public function store(Request $request)
    {
        $this->authorize('manage-barang');
        
        $user = Auth::user();

        // 1. Aturan Validasi Dasar
        $rules = [
            'part_name' => 'required|string|max:255',
            'merk'      => 'nullable|string|max:100',
            'part_code' => 'required|string|max:50|unique:barangs,part_code',
            'is_active' => 'nullable|boolean', 
        ];

        // 2. Validasi Kondisional (Harga)
        if ($user->can('view-price-selling-in')) {
            $rules['selling_in'] = 'required|numeric|min:0'; 
        }

        if ($user->can('view-price-selling-out')) {
            $rules['selling_out'] = 'required|numeric|min:0';
            $rules['retail']      = 'required|numeric|min:0';
        }

        // 3. Bersihkan Input Angka
        $this->cleanCurrencyRequest($request, ['selling_in', 'selling_out', 'retail']);

        // 4. Jalankan Validasi
        $validated = $request->validate($rules);

        // 5. Siapkan Data
        $data = [
            'part_name' => $validated['part_name'],
            'merk'      => $validated['merk'] ?? null,
            'part_code' => $validated['part_code'],
            'is_active' => $request->has('is_active') ? $request->is_active : true,
            'selling_in'  => 0,
            'selling_out' => 0,
            'retail'      => 0,
        ];

        if ($user->can('view-price-selling-in')) {
            $data['selling_in'] = $validated['selling_in'];
        }

        if ($user->can('view-price-selling-out')) {
            $data['selling_out'] = $validated['selling_out'];
            $data['retail']      = $validated['retail'];
        }

        Barang::create($data);

        return redirect()->route('admin.barangs.index')->with('success', 'Barang baru berhasil ditambahkan.');
    }

    /**
     * Menampilkan detail barang (biasanya untuk API/Modal).
     */
    public function show(Barang $barang)
    {
        $this->authorize('view-barang');
        return response()->json($barang);
    }

    /**
     * Mengupdate data barang.
     */
    public function update(Request $request, Barang $barang)
    {
        $this->authorize('manage-barang');
        
        $user = Auth::user();
        $request->session()->flash('edit_form_id', $barang->id);

        // 1. Aturan Validasi Dasar
        $rules = [
            'part_name' => 'required|string|max:255',
            'merk'      => 'nullable|string|max:100',
            'part_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('barangs')->ignore($barang->id),
            ],
            'is_active' => 'required|boolean',
        ];

        // 2. Validasi Kondisional
        if ($user->can('view-price-selling-in')) {
            $rules['selling_in'] = 'required|numeric|min:0';
        }

        if ($user->can('view-price-selling-out')) {
            $rules['selling_out'] = 'required|numeric|min:0';
            $rules['retail']      = 'required|numeric|min:0';
        }

        // 3. Bersihkan Input
        $this->cleanCurrencyRequest($request, ['selling_in', 'selling_out', 'retail']);

        // 4. Jalankan Validasi
        $validated = $request->validate($rules);

        // 5. VALIDASI LOGIKA: Cek Stok Sebelum Nonaktifkan
        // Jika user ingin menonaktifkan barang (is_active = 0)
        if ($request->has('is_active') && $request->is_active == 0) {
            // Cek total stok menggunakan accessor yang sudah ada di Model Barang
            if ($barang->total_stock > 0) {
                return back()->with('error', "Gagal menonaktifkan! Barang ini masih memiliki sisa stok {$barang->total_stock} unit. Harap kosongkan stok (Retur/Adjustment/Jual) terlebih dahulu.")->withInput();
            }
        }

        // 6. Siapkan Data Update
        $data = [
            'part_name' => $validated['part_name'],
            'merk'      => $validated['merk'] ?? null,
            'part_code' => $validated['part_code'],
            'is_active' => $validated['is_active'],
        ];

        // 7. Update Harga SECARA SELEKTIF
        if ($user->can('view-price-selling-in')) {
            $data['selling_in'] = $validated['selling_in'];
        }

        if ($user->can('view-price-selling-out')) {
            $data['selling_out'] = $validated['selling_out'];
            $data['retail']      = $validated['retail'];
        }

        $barang->update($data);

        return redirect()->route('admin.barangs.index')->with('success', 'Data barang berhasil diperbarui.');
    }

    /**
     * Menghapus barang.
     */
    public function destroy(Barang $barang)
    {
        $this->authorize('manage-barang');

        try {
            $barang->delete();
            return redirect()->route('admin.barangs.index')->with('success', 'Data barang berhasil dihapus.');
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->route('admin.barangs.index')->with('error', 'Gagal menghapus barang. Data ini mungkin sudah digunakan dalam transaksi.');
        }
    }

    /**
     * Helper: Menghapus titik dari format currency Indonesia
     */
    private function cleanCurrencyRequest(Request $request, array $fields)
    {
        foreach ($fields as $field) {
            if ($request->has($field)) {
                $val = $request->input($field);
                if (is_string($val)) {
                    $cleanVal = str_replace('.', '', $val);
                    $request->merge([$field => $cleanVal]);
                }
            }
        }
    }
}