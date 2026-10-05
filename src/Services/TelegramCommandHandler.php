<?php
declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Logger;
use App\Security;
use App\Str;

/**
 * معالج أوامر بوت تلجرام.
 *
 * مبادئ التصميم:
 *  - الربط اختياري تماماً: كل الأوامر تعمل للزائر، وما عدا الأسئلة المجانية
 *    والمحتوى المدفوع يبقى محجوباً عن غير المشترك.
 *  - لا تُخزَّن أي بيانات حساسة في حالة المحادثة، والرمز المؤقت يُستهلك مرة واحدة.
 *  - كل رد يُسجَّل في telegram_messages للتدقيق والدعم.
 */
class TelegramCommandHandler
{
    private const PRACTICE_LENGTH = 5;
    private const MAX_MESSAGE = 3500;

    private Database $db;
    private ?array $cacheUser = null;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    /** نقطة الدخول: تحديث واحد من تلجرام */
    public function handle(array $update): void
    {
        try {
            if (isset($update['callback_query'])) {
                $this->handleCallback($update['callback_query']);
                return;
            }
            $message = $update['message'] ?? null;
            if (!is_array($message)) {
                return;
            }
            $chatId = (string) ($message['chat']['id'] ?? '');
            $text = trim((string) ($message['text'] ?? ''));
            if ($chatId === '') {
                return;
            }
            $tgUserId = (int) ($message['from']['id'] ?? 0);
            $this->touchLink($tgUserId, $message);

            if ($text === '') {
                $this->reply($chatId, $tgUserId, 'أرسل نصاً أو استخدم الأوامر. اكتب /help لعرض الأوامر المتاحة.');
                return;
            }
            $this->route($chatId, $tgUserId, $text);
        } catch (\Throwable $e) {
            Logger::error('telegram.handle: ' . $e->getMessage());
        }
    }

    /* ==================== التوجيه ==================== */

    private function route(string $chatId, int $tgUserId, string $text): void
    {
        $state = $this->state($tgUserId);
        if (str_starts_with($text, '/')) {
            [$command, $argument] = $this->splitCommand($text);
            $this->setState($tgUserId, 'idle');
            switch ($command) {
                case '/start':
                case '/help':
                    $this->cmdHelp($chatId, $tgUserId, $argument);
                    return;
                case '/link':
                case '/ربط':
                    $this->cmdLink($chatId, $tgUserId, $argument);
                    return;
                case '/status':
                case '/حالتي':
                    $this->cmdStatus($chatId, $tgUserId);
                    return;
                case '/subscribe':
                case '/اشتراك':
                    $this->cmdSubscribe($chatId, $tgUserId);
                    return;
                case '/today':
                case '/اليوم':
                    $this->cmdToday($chatId, $tgUserId);
                    return;
                case '/practice':
                case '/تدريب':
                    $this->cmdPractice($chatId, $tgUserId);
                    return;
                case '/stats':
                case '/إحصاء':
                    $this->cmdStats($chatId, $tgUserId);
                    return;
                case '/support':
                case '/دعم':
                    $this->cmdSupport($chatId, $tgUserId, $argument);
                    return;
                case '/unlink':
                case '/فصل':
                    $this->cmdUnlink($chatId, $tgUserId);
                    return;
                case '/privacy':
                case '/خصوصية':
                    $this->cmdPrivacy($chatId, $tgUserId);
                    return;
                case '/cancel':
                case '/إلغاء':
                    $this->reply($chatId, $tgUserId, 'تم الإلغاء. اكتب /help لعرض الأوامر.');
                    return;
                default:
                    $this->reply($chatId, $tgUserId, 'أمر غير معروف. اكتب /help لعرض الأوامر المتاحة.');
                    return;
            }
        }

        switch ($state) {
            case 'await_link_code':
                $this->cmdLink($chatId, $tgUserId, $text);
                return;
            case 'await_support_message':
                $this->supportMessage($chatId, $tgUserId, $text);
                return;
            case 'await_payment_proof':
                $this->paymentProof($chatId, $tgUserId, $text);
                return;
            case 'practice':
                $this->practiceAnswer($chatId, $tgUserId, $text);
                return;
            default:
                $this->reply($chatId, $tgUserId, "لم أفهم رسالتك. اكتب /help لعرض الأوامر المتاحة، أو /support لإرسال استفسار للدعم الفني.");
        }
    }

    /** @return array{0:string,1:string} */
    private function splitCommand(string $text): array
    {
        $parts = preg_split('/\s+/u', $text, 2) ?: [$text, ''];
        $command = strtolower(strtok($parts[0], '@') ?: $parts[0]);
        return [$command, trim($parts[1] ?? '')];
    }

