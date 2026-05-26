
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Onboarding Incomplete</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style>
            body { font-family: sans-serif; text-align: center; padding: 40px; }
            .btn {
                display: inline-block;
                padding: 12px 24px;
                background-color: #6772e5;
                color: white;
                text-decoration: none;
                border-radius: 6px;
                margin-top: 20px;
            }
            .btn:hover {
                background-color: #5469d4;
            }
        </style>
    </head>
    <body>
        <div class="alert alert-warning">
            <h1>Stripe Setup Not Completed</h1>
            <p>Your driver onboarding process was not completed successfully.</p>
        </div>
        <div class="retry-section">
            <p>Please click below to try the onboarding process again:</p>

           
            <a href="{{ route('stripe.onboarding', ['account_id' => $account->id]) }}" class="btn btn-primary">
                Retry Onboarding
            </a>

        </div>
    </body>
</html>



<style>
.onboarding-incomplete {
    max-width: 600px;
    margin: 30px auto;
    padding: 20px;
}

.retry-section, .support-section {
    margin: 20px 0;
    text-align: center;
}

.btn {
    margin: 10px;
    padding: 10px 20px;
}
</style>