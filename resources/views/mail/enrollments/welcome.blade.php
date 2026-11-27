@component('mail::message')
# ¡Felicidades, {{ $studentName }}! 🎉

Ya estás **inscrito en {{ config('app.name') }}**. Nos alegra muchísimo que formes parte de la academia y tenerte con nosotros en la pista.

Estos son los datos de tu curso:

@component('mail::panel')
**Curso:** {{ $danceTypeName ?? 'Por confirmar' }}

**Nivel:** {{ $levelName ?? 'Por confirmar' }}

**Horario:**
@forelse ($schedule as $slot)
- {{ $slot }}
@empty
Te confirmaremos el horario muy pronto.
@endforelse

@if ($placeName)
**Lugar:** {{ $placeName }}{{ $placeAddress ? ' · '.$placeAddress : '' }}
@endif

@if ($teacherName)
**Profesor:** {{ $teacherName }}
@endif
@endcomponent

Ya tienes disponible la **plataforma de repaso**: allí puedes practicar las figuras de tu nivel, escuchar la música recomendada y mantener tu racha entre clase y clase.

@component('mail::button', ['url' => $reviewUrl])
Ir a la plataforma de repaso
@endcomponent

Si tienes cualquier duda sobre tu curso, responde a este correo y con gusto te ayudamos.

¡Nos vemos en la pista! 🕺

Con cariño,<br>
El equipo de {{ config('app.name') }}
@endcomponent
