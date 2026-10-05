<?php
declare(strict_types=1);

namespace App\Services;

use App\Logger;

/**
 * عميل Telegram Bot API.
 * التوكن يُقرأ من .env فقط (TELEGRAM_BOT_TOKEN) ولا يُكتب في الكود أو قاعدة البيانات.
 */
final class TelegramService
{
    private string $token;
    private string $baseUrl;

    public function __construct(?string $token = null)
    {
        $this->token = trim($token ?? (string) config('telegram.token', ''));
        $this->baseUrl = rtrim((string) config('telegram.api_base', 'https://api.telegram.org'), '/');
    }

    public static function enabled(): bool
    {
        return trim((string) config('telegram.token', '')) !== '';
    }

    public static function instance(): self
    {
        return new self();
    }

    /**
     * استدعاء أي دالة في Telegram Bot API.
     * @param array<string,mixed> $params
     * @return array{ok:bool,result?:mixed,description?:string,error?:string}
     */
    public function api(string $method, array $params = [], int $timeout = 20): array
    {
        if ($this->token === '') {
            return ['ok' => false, 'error' => 'توكن البوت غير مضبوط في ملف .env (TELEGRAM_BOT_TOKEN).'];
        }
        $url = sprintf('%s/bot%s/%s', $this->baseUrl, $this->token, $method);

        try {
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => json_encode($params, JSON_UNESCAPED_UNICODE),
                    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                    CURLOPT_TIMEOUT        => $timeout,
                    CURLOPT_CONNECTTIMEOUT => 10,
                ]);
                $response = curl_exec($ch);
                $error = curl_error($ch);
                $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($response === false) {
                    Logger::telegram('فشل الاتصال بتلجرام: ' . $error, ['method' => $method]);
                    return ['ok' => false, 'error' => 'تعذّر الاتصال بخادم تلجرام: ' . $error];
                }
            } else {
                $context = stream_context_create(['http' => [
                    'method'        => 'POST',
                    'header'        => "Content-Type: application/json\r\n",
                    'content'       => json_encode($params, JSON_UNESCAPED_UNICODE),
                    'timeout'       => $timeout,
                    'ignore_errors' => true,
                ]]);
                $response = @file_get_contents($url, false, $context);
                $status = 200;
                if ($response === false) {
                    return ['ok' => false, 'error' => 'تعذّر الاتصال بخادم تلجرام.'];
                }
            }

            $decoded = json_decode((string) $response, true);
            if (!is_array($decoded)) {
                return ['ok' => false, 'error' => 'رد غير مفهوم من تلجرام (HTTP ' . $status . ').'];
            }
            if (($decoded['ok'] ?? false) !== true) {
                Logger::telegram('خطأ من واجهة تلجرام', [
                    'method'      => $method,
                    'description' => $decoded['description'] ?? '',
                    'code'        => $decoded['error_code'] ?? 0,
                ]);
            }
            return $decoded;
        } catch (\Throwable $e) {
            Logger::telegram('استثناء أثناء استدعاء تلجرام: ' . $e->getMessage(), ['method' => $method]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** @return array{ok:bool,error?:string} */
    public function sendMessage(int|string $chatId, string $text, array $options = []): array
    {
        $result = $this->api('sendMessage', array_merge([
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ], $options));
        return ['ok' => (bool) ($result['ok'] ?? false), 'error' => (string) ($result['description'] ?? $result['error'] ?? '')];
    }

    /** @return array{ok:bool,error?:string} */
    public function sendPhoto(int|string $chatId, string $photo, string $caption = '', array $options = []): array
    {
        $result = $this->api('sendPhoto', array_merge([
            'chat_id'    => $chatId,
            'photo'      => $photo,
            'caption'    => $caption,
            'parse_mode' => 'HTML',
        ], $options));
        return ['ok' => (bool) ($result['ok'] ?? false), 'error' => (string) ($result['description'] ?? $result['error'] ?? '')];
    }

    public function sendDocument(int|string $chatId, string $document, string $caption = ''): array
    {
        return $this->api('sendDocument', [
            'chat_id'    => $chatId,
            'document'   => $document,
            'caption'    => $caption,
            'parse_mode' => 'HTML',
        ]);
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text = '', bool $alert = false): array
    {
        return $this->api('answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text'              => $text,
            'show_alert'        => $alert,
        ]);
    }

    public function setWebhook(string $url, ?string $secret = null): array
    {
        $params = [
            'url'             => $url,
            'allowed_updates' => ['message', 'callback_query', 'edited_message'],
            'drop_pending_updates' => true,
        ];
        if ($secret !== null && $secret !== '') {
            $params['secret_token'] = $secret;
        }
        return $this->api('setWebhook', $params);
    }

    public function deleteWebhook(): array
    {
        return $this->api('deleteWebhook', ['drop_pending_updates' => true]);
    }

    public function getWebhookInfo(): array
    {
        return $this->api('getWebhookInfo');
    }

    public function getMe(): array
    {
        return $this->api('getMe');
    }

    /** @return array<int,array<string,mixed>> */
    public function getUpdates(int $offset = 0, int $timeout = 30): array
    {
        $result = $this->api('getUpdates', [
            'offset'          => $offset,
            'timeout'         => $timeout,
            'allowed_updates' => ['message', 'callback_query'],
        ], $timeout + 10);
        $updates = $result['result'] ?? [];
        return is_array($updates) ? $updates : [];
    }

    /** تنزيل ملف (مثل صورة إثبات الدفع) إلى مسار محلي */
    public function downloadFile(string $fileId, string $saveTo): bool
    {
        $info = $this->api('getFile', ['file_id' => $fileId]);
        $filePath = (string) ($info['result']['file_path'] ?? '');
        if ($filePath === '') {
            return false;
        }
        $url = sprintf('%s/file/bot%s/%s', $this->baseUrl, $this->token, $filePath);
        $dir = dirname($saveTo);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $data = false;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60]);
            $data = curl_exec($ch);
            curl_close($ch);
        } else {
            $data = @file_get_contents($url);
        }
        if ($data === false) {
            return false;
        }
        return file_put_contents($saveTo, $data) !== false;
    }

    /** تهريب النص ليعمل مع parse_mode = HTML */
    public static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * تحويل تنسيق بسيط إلى HTML مدعوم في تلجرام: *عريض* و _مائل_ و `كود`
     */
    public static function toHtml(string $text): string
    {
        $escaped = self::escape($text);
        $escaped = (string) preg_replace('/\*(.+?)\*/s', '<b>$1</b>', $escaped);
        $escaped = (string) preg_replace('/_(.+?)_/s', '<i>$1</i>', $escaped);
        $escaped = (string) preg_replace('/`(.+?)`/s', '<code>$1</code>', $escaped);
        return $escaped;
    }
}
