<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', fn (Blueprint $t) => $t->longText('document_branding')->nullable());
        Schema::table('card_swaps', function (Blueprint $t) {
            $t->longText('branding_snapshot')->nullable();
            $t->softDeletes();
            $t->index(['company_id', 'created_by', 'status', 'deleted_at'], 'swap_draft_recovery_index');
        });
    }

    public function down(): void
    {
        Schema::table('card_swaps', function (Blueprint $t) {
            $t->dropIndex('swap_draft_recovery_index');
            $t->dropColumn(['branding_snapshot', 'deleted_at']);
        });
        Schema::table('companies', fn (Blueprint $t) => $t->dropColumn('document_branding'));
    }
};