    /* ==================== الأوامر ==================== */

    private function cmdHelp(string $chatId, int $tgUserId, string $argument = ''): void
    {
        if ($argument !== '') {
            $this->cmdLink($chatId, $tgUserId, $argument);
            return;
        }
        $lines = [
            '<b>منصة الرخصة المهنية للمعلمين</b>',
            'بوت الأسئلة والاشتراك. الربط بحسابك اختياري، ويمكنك الاستمرار بالأسئلة المجانية فقط.',
            '',
            '/link رمز — ربط حسابك (من صفحة «ربط تلجرام» في حسابك على المنصة)',
            '/status — حالة اشتراكك والأيام المتبقية',
            '/today — أسئلة مجانية متجددة',
            '/practice — تدريب سريع من 5 أسئلة مع التصحيح الفوري',
            '/stats — نقاط قوتك وضعفك في المجالات',
            '/subscribe — باقات الاشتراك وطرق الدفع',
            '/support نص الرسالة — مراسلة الدعم الفني',
            '/unlink — فصل الحساب',
            '/privacy — الخصوصية والبيانات',
            '/cancel — إلغاء العملية الحالية',
        ];
        $this->reply($chatId, $tgUserId, implode("\n", $lines));
    }

    private function cmdLink(string $chatId, int $tgUserId, string $code): void
    {
        // توحيد الرمز إلى الصيغة القياسية ABCD-EFGH (يقبل الإدخال بدون شرطة أيضاً)
        $code = Security::normalizeLinkCode($code);
        if (strlen((string) preg_replace('/[^A-Z0-9]/', '', $code)) < 6) {
            $this->setState($tgUserId, 'await_link_code');
            $this->reply($chatId, $tgUserId, "أرسل رمز الربط الذي يظهر في صفحة «ربط تلجرام» من حسابك على المنصة.\nمثال: /link ABCD-EFGH");
            return;
        }

        // الأكواد مخزّنة مشفّرة (SHA-256) وصالحة 30 دقيقة وتُستهلك مرة واحدة
        $row = $this->db->one(
            'SELECT * FROM `telegram_link_codes`
              WHERE code_hash = :hash AND used_at IS NULL AND expires_at > NOW()
              ORDER BY id DESC LIMIT 1',
            ['hash' => hash('sha256', $code)]
        );
        if ($row === null) {
            $used = (int) $this->db->value(
                'SELECT COUNT(*) FROM `telegram_link_codes` WHERE code_hash = :hash',
                ['hash' => hash('sha256', $code)]
            ) > 0;
            $this->reply($chatId, $tgUserId, $used
                ? 'هذا الرمز مُستخدم أو منتهي الصلاحية. أنشئ رمزاً جديداً من صفحة «ربط تلجرام» في حسابك.'
                : 'رمز الربط غير صحيح. تأكد من نسخه كما هو، أو أنشئ رمزاً جديداً من حسابك.');
            return;
        }

        $userId = (int) $row['user_id'];
        $user = $this->db->one('SELECT id, full_name FROM `users` WHERE id = :id AND status = :status', ['id' => $userId, 'status' => 'active']);
        if ($user === null) {
            $this->reply($chatId, $tgUserId, 'تعذّر إتمام الربط: الحساب غير موجود أو غير مفعّل.');
            return;
        }

        $this->db->transaction(function () use ($row, $userId, $tgUserId, $chatId, $code): void {
            $existing = $this->db->one('SELECT id FROM `telegram_links` WHERE telegram_user_id = :tg LIMIT 1', ['tg' => $tgUserId]);
            $fields = [
                'user_id'             => $userId,
                'telegram_chat_id'    => $chatId,
                'telegram_username'   => $this->lastUsername,
                'telegram_first_name' => $this->lastFirstName,
                'linked_at'           => date('Y-m-d H:i:s'),
                'is_blocked'          => 0,
                'last_interaction_at' => date('Y-m-d H:i:s'),
            ];
            if ($existing !== null) {
                $this->db->update('telegram_links', $fields, 'id = :id', ['id' => (int) $existing['id']]);
            } else {
                $this->db->insert('telegram_links', array_merge($fields, ['telegram_user_id' => $tgUserId]));
            }
            // فصل أي حساب آخر كان مرتبطاً بنفس المستخدم (ربط واحد نشط لكل طالب)
            $this->db->update('telegram_links', ['is_blocked' => 1], 'user_id = :u AND telegram_user_id <> :tg', ['u' => $userId, 'tg' => $tgUserId]);
            $this->db->update('users', ['telegram_chat_id' => $chatId], 'id = :id', ['id' => $userId]);

            // استهلاك الرمز (مرة واحدة)
            $this->db->update('telegram_link_codes', ['used_at' => date('Y-m-d H:i:s'), 'attempts' => (int) $row['attempts'] + 1], 'id = :id', ['id' => (int) $row['id']]);
            $this->db->delete('telegram_link_codes', 'user_id = :u AND id <> :id', ['u' => $userId, 'id' => (int) $row['id']]);
            $this->db->insert('telegram_messages', [
                'telegram_user_id' => $tgUserId,
                'user_id'          => $userId,
                'direction'        => 'in',
                'message_type'     => 'link',
                'content'          => '••••-' . substr($code, -4),
            ]);
        });

        $this->cacheUser = null;
        $this->reply($chatId, $tgUserId, 'تم ربط حسابك بنجاح ✔' . "\n" . 'مرحباً ' . $this->escape((string) $user['full_name'])
            . ". اكتب /status لعرض اشتراكك أو /practice للتدريب.");
    }

