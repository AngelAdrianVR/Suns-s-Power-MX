<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ContactController extends Controller
{
    public function index()
    {
        //
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

        $validated = $request->validate([
            'contactable_id' => 'required|integer',
            'contactable_type' => 'required|string',
            'name' => 'required|string|max:255',
            'job_title' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'is_primary' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $validated['branch_id'] = $branchId;

        $contact = Contact::create($validated);

        return back()->with('success', 'Contacto agregado correctamente.');
    }

    public function show(Contact $contact)
    {
        //
    }

    public function edit(Contact $contact)
    {
        //
    }
    
    public function update(Request $request, Contact $contact)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($contact->branch_id !== $branchId) {
            return back()->with('error', 'No autorizado.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'job_title' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'is_primary' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $contact->update($validated);

        return back()->with('success', 'Contacto actualizado correctamente.');
    }

    public function destroy(Contact $contact)
    {
        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;
        if ($contact->branch_id !== $branchId) {
            return back()->with('error', 'No autorizado.');
        }

        // Validar que no sea el último contacto del contactable
        $contactable = $contact->contactable;
        if ($contactable && $contactable->contacts()->count() <= 1) {
            throw ValidationException::withMessages([
                'delete' => 'No se puede eliminar el único contacto. Debe existir al menos uno.'
            ]);
        }

        $contact->delete();

        return back()->with('success', 'Contacto eliminado correctamente.');
    }
}
