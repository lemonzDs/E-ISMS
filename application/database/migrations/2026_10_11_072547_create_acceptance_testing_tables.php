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
        Schema::create('acceptance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('tester_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->string('build_reference', 100);
            $table->text('scope');
            $table->string('status')->default('draft')->index();
            $table->unsignedInteger('lock_version')->default(0);
            $table->text('decision_comment')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
        Schema::create('acceptance_scenarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('acceptance_session_id')->constrained()->restrictOnDelete();
            $table->string('module', 100);
            $table->string('title');
            $table->text('steps');
            $table->text('expected');
            $table->timestamps();
        });
        Schema::create('acceptance_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('acceptance_scenario_id')->constrained()->restrictOnDelete();
            $table->foreignId('tested_by')->constrained('users')->restrictOnDelete();
            $table->string('result');
            $table->text('observed');
            $table->string('evidence_path')->nullable();
            $table->string('evidence_name')->nullable();
            $table->text('fix_note')->nullable();
            $table->foreignId('fixed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('fixed_at')->nullable();
            $table->timestamps();
        });
        Schema::table('audit_events', function (Blueprint $table) {
            $table->foreignId('acceptance_session_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('acceptance_session_id');
        });
        Schema::dropIfExists('acceptance_attempts');
        Schema::dropIfExists('acceptance_scenarios');
        Schema::dropIfExists('acceptance_sessions');
    }
};
