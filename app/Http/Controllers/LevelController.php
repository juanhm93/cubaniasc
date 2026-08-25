<?php

namespace App\Http\Controllers;

use App\Models\Level;
use Illuminate\Http\RedirectResponse;

class LevelController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('content.index');
    }

    public function show(Level $level): RedirectResponse
    {
        return redirect()->route('content.levels.show', [
            'danceType' => $level->dance_type_id,
            'level' => $level,
        ]);
    }
}
