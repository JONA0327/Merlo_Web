<?php

namespace App\Models;

use App\Mail\InternalAccountWelcomeMail;
use App\Mail\PasswordResetCodeMail;
use App\Mail\VerificationCodeMail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_CLIENTE = 'cliente';

    public const ROLE_SUPERADMIN = 'superadmin';

    public const ROLE_ADMINISTRACION = 'administracion';

    public const ROLE_PAQUETERIA = 'paqueteria';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'email_verified_at',
        'openpay_customer_id',
        'phone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'verification_code',
        'password_reset_code',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'verification_code_expires_at' => 'datetime',
            'password_reset_code_expires_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN;
    }

    public function canAccessPaqueteria(): bool
    {
        return in_array($this->role, [self::ROLE_SUPERADMIN, self::ROLE_PAQUETERIA], true);
    }

    public function seatReservations(): HasMany
    {
        return $this->hasMany(SeatReservation::class);
    }

    /**
     * Cards the customer has saved (tokenized via OpenPay) for
     * one-click checkout. Sensitive PAN/CVV never touches the
     * database — we only store the OpenPay card id plus the
     * non-sensitive metadata OpenPay exposes (brand, last 4, exp).
     */
    public function savedCards(): HasMany
    {
        return $this->hasMany(SavedCard::class);
    }

    public function defaultSavedCard(): ?SavedCard
    {
        return $this->savedCards()->orderByDesc('is_default')->orderByDesc('id')->first();
    }

    /**
     * Generate a fresh 6-digit code, store its hash with a 15-minute
     * expiry, and email it to the user. Replaces Breeze's default
     * signed-link verification notification.
     */
    public function sendEmailVerificationNotification(): void
    {
        $code = (string) random_int(100000, 999999);

        $this->forceFill([
            'verification_code' => Hash::make($code),
            'verification_code_expires_at' => now()->addMinutes(15),
        ])->save();

        Mail::to($this->email)->send(new VerificationCodeMail($this, $code));
    }

    /**
     * A code is only ever checked against the hash stored on THIS user,
     * so a code captured from another account's email can never verify
     * a different account.
     */
    public function verificationCodeIsValid(string $code): bool
    {
        return $this->verification_code
            && $this->verification_code_expires_at
            && now()->lt($this->verification_code_expires_at)
            && Hash::check($code, $this->verification_code);
    }

    public function clearVerificationCode(): void
    {
        $this->forceFill([
            'verification_code' => null,
            'verification_code_expires_at' => null,
        ])->save();
    }

    /**
     * Generate a fresh 6-digit password reset code, store its hash with
     * a 15-minute expiry, and email it to the user.
     */
    public function sendPasswordResetCode(): void
    {
        $code = (string) random_int(100000, 999999);

        $this->forceFill([
            'password_reset_code' => Hash::make($code),
            'password_reset_code_expires_at' => now()->addMinutes(15),
        ])->save();

        Mail::to($this->email)->send(new PasswordResetCodeMail($this, $code));
    }

    /**
     * Internal accounts (Administración / Paquetería) are created by a
     * superadmin with a random unusable password, so they reuse the same
     * code-based reset flow as "forgot my password" to set a real one.
     */
    public function sendInternalAccountWelcome(): void
    {
        $code = (string) random_int(100000, 999999);

        $this->forceFill([
            'password_reset_code' => Hash::make($code),
            'password_reset_code_expires_at' => now()->addMinutes(15),
        ])->save();

        Mail::to($this->email)->send(new InternalAccountWelcomeMail($this, $code));
    }

    /**
     * Checked only against the hash stored on THIS user, so a code
     * captured for one account can never reset another account's password.
     */
    public function passwordResetCodeIsValid(string $code): bool
    {
        return $this->password_reset_code
            && $this->password_reset_code_expires_at
            && now()->lt($this->password_reset_code_expires_at)
            && Hash::check($code, $this->password_reset_code);
    }

    public function clearPasswordResetCode(): void
    {
        $this->forceFill([
            'password_reset_code' => null,
            'password_reset_code_expires_at' => null,
        ])->save();
    }
}
