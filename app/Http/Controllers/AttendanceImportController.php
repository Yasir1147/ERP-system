<?php

namespace App\Http\Controllers;

use App\Services\Attendance\AttendanceImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bulk import of historical attendance from a reviewed workbook.
 *
 * Upload always previews first. Writing needs a second, deliberate action,
 * because this creates attendance and therefore pay for past months.
 */
class AttendanceImportController extends Controller
{
    private const UPLOAD_DISK = 'local';
    private const UPLOAD_DIRECTORY = 'imports';

    public function __construct(private readonly AttendanceImportService $import)
    {
    }

    /**
     * Resolves the upload through the disk rather than building a path by
     * hand: the local disk is rooted at storage/app/private, not storage/app.
     */
    private function uploadPath(string $token): string
    {
        return Storage::disk(self::UPLOAD_DISK)->path(self::UPLOAD_DIRECTORY.'/'.$token);
    }

    public function create(): Response
    {
        return Inertia::render('Attendance/Import', [
            'preview' => session('import_preview'),
            'token' => session('import_token'),
        ]);
    }

    /**
     * Stores the upload and shows what it would do. Nothing is written.
     */
    public function preview(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        $token = 'import-'.$request->user()->id.'-'.now()->format('YmdHis').'.xlsx';
        $request->file('file')->storeAs(self::UPLOAD_DIRECTORY, $token, self::UPLOAD_DISK);

        $result = $this->import->preview($this->uploadPath($token));

        return back()
            ->with('import_preview', $result)
            ->with('import_token', $result['fatal'] ? null : $token);
    }

    /**
     * Writes the rows the preview marked importable.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $path = $this->uploadPath(basename($data['token']));

        if (! is_file($path)) {
            return back()->withErrors(['file' => 'The uploaded file is no longer available. Upload it again.']);
        }

        $result = $this->import->import($path, $request->user());

        if ($result['fatal']) {
            return back()->withErrors(['file' => $result['fatal']]);
        }

        return back()
            ->with('import_preview', $result)
            ->with('success', $result['created'].' attendance record(s) imported.');
    }
}
