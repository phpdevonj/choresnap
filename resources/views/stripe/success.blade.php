<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stripe Onboarding Complete</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 2rem;
            text-align: center;
            background-color: #f8f9fa;
        }
        .box {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            display: inline-block;
        }
        .success {
            color: #28a745;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        a.button {
            display: inline-block;
            margin-top: 1rem;
            background: #007bff;
            color: white;
            padding: 0.5rem 1.2rem;
            border-radius: 5px;
            text-decoration: none;
        }
        a.button:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="box">
        @if($status === 'completed')
            <div class="success">{{ $message }}</div>
            <p>Your Stripe account has been successfully connected.</p>
        @else
            <div class="warning">{{ $message }}</div>
            <p>Please complete your Stripe setup to start receiving payments.</p>
            <!-- <a class="button" href="{{ route('stripe.reauth', ['account_id' => $accountId]) }}">
                Continue Onboarding
            </a> -->
        @endif
        <!-- <div class="success">🎉 Stripe Onboarding Complete!</div>
        <p>Your Stripe account has been successfully connected.</p> -->
        <!-- <a class="button" href="yourapp://stripe-success">Back to App</a> -->
    </div>
</body>
<script>
  function notifyFlutter(status) {
    if (window.JSBridge) {
      JSBridge.postMessage(status);
    } else {
      console.log('Flutter JSBridge not found');
    }
  }

     // Run this on page load
     window.addEventListener('load', function () {
        setTimeout(function () {
        //  Notify Flutter after 5 seconds
        notifyFlutter('success');
        console.log('notifyFlutter("success") called after 5 seconds');
        }, 5000);
    });
</script>
</html>