    private function cmdUnlink(string $chatId, int $tgUserId): void
    {
        $link = $this->link($tgUserId);
        if ($link === null) {
            $this->reply($chatId, $tgUserId, 'حسابك غير مرتبط أصلاً.');
            return;
        }
        $this->db->delete('telegram_links', 'id = :id', ['id' => (int) $link['id']]);
        if ($link['user_id'] !== null) {
            $this->db->update('users', ['telegram_chat_id' => null], 'id = :id', ['id' => (int) $link['user_id']]);
            $this->db->update('telegram_links', ['is_blocked' => 1], 'user_id = :u', ['u' => (int) $link['user_id']]);
            $this->db->delete('telegram_link_codes', 'user_id = :u', ['u' => (int) $link['user_id']]);
        }
        $this->cacheUser = null;
        $this->reply($chatId, $tgUserId, 'تم فصل الحساب. يمكنك الربط مرة أخرى في أي وقت من حسابك على المنصة.');
    }

    private function cmdPrivacy(string $chatId, int $tgUserId): void
    {
        $this->reply($chatId, $tgUserId, implode("\n", [
            '<b>الخصوصية والبيانات</b>',
            '• نستخدم بيانات تلجرام (المعرّف، الاسم، اسم المستخدم) لغرض الربط والإشعارات فقط.',
            '• لا نشارك بياناتك مع أي طرف ثالث، ولا نرسل رسائل تسويقية من مصادر أخرى.',
            '• الربط اختياري ويمكن إلغاؤه في أي وقت بالأمر /unlink أو من صفحة «ربط تلجرام» في حسابك.',
            '• سجلّ المحادثات يُحفظ للتدقيق الفني والدعم فقط.',
        ]));
    }

    private function cmdStatus(string $chatId, int $tgUserId): void
    {
        $user = $this->linkedUser($tgUserId);
        if ($user === null) {
            $this->reply($chatId, $tgUserId, 'حسابك غير مرتبط بعد. اكتب /link ثم رمز الربط من صفحتك على المنصة.' . "\n" . 'الأسئلة المجانية متاحة لك حتى بدون ربط: /today');
            return;
        }
        $sub = $this->subscription((int) $user['id']);
        if ($sub === null) {
            $this->reply($chatId, $tgUserId, "لا يوجد اشتراك مرتبط بحسابك.\nالأسئلة المجانية متاحة: /today\nللاشتراك: /subscribe");
            return;
        }
        $status = (string) $sub['status'];
        if (in_array($status, ['active'], true) && $sub['expires_at'] !== null && strtotime((string) $sub['expires_at']) > time()) {
            $days = (int) ceil((strtotime((string) $sub['expires_at']) - time()) / 86400);
            $this->reply($chatId, $tgUserId, "اشتراكك نشط ✔\nالخطة: " . $this->escape((string) $sub['plan_name'])
                . "\nينتهي في: " . $this->escape((string) $sub['expires_at']) . ' (' . $days . ' يوماً متبقياً)');
            return;
        }
        $map = [
            'pending'  => 'طلبك قيد المراجعة من الإدارة، وسيصلك إشعار عند التفعيل.',
            'rejected' => 'تم رفض طلب الاشتراك الأخير. تواصل مع الدعم: /support',
            'expired'  => 'اشتراكك منتهٍ. للتجديد: /subscribe',
            'cancelled'=> 'اشتراكك ملغى. للتجديد: /subscribe',
        ];
        $this->reply($chatId, $tgUserId, $map[$status] ?? ('حالة اشتراكك: ' . $this->escape($status)));
    }

