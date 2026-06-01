<!doctype html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{__('messages.email_verification')}}</title>
    </head>
    <body>
        <!-- ChoreSnap Password Reset Email -->
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
               style="margin:0; padding:0; background-color:#F5F8F9;">
            <tr>
                <td align="center" style="padding:24px 12px;">
                    <!-- Container -->
                    <table role="presentation" width="500" cellspacing="0" cellpadding="0" border="0" style="width:500px; max-width:500px; background-color:#FFFFFF; border-radius:14px; overflow:hidden; box-shadow:0 6px 18px rgba(0,0,0,0.08);">
                        <!-- Header -->
                        <tr>
                            <td style="background-color:#038D8D; padding:18px 22px;">
                                <img title="Choresnap Provider Logo.png" src="https://app.choresnap.com/storage/choresnap-logo-white.png" alt="Choresnap Provider Logo.png" width="60" height="60" style="display:block; border:0;">
                            </td>
                        </tr>
                        <!-- Content -->
                        <tr>
                            <td style="padding:28px 22px;">
                                <!-- Title -->
                                <div style="font-family:Arial,Helvetica,sans-serif; font-size:22px; font-weight:700; color:#0B2B2B;">
                                    {{__('messages.email_verification')}}
                                </div>

                                <!-- Message -->
                                <div style="font-family:Arial,Helvetica,sans-serif; font-size:14px; line-height:1.6; color:#1F2D2D; margin-top:12px;">
                                    {{__('messages.email_verification_message')}}
                                </div>

                                <!-- CTA -->
                                <div style="margin-top:22px; margin-bottom:22px; text-align:left;">
                                    <a href="{{ $verificationLink }}" style="display:inline-block; font-family:Arial,Helvetica,sans-serif; font-size:15px; font-weight:800; text-decoration:none; background-color:#038D8D; color:#FFFFFF; padding:14px 24px; border-radius:12px;">{{__('messages.verify_email')}}</a>
                                </div>
                            </td>
                        </tr>
                        <!-- Footer -->
                        <tr>
                            <td style="background-color:#0B3F3F; padding:16px;">
                                <div
                                    style="font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#D8F5F5; text-align:center;">
                                    © ChoreSnap B.V.
                                </div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
