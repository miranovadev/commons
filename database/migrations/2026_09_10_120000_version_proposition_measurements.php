<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposition_measurements', function (Blueprint $table): void {
            $table->string('calculator_version')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('proposition_measurements', function (Blueprint $table): void {
            $table->dropColumn('calculator_version');
        });
    }
};
