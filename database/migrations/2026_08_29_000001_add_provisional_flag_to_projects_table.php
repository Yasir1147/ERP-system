<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Raised from an attendance form because the site was not on the
            // list yet. It is a real project so costing works, but it is
            // flagged until an admin reviews, completes, or merges it.
            $table->boolean('is_provisional')->default(false)->after('type');
            $table->foreignId('created_by')->nullable()->after('description')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('is_provisional');
        });
    }
};