    private function cmdSubscribe(string $chatId, int $tgUserId): void
    {
        $user = $this->linkedUser($tgUserId);
        if ($user === null) {
            $this->reply($chatId, $tgUserId, 'اربط حسابك أولاً للحصول على اشتراك: /link');
            return;
        }
        $plans = $this->db->all('SELECT * FROM `subscription_plans` WHERE is_active = 1 ORDER BY price ASC, sort_order ASC LIMIT 5');
        $lines = ['<b>باقات الاشتراك</b>'];
        foreach ($plans as $plan) {
            $lines[] = '• ' . $this->escape((string) $plan['name_ar']) . ' — '
                . $this->escape(number_format((float) $plan['price'], 0)) . ' ' . $this->escape((string) ($plan['currency'] ?? 'SAR'))
                . ' لمدة ' . (int) $plan['duration_days'] . ' يوماً';
        }
        $lines[] = '';
        $lines[] = 'للاشتراك، ارفع إثبات التحويل من صفحة «الاشتراك» في المنصة، أو أرسل كلمة <code>إثبات</code> هنا وسأوجّهك خطوة بخطوة.';
        $lines[] = 'طرق الدفع المتاحة: تحويل بنكي / STC Pay / نقداً في المركز / البطاقات عند تفعيلها.';
        $this->setState($tgUserId, 'await_payment_proof');
        $this->reply($chatId, $tgUserId, implode("\n", $lines));
    }

    private function paymentProof(string $chatId, int $tgUserId, string $text): void
    {
        $user = $this->linkedUser($tgUserId);
        if ($user === null) {
            $this->reply($chatId, $tgUserId, 'اربط حسابك أولاً: /link');
            return;
        }
        $text = trim($text);
        if (mb_strlen($text) < 5) {
            $this->reply($chatId, $tgUserId, 'اكتب رقم الحوالة أو المرجع وطريقة الدفع والمبلغ (مثال: تحويل بنكي — 250.00 — الرقم 1234567).');
            return;
        }
        $ticketId = (int) $this->db->insert('support_tickets', [
            'user_id'   => (int) $user['id'],
            'telegram_user_id' => $tgUserId,
            'name'      => (string) $user['full_name'],
            'contact'   => (string) ($user['phone'] ?? ''),
            'channel'   => 'telegram',
            'subject'   => 'إثبات دفع اشتراك عبر تلجرام',
            'message'   => $text,
            'status'    => 'open',
            'priority'  => 'high',
        ]);
        $this->setState($tgUserId, 'idle');
        Notifier::toAdmins('إثبات دفع جديد عبر تلجرام', "التذكرة #{$ticketId} — " . Str::limit($text, 160));
        $this->reply($chatId, $tgUserId, 'تم استلام إثبات الدفع ✔' . "\n" . 'رقم التذكرة: ' . $ticketId . '. ستراجع الإدارة الطلب وستصلك رسالة عند التفعيل.');
    }

    private function cmdSupport(string $chatId, int $tgUserId, string $text): void
    {
        if (trim($text) === '') {
            $this->setState($tgUserId, 'await_support_message');
            $this->reply($chatId, $tgUserId, 'اكتب رسالتك الآن وسأرسلها للدعم الفني. للإلغاء أرسل /cancel');
            return;
        }
        $this->supportMessage($chatId, $tgUserId, $text);
    }

    private function supportMessage(string $chatId, int $tgUserId, string $text): void
    {
        $text = trim($text);
        if (mb_strlen($text) < 5) {
            $this->reply($chatId, $tgUserId, 'الرسالة قصيرة جداً. اكتب وصفاً موجزاً لمشكلتك.');
            return;
        }
        $user = $this->linkedUser($tgUserId);
        $ticketId = (int) $this->db->insert('support_tickets', [
            'user_id'   => $user['id'] ?? null,
            'telegram_user_id' => $tgUserId,
            'name'      => (string) ($user['full_name'] ?? ('زائر تلجرام #' . $tgUserId)),
            'contact'   => (string) ($user['phone'] ?? ('telegram:' . $tgUserId)),
            'channel'   => 'telegram',
            'subject'   => Str::limit($text, 120),
            'message'   => $text,
            'status'    => 'open',
            'priority'  => 'normal',
        ]);
        $this->setState($tgUserId, 'idle');
        Notifier::toAdmins('تذكرة دعم جديدة من تلجرام', "التذكرة #{$ticketId}\n" . Str::limit($text, 200));
        $this->reply($chatId, $tgUserId, 'تم إرسال رسالتك للدعم الفني ✔' . "\n" . 'رقم التذكرة: ' . $ticketId . '. يمكنك متابعتها من صفحة الدعم في حسابك.');
    }

