<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class BranchContextController extends Controller
{
    public function switch(Request $request)
    {
        $request->validate([
            'branch_id' => 'required|exists:branches,id'
        ]);

        // Aquí podrías validar si el usuario TIENE PERMISO de ver esa sucursal
        // if (!auth()->user()->canAccessBranch($request->branch_id)) abort(403);

        Session::put('current_branch_id', $request->branch_id);

        return back()->with('success', 'Sucursal cambiada correctamente.');
    }
}