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
        Schema::table('otp', function (Blueprint $table) {
            // ตัวอย่างการแก้ไข: เพิ่ม service, ปรับ type, เพิ่ม index, ฯลฯ
            $table->string('owner')->nullable()->after('otp'); // เพิ่มคอลัมน์ owner หลังคอลัมน์ otp
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('otp', function (Blueprint $table) {
            if (Schema::hasColumn('otp', 'owner')) {
                $table->dropColumn('owner');
            }
        });
    }
};
