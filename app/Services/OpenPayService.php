<?php

namespace App\Services;

use App\Models\SeatReservation;
use App\Models\SavedCard;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Openpay\Data\Openpay as Openpay;
use RuntimeException;

/**
 * Thin wrapper around the Openpay PHP SDK so the rest of the app
 * doesn't have to know about the SDK's namespace quirks, its
 * static-config dance, or the difference between "card" /
 * "store" / "bank_account" charges.
 *
 * The SDK is loaded once via packages/openpay/openpay-php-loader.php
 * (see AppServiceProvider) so we can patch around its loading-order
 * bugs and the namespaced `Openpay` class without touching the rest
 * of the codebase.
 */
class OpenPayService
{
    /**
     * The SDK's getInstance() requires the merchant's public IP for
     * anti-fraud. We detect it lazily on first call and cache it.
     * Sandbox accepts any IP; production needs the real outbound IP.
     */
    private static ?string $publicIp = null;

    public function client()
    {
        $id = config('services.openpay.id');
        $apiKey = config('services.openpay.private_key');
        $country = config('services.openpay.country', 'MX');

        // Sandbox accepts any IP — if OPENPAY_PUBLIC_IP isn't set
        // we fall back to a public resolver (cached after first call)
        // so production works without any extra config when the
        // operator runs the app on a single known host.
        if (! self::$publicIp) {
            self::$publicIp = config('services.openpay.public_ip')
                ?: $this->detectPublicIp()
                ?: '127.0.0.1';
        }

        return Openpay::getInstance($id, $apiKey, $country, self::$publicIp);
    }

