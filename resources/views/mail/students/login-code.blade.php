@component('mail::message')
# ¡Hola, {{ $student->name }}! 💃

Usa este código para entrar a la app de {{ config('app.name') }}:

@component('mail::panel')
# {{ $code }}
@endcomponent

El código vence en **{{ $expiresInMinutes }} minutos** y solo funciona una vez.

Si no intentaste entrar a la app, puedes ignorar este correo. Nadie podrá acceder a tu cuenta sin este código.

¡Nos vemos en la pista! 🕺

El equipo de {{ config('app.name') }}
@endcomponent
