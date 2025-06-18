<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateCompanyUserForeignKey extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // First drop the old foreign key
            $table->dropForeign(['company_id']);

            // Now re-add it with SET NULL instead of CASCADE
            $table->foreign('company_id')
                  ->references('id')
                  ->on('companies')
                  ->nullOnDelete(); // <- this is the key
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->foreignId('company_id')->nullable()->constrained()->onDelete('cascade');
        });
    }
}
