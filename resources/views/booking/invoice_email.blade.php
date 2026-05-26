<html>
<head>
    <title>Invoice for [[ booking_services_names ]] with [[ provider_name ]] 🧾</title>
</head>
<body>

@php
    $customer_name = ($booking->customer->first_name ?? '').' '.($booking->customer->last_name ?? '');
    $booking_services_names = $booking->service->name ?? '';
    $provider_name = ($provider->first_name ?? '').' '.($provider->last_name ?? '');
@endphp

    <!-- ChoreSnap Invoice Email (Customer / Teal) -->
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
       style="margin:0; padding:0; background-color:#F5F8F9;">
    <tr>
        <td align="center" style="padding:24px 12px;">

            <!-- Container -->
            <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0"
                   style="width:600px; max-width:600px; background-color:#FFFFFF; border-radius:14px; overflow:hidden; box-shadow:0 6px 18px rgba(0,0,0,0.08);">

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
                    <td style="padding:26px 22px;">

                        <!-- Title -->
                        <div
                            style="font-family:Arial,Helvetica,sans-serif; font-size:20px; font-weight:700; color:#0B2B2B;">
                            Your invoice is ready
                        </div>

                        <!-- Message -->
                        <div
                            style="font-family:Arial,Helvetica,sans-serif; font-size:14px; line-height:1.6; color:#1F2D2D; margin-top:12px;">
                            Hi <strong>{{ $customer_name }}</strong>,<br><br>
                            As requested, please find your invoice attached to this email.
                        </div>

                        <!-- Booking Context -->
                        <div
                            style="margin-top:18px; background-color:#F1FAFA; border:1px solid #D9F0F0; padding:14px; border-radius:10px;">

                            <div style="font-family:Arial,Helvetica,sans-serif; font-size:13px; color:#3E5D5D;">
                                Service
                            </div>
                            <div
                                style="font-family:Arial,Helvetica,sans-serif; font-size:14px; font-weight:700; color:#0B2B2B;">
                                {{ $booking_services_names }}
                            </div>

                            <div
                                style="margin-top:10px; font-family:Arial,Helvetica,sans-serif; font-size:13px; color:#3E5D5D;">
                                Provider
                            </div>
                            <div
                                style="font-family:Arial,Helvetica,sans-serif; font-size:14px; font-weight:700; color:#0B2B2B;">
                                {{ $provider_name }}
                            </div>

                            <div
                                style="margin-top:10px; font-family:Arial,Helvetica,sans-serif; font-size:13px; color:#3E5D5D;">
                                Booking ID
                            </div>
                            <div
                                style="font-family:Arial,Helvetica,sans-serif; font-size:14px; font-weight:700; color:#0B2B2B;">
                                {{ $booking->id }}
                            </div>

                        </div>

                        <!-- Support -->
                        <div
                            style="font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:1.6; color:#5F7C7C; margin-top:18px;">
                            If you have any questions regarding this invoice, please contact our support team at:
                            klantenservice@choresnap.nl.
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
