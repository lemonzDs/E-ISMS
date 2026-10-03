<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('role', 30)->default('officer')->index();
            $table->boolean('is_active')->default(true);
        });
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('title');
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['department_id', 'updated_at']);
        });
        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('number');
            $table->string('title')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 120);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('lock_version')->default(0);
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['document_id', 'number']);
        });
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('document_version_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('action', 60);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['document_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn(['role', 'is_active']);
        });
        Schema::dropIfExists('departments');
    }
};
