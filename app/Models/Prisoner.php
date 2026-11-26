<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class Prisoner extends Model
{
    public const CONDITIONS = [
        'ordinary' => 'Обычные',
        'eased' => 'Облегчённые',
        'strict' => 'Строгие',
        // Ст. 136 ч. 3, ст. 138 ч. 3 УИК РК.
        'privileged' => 'Льготные',
    ];

    public const SPENDING_MODES = [
        'unlimited' => 'Без лимита',
        'norm' => 'Норма учреждения (МРП)',
        'custom' => 'Своя сумма',
    ];

    public const SECURITY_QUESTIONS = [
        'grandmother' => 'Имя вашей бабушки по маме',
        'birth_place' => 'Город или село, где вы родились',
        'first_school' => 'Номер или название вашей первой школы',
    ];

    public const RECOVERY_METHODS = [
        'birth_date' => 'Дата рождения',
        'question' => 'Контрольный вопрос',
    ];

    public const MAX_FAILED_LOGINS = 5;

    /** Восстановление пароля: 3 неудачные попытки — блокировка на сутки. */
    public const MAX_FAILED_RECOVERY = 3;

    public const RECOVERY_LOCK_HOURS = 24;

    public const LOCK_MINUTES = 15;

    protected $fillable = ['iin', 'full_name', 'squad', 'institution_id', 'condition', 'spending_mode', 'monthly_limit_minor', 'parcel_code'];

    protected $hidden = ['iin', 'access_hash', 'parcel_code', 'balance_minor', 'monthly_limit_minor', 'security_answer_hash', 'pin_hash', 'family_code_hash', 'family_code_encrypted', 'birth_date'];

    protected $casts = [
        'balance_minor' => 'integer', 'monthly_limit_minor' => 'integer', 'failed_logins' => 'integer',
        'locked_until' => 'datetime', 'last_login_at' => 'datetime', 'registered_at' => 'datetime', 'consented_at' => 'datetime',
        'family_code_set_at' => 'datetime', 'limit_started_on' => 'date', 'family_code_encrypted' => 'encrypted',
        'birth_date' => 'date', 'recovery_failed' => 'integer', 'recovery_locked_until' => 'datetime',
        'reviewed_at' => 'datetime', 'recovery_window_at' => 'datetime', 'recovered_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Карточки от сотрудника (админка, импорт) сразу проверены. Самостоятельная регистрация
        // на планшете явно ставит reviewed_at = null — такая карточка ждёт проверки.
        static::creating(function (self $prisoner) {
            if (! array_key_exists('reviewed_at', $prisoner->getAttributes())) {
                $prisoner->reviewed_at = now();
            }
        });
    }

    /** ФИО для поиска: нижний регистр, ё→е, одинарные пробелы. */
    public static function normalizeName(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', str_replace('ё', 'е', mb_strtolower($name))));
    }

    /** Каждое введённое слово — начало какого-то слова в ФИО («иван петр» найдёт «Петров Иван»). */
    public function matchesName(string $query): bool
    {
        $words = explode(' ', self::normalizeName($this->full_name));
        foreach (explode(' ', self::normalizeName($query)) as $part) {
            if ($part !== '' && ! collect($words)->contains(fn ($word) => str_starts_with($word, $part))) {
                return false;
            }
        }

        return true;
    }

    public static function normalizeSquad(?string $squad): ?string
    {
        $squad = trim((string) preg_replace('/\s+/u', ' ', (string) $squad));

        return $squad === '' ? null : mb_substr($squad, 0, 50);
    }

    public function authMethod(): ?string
    {
        return $this->pin_hash !== null ? 'pin' : ($this->hasSecurityQuestion() ? 'question' : null);
    }

    public function isRegistered(): bool
    {
        return $this->registered_at !== null && $this->authMethod() !== null;
    }

    /** Пароль — 12 цифр (рекомендуем ИИН) либо ответ на один из трёх вопросов. Второй способ стирается. */
    public function setCredentials(string $method, ?string $pin, ?string $question, ?string $answer): void
    {
        $this->pin_hash = $method === 'pin' ? Hash::make((string) $pin) : null;
        $this->setSecurityAnswer($method === 'question' ? $question : null, $method === 'question' ? $answer : null);
    }

    /** Новый пароль — 6 цифр. Контрольный вопрос при этом сохраняется: он нужен для восстановления. */
    public function setPin(string $pin): void
    {
        $this->pin_hash = Hash::make($pin);
    }


    /** Карточка, заведённая самим человеком на планшете, ждёт проверки сотрудником. */
    public function needsReview(): bool
    {
        return $this->reviewed_at === null;
    }

    /** Зарегистрированная карточка в учреждении по точным ФИО и дате рождения. */
    /** Все зарегистрированные в учреждении с таким ФИО (без учёта регистра и лишних пробелов). */
    public static function registeredByName(int $institutionId, string $fullName): \Illuminate\Support\Collection
    {
        $name = self::normalizeName($fullName);

        return self::with('institution')->where('institution_id', $institutionId)->whereNotNull('registered_at')->get()
            ->filter(fn (self $prisoner) => self::normalizeName($prisoner->full_name) === $name)->values();
    }

    public static function findRegistered(int $institutionId, string $fullName, string $birthDate): ?self
    {
        $name = self::normalizeName($fullName);
        $matches = self::with('institution')->where('institution_id', $institutionId)->whereDate('birth_date', $birthDate)
            ->whereNotNull('registered_at')->get()
            ->filter(fn (self $prisoner) => self::normalizeName($prisoner->full_name) === $name);

        return $matches->count() === 1 ? $matches->first() : null;
    }

    public function isRecoveryLocked(): bool
    {
        return $this->recovery_locked_until !== null && $this->recovery_locked_until->isFuture();
    }

    /** Способ восстановления, который реально можно использовать. */
    public function recoveryMethod(): ?string
    {
        if (($this->recovery_method ?? 'birth_date') === 'question' && $this->hasSecurityQuestion()) {
            return 'question';
        }

        return $this->birth_date ? 'birth_date' : ($this->hasSecurityQuestion() ? 'question' : null);
    }

    public function checkRecovery(string $value): bool
    {
        return match ($this->recoveryMethod()) {
            'birth_date' => $this->birth_date?->format('Y-m-d') === trim($value),
            'question' => $this->checkSecurityAnswer($value),
            default => false,
        };
    }

    public function checkSecret(string $secret): bool
    {
        return match ($this->authMethod()) {
            // 6 цифр — новый пароль; 12 цифр — у тех, кто зарегистрировался раньше.
            'pin' => preg_match('/^([0-9]{6}|[0-9]{12})$/', $secret) === 1 && Hash::check($secret, $this->pin_hash),
            'question' => $this->checkSecurityAnswer($secret),
            default => false,
        };
    }

    public function resetRegistration(): void
    {
        $this->setCredentials('none', null, null, null);
        $this->forceFill([
            'registered_at' => null, 'consented_at' => null, 'consent_version' => null, 'failed_logins' => 0, 'locked_until' => null,
            'family_code_hash' => null, 'family_code_encrypted' => null, 'family_code_set_at' => null,
            'birth_date' => null, 'recovery_method' => 'birth_date', 'recovery_failed' => 0, 'recovery_locked_until' => null,
            'recovery_window_at' => null,
        ]);
    }

    public static function familyCodeHash(string $code): string
    {
        return hash_hmac('sha256', 'family|'.preg_replace('/\D/', '', $code), (string) config('app.key'));
    }

    /**
     * Код для родственников — 6 цифр, легко продиктовать. Родственник вводит его вместе с фамилией.
     * Код можно посмотреть повторно в настройках; новый код отменяет старый.
     */
    public function issueFamilyCode(): string
    {
        do {
            $code = (string) random_int(100000, 999999);
        } while (self::where('family_code_hash', self::familyCodeHash($code))->exists());
        $this->forceFill([
            'family_code_hash' => self::familyCodeHash($code), 'family_code_encrypted' => $code, 'family_code_set_at' => now(),
        ])->save();

        return self::formatFamilyCode($code);
    }

    public function familyCode(): ?string
    {
        return $this->family_code_hash && $this->family_code_encrypted ? self::formatFamilyCode($this->family_code_encrypted) : null;
    }

    public static function formatFamilyCode(string $code): string
    {
        return substr($code, 0, 3).' '.substr($code, 3);
    }

    public function disableFamilyCode(): void
    {
        $this->forceFill(['family_code_hash' => null, 'family_code_encrypted' => null, 'family_code_set_at' => null])->save();
    }

    /** Фамилия совпадает с одним из слов ФИО (регистр и ё не важны). */
    public function hasSurname(string $surname): bool
    {
        $surname = self::normalizeName($surname);

        return mb_strlen($surname) >= 2 && in_array($surname, explode(' ', self::normalizeName($this->full_name)), true);
    }

    public static function normalizeAnswer(string $answer): string
    {
        $answer = mb_strtolower(trim($answer));
        $answer = str_replace('ё', 'е', $answer);

        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $answer));
    }

    public function setSecurityAnswer(?string $question, ?string $answer): void
    {
        $this->security_question = $question;
        $this->security_answer_hash = $question && $answer !== null ? Hash::make(self::normalizeAnswer($answer)) : null;
    }

    public function checkSecurityAnswer(string $answer): bool
    {
        return $this->security_answer_hash !== null && Hash::check(self::normalizeAnswer($answer), $this->security_answer_hash);
    }

    public function hasSecurityQuestion(): bool
    {
        return $this->security_question !== null && $this->security_answer_hash !== null;
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function maskedIin(): string
    {
        return $this->iin ? '••••••••'.substr($this->iin, -4) : 'не указан';
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function transfers()
    {
        return $this->hasMany(PrisonerTransfer::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function limitPeriods()
    {
        return $this->hasMany(PrisonerLimitPeriod::class);
    }

    public function depositRequests()
    {
        return $this->hasMany(DepositRequest::class);
    }
}
