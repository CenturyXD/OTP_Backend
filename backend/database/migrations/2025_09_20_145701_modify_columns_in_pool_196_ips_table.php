<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pool_196_ips', function (Blueprint $table) {
            // ลบคอลัมน์ 'division' ที่ไม่ต้องการออก
            if (Schema::hasColumn('pool_196_ips', 'division')) {
                $table->dropColumn('division');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pool_196_ips', function (Blueprint $table) {
            // เพิ่มคอลัมน์ 'division' กลับมา (สำหรับการย้อนกลับ)
            if (!Schema::hasColumn('pool_196_ips', 'division')) {
                $table->string('division')->after('customer')->nullable();
            }
        });
    }
};
