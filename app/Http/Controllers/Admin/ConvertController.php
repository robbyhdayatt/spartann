<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Convert;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class ConvertController extends Controller
{
    /**
     * Tambahkan constructor untuk menerapkan middleware Gate.
     */
    public function __construct()
    {
        // Hanya user dengan gate 'manage-converts' yang bisa mengakses method controller ini
        $this->middleware('can:manage-converts');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // $this->authorize('manage-converts'); // Tidak perlu jika sudah di middleware
        $converts = Convert::orderBy('nama_job')->get();
        return view('admin.converts.index', compact('converts'));
    }

    // ... (method create, store, edit, getEditData, update, destroy tetap sama) ...
    // Tidak perlu menambahkan $this->authorize() di setiap method karena sudah dilindungi oleh middleware di constructor.

     /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        abort(404);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'original_part_code' => 'nullable|string|max:255',
            'nama_job' => 'required|string|max:255|unique:converts,nama_job',
            'part_name' => 'required|string|max:255',
            'merk' => 'nullable|string|max:255',
            'part_code_input' => 'required|string|max:255',
            'keterangan' => 'nullable|string',
            'quantity' => 'required|integer|min:1',
            'harga_modal' => 'required|numeric|min:0',
            'harga_jual' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            Convert::create($validator->validated());
            return response()->json(['success' => 'Data convert berhasil ditambahkan.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Gagal menambahkan data convert. Silakan coba lagi.'], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
     public function edit(Convert $convert)
     {
         abort(404);
     }

    /**
     * Get data for editing via AJAX.
     */
     public function getEditData(Convert $convert)
     {
         return response()->json($convert);
     }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Convert $convert)
    {
         $validator = Validator::make($request->all(), [
            'original_part_code' => 'nullable|string|max:255',
            'nama_job' => ['required', 'string', 'max:255', Rule::unique('converts')->ignore($convert->id)],
            'part_name' => 'required|string|max:255',
            'merk' => 'nullable|string|max:255',
            'part_code_input' => 'required|string|max:255',
            'keterangan' => 'nullable|string',
            'quantity' => 'required|integer|min:1',
            'harga_modal' => 'required|numeric|min:0',
            'harga_jual' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $convert->update($validator->validated());
            return response()->json(['success' => 'Data convert berhasil diperbarui.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Gagal memperbarui data convert. Silakan coba lagi.'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Convert $convert)
    {
        try {
            $convert->delete();
            return redirect()->route('admin.converts.index')->with('success', 'Data convert berhasil dihapus.');
        } catch (\Exception $e) {
            return redirect()->route('admin.converts.index')->with('error', 'Gagal menghapus data convert.');
        }
    }
}