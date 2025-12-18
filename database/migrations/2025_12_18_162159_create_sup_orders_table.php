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
        Schema::create('sup_orders', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('sup_order_id');
            $table->bigInteger('bitrix_deal_id');
            $table->string('status', 255)->default('new')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sup_orders');
    }
};
