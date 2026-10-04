@component('mail::message')
# 🚀 ¡Un curso subió de nivel!

@if ($actorName)
**{{ $actorName }}** pasó el curso de **{{ $previousLevelName }}** a **{{ $newLevelName }}**.
@else
El curso pasó de **{{ $previousLevelName }}** a **{{ $newLevelName }}**.
@endif

@component('mail::panel')
**Curso:** {{ $courseLabel }}

**Horario:** {{ $schedule !== '' ? $schedule : 'Sin horario definido' }}

**Profesor:** {{ $teacherName ?? 'Sin asignar' }}

**Nivel anterior:** {{ $previousLevelName }}

**Nivel nuevo:** {{ $newLevelName }}

**Alumnos activos avisados:** {{ $studentCount }}
@endcomponent

@component('mail::button', ['url' => $actionUrl])
Ver el curso
@endcomponent

Gracias,<br>
{{ config('app.name') }}
@endcomponent
