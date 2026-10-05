<?php
declare(strict_types=1);

namespace App\Services;

use App\Logger;

/**
 * إرسال البريد الإلكتروني.
 * MAIL_DRIVER=log  → يُكتب البريد في storage/logs (مناسب للتطوير/XAMPP بدون إعداد)
 * MAIL_DRIVER=mail → دالة mail() في PHP
 * MAIL_DRIVER=smtp → SMTP مباشر (بدون مكتبات خارجية)
 */
final class Mailer
{
    /** @return bool */
    public static function send(string $to, string $subject, string $bodyHtml, array $options = []): bool
    {
        $driver = (string) config('mail.driver', 'log');
        $from = (string) config('mail.from', 'noreply@example.com');
        $fromName = (string) config('mail.from_name', (string) config('app.name'));

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . self::encodeHeader($fromName) . ' <' . $from . '>',
            'Reply-To: ' . $from,
            'X-Mailer: ProfessionalLicense/' . (string) config('app.version'),
        ];

        $html = self::wrapTemplate($subject, $bodyHtml);

        return match ($driver) {
            'smtp'  => self::sendSmtp($to, $subject, $html, $headers),
            'mail'  => self::sendNative($to, $subject, $html, $headers),
            default => self::sendLog($to, $subject, $html, $options),
        };
    }

    /** إرسال رابط إعادة تعيين كلمة المرور */
    public static function sendPasswordReset(string $to, string $name, string $link): bool
    {
        $body = '<p>مرحباً ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '،</p>'
            . '<p>وصلنا طلب لإعادة تعيين كلمة المرور الخاصة بحسابك في ' . htmlspecialchars((string) config('app.name'), ENT_QUOTES, 'UTF-8') . '.</p>'
            . '<p style="text-align:center;margin:24px 0;"><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" '
            . 'style="background:#0b6b3a;color:#fff;padding:12px 22px;border-radius:8px;text-decoration:none;font-weight:700;">إعادة تعيين كلمة المرور</a></p>'
            . '<p style="color:#666;font-size:13px;">الرابط صالح لمدة ساعة واحدة. إذا لم تطلب ذلك فتجاهل هذه الرسالة.</p>';
        return self::send($to, 'إعادة تعيين كلمة المرور', $body, ['reset_link' => $link]);
    }

    public static function sendSubscriptionActivated(string $to, string $name, ?string $expiresAt): bool
    {
        $body = '<p>مرحباً ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '،</p>'
            . '<p>تم تفعيل اشتراكك بنجاح ✅ يمكنك الآن الدخول إلى المنصة والبدء بالتدريب.</p>'
            . ($expiresAt !== null ? '<p>تاريخ انتهاء الاشتراك: <strong>' . htmlspecialchars($expiresAt, ENT_QUOTES, 'UTF-8') . '</strong></p>' : '')
            . '<p style="text-align:center;margin:24px 0;"><a href="' . htmlspecialchars(\url('student/dashboard'), ENT_QUOTES, 'UTF-8') . '" '
            . 'style="background:#0b6b3a;color:#fff;padding:12px 22px;border-radius:8px;text-decoration:none;font-weight:700;">ابدأ التدريب</a></p>';
        return self::send($to, 'تم تفعيل اشتراكك', $body);
    }

    private static function wrapTemplate(string $subject, string $body): string
    {
        $name = htmlspecialchars((string) settings('site_name', (string) config('app.name')), ENT_QUOTES, 'UTF-8');
        return '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8">'
            . '<title>' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</title></head>'
            . '<body style="font-family:Tahoma,Arial,sans-serif;background:#f5f8f6;padding:24px;direction:rtl;">'
            . '<div style="max-width:600px;margin:auto;background:#fff;border-radius:14px;padding:24px;border:1px solid #e3eae6;">'
            . '<h2 style="color:#0b6b3a;margin-top:0;">' . $name . '</h2>' . $body
            . '<hr style="border:none;border-top:1px solid #eee;margin:22px 0;">'
            . '<p style="color:#888;font-size:12px;margin:0;">هذه رسالة آلية من ' . $name . ' — لا ترد عليها.</p>'
            . '</div></body></html>';
    }

    private static function sendLog(string $to, string $subject, string $html, array $options = []): bool
    {
        $dir = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $entry = sprintf(
            "[%s]\nTO: %s\nSUBJECT: %s\n%s\n%s\n%s\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            isset($options['reset_link']) ? 'RESET LINK: ' . $options['reset_link'] : '',
            strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n"], $html)),
            str_repeat('=', 70)
        );
        @file_put_contents($dir . '/mail-' . date('Y-m-d') . '.log', $entry, FILE_APPEND | LOCK_EX);
        Logger::info('بريد (وضع التسجيل): ' . $subject, ['to' => $to]);
        return true;
    }

    private static function sendNative(string $to, string $subject, string $html, array $headers): bool
    {
        $result = @mail($to, self::encodeHeader($subject), $html, implode("\r\n", $headers));
        if (!$result) {
            Logger::error('فشل إرسال البريد عبر mail()', ['to' => $to]);
        }
        return $result;
    }

    private static function sendSmtp(string $to, string $subject, string $html, array $headers): bool
    {
        $host = (string) config('mail.host', '');
        $port = (int) config('mail.port', 587);
        $username = (string) config('mail.username', '');
        $password = (string) config('mail.password', '');
        if ($host === '') {
            Logger::error('SMTP غير مهيأ: MAIL_HOST فارغ');
            return false;
        }

        $socket = @stream_socket_client('tcp://' . $host . ':' . $port, $errno, $errstr, 20);
        if ($socket === false) {
            Logger::error('تعذّر الاتصال بـ SMTP: ' . $errstr, ['host' => $host, 'port' => $port]);
            return false;
        }
        $read = static function () use ($socket): string {
            $data = '';
            while ($line = fgets($socket, 515)) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $data;
        };
        $write = static function (string $command) use ($socket): void {
            fwrite($socket, $command . "\r\n");
        };

        try {
            $read();
            $write('EHLO ' . (string) ($_SERVER['SERVER_NAME'] ?? 'localhost'));
            $read();
            if ($username !== '') {
                $write('AUTH LOGIN');
                $read();
                $write(base64_encode($username));
                $read();
                $write(base64_encode($password));
                $response = $read();
                if (!str_starts_with($response, '235')) {
                    Logger::error('فشل تسجيل الدخول إلى SMTP');
                    return false;
                }
            }
            $write('MAIL FROM: <' . config('mail.from') . '>');
            $read();
            $write('RCPT TO: <' . $to . '>');
            $read();
            $write('DATA');
            $read();
            $message = 'Subject: ' . self::encodeHeader($subject) . "\r\n"
                . 'To: <' . $to . ">\r\n"
                . implode("\r\n", $headers) . "\r\n\r\n"
                . $html . "\r\n.";
            $write($message);
            $result = $read();
            $write('QUIT');
            return str_starts_with($result, '250');
        } finally {
            fclose($socket);
        }
    }

    private static function encodeHeader(string $text): string
    {
        return '=?UTF-8?B?' . base64_encode($text) . '?=';
    }
}
