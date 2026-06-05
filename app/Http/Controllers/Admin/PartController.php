<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Part;
use Illuminate\Http\Request;
use App\Imports\PartsImport;
use App\Exports\PartsTemplateExport;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\Facades\DataTables;

class PartController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('view-ygp');

        if ($request->ajax()) {
            $data = Part::latest(); // Jangan pakai get(), biarkan Yajra yang memprosesnya

            return DataTables::of($data)
                ->addIndexColumn()
                ->editColumn('qty_stok', function($row){
                    if($row->qty_stok <= $row->stok_minimum) {
                        return '<span class="text-danger font-weight-bold">'.$row->qty_stok.'</span>';
                    }
                    return $row->qty_stok;
                })
                ->editColumn('cost', function($row){
                    return 'Rp ' . number_format($row->cost, 0, ',', '.');
                })
                ->editColumn('retail', function($row){
                    return 'Rp ' . number_format($row->retail, 0, ',', '.');
                })
                ->editColumn('is_active', function($row){
                    return $row->is_active 
                        ? '<span class="badge badge-success">Aktif</span>' 
                        : '<span class="badge badge-danger">Non-Aktif</span>';
                })
                ->addColumn('aksi', function($row){
                    $btn = '';
                    if(auth()->user()->can('manage-ygp')) {
                        $btn .= '<button type="button" class="btn btn-xs btn-warning btn-edit" 
                                    data-id="'.$row->id.'" data-kode="'.$row->kode_part.'" 
                                    data-nama="'.$row->nama_part.'" data-min="'.$row->stok_minimum.'" 
                                    data-qty="'.$row->qty_stok.'" data-cost="'.$row->cost.'" data-retail="'.$row->retail.'" 
                                    data-active="'.$row->is_active.'" title="Edit">
                                    <i class="fas fa-edit"></i>
                                 </button> ';
                                 
                        $deleteUrl = route('admin.parts.destroy', $row->id);
                        $csrf = csrf_field();
                        $method = method_field('DELETE');
                        
                        $btn .= '<form action="'.$deleteUrl.'" method="POST" style="display:inline-block;" onsubmit="return confirm(\'Yakin ingin menghapus item ini?\');">
                                    '.$csrf.' '.$method.'
                                    <button type="submit" class="btn btn-xs btn-danger" title="Hapus"><i class="fas fa-trash"></i></button>
                                 </form>';
                    } else {
                        $btn = '-';
                    }
                    return $btn;
                })
                ->rawColumns(['qty_stok', 'is_active', 'aksi']) // Agar tag HTML terbaca
                ->make(true);
        }

        return view('admin.parts.index');
    }

    public function store(Request $request)
    {
        $this->authorize('manage-ygp');
        $request->validate([
            'kode_part' => 'required|unique:parts,kode_part',
            'nama_part' => 'required|string',
            'stok_minimum' => 'required|integer|min:0',
            'qty_stok' => 'required|integer|min:0',
            'cost' => 'required|numeric|min:0',
            'retail' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',
        ]);

        Part::create($request->all());

        return back()->with('success', 'Item YGP berhasil ditambahkan.');
    }

    public function update(Request $request, Part $part)
    {
        $this->authorize('manage-ygp');

        $request->validate([
            'kode_part' => 'required|unique:parts,kode_part,' . $part->id,
            'nama_part' => 'required|string',
            'stok_minimum' => 'required|integer|min:0',
            'qty_stok' => 'required|integer|min:0',
            'cost' => 'required|numeric|min:0',
            'retail' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',
        ]);

        $part->update($request->all());

        return back()->with('success', 'Item YGP berhasil diperbarui.');
    }

    public function destroy(Part $part)
    {
        $this->authorize('manage-ygp');
        $part->delete();
        return back()->with('success', 'Item YGP berhasil dihapus.');
    }

    public function import(Request $request)
    {
        $this->authorize('manage-ygp');
        
        $request->validate([
            'file' => 'required|mimes:xls,xlsx,csv'
        ]);

        ini_set('max_execution_time', 0); 

        try {
            Excel::import(new PartsImport, $request->file('file'));
            return back()->with('success', 'Data Item YGP berhasil diimport!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mengimpor file: ' . $e->getMessage());
        }
    }

    public function downloadTemplate()
    {
        $this->authorize('manage-ygp');
        
        return Excel::download(new PartsTemplateExport, 'template_import_item_ygp.xlsx');
    }
}