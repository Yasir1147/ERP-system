<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cheque_formats', function (Blueprint $table) {
            $table->string('logo_image_path')->nullable()->after('background_image_path');
        });

        $now = now();
        $fields = DB::table('cheque_formats')
            ->select('id')
            ->get()
            ->map(fn (object $format) => [
                'cheque_format_id' => $format->id,
                'field_key' => 'company_logo',
                'display_name' => 'Company Logo',
                'x_position_mm' => 85,
                'y_position_mm' => 65,
                'width_mm' => 25,
                'height_mm' => 18,
                'font_family' => 'Arial',
                'font_size_pt' => 10,
                'font_weight' => 400,
                'is_italic' => false,
                'is_underline' => false,
                'text_align' => 'center',
                'is_visible' => true,
                'sort_order' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($fields !== []) {
            DB::table('cheque_format_fields')->insert($fields);
        }
    }

    public function down(): void
    {
        DB::table('cheque_format_fields')->where('field_key', 'company_logo')->delete();

        Schema::table('cheque_formats', function (Blueprint $table) {
            $table->dropColumn('logo_image_path');
        });
    }
};
