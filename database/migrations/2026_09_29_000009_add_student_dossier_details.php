<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->string('photo_path')->nullable()->after('previous_school');
        });

        Schema::table('responsible_persons', function (Blueprint $table): void {
            $table->string('parent_code', 50)->nullable()->unique()->after('teacher_id');
            $table->string('title', 30)->nullable()->after('parent_code');
            $table->string('first_name', 100)->nullable()->after('full_name');
            $table->string('middle_name', 100)->nullable()->after('first_name');
            $table->string('last_name', 100)->nullable()->after('middle_name');
            $table->string('religion', 100)->nullable()->after('type');
            $table->string('family_type', 100)->nullable()->after('religion');
            $table->string('occupation', 150)->nullable()->after('family_type');
            $table->string('occupation_details')->nullable()->after('occupation');
            $table->string('register_number', 100)->nullable()->after('occupation_details');
            $table->string('district', 100)->nullable()->after('register_number');
            $table->string('town', 100)->nullable()->after('district');
            $table->string('nationality', 100)->nullable()->after('town');
            $table->string('secondary_nationality', 100)->nullable()->after('nationality');
            $table->boolean('is_alumni')->default(false)->after('secondary_nationality');
            $table->boolean('is_deceased')->default(false)->after('is_alumni');
            $table->string('employment_status', 100)->nullable()->after('is_deceased');
            $table->string('employer')->nullable()->after('employment_status');
            $table->string('position')->nullable()->after('employer');
            $table->text('work_address')->nullable()->after('position');
            $table->string('family_status', 100)->nullable()->after('work_address');
            $table->boolean('is_second_marriage')->default(false)->after('family_status');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropColumn('photo_path');
        });

        Schema::table('responsible_persons', function (Blueprint $table): void {
            $table->dropUnique(['parent_code']);
            $table->dropColumn([
                'parent_code', 'title', 'first_name', 'middle_name', 'last_name', 'religion', 'family_type',
                'occupation', 'occupation_details', 'register_number', 'district', 'town', 'nationality',
                'secondary_nationality', 'is_alumni', 'is_deceased', 'employment_status', 'employer', 'position',
                'work_address', 'family_status', 'is_second_marriage',
            ]);
        });
    }
};
