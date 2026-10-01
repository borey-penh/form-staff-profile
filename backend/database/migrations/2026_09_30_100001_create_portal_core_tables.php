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
            $table->string('name');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('staff_id')->unique()->after('id');
            $table->string('first_name')->after('staff_id');
            $table->string('last_name')->after('first_name');
            $table->string('name_kh')->nullable()->after('last_name');
            $table->enum('role', ['staff', 'admin'])->default('staff')->after('password');
            $table->string('position')->nullable()->after('role');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete()->after('position');
            $table->string('phone', 32)->nullable()->after('department_id');
            $table->text('address')->nullable()->after('phone');
            $table->string('photo_path')->nullable()->after('address');
            $table->string('signature_path')->nullable()->after('photo_path');
            $table->date('dob')->nullable()->after('signature_path');
            $table->enum('gender', ['Male', 'Female', 'Other'])->nullable()->after('dob');
            $table->string('pob')->nullable()->after('gender');
            $table->string('nationality')->default('Khmer')->after('pob');
            $table->string('nid', 64)->nullable()->after('nationality');
            $table->enum('marital', ['Single', 'Married', 'Divorced', 'Widowed'])->default('Single')->after('nid');
        });

        Schema::create('qualifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['education', 'experience']);
            $table->string('title');           // degree name or position
            $table->string('institution');     // school or organization
            $table->string('field')->nullable(); // field of study
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('description')->nullable(); // responsibilities
            $table->string('file_path')->nullable(); // certificate
            $table->timestamps();
        });

        Schema::create('children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('dob')->nullable();
            $table->enum('gender', ['Male', 'Female', 'Other'])->nullable();
            $table->enum('status', ['Alive', 'Deceased'])->default('Alive');
            $table->timestamps();
        });

        Schema::create('emergency_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship');
            $table->string('phone', 32);
            $table->string('addr')->nullable();
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');            // configurable label, e.g. National ID, CV
            $table->string('file_path');
            $table->string('original_name');
            $table->enum('status', ['Pending', 'Verified', 'Rejected'])->default('Pending');
            $table->timestamps();
        });

        Schema::create('spouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('occupation')->nullable();
            $table->string('phone', 32)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spouses');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('emergency_contacts');
        Schema::dropIfExists('children');
        Schema::dropIfExists('qualifications');
        Schema::dropIfExists('departments');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn([
            'staff_id', 'first_name', 'last_name', 'name_kh', 'role', 'position',
            'department_id', 'phone', 'address', 'photo_path', 'signature_path',
            'dob', 'gender', 'pob', 'nationality', 'nid', 'marital',
        ]));
    }
};
