<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained();
            $t->string('language', 2);
            $t->string('version', 80);
            $t->text('declaration');
            $t->text('receipt');
            $t->foreignId('approved_by')->constrained('users');
            $t->timestamp('approved_at');
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['company_id', 'language', 'version']);
        });
        Schema::create('card_swaps', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 50)->unique();
            $t->uuid('submission_key');
            $t->foreignId('company_id')->constrained();
            $t->foreignId('created_by')->constrained('users');
            $t->foreignId('verified_by')->nullable()->constrained('users');
            $t->foreignId('location_id')->nullable()->constrained();
            $t->foreignId('consent_version_id')->nullable()->constrained();
            $t->string('status', 40)->default('draft');
            $t->unsignedTinyInteger('step')->default(1);
            $t->unsignedInteger('revision')->default(0);
            $t->date('swap_date')->nullable();
            $t->text('commuter_name')->nullable();
            $t->text('phone')->nullable();
            $t->text('email')->nullable();
            $t->text('national_id')->nullable();
            $t->text('old_card')->nullable();
            $t->text('new_card')->nullable();
            foreach (['national_id_hash', 'old_card_hash', 'new_card_hash', 'name_hash'] as $column) {
                $t->string($column, 64)->nullable();
                $t->index(['company_id', $column]);
            }
            $t->decimal('old_balance', 14, 2)->nullable();
            $t->decimal('new_balance', 14, 2)->nullable();
            $t->string('reason', 20)->nullable();
            $t->text('reason_other')->nullable();
            $t->text('remarks')->nullable();
            $t->string('consent_language', 2)->nullable();
            $t->text('consent_name')->nullable();
            $t->text('company_name_snapshot')->nullable();
            $t->text('staff_name_snapshot')->nullable();
            $t->timestamp('consent_signed_at')->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->timestamp('physical_completed_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->string('pdf_path')->nullable();
            $t->string('pdf_hash', 64)->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'created_by', 'submission_key']);
            $t->index(['company_id', 'swap_date', 'status']);
            $t->index(['company_id', 'created_by', 'swap_date']);
            $t->index(['company_id', 'reason']);
            $t->index(['company_id', 'location_id']);
        });
        Schema::create('swap_signatures', function (Blueprint $t) {
            $t->id();
            $t->foreignId('card_swap_id')->constrained();
            $t->foreignId('company_id')->constrained();
            $t->foreignId('captured_by')->constrained('users');
            $t->string('purpose', 20);
            $t->unsignedInteger('revision');
            $t->string('path');
            $t->string('sha256', 64);
            $t->string('payload_hash', 64);
            $t->longText('payload_snapshot');
            $t->timestamp('signed_at');
            $t->timestamps();
            $t->unique(['card_swap_id', 'purpose', 'revision']);
        });
        Schema::create('swap_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('card_swap_id')->constrained();
            $t->foreignId('company_id')->constrained();
            $t->foreignId('actor_id')->constrained('users');
            $t->string('type', 60);
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'card_swap_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('swap_events');
        Schema::dropIfExists('swap_signatures');
        Schema::dropIfExists('card_swaps');
        Schema::dropIfExists('consent_versions');
    }
};
