<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DanceType;
use App\Models\Level;
use Inertia\Inertia;
use Inertia\Response;

class ContentController extends Controller
{
    public function index(): Response
    {
        $danceTypes = DanceType::query()
            ->withCount(['levels', 'levelContents as figures_count'])
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('content/index', [
            'danceTypes' => $danceTypes,
        ]);
    }

    public function show(DanceType $danceType): Response
    {
        $danceType->load([
            'levels' => function ($query): void {
                $query->orderBy('sort_order')->with([
                    'levelContents' => function ($query): void {
                        $query->orderBy('sort_order');
                    },
                ]);
            },
        ]);

        $danceType->loadCount(['levels', 'levelContents as figures_count']);

        return Inertia::render('content/show', [
            'danceType' => $danceType,
        ]);
    }

    public function level(DanceType $danceType, Level $level): Response
    {
        $level->load([
            'danceType',
            'levelContents' => function ($query): void {
                $query->orderBy('sort_order');
            },
        ]);

        return Inertia::render('content/level', [
            'danceType' => $danceType,
            'level' => $level,
        ]);
    }
}
