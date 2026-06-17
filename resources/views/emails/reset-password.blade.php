<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password</title>
</head>
<body style="margin:0;padding:0;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f5f7fb;padding:30px 15px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="padding:32px 32px 20px 32px;">
                            <h2 style="margin:0 0 16px 0;font-size:24px;line-height:1.3;color:#111827;">
                                Reset Your Password
                            </h2>

```
                        <p style="margin:0 0 14px 0;font-size:15px;line-height:1.7;color:#374151;">
                            We received a request to reset the password for your account.
                        </p>

                        <p style="margin:0 0 24px 0;font-size:15px;line-height:1.7;color:#374151;">
                            Click the button below to create a new password.
                        </p>

                        <table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px 0;">
                            <tr>
                                <td align="center" style="border-radius:8px;" bgcolor="#2563eb">
                                    <a href="{{ $url }}"
                                       style="display:inline-block;padding:12px 20px;font-size:15px;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:8px;background:#2563eb;">
                                        Reset Password
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0 0 10px 0;font-size:14px;line-height:1.7;color:#6b7280;">
                            If the button does not work, copy and paste this link into your browser:
                        </p>

                        <p style="margin:0 0 18px 0;font-size:14px;line-height:1.7;word-break:break-all;">
                            <a href="{{ $url }}" style="color:#2563eb;text-decoration:none;">{{ $url }}</a>
                        </p>

                        <p style="margin:0 0 12px 0;font-size:13px;line-height:1.7;color:#9ca3af;">
                            This password reset link will expire in 60 minutes.
                        </p>

                        <p style="margin:0;font-size:13px;line-height:1.7;color:#9ca3af;">
                            If you did not request a password reset, you can safely ignore this email.
                        </p>
                    </td>
                </tr>
            </table>

            <p style="margin:16px 0 0 0;font-size:12px;color:#9ca3af;">
                Please do not reply to this email directly.
            </p>
        </td>
    </tr>
</table>
```

</body>
</html>
