<!DOCTYPE html>
<html>
<head>
    <title>Application Received</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #000; color: #fff; padding: 20px; text-align: center; }
        .content { padding: 30px 20px; background: #f9f9f9; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Gotta</h1>
        </div>
        <div class="content"> 
            <h2>Welcome to Gotta, {{ $name }}!</h2> 
            <p>Thank you for registering with Gotta. Your account has been created successfully.</p> 
            <p>You can now log in to your account, explore available coaching services, connect with coaches, and start your journey with us.</p> 
            <p>If you have any questions or need assistance, our support team is here to help.</p> 
            <p>We're excited to have you as part of the Gotta community.</p>
            <p> Best regards,<br> The Gotta Team </p> 
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Gotta. All rights reserved.
        </div>
    </div>
</body>
</html>