<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->unique(); // IP ห้ามซ้ำภายในตารางนี้
            $table->string('division');
            $table->string('contact');
            $table->string('phone');
            $table->text('remark')->nullable();
            $table->enum('status', ['active', 'inactive', 'reserved', 'maintenance'])->default('active');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_ips');
    }
};
