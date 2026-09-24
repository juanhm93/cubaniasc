@component('mail::message')
# ¡Listo, {{ $preRegistration->name }}! 💃

Tu proceso de preinscripción **finalizó correctamente** y ya tenemos tus datos con nosotros.

Muy pronto estaremos comunicándonos contigo por correo o por teléfono para contarte los siguientes pasos: los horarios disponibles, los niveles que mejor se ajustan a ti y todo lo que necesitas para tu primera clase.

Mientras tanto, no tienes que hacer nada más. Solo te pedimos dos cosas:

- Revisa tu bandeja de entrada y la carpeta de spam, para que no se te escape nuestro mensaje.
- Ten a mano cualquier duda que quieras preguntarnos; con gusto te la resolvemos cuando hablemos.

Gracias por querer ser parte de {{ config('app.name') }}. Nos emociona muchísimo tenerte cerca.

¡Nos vemos en la pista! 🕺

Con cariño,<br>
El equipo de {{ config('app.name') }}
@endcomponent
