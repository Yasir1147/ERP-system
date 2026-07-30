<?php

namespace App\Http\Controllers;

use App\Models\DocumentCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DocumentCategoryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        DocumentCategory::query()->create($this->validatedData($request));

        return back()->with('success', 'Document category created.');
    }

    public function update(Request $request, DocumentCategory $documentCategory): RedirectResponse
    {
        $documentCategory->update($this->validatedData($request, $documentCategory));

        return back()->with('success', 'Document category updated.');
    }

    public function destroy(DocumentCategory $documentCategory): RedirectResponse
    {
        if ($documentCategory->documents()->exists()) {
            return back()->withErrors(['category' => 'This category has employee documents and cannot be deleted.']);
        }

        $documentCategory->delete();

        return back()->with('success', 'Document category deleted.');
    }

    private function validatedData(Request $request, ?DocumentCategory $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('document_categories', 'name')->ignore($category)],
            'default_reminder_days' => ['required', 'integer', 'min:0', 'max:365'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
