<?php

namespace App\Http\Controllers;

use App\Models\ChequeParty;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ChequePartyController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = (int) $request->query('per_page', 10);
        $perPage = in_array($perPage, [10, 15, 25, 50], true) ? $perPage : 10;

        $parties = ChequeParty::query()
            ->withCount('cheques')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(fn ($query) => $query
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('contact_person', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('mobile', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%'));
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (ChequeParty $party) => $this->payload($party));

        return Inertia::render('ChequeParties/Index', [
            'parties' => $parties->items(),
            'pagination' => [
                'currentPage' => $parties->currentPage(),
                'lastPage' => $parties->lastPage(),
                'perPage' => $parties->perPage(),
                'total' => $parties->total(),
                'from' => $parties->firstItem(),
                'to' => $parties->lastItem(),
            ],
            'filters' => ['search' => $search, 'perPage' => $perPage],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('party', $this->rules());
        $party = ChequeParty::query()->create([
            ...$data,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Party added successfully.')->with('new_party_id', $party->id);
    }

    public function update(Request $request, ChequeParty $chequeParty): RedirectResponse
    {
        $data = $request->validateWithBag('party', $this->rules());
        $chequeParty->update([...$data, 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Party updated successfully.');
    }

    public function destroy(ChequeParty $chequeParty): RedirectResponse
    {
        if ($chequeParty->cheques()->exists()) {
            throw ValidationException::withMessages(['party' => 'A party used by prepared cheques cannot be deleted.']);
        }

        $chequeParty->delete();

        return back()->with('success', 'Party deleted successfully.');
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'fax' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:2000'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    private function payload(ChequeParty $party): array
    {
        return [
            'id' => $party->id,
            'name' => $party->name,
            'contactPerson' => $party->contact_person,
            'email' => $party->email,
            'mobile' => $party->mobile,
            'phone' => $party->phone,
            'fax' => $party->fax,
            'address' => $party->address,
            'remarks' => $party->remarks,
            'isActive' => $party->is_active,
            'chequeCount' => $party->cheques_count ?? $party->cheques()->count(),
        ];
    }
}