    private function cmdToday(string $chatId, int $tgUserId): void
    {
        $user = $this->linkedUser($tgUserId);
        $count = $user !== null && $this->isSubscribed((int) $user['id']) ? 5 : 3;
        $questions = $this->randomQuestions($count);
        if ($questions === []) {
            $this->reply($chatId, $tgUserId, 'لا يوجد محتوى منشور بعد. سيتوفر قريباً بإذن الله.');
            return;
        }
        $lines = ['<b>أسئلة اليوم</b>' . ($user !== null && $this->isSubscribed((int) $user['id']) ? ' (عضوية نشطة)' : ' (نسخة مجانية)')];
        foreach ($questions as $index => $question) {
            $lines[] = '';
            $lines[] = '<b>' . ($index + 1) . ')</b> ' . $this->escape((string) $question['question_text']);
            foreach (['a' => 'أ', 'b' => 'ب', 'c' => 'ج', 'd' => 'د'] as $letter => $label) {
                $value = (string) ($question['option_' . $letter] ?? '');
                if ($value !== '') {
                    $lines[] = $label . ') ' . $this->escape($value);
                }
            }
        }
        $lines[] = '';
        $lines[] = 'للتصحيح الفوري استخدم /practice — الأسئلة هناك تُصحَّح فوراً.';
        $this->reply($chatId, $tgUserId, implode("\n", $lines));
    }

    private function cmdPractice(string $chatId, int $tgUserId): void
    {
        $questions = $this->randomQuestions(self::PRACTICE_LENGTH, true);
        if ($questions === []) {
            $this->reply($chatId, $tgUserId, 'لا توجد أسئلة متاحة للتدريب حالياً.');
            return;
        }
        $payload = ['index' => 0, 'correct' => 0, 'wrong' => 0];
        $this->setState($tgUserId, 'practice', $payload);
        $this->sendQuestion($chatId, $tgUserId, $questions, 0);
    }

    private function practiceAnswer(string $chatId, int $tgUserId, string $text): void
    {
        $state = $this->state($tgUserId);
        $payload = $state['payload'];
        $questionId = (int) ($payload['question_ids'][$payload['index']] ?? 0);
        $question = $questionId > 0 ? $this->db->one('SELECT * FROM `questions` WHERE id = :id', ['id' => $questionId]) : null;

        $letter = $this->normalizeLetter($text);
        if ($letter === null || $question === null) {
            $this->reply($chatId, $tgUserId, 'أرسل حرف الإجابة فقط: أ / ب / ج / د');
            return;
        }
        if ($question['correct_answer'] === null) {
            $this->reply($chatId, $tgUserId, 'هذا السؤال بانتظار مراجعة الإجابة من المحتوى، وسنتجاوزه.' . "\n" . 'اكتب /practice لبدء جولة جديدة.');
            $this->setState($tgUserId, 'idle');
            return;
        }

        $correct = (string) $question['correct_answer'];
        $isRight = $letter === $correct;
        $feedback = $isRight ? '✔ إجابة صحيحة' : ('✘ إجابة خاطئة. الصحيحة: ' . $this->letterAr($correct));
        $explanation = trim((string) ($question['explanation'] ?? ''));
        if ($explanation !== '') {
            $feedback .= "\n" . 'الشرح: ' . $this->escape(Str::limit($explanation, 300));
        }

        $payload[$isRight ? 'correct' : 'wrong'] = (int) $payload[$isRight ? 'correct' : 'wrong'] + 1;
        $payload['index'] = (int) $payload['index'] + 1;
        $payload['question_ids'] = array_values($payload['question_ids'] ?? []);
        $this->setState($tgUserId, 'practice', $payload);
        $this->recordPractice($tgUserId, $questionId, $letter, $isRight);
        $this->reply($chatId, $tgUserId, $feedback);

        if ($payload['index'] >= count($payload['question_ids'])) {
            $this->setState($tgUserId, 'idle');
            $total = (int) $payload['correct'] + (int) $payload['wrong'];
            $this->reply($chatId, $tgUserId, 'انتهت جولة التدريب: ' . (int) $payload['correct'] . ' من ' . $total
                . '.' . "\n" . 'اكتب /practice لجولة جديدة، أو /stats لعرض نقاط القوة والضعف.');
            return;
        }
        $questions = $this->questionsByIds($payload['question_ids']);
        $this->sendQuestion($chatId, $tgUserId, $questions, (int) $payload['index']);
    }

