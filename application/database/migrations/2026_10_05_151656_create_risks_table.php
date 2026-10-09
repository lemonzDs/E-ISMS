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
        Schema::create('risks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->string('asset_process');
            $table->text('threat');
            $table->text('vulnerability');
            $table->text('consequence');
            $table->text('existing_controls');
            $table->text('rationale');
            $table->unsignedTinyInteger('likelihood');
            $table->unsignedTinyInteger('impact');
            $table->unsignedTinyInteger('score');
            $table->string('level', 20);
            $table->json('method');
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('lock_version')->default(0);
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['department_id', 'status']);
        });
        Schema::table('audit_events', function (Blueprint $table) {
            $table->foreignId('risk_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('risk_id');
        });
        Schema::dropIfExists('risks');
    }
};
