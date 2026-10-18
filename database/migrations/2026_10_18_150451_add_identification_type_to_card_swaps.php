<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_swaps', function (Blueprint $table) {
            $table->string('identification_type', 32)->nullable();
            $table->index(['company_id', 'identification_type']);
        });
    }

    public function down(): void
    {
        Schema::table('card_swaps', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'identification_type']);
            $table->dropColumn('identification_type');
        });
    }
};
