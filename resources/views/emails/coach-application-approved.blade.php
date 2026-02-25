<!DOCTYPE html>
<html>
<head>
    <title>Application Approved</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #000; color: #fff; padding: 20px; text-align: center; }
        .content { padding: 30px 20px; background: #f9f9f9; }
        .button { display: inline-block; padding: 12px 30px; background: #000; color: #fff; text-decoration: none; border-radius: 50px; margin-top: 20px; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Gotta</h1>
        </div>
        <div class="content">
            <h2>Congratulations, {{ $name }}!</h2>
            <p>We're excited to inform you that your coach application has been approved!</p>
            <p>You can now complete your coach profile and start offering coaching sessions on our platform.</p>
            <p>To get started, please log in using your email: <strong>{{ $email }}</strong></p>
            <a href="{{ url('/coach/onboarding') }}" class="button">Complete Your Profile</a>
            <p style="margin-top: 30px;">Welcome to the Gotta coaching community!</p>
            <p>Best regards,<br>The Gotta Team</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Gotta. All rights reserved.
        </div>
    </div>
</body>
</html>