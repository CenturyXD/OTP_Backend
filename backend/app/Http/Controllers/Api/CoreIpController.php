<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoreIp;
use Illuminate\Http\Request;
use App\Http\Requests\Api\CoreIpRequest;
use Illuminate\Support\Facades\Auth;

class CoreIpController extends Controller
{
    /**
     * แสดงรายการ IP ทั้งหมดใน Core
     */
    public function index(Request $request)
    {
        // 1. ดึงค่า per_page จาก request, ถ้าไม่มีให้ใช้ 5 เป็นค่าเริ่มต้น
        $perPage = $request->query('per_page', 5);
        $searchTerm = $request->query('search');

        $query = CoreIp::query()->with('updater')
            ->when($searchTerm, function ($q, $searchTerm) {
                // แปลงคำค้นหาเป็นตัวพิมพ์เล็ก
                $lowerSearchTerm = strtolower($searchTerm);

                // ใช้ whereRaw เพื่อเปรียบเทียบกับข้อมูลที่แปลงเป็นตัวพิมพ์เล็กแล้ว
                return $q->where(function ($subQuery) use ($lowerSearchTerm) {
                    $subQuery->whereRaw('LOWER(ip_address) LIKE ?', ["%{$lowerSearchTerm}%"])
                             ->orWhereRaw('LOWER(division) LIKE ?', ["%{$lowerSearchTerm}%"])
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
    public function store(CoreIpRequest $request) // 2. เปลี่ยน Request
    {
        $validatedData = $request->validated(); // 3. ใช้ validated()
        $validatedData['updated_by'] = Auth::id();

        $ip = CoreIp::create($validatedData);
        $ip->load('updater');

        return response()->json([
            'message' => 'Core IP created successfully.',
            'data' => $ip
        ], 201);
    }

    /**
     * แสดงข้อมูล IP เดียว
     */
    public function show(CoreIp $coreIp)
    {
        return response()->json($coreIp);
    }

    /**
     * อัปเดตข้อมูล IP
     */
    public function update(CoreIpRequest $request, CoreIp $coreIp) // 4. เปลี่ยน Request
    {
        $validatedData = $request->validated();
        $validatedData['updated_by'] = Auth::id();

        $coreIp->update($validatedData);
        $coreIp->load('updater');

        return response()->json([
            'message' => 'Core IP updated successfully.',
            'data' => $coreIp
        ]);
    }

    /**
     * ลบ IP
     */
    public function destroy(CoreIp $coreIp)
    {
        $coreIp->delete();
        return response()->json(['message' => 'Core IP deleted successfully.']);
    }
}
