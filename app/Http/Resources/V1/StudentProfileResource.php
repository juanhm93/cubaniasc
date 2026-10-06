<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Level;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Student */
class StudentProfileResource extends JsonResource
{
    public function __construct(Student $student, private readonly ?Level $currentLevel = null)
    {
        parent::__construct($student);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'dni' => $this->dni,
            'phone' => $this->phone,
            'birthday' => $this->birthday?->toDateString(),
            'current_level' => $this->currentLevel === null
                ? null
                : LevelResource::make($this->currentLevel->loadMissing('danceType')),
        ];
    }
}
