<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['roles', 'model_has_roles', 'model_has_permissions'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete());
        }
    }

    public function down(): void
    {
        foreach (['model_has_permissions', 'model_has_roles', 'roles'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropForeign(['company_id']));
        }
    }
};
