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
        Schema::create('internal_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->text('scope');
            $table->text('criteria');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('draft')->index();
            $table->unsignedInteger('lock_version')->default(0);
            $table->text('conclusion')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('internal_audit_user', function (Blueprint $table) {
            $table->foreignId('internal_audit_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->boolean('is_lead')->default(false);
            $table->primary(['internal_audit_id', 'user_id']);
        });
        Schema::create('audit_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internal_audit_id')->constrained()->restrictOnDelete();
            $table->text('requirement');
            $table->string('reference');
            $table->string('result')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('audit_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internal_audit_id')->constrained()->restrictOnDelete();
            $table->foreignId('audit_checklist_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->string('category');
            $table->string('severity');
            $table->text('description');
            $table->date('due_date');
            $table->string('status')->default('open')->index();
            foreach (['root_cause', 'correction', 'corrective_action', 'effectiveness_measure', 'review_comment'] as $field) {
                $table->text($field)->nullable();
            }
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('audit_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internal_audit_id')->constrained()->restrictOnDelete();
            $table->foreignId('audit_checklist_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('audit_finding_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('kind');
            $table->text('summary');
            $table->timestamps();
        });
        Schema::table('audit_events', function (Blueprint $table) {
            $table->foreignId('internal_audit_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('audit_finding_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('audit_finding_id');
            $table->dropConstrainedForeignId('internal_audit_id');
        });
        foreach (['audit_evidence', 'audit_findings', 'audit_checklist_items', 'internal_audit_user', 'internal_audits'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
