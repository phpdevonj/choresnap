<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{__('messages.email_verification')}}</title>
</head>
<body>
    <p>{{__('messages.email_verification_message')}}</p>
    <a href="{{ $verificationLink }}" class="text-decoration-underline">{{__('messages.verify_email')}}</a>
</body>
</html>