    private function cmdStats(string $chatId, int $tgUserId): void
    {
        $user = $this->linkedUser($tgUserId);
        if ($user === null) {
            $this->reply($chatId, $tgUserId, 'اربط حسابك أولاً لعرض إحصاءاتك: /link');
            return;
        }
        $rows = $this->db->all(
            'SELECT s.category_id, s.answered, s.correct, s.accuracy, c.name_ar
               FROM `user_category_stats` s
               JOIN `categories` c ON c.id = s.category_id
              WHERE s.user_id = :id AND s.answered >= 1
              ORDER BY s.accuracy DESC LIMIT 12',
            ['id' => (int) $user['id']]
        );
        if ($rows === []) {
            $this->reply($chatId, $tgUserId, "لا توجد إحصاءات بعد. ابدأ اختباراً أو تدريباً ثم عد إلى هنا.\n/practice");
            return;
        }
        $strong = array_slice(array_filter($rows, static fn(array $r) => (float) $r['accuracy'] >= 80.0), 0, 3);
        $weak = array_slice(array_reverse(array_filter($rows, static fn(array $r) => (float) $r['accuracy'] < 60.0)), 0, 3);
        $lines = ['<b>ملخص أدائك</b>'];
        if ($strong !== []) {
            $lines[] = '';
            $lines[] = '<b>نقاط القوة</b>';
            foreach ($strong as $row) {
                $lines[] = '• ' . $this->escape((string) $row['name_ar']) . ' — ' . (int) round((float) $row['accuracy']) . '%';
            }
        }
        if ($weak !== []) {
            $lines[] = '';
            $lines[] = '<b>يحتاج تحسيناً</b>';
            foreach ($weak as $row) {
                $lines[] = '• ' . $this->escape((string) $row['name_ar']) . ' — ' . (int) round((float) $row['accuracy']) . '%';
            }
        }
        if ($strong === [] && $weak === []) {
            $lines[] = '';
            $lines[] = 'أداؤك متوازن حتى الآن. راجع التفاصيل الكاملة من صفحة الإحصاءات في حسابك.';
        }
        $this->reply($chatId, $tgUserId, implode("\n", $lines));
    }

    /* ==================== الإجابات التفاعلية ==================== */

    private function handleCallback(array $callback): void
    {
        $tgUserId = (int) ($callback['from']['id'] ?? 0);
        $chatId = (string) ($callback['message']['chat']['id'] ?? '');
        $data = (string) ($callback['data'] ?? '');
        $callbackId = (string) ($callback['id'] ?? '');
        if ($chatId === '' || $callbackId === '') {
            return;
        }

        if (preg_match('/^ans:(\d+):([a-d])$/', $data, $matches) === 1) {
            $questionId = (int) $matches[1];
            $letter = $matches[2];
            $question = $this->db->one('SELECT id, question_text, correct_answer, explanation FROM `questions` WHERE id = :id', ['id' => $questionId]);
            if ($question === null) {
                TelegramService::instance()->answerCallbackQuery($callbackId, 'انتهت صلاحية السؤال.');
                return;
            }
            if ($question['correct_answer'] === null) {
                TelegramService::instance()->answerCallbackQuery($callbackId, 'هذا السؤال بانتظار مراجعة الإدارة.');
                return;
            }
            $isRight = $letter === (string) $question['correct_answer'];
            TelegramService::instance()->answerCallbackQuery($callbackId, $isRight ? '✔ إجابة صحيحة' : ('✘ الصحيحة: ' . $this->letterAr((string) $question['correct_answer'])));
            $this->recordPractice($tgUserId, $questionId, $letter, $isRight);
            return;
        }

        if ($data === 'stop') {
            $this->setState($tgUserId, 'idle');
            TelegramService::instance()->answerCallbackQuery($callbackId, 'تم إيقاف التدريب.');
            $this->reply($chatId, $tgUserId, 'أوقفت جولة التدريب. اكتب /practice للبدء من جديد.');
            return;
        }

        if (preg_match('/^link:(\d+)$/', $data, $matches) === 1) {
            $this->setState($tgUserId, 'await_link_code');
            TelegramService::instance()->answerCallbackQuery($callbackId, 'أرسل رمز الربط الآن.');
            $this->reply($chatId, $tgUserId, 'أرسل رمز الربط الظاهر في صفحتك على المنصة.');
            return;
        }

        TelegramService::instance()->answerCallbackQuery($callbackId, 'زر غير معروف.');
    }

    /* ==================== أدوات داخلية ==================== */

    /** آخر بيانات المستخدم من تلجرام — تُضبط في touchLink */
    private ?string $lastUsername = null;
    private ?string $lastFirstName = null;

