# SMTP Configuration Guide

## Email System Overview

The FunctionalFit calendar system uses Laravel's built-in mail system for sending transactional emails. All emails are queued and sent asynchronously via Redis queue workers.

## Configuration

### Development/Testing Mode

For local development and testing, use the `log` driver which writes emails to `storage/logs/laravel.log` instead of sending them:

```env
MAIL_MAILER=log
```

This allows you to test email functionality without configuring SMTP or risking accidental email sends.

### Production Mode

For production, configure SMTP with the following settings:

```env
MAIL_MAILER=smtp
MAIL_HOST=mail.egeszsegkozpont-buda.hu
MAIL_PORT=465
MAIL_USERNAME=daniel.szilagyi@egeszsegkozpont-buda.hu
MAIL_PASSWORD=YOUR_SECURE_PASSWORD_HERE
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS="daniel.szilagyi@egeszsegkozpont-buda.hu"
MAIL_FROM_NAME="FunctionalFit Egészségközpont"
```

**Important Security Notes:**
- ⚠️ Never commit the `.env` file with actual passwords to version control
- ⚠️ The `.env` file is already in `.gitignore` - keep it that way
- ⚠️ Use strong, unique passwords for the email account
- ⚠️ Consider using app-specific passwords if available
- ⚠️ Rotate credentials regularly

## SMTP Server Details

**Provider:** mail.egeszsegkozpont-buda.hu
**Protocol:** SMTP with SSL
**Port:** 465 (SSL)
**Authentication:** Required

## Email Templates

The system includes 9 pre-configured email templates:

1. **registration_confirmation** - User registration confirmation
2. **password_reset** - Password reset request
3. **user_deleted** - Account deletion notification
4. **booking_confirmation** - Class booking confirmation
5. **booking_cancellation** - Booking cancellation notification
6. **waitlist_promotion** - Waitlist promotion notification
7. **class_reminder** - 24h class reminder
8. **class_modified** - Class modification notification
9. **class_deleted** - Class deletion notification

All templates support Hungarian and English languages and include `{{variable}}` placeholders.

## Queue Configuration

Emails are sent via Laravel queues for better performance:

```env
QUEUE_CONNECTION=database
```

**Queue Workers:**
- Queue name: `notifications`
- Retry attempts: 3
- Backoff delays: 15s, 30s, 60s

**Start queue worker:**
```bash
php artisan queue:work --queue=notifications
```

**For production (supervisor recommended):**
```bash
php artisan queue:work --queue=notifications --tries=3 --timeout=90 --sleep=3
```

## Testing Email Sending

### Method 1: Using the Admin UI

1. Navigate to Admin → Email Templates
2. Select any template
3. Click "Send Test Email"
4. Enter your email address
5. Check your inbox (and spam folder)

### Method 2: Using Tinker

```bash
php artisan tinker
```

```php
use App\Services\MailService;
use App\Models\User;

$user = User::first();
$service = app(MailService::class);

$service->send(
    'registration_confirmation',
    $user->email,
    [
        'user' => ['name' => $user->name, 'email' => $user->email],
        'confirm_url' => 'https://functionalfit.hu/confirm/test123',
        'company_name' => 'FunctionalFit Egészségközpont'
    ],
    $user->name
);
```

### Method 3: Via Queue Job

```bash
php artisan tinker
```

```php
use App\Jobs\SendRegistrationConfirmation;
use App\Models\User;

$user = User::first();
SendRegistrationConfirmation::dispatch($user);

// Process the queue
exit();
php artisan queue:work --once
```

## Troubleshooting

### Emails Not Sending

1. **Check queue is running:**
   ```bash
   php artisan queue:work
   ```

2. **Check failed jobs:**
   ```bash
   php artisan queue:failed
   ```

3. **Check logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

4. **Test SMTP connection:**
   ```bash
   php artisan tinker
   ```
   ```php
   Mail::raw('Test email', function($msg) {
       $msg->to('your@email.com')->subject('Test');
   });
   ```

### Common Issues

**Issue:** Connection timeout
- **Solution:** Check firewall allows outbound connections on port 465
- **Solution:** Verify SMTP host is reachable: `telnet mail.egeszsegkozpont-buda.hu 465`

**Issue:** Authentication failed
- **Solution:** Verify username and password are correct
- **Solution:** Check if account requires app-specific password

**Issue:** SSL certificate verification failed
- **Solution:** Ensure `MAIL_ENCRYPTION=ssl` is set (not `tls`)
- **Solution:** Update CA certificates on server

**Issue:** Emails in spam
- **Solution:** Configure SPF, DKIM, and DMARC records for domain
- **Solution:** Use consistent FROM address
- **Solution:** Avoid spam trigger words in subject/body

## Email Logs

All sent emails are logged in the `email_logs` table:

```sql
SELECT
    recipient_email,
    template_slug,
    status,
    attempts,
    sent_at,
    created_at
FROM email_logs
ORDER BY created_at DESC
LIMIT 20;
```

**Status values:**
- `queued` - Email queued for sending
- `sent` - Email sent successfully
- `failed` - Email sending failed after all retries

## Production Checklist

- [ ] Set `MAIL_MAILER=smtp` in `.env`
- [ ] Configure correct SMTP credentials
- [ ] Set `MAIL_FROM_ADDRESS` to a valid sending address
- [ ] Set `MAIL_FROM_NAME` to company name
- [ ] Test email sending with real recipient
- [ ] Configure queue worker as system service (supervisor)
- [ ] Set up monitoring for failed jobs
- [ ] Configure log rotation for email logs
- [ ] Implement rate limiting if needed
- [ ] Set up email analytics/tracking if needed
- [ ] Document emergency contact if email service fails

## Additional Resources

- [Laravel Mail Documentation](https://laravel.com/docs/11.x/mail)
- [Laravel Queue Documentation](https://laravel.com/docs/11.x/queues)
- [Supervisor Configuration for Queues](https://laravel.com/docs/11.x/queues#supervisor-configuration)

---

**Last Updated:** 2025-11-25
**Maintained By:** Development Team
