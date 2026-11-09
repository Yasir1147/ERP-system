<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCardTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AttendanceCardTemplateController extends Controller
{
    public function create()
    {
        return Inertia::render('AttendanceCards/TemplateForm', ['template' => null, 'defaultLayout' => AttendanceCardTemplate::defaultLayout()]);
    }

    public function edit(AttendanceCardTemplate $template)
    {
        return Inertia::render('AttendanceCards/TemplateForm', ['template' => $template->payload(), 'defaultLayout' => AttendanceCardTemplate::defaultLayout()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $template = AttendanceCardTemplate::create([...$data, 'created_by' => $request->user()->id, 'version' => 1]);

        return to_route('attendance-card-templates.edit', $template)->with('success', 'Attendance card format saved.');
    }

    public function update(Request $request, AttendanceCardTemplate $template)
    {
        $data = $this->validated($request, $template);
        DB::transaction(function () use ($data, $template) {
            $locked = AttendanceCardTemplate::lockForUpdate()->findOrFail($template->id);
            if ($locked->version !== (int) $data['version']) {
                throw ValidationException::withMessages(['version' => 'This format changed in another session. Reload before saving.']);
            }
            $locked->update([...$data, 'version' => $locked->version + 1]);
        });

        return back()->with('success', 'Attendance card format saved.');
    }

    public function duplicate(Request $request, AttendanceCardTemplate $template)
    {
        $name = $template->name.' Copy';
        $number = 2;
        while (AttendanceCardTemplate::where('name', $name)->exists()) {
            $name = $template->name.' Copy '.$number++;
        }
        // Asset files are retained when replaced, so copied formats can safely share them.
        $copy = $template->replicate();
        $copy->name = $name;
        $copy->version = 1;
        $copy->created_by = $request->user()->id;
        $copy->save();

        return to_route('attendance-card-templates.edit', $copy);
    }

    public function asset(Request $request, AttendanceCardTemplate $template)
    {
        $data = $request->validate(['kind' => ['required', Rule::in(['logo', 'signature'])], 'image' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120']]);
        $path = $data['image']->store('attendance-card-assets', 'public');
        $template->update([$data['kind'].'_path' => $path]);
        $template->increment('version');

        return back()->with('success', ucfirst($data['kind']).' uploaded.');
    }

    private function validated(Request $request, ?AttendanceCardTemplate $template = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:150', Rule::unique('attendance_card_templates')->ignore($template?->id)],
            'version' => [$template ? 'required' : 'nullable', 'integer', 'min:1'],
            'layout' => ['required', 'array'],
            'layout.width' => ['required', 'numeric', 'between:150,190'],
            'layout.height' => ['required', 'numeric', 'between:100,277'],
            'layout.font' => ['required', Rule::in(['Arial', 'Tahoma', 'Verdana', 'Times New Roman', 'Courier New'])],
            'layout.font_size' => ['required', 'numeric', 'between:5,20'],
            'layout.title' => ['required', 'string', 'max:100'],
            'layout.rest_day' => ['required', 'integer', 'between:-1,6'],
            'layout.show_ot_site' => ['required', 'boolean'],
            'layout.show_signature' => ['required', 'boolean'],
            'layout.sections' => ['required', 'array:title,logo,identity,grid,footer,signature'],
        ];
        foreach (['text_color', 'border_color', 'header_color', 'rest_color'] as $key) {
            $rules['layout.'.$key] = ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        }
        foreach (['attendance_label', 'site_label', 'overtime_label', 'admin_label', 'employee_label'] as $key) {
            $rules['layout.'.$key] = ['required', 'string', 'max:40'];
        }
        foreach (array_keys(AttendanceCardTemplate::defaultLayout()['sections']) as $key) {
            $rules['layout.sections.'.$key] = ['required', 'array:x,y,width,height,font_size,visible'];
            foreach (['x', 'y'] as $dimension) {
                $rules["layout.sections.$key.$dimension"] = ['required', 'numeric', 'min:0'];
            }
            foreach (['width', 'height'] as $dimension) {
                $rules["layout.sections.$key.$dimension"] = ['required', 'numeric', 'min:5'];
            }
            $rules["layout.sections.$key.font_size"] = ['required', 'numeric', 'between:5,30'];
            $rules["layout.sections.$key.visible"] = ['required', 'boolean'];
        }
        $data = $request->validate($rules);
        foreach ($data['layout']['sections'] as $key => $section) {
            if ($data['layout']['width'] < $section['x'] + $section['width'] || $data['layout']['height'] < $section['y'] + $section['height']) {
                throw ValidationException::withMessages(["layout.sections.$key" => ucfirst($key).' must stay inside the card dimensions.']);
            }
        }
        // Keep only supported keys; saved JSON must not become an arbitrary style/HTML channel.
        $data['layout'] = array_intersect_key($data['layout'], AttendanceCardTemplate::defaultLayout());

        return $data;
    }
}
