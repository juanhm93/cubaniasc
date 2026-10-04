@component('mail::message')
# 🎓 ¡Tenemos un alumno nuevo!

**{{ $student->name }}** quedó inscrito formalmente en **{{ $courseLabel }}**.

@component('mail::panel')
**Alumno:** {{ $student->name }}

**Correo:** {{ $student->email }}

**Teléfono:** {{ $student->phone ?? 'No indicado' }}

**Curso:** {{ $courseLabel }}

**Horario:** {{ $schedule !== '' ? $schedule : 'Sin horario definido' }}

**Profesor:** {{ $teacherName ?? 'Sin asignar' }}

@if ($actorName)
**Inscrito por:** {{ $actorName }}
@endif
@endcomponent

@component('mail::button', ['url' => $actionUrl])
Ver alumno
@endcomponent

¡Uno más para la rueda! 💃🕺

Gracias,<br>
{{ config('app.name') }}
@endcomponent
