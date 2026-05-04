<?php

namespace App\Http\Controllers;

use App\Models\DanceType;
use App\Models\Level;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LevelController extends Controller
{
    public function index(Request $request)
    {
        $levels = Level::query()->with('danceType')->orderBy('sort_order')->get();
        $danceTypes = DanceType::query()->orderBy('sort_order')->get();

        return Inertia::render('levels', [
            'levels' => $levels,
            'danceTypes' => $danceTypes,
        ]);
    }

    public function show(Request $request, string $level)
    {
        $level = Level::query()->findOrFail($level);

        return Inertia::render('level', [
            'level' => $level,
        ]);
    }
}
