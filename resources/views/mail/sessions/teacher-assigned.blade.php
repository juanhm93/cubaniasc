@component('mail::message')
# 🧑‍🏫 ¡Tienes una nueva clase a cargo, {{ $teacherName }}!

@if ($actorName)
**{{ $actorName }}** te asignó como profesor de **{{ $label }}**.
@else
Te asignaron como profesor de **{{ $label }}**.
@endif

@component('mail::panel')
**Tipo:** {{ $kindLabel }}

**Nombre:** {{ $label }}

**Cuándo:** {{ $when !== '' ? $when : 'Por definir' }}

**Lugar:** {{ $placeName ?? 'Por definir' }}
@endcomponent

@component('mail::button', ['url' => $actionUrl])
Ver los detalles
@endcomponent

¡A darlo todo en la pista! 💃🕺

{{ config('app.name') }}
@endcomponent
