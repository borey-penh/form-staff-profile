<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reusable compliance declarations (admin-configurable)
        Schema::create('compliances', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('policy_path')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('compliance_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compliance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('signature_path');
            $table->date('signed_at');
            $table->timestamps();
            $table->unique(['compliance_id', 'user_id']);
        });

        // Training courses (admin-configurable) with content sections
        Schema::create('trainings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('sections');      // [{title, body|video}]
            $table->json('quiz');          // [{question, options[], answer}]
            $table->unsignedTinyInteger('pass_score')->default(80);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('training_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('due_date')->nullable();
            $table->enum('status', ['Pending', 'Complete'])->default('Pending');
            $table->unsignedTinyInteger('progress')->default(0);   // 0-100
            $table->unsignedTinyInteger('score')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['training_id', 'user_id']);
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['Probationary', 'Full-Time', 'Part-Time', 'Intermittent', 'Volunteer', 'Internship']);
            $table->string('position');
            $table->string('department')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('salary', 12, 2)->nullable();
            $table->string('file_path')->nullable();
            $table->enum('status', ['Active', 'Completed', 'Terminated'])->default('Active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('training_assignments');
        Schema::dropIfExists('trainings');
        Schema::dropIfExists('compliance_signatures');
        Schema::dropIfExists('compliances');
    }
};
