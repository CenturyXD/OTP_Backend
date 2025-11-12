<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Intranet;
use Illuminate\Http\Request;
use App\Http\Requests\Api\IntranetRequest;
use Illuminate\Support\Facades\Auth;

class IntranetController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // 1. ดึงค่า per_page จาก request, ถ้าไม่มีให้ใช้ 5 เป็นค่าเริ่มต้น
        
        $perPage = $request->query('per_page', 5);
        $searchTerm = $request->query('search');

        $query = Intranet::query()->with('updater')
            ->when($searchTerm, function ($q, $searchTerm) {
                // แปลงคำค้นหาเป็นตัวพิมพ์เล็ก
                $lowerSearchTerm = strtolower($searchTerm);

                // ใช้ whereRaw เพื่อเปรียบเทียบกับข้อมูลที่แปลงเป็นตัวพิมพ์เล็กแล้ว
                return $q->where(function ($subQuery) use ($lowerSearchTerm) {
                    $subQuery->whereRaw('LOWER(ip_address) LIKE ?', ["%{$lowerSearchTerm}%"])
                             ->orWhereRaw('LOWER(contact) LIKE ?', ["%{$lowerSearchTerm}%"])
                             ->orWhereRaw('LOWER(phone) LIKE ?', ["%{$lowerSearchTerm}%"])
                             ->orWhereRaw('LOWER(remark) LIKE ?', ["%{$lowerSearchTerm}%"])
                             ->orWhereRaw('LOWER(customer) LIKE ?', ["%{$lowerSearchTerm}%"])
                             ->orWhereRaw('LOWER(status) LIKE ?', ["%{$lowerSearchTerm}%"]);
                });
            });

        // 2. ใช้ตัวแปร $perPage ในการแบ่งหน้า
        // $ips = $query->latest('updated_at')->paginate($perPage)->withQueryString();
        $ips = $query->orderBy('id', 'ASC')->paginate($perPage)->withQueryString();
        return response()->json($ips);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(IntranetRequest $request) // 2. เปลี่ยน Request
    {
        $validatedData = $request->validated(); // 3. ใช้ validated()
        $validatedData['updated_by'] = Auth::id();

        $ip = Intranet::create($validatedData);
        $ip->load('updater');

        return response()->json([
            'message' => 'Intranet IP created successfully.',
            'data' => $ip
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Intranet $IntraIp)
    {
        //
        return response()->json($IntraIp);
    }


    /**
     * Update the specified resource in storage.
     */

    public function update(IntranetRequest $request, Intranet $IntraIp) // 4. เปลี่ยน Request
    {
        $validatedData = $request->validated();
        $validatedData['updated_by'] = Auth::id();

        $IntraIp->update($validatedData);
        $IntraIp->load('updater');

        return response()->json([
            'message' => 'Brk IP updated successfully.',
            'data' => $IntraIp
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
