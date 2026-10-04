@component('mail::message')
# 💃 ¡Bienvenido a Cubania, {{ $student->name }}!

Ya eres parte de la familia. Tu inscripción en **{{ $courseLabel }}** está confirmada y te esperamos en la pista.

@component('mail::panel')
**Curso:** {{ $courseLabel }}

**Horario:** {{ $schedule !== '' ? $schedule : 'Te lo confirmaremos pronto' }}

@if ($place)
**Lugar:** {{ $place->name }}@if ($place->address) — {{ $place->address }}@endif

@endif
**Profesor:** {{ $teacherName ?? 'Te lo confirmaremos pronto' }}
@endcomponent

Entre clase y clase tienes tu **panel de repaso**: entra con tu correo o tu cédula, repasa las figuras que vamos viendo y suma días a tu racha.

@component('mail::button', ['url' => $reviewUrl])
Ir a mi panel de repaso
@endcomponent

¡Nos vemos bailando! 🎶

Gracias,<br>
{{ config('app.name') }}
@endcomponent
