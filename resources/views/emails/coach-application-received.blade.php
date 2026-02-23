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
            <h2>Thank You for Applying, {{ $name }}!</h2>
            <p>We have received your coach application and it's now being reviewed by our team.</p>
            <p>We typically review applications within 2-3 business days. You'll receive another email once a decision has been made.</p>
            <p>If you have any questions in the meantime, please don't hesitate to contact us.</p>
            <p>Best regards,<br>The Gotta Team</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Gotta. All rights reserved.
        </div>
    </div>
</body>
</html>