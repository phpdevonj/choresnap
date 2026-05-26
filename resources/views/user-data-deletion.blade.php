<!DOCTYPE html>
<html>
<head>
    <title>User Data Deletion</title>
</head>
<body>

<h2>User Data Deletion Request</h2>

<p>
If you have used Facebook Login on our app and would like your data to be deleted,
please submit your registered email address below.
</p>

<p>
We will process your request and permanently delete your data within 7 working days,
as per Facebook and GDPR guidelines.
</p>

@if(session('success'))
    <p style="color:green">{{ session('success') }}</p>
@endif

<form method="POST" action="{{ route('user.data.deletion.request') }}">
    @csrf
    <label>Email Address:</label><br>
    <input type="email" name="email" required><br><br>

    <button type="submit">Request Data Deletion</button>
</form>

</body>
</html>