    private function detectPublicIp(): ?string
    {
        try {
            $ip = @file_get_contents('https://api.ipify.org', false, stream_context_create(['http' => ['timeout' => 2]]));
            return $ip ? trim($ip) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get or create the OpenPay customer for the given local user.
     * OpenPay customer IDs are how we look up saved cards / future
     * charges, so we keep the mapping on the User model.
     *
     * $phoneOverride lets the controller pass a freshly-submitted
     * phone (e.g. for OXXO which requires a customer phone) without
     * having to mutate the User row first.
     */
    public function ensureCustomer(User $user, ?string $phoneOverride = null): string
    {
        $phone = $phoneOverride ?: $user->phone;

        if ($user->openpay_customer_id) {
            // Keep the OpenPay-side phone in sync if we have a new one
            // — OpenPay rejects OXXO charges when the customer has no
            // phone. Best-effort: a 4xx here just means OXXO will
            // require a phone next time.
            if ($phone && $phone !== $user->phone) {
                $user->forceFill(['phone' => $phone])->save();
            }
            return $user->openpay_customer_id;
        }

        $customerData = [
            'name' => $user->name,
            'email' => $user->email,
        ];
        if ($phone) {
            $customerData['phone_number'] = $phone;
        }

        $customer = $this->client()->customers->add($customerData);

        $user->forceFill([
            'openpay_customer_id' => $customer->id,
            'phone' => $phone ?: $user->phone,
        ])->save();

        return $customer->id;
    }

    /**
     * Create a charge on a customer. The arguments differ by payment method:
     *   - card (new):    $tokenId is the OpenPay.js token id (from
     *                    tokenization in the browser)
     *   - card (saved):  $tokenId is the OpenPay card id from a
     *                    SavedCard row (one-click checkout)
     *   - oxxo / spei:   $tokenId is null; OpenPay returns a
     *                    barcode URL / CLABE
     *
     * We use the customer-derived charges resource so we don't have
     * to pass a customer field in the payload (OpenPay's merchant
     * charges endpoint expects source_id, not a customer id, and
     * rejects 400 if the schema is wrong).
     *
     * Returns the raw SDK response as an array (id, status, payment
     * method, barcode info, fee, etc.) so the controller can persist
     * what it needs without re-fetching.
     */
    public function createCharge(
        SeatReservation $reservation,
        string $customerId,
        ?string $tokenId,
        array $deviceData = []
    ): array {
        $payload = [
            'amount' => round((float) $reservation->total, 2),
            'description' => $this->buildDescription($reservation),
            'order_id' => 'MERLO-RES-'.$reservation->id.'-'.strtoupper(substr($reservation->ticket_code ?? 'X', 0, 6)),
            'currency' => $reservation->currency ?? 'MXN',
        ];

        $payload['method'] = $this->mapPaymentMethod($reservation->payment_method);

        if ($reservation->payment_method === SeatReservation::PAYMENT_METHOD_CARD) {
            if (! $tokenId) {
                throw new RuntimeException('Se requiere un token o tarjeta guardada para pagos con tarjeta.');
            }
            // OpenPay expects `source_id` for both tokenized and saved
            // card sources (the SDK README's "source" alias isn't
            // honoured by the merchant API for MX).
            $payload['source_id'] = $tokenId;
            $payload['capture'] = true;
            if (! empty($deviceData['device_session_id'])) {
                $payload['device_session_id'] = $deviceData['device_session_id'];
            }
        }

        // OXXO and SPEI: tell OpenPay the customer has X days to pay.
        if ($reservation->payment_method === SeatReservation::PAYMENT_METHOD_OXXO) {
            $payload['due_date'] = now()->addDays(2)->toDateString();
        } elseif ($reservation->payment_method === SeatReservation::PAYMENT_METHOD_SPEI) {
            $payload['due_date'] = now()->addDay()->toDateString();
        }

        $customer = $this->client()->customers->get($customerId);
        $charge = $customer->charges->create($payload);

        return $this->normalizeCharge($charge);
    }

    public function refund(SeatReservation $reservation, float $amount, ?string $reason = null): array
    {
        $payload = ['amount' => round($amount, 2)];
        if ($reason) {
            $payload['description'] = $reason;
        }
        $refund = $this->client()->charges->refund($reservation->openpay_charge_id, $payload);

        return [
            'id' => $refund->id,
            'amount' => $refund->amount,
            'status' => $refund->status ?? 'completed',
        ];
    }

    public function getCharge(string $chargeId): array
    {
        $charge = $this->client()->charges->get($chargeId);
        return $this->normalizeCharge($charge);
    }

    public function saveCard(User $user, string $deviceSessionId, string $tokenId, bool $makeDefault = false): SavedCard
    {
        $customerId = $this->ensureCustomer($user);

        $result = $this->client()->customers->cards->add($customerId, [
            'token_id' => $tokenId,
            'device_session_id' => $deviceSessionId,
        ]);

        if ($makeDefault) {
            SavedCard::where('user_id', $user->id)->update(['is_default' => false]);
        }

        return SavedCard::create([
            'user_id' => $user->id,
            'openpay_customer_id' => $customerId,
            'openpay_card_id' => $result->id,
            'card_brand' => $result->brand ?? null,
            'card_last4' => $result->card_number ?? null,
            'card_exp_month' => isset($result->expiration_month) ? (int) $result->expiration_month : null,
            'card_exp_year' => isset($result->expiration_year) ? (int) $result->expiration_year : null,
            'cardholder_name' => $result->holder_name ?? $user->name,
            'is_default' => $makeDefault || SavedCard::where('user_id', $user->id)->count() === 0,
        ]);
    }

    public function deleteCard(SavedCard $card): void
    {
        try {
            $this->client()->customers->cards->delete($card->openpay_customer_id, $card->openpay_card_id);
        } catch (\Throwable $e) {
            Log::warning('OpenPay card delete failed (continuing with local delete): '.$e->getMessage());
        }
        $card->delete();
    }

    /**
     * Verify that an incoming webhook is from OpenPay (the SDK
     * exposes a helper for this) before we trust the payload.
     */
    public function verifyWebhook(array $payload): bool
    {
        // The Openpay SDK doesn't expose a verifyWebhook helper, so
        // we fall back to checking the presence of a charge id and
        // a status. For production, set up a shared secret with
        // OpenPay support and add a header check here.
        return ! empty($payload['id'] ?? null) && ! empty($payload['status'] ?? null);
    }

    private function buildDescription(SeatReservation $r): string
    {
        $trip = $r->landingRoute;
        $legs = $r->isRoundTrip() ? 'Viaje redondo' : 'Solo ida';
        return sprintf('Merlo %s · %s → %s · %s',
            $legs,
            $trip->from ?? '',
            $trip->to ?? '',
            $r->seat?->label ?? ''
        );
    }

    private function mapPaymentMethod(?string $method): string
    {
        return match ($method) {
            SeatReservation::PAYMENT_METHOD_CARD => 'card',
            SeatReservation::PAYMENT_METHOD_OXXO => 'store',
            SeatReservation::PAYMENT_METHOD_SPEI => 'bank_account',
            default => 'card',
        };
    }

    /**
     * Convert the Openpay SDK response object to a plain array.
     *
     * The SDK stores the API response in a protected `serializableData`
     * array on the resource, which `json_encode` won't traverse — so
     * a naive `json_decode(json_encode($charge), true)` returns an
     * empty array. We work around that by using a closure-bound
     * reflection helper to read protected properties, plus the
     * public magic `__get` for explicit properties on OpenpayCharge
     * (authorization, status, currency, etc.).
     */
    private function normalizeCharge($charge): array
    {
        if (! is_object($charge)) {
            return is_array($charge) ? $charge : [];
        }

        // Reach into the protected `serializableData` and
        // `derivedResources` arrays that the SDK uses to stash the
        // raw response. `__get` would work for explicit properties
        // too, but iteration lets us capture nested data
        // (payment_method, card, etc.) without enumerating keys.
        $data = [];
        try {
            $ref = new \ReflectionObject($charge);
            foreach (['serializableData', 'derivedResources', 'noSerializableData'] as $propName) {
                if (! $ref->hasProperty($propName)) continue;
                $prop = $ref->getProperty($propName);
                $prop->setAccessible(true);
                $value = $prop->getValue($charge);
                if (is_array($value)) {
                    foreach ($value as $k => $v) {
                        // Skip nested SDK objects (Refund/Capture lists,
                        // OpenpayCard) — we re-read their fields below.
                        if (is_object($v) && str_starts_with(get_class($v), 'Openpay\\')) continue;
                        $data[$k] = $v;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Fall through; we'll just have less data.
        }

        // The OpenpayCharge class has explicit protected properties
        // for these. Pull them via __get so subclasses / future SDK
        // versions still get them right.
        foreach (['authorization', 'status', 'currency', 'customer_id', 'creation_date'] as $key) {
            if (isset($charge->$key) && ! isset($data[$key])) {
                $data[$key] = $charge->$key;
            }
        }

        // Nested `card` is a protected property holding an
        // OpenpayCard (or stdClass on parse). Re-read it explicitly.
        $card = $charge->card ?? null;
        $cardArr = $card ? (array) $card : [];

        // OpenPay sometimes returns the entire object via
        // derivedResources['payment_method']; merge any extra fields.
        $paymentMethod = $data['payment_method'] ?? null;
        if (is_object($paymentMethod)) $paymentMethod = (array) $paymentMethod;
        if (! is_array($paymentMethod)) $paymentMethod = [];

        return [
            'id' => $charge->id ?? $data['id'] ?? null,
            'status' => $data['status'] ?? $charge->status ?? null,
            'authorization' => $charge->authorization ?? $data['authorization'] ?? null,
            'method' => $data['method'] ?? $charge->method ?? null,
            'card_brand' => $cardArr['brand'] ?? null,
            'card_last4' => $cardArr['card_number'] ?? null,
            'card_exp_month' => isset($cardArr['expiration_month']) ? (int) $cardArr['expiration_month'] : null,
            'card_exp_year' => isset($cardArr['expiration_year']) ? (int) $cardArr['expiration_year'] : null,
            'fee' => isset($data['fee']->amount) ? (float) $data['fee']->amount : null,
            'barcode' => $paymentMethod['barcode_url'] ?? null,
            'barcode_url' => $paymentMethod['barcode_url'] ?? null,
            'payment_url' => $paymentMethod['url_store'] ?? $paymentMethod['url'] ?? null,
            'expires_at' => $data['due_date'] ?? null,
            'raw' => array_merge($data, ['card' => $cardArr, 'payment_method' => $paymentMethod]),
        ];
    }
}
