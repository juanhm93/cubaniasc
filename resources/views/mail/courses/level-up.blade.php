@component('mail::message')
# 🚀 ¡Felicidades, {{ $studentName }}! Subieron de nivel

Tu grupo terminó **{{ $previousLevelName }}** y ahora empieza **{{ $newLevelName }}**. Todo lo que practicaste hasta aquí es la base de lo que viene.

@component('mail::panel')
**Nivel que terminaste:** {{ $previousLevelName }}

**Nuevo nivel:** {{ $newLevelName }}

**Curso:** {{ $courseLabel }}
@endcomponent

Tu panel de repaso ya tiene las figuras del nuevo nivel. Entra, mira los videos y empieza a soltarte con los pasos nuevos. Recuerda: **en la práctica está la mejora**. 💪

@component('mail::button', ['url' => $reviewUrl])
Repasar el nuevo nivel
@endcomponent

¡Nos vemos en la pista! 💃🕺

{{ config('app.name') }}
@endcomponent
