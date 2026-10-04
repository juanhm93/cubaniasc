@component('mail::message')
# ✅ Nueva figura vista en clase

@if ($actorName)
**{{ $actorName }}** marcó la figura **{{ $figureName }}** como vista en **{{ $courseLabel }}**.
@else
Se marcó la figura **{{ $figureName }}** como vista en **{{ $courseLabel }}**.
@endif

@component('mail::panel')
**Figura:** {{ $figureName }}

**Curso:** {{ $courseLabel }}

**Horario:** {{ $schedule !== '' ? $schedule : 'Sin horario definido' }}

**Avance del nivel:** {{ $coveredCount }} de {{ $totalCount }} figuras
@endcomponent

@component('mail::button', ['url' => $actionUrl])
Ver el curso
@endcomponent

Gracias,<br>
{{ config('app.name') }}
@endcomponent
