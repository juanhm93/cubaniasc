@component('mail::message')
# 🎉 ¡Terminaste tu repaso, {{ $studentName }}!

¡Vas muy bien! Hoy le dedicaste tiempo a tu baile y eso se nota. Recuerda: **en la práctica está la mejora**. 💃🕺

{{ $milestoneMessage }}

@component('mail::panel')
**Racha actual:** {{ $streak }} {{ $streak === 1 ? 'día' : 'días' }} 🔥

@if ($levelName)
**Nivel:** {{ $levelName }}

@endif
@if (count($figureNames) > 0)
**Figuras que repasaste:** {{ implode(', ', $figureNames) }}
@endif
@endcomponent

Cada vuelta que repasas hoy es un paso que te sale solo mañana en la pista. ¡Sigue así!

@component('mail::button', ['url' => $reviewUrl])
Volver a mi panel de repaso
@endcomponent

¡A seguir bailando! 🎶

{{ config('app.name') }}
@endcomponent
