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
        Schema::create('ferro_sup_orders', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('sup_order_id');
            $table->bigInteger('bitrix_contact_id');
            $table->string('contact_sup_id')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ferro_sup_orders');
    }
};
