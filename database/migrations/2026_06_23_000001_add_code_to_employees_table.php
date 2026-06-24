<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('code')->nullable()->after('id');
        });

        $nextCode = 310;

        DB::table('employees')
            ->orderBy('id')
            ->select(['id'])
            ->chunkById(100, function ($employees) use (&$nextCode) {
                foreach ($employees as $employee) {
                    DB::table('employees')
                        ->where('id', $employee->id)
                        ->update(['code' => (string) $nextCode]);

                    $nextCode++;
                }
            });

        Schema::table('employees', function (Blueprint $table) {
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
