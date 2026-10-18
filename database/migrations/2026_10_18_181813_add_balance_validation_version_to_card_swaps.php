<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_swaps', function (Blueprint $table) {
            $table->unsignedTinyInteger('balance_validation_version')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('card_swaps', function (Blueprint $table) {
            $table->dropColumn('balance_validation_version');
        });
    }
};
