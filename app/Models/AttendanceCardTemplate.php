<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AttendanceCardTemplate extends Model
{
    protected $fillable = ['name', 'layout', 'logo_path', 'signature_path', 'version', 'created_by'];

    protected function casts(): array
    {
        return ['layout' => 'array', 'version' => 'integer'];
    }

    public static function defaultLayout(): array
    {
        return [
            'width' => 190, 'height' => 132, 'font' => 'Arial', 'font_size' => 7,
            'title' => 'ATTENDANCE CARD - {year}', 'text_color' => '#000000', 'border_color' => '#000000',
            'header_color' => '#DDDDDD', 'rest_color' => '#E5484D', 'rest_day' => 5,
            'show_ot_site' => false, 'show_signature' => true, 'attendance_label' => 'Attendance', 'site_label' => 'Site No.',
            'overtime_label' => 'Over Time', 'admin_label' => 'Admin Signature', 'employee_label' => "Employee's Signature",
            'sections' => [
                'title' => ['x' => 5, 'y' => 4, 'width' => 135, 'height' => 11, 'font_size' => 15, 'visible' => true],
                'logo' => ['x' => 148, 'y' => 3, 'width' => 36, 'height' => 13, 'font_size' => 7, 'visible' => true],
                'identity' => ['x' => 3, 'y' => 19, 'width' => 184, 'height' => 15, 'font_size' => 8, 'visible' => true],
                'grid' => ['x' => 3, 'y' => 37, 'width' => 184, 'height' => 75, 'font_size' => 7, 'visible' => true],
                'footer' => ['x' => 3, 'y' => 115, 'width' => 184, 'height' => 13, 'font_size' => 7, 'visible' => true],
            ],
        ];
    }

    public function payload(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'version' => $this->version, 'layout' => array_replace_recursive(self::defaultLayout(), $this->layout ?? []),
            'logoUrl' => $this->logo_path ? Storage::disk('public')->url($this->logo_path) : '/al-mohafiz-logo.png',
            'signatureUrl' => $this->signature_path ? Storage::disk('public')->url($this->signature_path) : null];
    }
}
