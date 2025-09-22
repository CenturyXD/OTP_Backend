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
        Schema::create('brk_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45);
            $table->string('customer', 255); // เปลี่ยนจาก division เป็น customer
            $table->string('contact', 255);
            $table->string('phone', 255);
            $table->text('remark')->nullable();
            $table->string('status', 255)->default('active');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brk_ips');
    }
};
