<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BrkIp;
use Illuminate\Http\Request;
use App\Http\Requests\Api\BrkIpRequest;
use Illuminate\Support\Facades\Auth;

class BrkIpController extends Controller
{
    /**
     * แสดงรายการ IP ทั้งหมดใน Brk
     */
    public function index(Request $request)
    {
        // 1. ดึงค่า per_page จาก request, ถ้าไม่มีให้ใช้ 5 เป็นค่าเริ่มต้น
        $perPage = $request->query('per_page', 5);
        $searchTerm = $request->query('search');

        $query = BrkIp::query()->with('updater')
            ->when($searchTerm, function ($q, $searchTerm) {
                // แปลงคำค้นหาเป็นตัวพิมพ์เล็ก
                $lowerSearchTerm = strtolower($searchTerm);

                // ใช้ whereRaw เพื่อเปรียบเทียบกับข้อมูลที่แปลงเป็นตัวพิมพ์เล็กแล้ว
                return $q->where(function ($subQuery) use ($lowerSearchTerm) {
                    $subQuery->whereRaw('LOWER(ip_address) LIKE ?', ["%{$lowerSearchTerm}%"])
                             ->orWhereRaw('LOWER(contact) LIKE ?', ["%{$lowerSearchTerm}%"])
                             ->orWhereRaw('LOWER(phone) LIKE ?', ["%{$lowerSearchTerm}%"])
                             ->orWhereRaw('LOWER(remark) LIKE ?', ["%{$lowerSearchTerm}%"])
                             ->orWhereRaw('LOWER(status) LIKE ?', ["%{$lowerSearchTerm}%"]);
                });
            });

        // 2. ใช้ตัวแปร $perPage ในการแบ่งหน้า
        $ips = $query->latest('updated_at')->paginate($perPage)->withQueryString();

        return response()->json($ips);
    }

    /**
     * สร้าง IP ใหม่ใน Core
     */
    public function store(BrkIpRequest $request) // 2. เปลี่ยน Request
    {
        $validatedData = $request->validated(); // 3. ใช้ validated()
        $validatedData['updated_by'] = Auth::id();

        $ip = BrkIp::create($validatedData);
        $ip->load('updater');

        return response()->json([
            'message' => 'Brk IP created successfully.',
            'data' => $ip
        ], 201);
    }

    /**
     * แสดงข้อมูล IP เดียว
     */
    public function show(BrkIp $brkIp)
    {
        return response()->json($brkIp);
    }

    /**
     * อัปเดตข้อมูล IP
     */
    public function update(BrkIpRequest $request, BrkIp $brkIp) // 4. เปลี่ยน Request
    {
        $validatedData = $request->validated();
        $validatedData['updated_by'] = Auth::id();

        $brkIp->update($validatedData);
        $brkIp->load('updater');

        return response()->json([
            'message' => 'Brk IP updated successfully.',
            'data' => $brkIp
        ]);
    }


}
