<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DanceType;
use App\Models\Level;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContentController extends Controller
{
    public function index(Request $request): Response
    {
        $danceTypes = DanceType::query()
            ->withCount(['levels', 'levelContents as figures_count'])
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('content/index', [
            'danceTypes' => $danceTypes,
            'canDelete' => $this->canDelete($request),
        ]);
    }

    public function show(Request $request, DanceType $danceType): Response
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
            'canDelete' => $this->canDelete($request),
        ]);
    }

    public function level(Request $request, DanceType $danceType, Level $level): Response
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
            'canDelete' => $this->canDelete($request),
        ]);
    }

    private function canDelete(Request $request): bool
    {
        $user = $request->user();

        return $user !== null && ($user->isAdmin() || $user->isOwner());
    }
}
