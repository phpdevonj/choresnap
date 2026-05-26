<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset your ChoreSnap password 🔑</title>
</head>
<body>
<!-- ChoreSnap Password Reset Email -->
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
       style="margin:0; padding:0; background-color:#F5F8F9;">
    <tr>
        <td align="center" style="padding:24px 12px;">

            <!-- Container -->
            <table role="presentation" width="500" cellspacing="0" cellpadding="0" border="0"
                   style="width:500px; max-width:500px; background-color:#FFFFFF; border-radius:14px; overflow:hidden; box-shadow:0 6px 18px rgba(0,0,0,0.08);">

                <!-- Header -->
                <tr>
                    <td style="background-color:#038D8D; padding:18px 22px;">
                        <div
                            style="font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#D8F5F5; text-align:left;">
                            ChoreSnap B.V.
                        </div>
                    </td>
                </tr>


                <!-- Content -->
                <tr>
                    <td style="padding:28px 22px;">

                        <!-- Title -->
                        <div style="font-family:Arial,Helvetica,sans-serif; font-size:22px; font-weight:700; color:#0B2B2B;">
                            {{__('messages.reset_password_title')}}
                        </div>

                        <!-- Message -->
                        <div style="font-family:Arial,Helvetica,sans-serif; font-size:14px; line-height:1.6; color:#1F2D2D; margin-top:12px;">
                            {{__('messages.password_reset_request_message')}}
                        </div>

                        <!-- CTA -->
                        <div style="margin-top:22px; margin-bottom:22px; text-align:center;">
                            <a href="{{ $link  }}" style="display:inline-block; font-family:Arial,Helvetica,sans-serif; font-size:15px; font-weight:800; text-decoration:none; background-color:#038D8D; color:#FFFFFF; padding:14px 24px; border-radius:12px;">
                                {{__('messages.reset_password')}}
                            </a>
                        </div>

                        <!-- Expiry -->
                        <div style="font-family:Arial,Helvetica,sans-serif; font-size:13px; color:#5F7C7C;">
                            {{__('messages.link_expiry_message')}}
                        </div>

                        <!-- Security Notice -->
                        <div style="margin-top:18px; background-color:#EAF7F7; border:1px solid #CFEAEA; padding:14px; border-radius:10px;">
                            <div style="font-family:Arial,Helvetica,sans-serif; font-size:14px; font-weight:700; color:#0B2B2B;">
                                {{__('messages.security_notice')}}
                            </div>
                            <div style="font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:1.6; color:#1F2D2D; margin-top:6px;">
                                {{__('messages.password_reset_security_notice')}}
                            </div>
                        </div>

                        <!-- Fallback Link -->
                        <div style="margin-top:18px; font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#7A8F8F; line-height:1.5;">
                            {{__('messages.fallback_link_message')}}
                            <br><br>
                            <a href="{{ $link }}" style="color:#038D8D; word-break:break-all;">
                                {{ $link }}
                            </a>
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
