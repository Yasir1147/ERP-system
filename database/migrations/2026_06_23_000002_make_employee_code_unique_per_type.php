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
            if ($this->indexExists('employees_code_unique')) {
                $table->dropUnique(['code']);
            }

            if (! $this->indexExists('employees_type_code_unique')) {
                $table->unique(['type', 'code']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if ($this->indexExists('employees_type_code_unique')) {
                $table->dropUnique(['type', 'code']);
            }

            if (! $this->indexExists('employees_code_unique')) {
                $table->unique('code');
            }
        });
    }

    private function indexExists(string $indexName): bool
    {
        // SHOW INDEX is MySQL-only; the schema builder works on every driver.
        return collect(Schema::getIndexes('employees'))
            ->contains(fn (array $index) => $index['name'] === $indexName);
    }
};