    private function touchLink(int $tgUserId, array $message): void
    {
        if ($tgUserId <= 0) {
            return;
        }
        $from = $message['from'] ?? [];
        $this->lastUsername = isset($from['username']) ? (string) $from['username'] : null;
        $this->lastFirstName = isset($from['first_name']) ? (string) $from['first_name'] : null;

        $this->db->query(
            'INSERT INTO `telegram_links` (`telegram_user_id`, `telegram_username`, `telegram_first_name`, `telegram_chat_id`, `last_interaction_at`)
             VALUES (:tg, :username, :first_name, :chat, NOW())
             ON DUPLICATE KEY UPDATE `telegram_username` = VALUES(`telegram_username`),
                                     `telegram_first_name` = VALUES(`telegram_first_name`),
                                     `telegram_chat_id` = VALUES(`telegram_chat_id`),
                                     `last_interaction_at` = NOW()',
            [
                'tg'         => $tgUserId,
                'username'   => $this->lastUsername,
                'first_name' => $this->lastFirstName,
                'chat'       => (string) ($message['chat']['id'] ?? ''),
            ]
        );
    }

    /** @return array{state:string,payload:array<string,mixed>} */
    private function state(int $tgUserId): array
    {
        if ($tgUserId <= 0) {
            return ['state' => 'idle', 'payload' => []];
        }
        $row = $this->db->one('SELECT * FROM `telegram_states` WHERE telegram_user_id = :tg', ['tg' => $tgUserId]);
        if ($row === null) {
            return ['state' => 'idle', 'payload' => []];
        }
        $payload = json_decode((string) ($row['payload'] ?? ''), true);
        return ['state' => (string) $row['state'], 'payload' => is_array($payload) ? $payload : []];
    }

    private function setState(int $tgUserId, string $state, array $payload = []): void
    {
        if ($tgUserId <= 0) {
            return;
        }
        $this->db->query(
            'INSERT INTO `telegram_states` (`telegram_user_id`, `state`, `payload`)
             VALUES (:tg, :state, :payload)
             ON DUPLICATE KEY UPDATE `state` = VALUES(`state`), `payload` = VALUES(`payload`)',
            [
                'tg'      => $tgUserId,
                'state'   => $state,
                'payload' => $payload === [] ? null : json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]
        );
    }

    private function link(int $tgUserId): ?array
    {
        if ($tgUserId <= 0) {
            return null;
        }
        return $this->db->one(
            'SELECT * FROM `telegram_links` WHERE telegram_user_id = :tg AND user_id IS NOT NULL AND is_blocked = 0 LIMIT 1',
            ['tg' => $tgUserId]
        );
    }

    private function linkedUser(int $tgUserId): ?array
    {
        if ($this->cacheUser !== null) {
            return $this->cacheUser;
        }
        $link = $this->link($tgUserId);
        if ($link === null || $link['user_id'] === null) {
            return null;
        }
        $this->cacheUser = $this->db->one(
            'SELECT id, full_name, phone, email FROM `users` WHERE id = :id AND status = :status',
            ['id' => (int) $link['user_id'], 'status' => 'active']
        );
        return $this->cacheUser;
    }

    private function subscription(int $userId): ?array
    {
        return $this->db->one(
            'SELECT s.*, p.name_ar AS plan_name, p.price, p.currency
               FROM `subscriptions` s
               LEFT JOIN `subscription_plans` p ON p.id = s.plan_id
              WHERE s.user_id = :id
              ORDER BY s.id DESC LIMIT 1',
            ['id' => $userId]
        );
    }

    private function isSubscribed(int $userId): bool
    {
        $value = $this->db->value(
            "SELECT COUNT(*) FROM `subscriptions`
              WHERE user_id = :id AND status = 'active' AND (expires_at IS NULL OR expires_at > NOW())",
            ['id' => $userId]
        );
        return (int) $value > 0;
    }

    /** @return array<int,array<string,mixed>> */
    private function randomQuestions(int $count, bool $onlyWithAnswer = false): array
    {
        $sql = "SELECT q.id, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_answer, q.explanation, q.difficulty
                  FROM `questions` q
                 WHERE q.is_active = 1 AND q.needs_review = 0"
            . ($onlyWithAnswer ? ' AND q.correct_answer IS NOT NULL' : '')
            . ' ORDER BY RAND() LIMIT ' . max(1, min(10, $count));
        return $this->db->all($sql);
    }

