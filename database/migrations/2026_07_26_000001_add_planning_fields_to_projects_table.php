<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('project_code', 50)->nullable()->unique()->after('id');
            $table->string('client_name')->nullable()->after('name');
            $table->string('location')->nullable()->after('client_name');
            $table->string('project_manager')->nullable()->after('location');
            $table->date('start_date')->nullable()->after('type');
            $table->date('expected_end_date')->nullable()->after('start_date');
            $table->decimal('contract_value', 16, 2)->nullable()->after('expected_end_date');
            $table->decimal('cost_budget', 16, 2)->nullable()->after('contract_value');
            $table->unsignedTinyInteger('progress_percentage')->default(0)->after('cost_budget');
            $table->text('description')->nullable()->after('progress_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique(['project_code']);
            $table->dropColumn([
                'project_code',
                'client_name',
                'location',
                'project_manager',
                'start_date',
                'expected_end_date',
                'contract_value',
                'cost_budget',
                'progress_percentage',
                'description',
            ]);
        });
    }
};
