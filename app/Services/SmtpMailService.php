<?php

namespace App\Services;

use App\Models\SmtpConfig;
use App\Rules\SafeSmtpHost;
use Exception;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class SmtpMailService
{
    /**
     * Create dynamic Symfony Mailer transport from SmtpConfig model
     */
    public function getTransport(SmtpConfig $config): TransportInterface
    {
        $scheme = match (strtolower($config->encryption ?? 'tls')) {
            'ssl', 'smtps' => 'smtps',
            'tls', 'starttls' => 'smtp',
            default => 'smtp',
        };

        $this->assertHostIsReachable($config);

        // Built as a Dsn object rather than an interpolated string: a host of
        // `real.host?verify_peer=0` used to survive sprintf() and reach the DSN
        // parser, silently switching off certificate verification.
        //
        // It has to go through fromDsnObject(), NOT the static fromDsn(): that
        // helper is typed `string $dsn` in Symfony Mailer 7.x and 8.x alike, so
        // handing it a Dsn object raises a TypeError and every send fails. The
        // two lines below are the static helper's own body with fromString()
        // swapped for fromDsnObject(), which takes the parsed object directly
        // and so never re-parses the host as DSN syntax.
        $factory = new Transport(iterator_to_array(Transport::getDefaultFactories()));

        return $factory->fromDsnObject(new Dsn(
            $scheme,
            (string) $config->host,
            $config->username ?: null,
            $config->password ?: null,
            (int) $config->port,
        ));
    }

    /**
     * Re-checks the host at connect time, not just at save time.
     *
     * Validation happens when the relay is stored, but DNS can change between
     * then and now — re-resolving here closes the rebinding window that would
     * otherwise still let a saved relay reach into private space.
     */
    protected function assertHostIsReachable(SmtpConfig $config): void
    {
        if (! config('mailflow.smtp.block_private_hosts', true)) {
            return;
        }

        $host = (string) $config->host;

        $ips = filter_var($host, FILTER_VALIDATE_IP)
            ? [$host]
            : array_filter(array_map(
                fn (array $record) => $record['ip'] ?? $record['ipv6'] ?? null,
                @dns_get_record($host, DNS_A | DNS_AAAA) ?: []
            ));

        foreach ($ips as $ip) {
            if (! SafeSmtpHost::isPublic($ip)) {
                throw new Exception('This SMTP host resolves to a private or reserved address.');
            }
        }
    }

    /**
     * Run a live diagnostic test send with socket and handshake log capture
     */
    public function testConnection(SmtpConfig $config, string $toEmail): array
    {
        $logs = [];
        $logs[] = '['.date('H:i:s').'] Initiating test connection to '.$config->host.':'.$config->port;
        $logs[] = '['.date('H:i:s').'] Encryption mode: '.strtoupper($config->encryption ?? 'TLS');

        try {
            // 1. Direct Socket Verification
            $timeout = 10;
            $socketHost = ($config->encryption === 'ssl') ? 'ssl://'.$config->host : $config->host;
            $fp = @fsockopen($socketHost, $config->port, $errno, $errstr, $timeout);

            if (! $fp) {
                throw new Exception("Could not establish a connection to {$config->host}:{$config->port}.");
            }

            // The banner is read to confirm the peer really speaks SMTP, but it
            // is never returned: echoing it back turns this screen into an
            // internal-service fingerprinting oracle.
            $banner = fgets($fp, 512);
            $logs[] = '['.date('H:i:s').'] Socket open, server responded to handshake.';
            fclose($fp);

            if ($banner === false || ! preg_match('/^\d{3}/', trim($banner))) {
                throw new Exception('The host answered, but did not respond like an SMTP server.');
            }

            // 2. Symfony Transport Execution
            $logs[] = '['.date('H:i:s').'] Authenticating with user: '.$config->username;
            $transport = $this->getTransport($config);
            $mailer = new Mailer($transport);

            $fromEmail = $config->from_email ?: ($config->username ?: 'test@mailflow.local');
            $fromName = $config->from_name ?: 'MailFlow Platform';

            $email = (new Email)
                ->from(new Address($fromEmail, $fromName))
                ->to(new Address($toEmail))
                ->subject('✅ MailFlow SMTP Test Diagnostic - '.date('Y-m-d H:i:s'))
                ->html('
                    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px;">
                        <h2 style="color: #4f46e5; margin-top: 0;">🎉 SMTP Connection Successful!</h2>
                        <p style="color: #334155; font-size: 14px; line-height: 1.6;">Your SMTP relay is properly configured and communicating with MailFlow.</p>
                        <table style="width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 13px;">
                            <tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 8px; color: #64748b;"><strong>SMTP Host:</strong></td><td style="padding: 8px; color: #0f172a;">'.htmlspecialchars($config->host).'</td></tr>
                            <tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 8px; color: #64748b;"><strong>Port:</strong></td><td style="padding: 8px; color: #0f172a;">'.$config->port.' ('.strtoupper($config->encryption ?? 'TLS').')</td></tr>
                            <tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 8px; color: #64748b;"><strong>Sender:</strong></td><td style="padding: 8px; color: #0f172a;">'.htmlspecialchars($fromName.' <'.$fromEmail.'>').'</td></tr>
                            <tr><td style="padding: 8px; color: #64748b;"><strong>Timestamp:</strong></td><td style="padding: 8px; color: #0f172a;">'.date('Y-m-d H:i:s T').'</td></tr>
                        </table>
                        <p style="color: #10b981; font-size: 13px; font-weight: bold;">🛡️ RFC 8058 One-Click Header Support: Active</p>
                    </div>
                ')
                ->text("MailFlow SMTP Test Connection Successful!
Host: {$config->host}
Port: {$config->port}
Time: ".date('Y-m-d H:i:s'));

            if ($config->reply_to) {
                $email->replyTo(new Address($config->reply_to));
            }

            // Anti-Spam Headers
            $email->getHeaders()->addTextHeader('X-Mailer', 'MailFlow Enterprise Engine');
            $email->getHeaders()->addTextHeader('X-Priority', '3');

            $mailer->send($email);

            $logs[] = '['.date('H:i:s').'] 250 Message accepted for delivery to '.$toEmail;
            $logs[] = '['.date('H:i:s').'] Test finished successfully!';

            return [
                'success' => true,
                'message' => "SMTP connection verified! Test email successfully sent to {$toEmail}.",
                'logs' => $logs,
            ];
        } catch (\Throwable $e) {
            // Symfony transport exceptions routinely quote the DSN, which carries
            // the relay password — so the detail is logged, never returned.
            Log::warning('SMTP diagnostic failed', [
                'smtp_config_id' => $config->id,
                'host' => $config->host,
                'port' => $config->port,
                'error' => $e->getMessage(),
            ]);

            $reason = $this->safeFailureReason($e);
            $logs[] = '['.date('H:i:s').'] ERROR: '.$reason;

            return [
                'success' => false,
                'message' => 'SMTP Test Failed: '.$reason,
                'logs' => $logs,
            ];
        }
    }

    /**
     * Maps a transport failure onto a message that is safe to show.
     *
     * Enough for the operator to act on, without reflecting server banners,
     * connection strings or credentials back into the browser.
     */
    protected function safeFailureReason(\Throwable $e): string
    {
        $message = $e->getMessage();

        // Messages this service raises itself are already sanitised.
        if ($e instanceof Exception && ! str_contains($message, '://')) {
            return $message;
        }

        return match (true) {
            str_contains($message, 'authenticat'), str_contains($message, '535') => 'Authentication was rejected — check the username and password.',
            str_contains($message, 'SSL'), str_contains($message, 'TLS'), str_contains($message, 'certificate') => 'The TLS handshake failed — check the encryption mode and port.',
            str_contains($message, 'timed out'), str_contains($message, 'timeout') => 'The connection timed out.',
            default => 'The relay refused the message. The full error has been written to the application log.',
        };
    }

    /**
     * Dispatch an actual campaign email with full anti-spam headers, multipart payload, and open/click tracking
     */
    public function sendCampaignEmail(
        SmtpConfig $config,
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody,
        string $trackingToken,
        ?string $unsubscribeUrl = null,
        ?string $embeddedLogoPath = null
    ): bool {
        $transport = $this->getTransport($config);
        $mailer = new Mailer($transport);

        $fromEmail = $config->from_email ?: ($config->username ?: 'mailer@mailflow.local');
        $fromName = $config->from_name ?: 'MailFlow Platform';

        // Inject 1x1 Transparent Open Tracking Pixel
        $appUrl = rtrim(config('app.url'), '/');
        $trackingPixelUrl = "{$appUrl}/track/open/{$trackingToken}.png";
        $pixelTag = '<img src="'.$trackingPixelUrl.'" width="1" height="1" style="display:none !important; max-height:0; visibility:hidden;" alt="" />';

        if (str_contains($htmlBody, '</body>')) {
            $htmlWithPixel = str_replace('</body>', $pixelTag.'</body>', $htmlBody);
        } else {
            $htmlWithPixel = $htmlBody.$pixelTag;
        }

        $email = (new Email)
            ->from(new Address($fromEmail, $fromName))
            ->to(new Address($toEmail, $toName ?: $toEmail))
            ->subject($subject)
            ->html($htmlWithPixel)
            ->text($textBody ?: strip_tags($htmlBody));

        // Logos are embedded as a cid: part rather than linked, so they render
        // without the app needing to be reachable from the recipient's client.
        if ($embeddedLogoPath && is_file($embeddedLogoPath)) {
            $email->embedFromPath($embeddedLogoPath, 'mailflow-logo');
        }

        if ($config->reply_to) {
            $email->replyTo(new Address($config->reply_to));
        }

        // Anti-Spam & Deliverability Headers
        $headers = $email->getHeaders();
        $headers->addTextHeader('X-Mailer', 'MailFlow Enterprise Engine');
        $headers->addTextHeader('X-Campaign-Token', $trackingToken);

        // RFC 8058 One-Click List-Unsubscribe Header (Mandatory for Google/Yahoo 2024+)
        if ($unsubscribeUrl) {
            $headers->addTextHeader('List-Unsubscribe', "<{$unsubscribeUrl}>");
            $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        }

        $mailer->send($email);

        return true;
    }
}
