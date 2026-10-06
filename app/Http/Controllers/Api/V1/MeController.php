<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\StudentProfileResource;
use App\Models\Student;
use App\Services\Review\StudentLevelResolver;
use Illuminate\Http\Request;

final class MeController extends Controller
{
    public function __construct(private readonly StudentLevelResolver $levelResolver) {}

    public function __invoke(Request $request): StudentProfileResource
    {
        /** @var Student $student */
        $student = $request->user();

        return new StudentProfileResource($student, $this->levelResolver->findLevel($student));
    }
}
