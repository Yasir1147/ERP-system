<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cheques', function (Blueprint $table) {
            $table->boolean('fils_on_second_line')->default(false)->after('amount_in_words');
        });

        DB::table('cheques')
            ->where('amount_in_words', 'like', '% Cents')
            ->get(['id', 'amount_in_words'])
            ->each(function (object $cheque): void {
                $words = preg_replace('/ and (.+) Cents$/', ' And Fils $1', $cheque->amount_in_words);
                DB::table('cheques')->where('id', $cheque->id)->update(['amount_in_words' => $words]);
            });
    }

    public function down(): void
    {
        DB::table('cheques')
            ->where('amount_in_words', 'like', '% And Fils %')
            ->get(['id', 'amount_in_words'])
            ->each(function (object $cheque): void {
                $words = preg_replace('/ And Fils (.+)$/', ' and $1 Cents', $cheque->amount_in_words);
                DB::table('cheques')->where('id', $cheque->id)->update(['amount_in_words' => $words]);
            });

        Schema::table('cheques', function (Blueprint $table) {
            $table->dropColumn('fils_on_second_line');
        });
    }
};
