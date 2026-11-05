{{-- Email de envío de un documento: el texto que escribe el usuario, con el PDF adjunto. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
</head>
<body style="margin: 0; padding: 16px; color: #1f2328; font-family: Helvetica, Arial, sans-serif; font-size: 14px; line-height: 1.5;">
    {!! nl2br(e($send->body)) !!}
</body>
</html>