    /** @param array<int,int> $ids @return array<int,array<string,mixed>> */
    private function questionsByIds(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static fn(int $id) => $id > 0));
        if ($ids === []) {
            return [];
        }
        $placeholders = [];
        $params = [];
        foreach ($ids as $index => $id) {
            $placeholders[] = ':id' . $index;
            $params['id' . $index] = $id;
        }
        $rows = $this->db->all(
            'SELECT * FROM `questions` WHERE id IN (' . implode(',', $placeholders) . ')',
            $params
        );
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int) $row['id']] = $row;
        }
        $ordered = [];
        foreach ($ids as $id) {
            if (isset($indexed[$id])) {
                $ordered[] = $indexed[$id];
            }
        }
        return $ordered;
    }

    /** @param array<int,array<string,mixed>> $questions */
    private function sendQuestion(string $chatId, int $tgUserId, array $questions, int $index): void
    {
        $question = $questions[$index] ?? null;
        if ($question === null) {
            return;
        }
        $lines = ['<b>سؤال ' . ($index + 1) . ' من ' . count($questions) . '</b>', ''];
        $lines[] = $this->escape((string) $question['question_text']);
        $lines[] = '';
        $keyboard = ['inline_keyboard' => []];
        $rowButtons = [];
        foreach (['a' => 'أ', 'b' => 'ب', 'c' => 'ج', 'd' => 'د'] as $letter => $label) {
            if ((string) ($question['option_' . $letter] ?? '') !== '') {
                $lines[] = $label . ') ' . $this->escape((string) $question['option_' . $letter]);
                $rowButtons[] = ['text' => $label, 'callback_data' => 'ans:' . (int) $question['id'] . ':' . $letter];
            }
        }
        if ($rowButtons !== []) {
            $keyboard['inline_keyboard'][] = $rowButtons;
        }
        $state = $this->state($tgUserId);
        if ($state['state'] === 'practice') {
            $keyboard['inline_keyboard'][] = [['text' => 'إيقاف التدريب', 'callback_data' => 'stop']];
        }
        $this->reply($chatId, $tgUserId, implode("\n", $lines), $keyboard);
    }

    private function recordPractice(int $tgUserId, int $questionId, string $letter, bool $isRight): void
    {
        try {
            $user = $this->linkedUser($tgUserId);
            if ($user === null) {
                return;
            }
            $this->db->insert('telegram_messages', [
                'telegram_user_id' => $tgUserId,
                'user_id'          => (int) $user['id'],
                'direction'        => 'in',
                'message_type'     => 'practice_answer',
                'content'          => $letter,
                'meta'             => json_encode(['question_id' => $questionId, 'correct' => $isRight], JSON_UNESCAPED_UNICODE),
            ]);
            StatisticsService::recordAnswer((int) $user['id'], $questionId, $isRight);
        } catch (\Throwable $e) {
            Logger::warning('telegram.practice: ' . $e->getMessage());
        }
    }

    private function normalizeLetter(string $text): ?string
    {
        $text = trim(preg_replace('/\s+/u', '', $text) ?? '');
        $map = [
            'a' => 'a', 'b' => 'b', 'c' => 'c', 'd' => 'd',
            'أ' => 'a', 'ا' => 'a', 'ب' => 'b', 'ج' => 'c', 'د' => 'd',
            '1' => 'a', '2' => 'b', '3' => 'c', '4' => 'd',
        ];
        if (isset($map[$text])) {
            return $map[$text];
        }
        $lower = strtolower($text);
        return isset($map[$lower]) ? $map[$lower] : null;
    }

    private function letterAr(string $letter): string
    {
        return ['a' => 'أ', 'b' => 'ب', 'c' => 'ج', 'd' => 'د'][$letter] ?? $letter;
    }

    private function reply(string $chatId, int $tgUserId, string $text, ?array $keyboard = null): void
    {
        $text = $this->truncate($text);
        $ok = false;
        if (TelegramService::enabled()) {
            $options = $keyboard === null ? [] : ['reply_markup' => $keyboard];
            $result = TelegramService::instance()->sendMessage($chatId, $text, $options);
            $ok = (bool) ($result['ok'] ?? false);
        }
        if (strlen($text) < self::MAX_MESSAGE + 200) {
            try {
                $this->db->insert('telegram_messages', [
                    'telegram_user_id' => $tgUserId > 0 ? $tgUserId : null,
                    'direction'        => 'out',
                    'message_type'     => 'text',
                    'content'          => $text,
                    'meta'             => json_encode(['sent' => $ok], JSON_UNESCAPED_UNICODE),
                ]);
            } catch (\Throwable) {
                // السجل ليس حرجاً
            }
        }
    }

    private function truncate(string $text): string
    {
        return mb_strlen($text) > self::MAX_MESSAGE ? mb_substr($text, 0, self::MAX_MESSAGE - 20) . "\n…" : $text;
    }

    private function escape(string $text): string
    {
        return TelegramService::escape($text);
    }
}
