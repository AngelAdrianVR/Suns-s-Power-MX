<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'body' => 'required|string|max:1000',
            'commentable_type' => 'required|string',
            'commentable_id' => 'required|integer',
        ]);

        // Determinar el modelo (por ahora solo Tasks, pero es polimórfico para futuro)
        $modelClass = $validated['commentable_type'] === 'task' ? Task::class : null;
        
        if (!$modelClass) {
            return back()->with('error', 'Tipo de comentario no válido.');
        }

        $model = $modelClass::findOrFail($validated['commentable_id']);

        $model->comments()->create([
            'body' => $validated['body'],
            'user_id' => Auth::id(),
        ]);

        return back()->with('success', 'Comentario agregado.');
    }
}