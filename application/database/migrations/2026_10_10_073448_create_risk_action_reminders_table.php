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
        Schema::create('risk_action_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_action_id')->constrained()->restrictOnDelete();
            $table->foreignId('assignee_id')->constrained('users')->restrictOnDelete();
            $table->date('due_date');
            $table->string('stage', 20);
            $table->timestamp('created_at');
            $table->unique(['risk_action_id', 'assignee_id', 'due_date', 'stage'], 'risk_action_reminders_delivery_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('risk_action_reminders');
    }
};
