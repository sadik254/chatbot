<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlansTable extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. Free, Basic, Pro
            $table->string('paypal_plan_id')->nullable(); // From PayPal
            $table->decimal('price', 8, 2)->default(0.00);
            $table->string('currency')->default('USD');
            $table->json('features')->nullable(); // Optional
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
}

