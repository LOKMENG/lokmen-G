<?php
declare(strict_types=1);

namespace App;

/**
 * مُحقّق مدخلات بسيط مع رسائل عربية.
 * مثال:
 *   $v = Validator::make($_POST, ['full_name' => 'required|min:3', 'email' => 'required|email|unique:users,email']);
 *   if (!$v->validate()) { $errors = $v->errors(); }
 */
final class Validator
{
    /** @var array<string,mixed> */
    private array $data;
    /** @var array<string,string> */
    private array $rules;
    /** @var array<string,string> */
    private array $labels;
    /** @var array<string,string> */
    private array $errors = [];

    private const LABELS = [
        'full_name'   => 'الاسم الكامل',
        'phone'       => 'رقم الجوال',
        'email'       => 'البريد الإلكتروني',
        'password'    => 'كلمة المرور',
        'password_confirmation' => 'تأكيد كلمة المرور',
        'current_password' => 'كلمة المرور الحالية',
        'title'       => 'العنوان',
        'question_text' => 'نص السؤال',
        'correct_answer' => 'الإجابة الصحيحة',
        'category_id' => 'المجال',
        'track_id'    => 'المسار',
        'reference_number' => 'رقم العملية',
    ];

    /** @param array<string,mixed> $data @param array<string,string> $rules @param array<string,string> $labels */
    public function __construct(array $data, array $rules, array $labels = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->labels = $labels;
    }

    /** @param array<string,mixed> $data @param array<string,string> $rules */
    public static function make(array $data, array $rules, array $labels = []): self
    {
        return new self($data, $rules, $labels);
    }

    public function validate(): bool
    {
        foreach ($this->rules as $field => $ruleString) {
            $value = $this->value($field);
            $rules = explode('|', $ruleString);
            $required = in_array('required', $rules, true);

            if (!$required && ($value === null || $value === '')) {
                continue;
            }
            foreach ($rules as $rule) {
                [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);
                $this->apply($field, $value, $name, $parameter);
                if (isset($this->errors[$field])) {
                    break; // خطأ واحد لكل حقل
                }
            }
        }
        return $this->errors === [];
    }

    /** اختصار مقروء لعكس validate() — تحقق من صحة البيانات */
    public function fails(): bool
    {
        return !$this->validate();
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): string
    {
        return (string) (reset($this->errors) ?: '');
    }

    /** @return array<string,mixed> القيم المُنقّاة */
    public function validated(): array
    {
        $out = [];
        foreach (array_keys($this->rules) as $field) {
            if (array_key_exists($field, $this->data)) {
                $out[$field] = is_string($this->data[$field]) ? trim($this->data[$field]) : $this->data[$field];
            }
        }
        return $out;
    }

    private function value(string $field): mixed
    {
        $value = $this->data[$field] ?? null;
        return is_string($value) ? trim($value) : $value;
    }

    private function label(string $field): string
    {
        return $this->labels[$field] ?? self::LABELS[$field] ?? $field;
    }

    private function fail(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }

    private function apply(string $field, mixed $value, string $rule, ?string $parameter): void
    {
        $label = $this->label($field);
        $stringValue = is_string($value) ? $value : (string) $value;

        switch ($rule) {
            case 'required':
                if ($value === null || $stringValue === '') {
                    $this->fail($field, $label . ' مطلوب.');
                }
                break;

            case 'email':
                if (filter_var($stringValue, FILTER_VALIDATE_EMAIL) === false) {
                    $this->fail($field, $label . ' غير صالح.');
                }
                break;

            case 'phone_sa':
                $digits = preg_replace('/\D+/', '', Str::normalizeDigits($stringValue)) ?? '';
                if (!preg_match('/^(9665\d{8}|05\d{8}|5\d{8})$/', $digits)) {
                    $this->fail($field, $label . ' يجب أن يكون رقم جوال سعودي صحيح (مثال: 05XXXXXXXX).');
                }
                break;

            case 'min':
                $min = (int) $parameter;
                if (mb_strlen($stringValue, 'UTF-8') < $min) {
                    $this->fail($field, sprintf('%s يجب ألا يقل عن %d حرفاً.', $label, $min));
                }
                break;

            case 'max':
                $max = (int) $parameter;
                if (mb_strlen($stringValue, 'UTF-8') > $max) {
                    $this->fail($field, sprintf('%s يجب ألا يزيد عن %d حرفاً.', $label, $max));
                }
                break;

            case 'password':
                $min = (int) config('security.password_min_length', 8);
                if (mb_strlen($stringValue, 'UTF-8') < $min) {
                    $this->fail($field, sprintf('%s يجب أن تكون %d أحرف على الأقل.', $label, $min));
                } elseif (preg_match('/[A-Za-z]/', $stringValue) !== 1 || preg_match('/\d/', $stringValue) !== 1) {
                    $this->fail($field, $label . ' يجب أن تحتوي على حرف ورقم.');
                }
                break;

            case 'confirmed':
                if ($stringValue !== (string) ($this->data[$field . '_confirmation'] ?? '')) {
                    $this->fail($field, $label . ' غير متطابق مع التأكيد.');
                }
                break;

            case 'same':
                if ($stringValue !== (string) ($this->data[(string) $parameter] ?? '')) {
                    $this->fail($field, $label . ' غير متطابق.');
                }
                break;

            case 'numeric':
                if (!is_numeric($stringValue)) {
                    $this->fail($field, $label . ' يجب أن يكون رقماً.');
                }
                break;

            case 'integer':
                if (filter_var($stringValue, FILTER_VALIDATE_INT) === false) {
                    $this->fail($field, $label . ' يجب أن يكون رقماً صحيحاً.');
                }
                break;

            case 'date':
                if (strtotime($stringValue) === false) {
                    $this->fail($field, $label . ' تاريخ غير صالح.');
                }
                break;

            case 'in':
                $allowed = explode(',', (string) $parameter);
                if (!in_array($stringValue, $allowed, true)) {
                    $this->fail($field, $label . ' قيمة غير مسموحة.');
                }
                break;

            case 'unique':
                [$table, $column, $ignoreId] = array_pad(explode(',', (string) $parameter), 3, null);
                if ($table !== null && $column !== null) {
                    $sql = sprintf('SELECT COUNT(*) FROM `%s` WHERE `%s` = :value', $table, $column);
                    $params = ['value' => $stringValue];
                    if ($ignoreId !== null && ctype_digit($ignoreId)) {
                        $sql .= ' AND id <> :ignore';
                        $params['ignore'] = (int) $ignoreId;
                    }
                    $count = (int) Database::instance()->value($sql, $params, 0);
                    if ($count > 0) {
                        $this->fail($field, $label . ' مستخدم مسبقاً.');
                    }
                }
                break;

            case 'exists':
                [$table, $column] = array_pad(explode(',', (string) $parameter), 2, null);
                if ($table !== null && $column !== null) {
                    $count = (int) Database::instance()->value(
                        sprintf('SELECT COUNT(*) FROM `%s` WHERE `%s` = :value', $table, $column),
                        ['value' => $stringValue],
                        0
                    );
                    if ($count === 0) {
                        $this->fail($field, $label . ' غير موجود.');
                    }
                }
                break;
        }
    }
}
