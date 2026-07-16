<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cheques', function (Blueprint $table) {
            $table->date('issued_date')->nullable()->after('cheque_date');
            $table->text('purpose')->nullable()->after('remarks');
            $table->string('received_by')->nullable()->after('purpose');
            $table->string('receiver_id')->nullable()->after('received_by');
            $table->string('receiver_mobile')->nullable()->after('receiver_id');
            $table->string('prepared_by')->nullable()->after('receiver_mobile');
            $table->string('checked_by')->nullable()->after('prepared_by');
            $table->string('approved_by')->nullable()->after('checked_by');
        });
    }

    public function down(): void
    {
        Schema::table('cheques', function (Blueprint $table) {
            $table->dropColumn([
                'issued_date',
                'purpose',
                'received_by',
                'receiver_id',
                'receiver_mobile',
                'prepared_by',
                'checked_by',
                'approved_by',
            ]);
        });
    }
};
