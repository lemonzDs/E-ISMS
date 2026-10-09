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
        Schema::table('risks', function (Blueprint $table) {
            $table->unsignedInteger('assessment_cycle')->default(1);
            $table->unsignedInteger('treatment_revision')->default(0);
        });
        Schema::create('risk_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('cycle');
            $table->string('title');
            $table->text('description');
            $table->foreignId('assignee_id')->constrained('users')->restrictOnDelete();
            $table->date('due_date');
            $table->string('status', 20)->default('open');
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->index(['risk_id', 'cycle', 'status']);
        });
        Schema::create('risk_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_action_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploader_id')->constrained('users')->restrictOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->unsignedBigInteger('size');
            $table->text('summary');
            $table->timestamps();
        });
        Schema::create('residual_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_id')->constrained()->restrictOnDelete();
            $table->foreignId('assessor_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('cycle');
            $table->unsignedInteger('treatment_revision');
            $table->unsignedTinyInteger('likelihood');
            $table->unsignedTinyInteger('impact');
            $table->unsignedTinyInteger('score');
            $table->string('level', 20);
            $table->text('controls');
            $table->text('rationale');
            $table->json('method');
            $table->json('action_ids');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('residual_assessments');
        Schema::dropIfExists('risk_evidence');
        Schema::dropIfExists('risk_actions');
        Schema::table('risks', function (Blueprint $table) {
            $table->dropColumn(['assessment_cycle', 'treatment_revision']);
        });
    }
};
