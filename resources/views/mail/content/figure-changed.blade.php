@component('mail::message')
# ✏️ Hubo movimiento en el catálogo

@if ($actorName)
**{{ $actorName }}** actualizó la figura **{{ $figure->name }}**.
@else
Se actualizó la figura **{{ $figure->name }}**.
@endif

@component('mail::panel')
**Figura:** {{ $figure->name }}

**Nivel:** {{ $danceTypeName ? $danceTypeName.' — ' : '' }}{{ $levelName ?? 'Sin nivel' }}

**Cambios:**
@foreach ($changeLabels as $label)
- {{ $label }}
@endforeach
@endcomponent

@if ($figure->video_url && ! $figure->trashed())
**Video:** {{ $figure->video_url }}
@endif

@component('mail::button', ['url' => $actionUrl])
Ver el nivel
@endcomponent

Gracias,<br>
{{ config('app.name') }}
@endcomponent
