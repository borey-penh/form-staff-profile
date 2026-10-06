<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expands the Personnel Profile (5-tab wizard) data model:
 *  - users: alternate phone/email, structured address, notes, declaration timestamp,
 *           gender extended per the new form spec.
 *  - qualifications: type extended with "training" and "membership".
 *  - emergency_contacts: email column.
 *  - documents: description + remark meta.
 *  - new tables: expertises, language_skills, geographic_experiences, beneficiaries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('gender', ['Male', 'Female', 'Other', 'Non-binary', 'Prefer not to say'])
                ->nullable()->change();

            $table->string('phone_alt', 32)->nullable()->after('phone');
            $table->string('email_alt')->nullable()->after('phone_alt');
            $table->string('addr_house', 64)->nullable()->after('email_alt');
            $table->string('addr_street', 128)->nullable()->after('addr_house');
            $table->string('addr_village', 128)->nullable()->after('addr_street');
            $table->string('addr_commune', 128)->nullable()->after('addr_village');
            $table->string('addr_district', 128)->nullable()->after('addr_commune');
            $table->string('addr_province', 128)->nullable()->after('addr_district');
            $table->string('addr_postal', 16)->nullable()->after('addr_province');
            $table->text('notes_to_org')->nullable()->after('addr_postal');
            $table->timestamp('declaration_accepted_at')->nullable()->after('notes_to_org');
        });

        Schema::table('qualifications', function (Blueprint $table) {
            $table->enum('type', ['education', 'experience', 'training', 'membership'])->change();
        });

        Schema::table('emergency_contacts', function (Blueprint $table) {
            $table->string('email')->nullable()->after('phone');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->string('description')->nullable()->after('type');
            $table->string('remark')->nullable()->after('description');
        });

        Schema::create('expertises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('language_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('language', 64);
            $table->boolean('is_mother_tongue')->default(false);
            $table->enum('reading', ['Fluent', 'Good', 'Fair', 'Basic'])->nullable();
            $table->enum('writing', ['Fluent', 'Good', 'Fair', 'Basic'])->nullable();
            $table->enum('speaking', ['Fluent', 'Good', 'Fair', 'Basic'])->nullable();
            $table->enum('understanding', ['Fluent', 'Good', 'Fair', 'Basic'])->nullable();
            $table->timestamps();
        });

        Schema::create('geographic_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('country', 128);
            $table->string('province', 128)->nullable();
            $table->timestamps();
        });

        Schema::create('beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->date('dob')->nullable();
            $table->string('id_number', 64)->nullable();
            $table->string('relationship')->nullable();
            $table->string('contact', 32)->nullable();
            $table->string('address')->nullable();
            $table->decimal('share', 5, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiaries');
        Schema::dropIfExists('geographic_experiences');
        Schema::dropIfExists('language_skills');
        Schema::dropIfExists('expertises');

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['description', 'remark']);
        });

        Schema::table('emergency_contacts', function (Blueprint $table) {
            $table->dropColumn('email');
        });

        Schema::table('qualifications', function (Blueprint $table) {
            $table->enum('type', ['education', 'experience'])->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('gender', ['Male', 'Female', 'Other'])->nullable()->change();
            $table->dropColumn([
                'phone_alt', 'email_alt', 'addr_house', 'addr_street', 'addr_village',
                'addr_commune', 'addr_district', 'addr_province', 'addr_postal',
                'notes_to_org', 'declaration_accepted_at',
            ]);
        });
    }
};
