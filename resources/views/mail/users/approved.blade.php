@component('mail::message')
# ✅ ¡Bienvenido al equipo, {{ $userName }}!

Tu cuenta fue aprobada. Ya puedes entrar a la plataforma con tu correo y tu contraseña.

@component('mail::button', ['url' => $loginUrl])
Entrar a la plataforma
@endcomponent

¡Gracias por sumarte a Cubania! 💃🕺

{{ config('app.name') }}
@endcomponent
