<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wire seat_reservations up to OpenPay so each successful
     * purchase (or pending store/SPEI charge) is fully traceable.
     *
     * Highlights:
     *   - openpay_charge_id is unique so we never accidentally
     *     double-import the same charge on a webhook retry.
     *   - subtotal/tax/total mirror the IVA-16% breakdown the
     *     client sees (admin needs the same numbers to reconcile).
     *   - The *_verifiable timestamps (refunded_at, chargeback_at)
     *     are separate from the check-in verifications (outbound /
     *     return) so the two workflows don't collide.
     */
    public function up(): void
    {
        Schema::table('seat_reservations', function (Blueprint $table) {
            $table->string('payment_method', 20)->nullable()->after('ticket_code'); // card | oxxo | spei
            $table->string('payment_status', 20)->default('pending')->after('payment_method'); // pending | completed | failed | refunded | chargeback
            $table->decimal('subtotal', 10, 2)->nullable()->after('payment_status');
            $table->decimal('tax', 10, 2)->nullable()->after('subtotal');
            $table->decimal('total', 10, 2)->nullable()->after('tax');
            $table->string('currency', 3)->default('MXN')->after('total');
            $table->decimal('openpay_fee', 10, 2)->nullable()->after('currency');

            $table->string('openpay_customer_id', 64)->nullable()->after('openpay_fee');
            $table->string('openpay_charge_id', 64)->nullable()->unique()->after('openpay_customer_id');
            $table->string('openpay_authorization', 64)->nullable()->after('openpay_charge_id');
            $table->string('openpay_payment_method', 20)->nullable()->after('openpay_authorization');
            $table->string('openpay_card_brand', 20)->nullable()->after('openpay_payment_method');
            $table->string('openpay_card_last4', 4)->nullable()->after('openpay_card_brand');
            $table->unsignedTinyInteger('openpay_card_exp_month')->nullable()->after('openpay_card_last4');
            $table->unsignedSmallInteger('openpay_card_exp_year')->nullable()->after('openpay_card_exp_month');
            $table->text('openpay_barcode_url')->nullable()->after('openpay_card_exp_year');
            $table->text('openpay_barcode')->nullable()->after('openpay_barcode_url');
            $table->string('openpay_payment_url')->nullable()->after('openpay_barcode');
            $table->timestamp('openpay_expires_at')->nullable()->after('openpay_payment_url');
            $table->timestamp('paid_at')->nullable()->after('openpay_expires_at');
            $table->text('openpay_raw_response')->nullable()->after('paid_at');

            $table->string('customer_phone', 30)->nullable()->after('openpay_raw_response');
            $table->json('billing_address')->nullable()->after('customer_phone');
            $table->string('ip_address', 45)->nullable()->after('billing_address');
            $table->string('device_fingerprint', 128)->nullable()->after('ip_address');

            $table->timestamp('refunded_at')->nullable()->after('device_fingerprint');
            $table->decimal('refund_amount', 10, 2)->nullable()->after('refunded_at');
            $table->text('refund_reason')->nullable()->after('refund_amount');
            $table->timestamp('chargeback_at')->nullable()->after('refund_reason');

            $table->index('payment_status');
            $table->index(['openpay_customer_id', 'payment_method']);
        });
    }

    public function down(): void
    {
        Schema::table('seat_reservations', function (Blueprint $table) {
            $table->dropIndex(['openpay_customer_id', 'payment_method']);
            $table->dropIndex(['payment_status']);
            $table->dropUnique(['openpay_charge_id']);
            $table->dropColumn([
                'payment_method', 'payment_status',
                'subtotal', 'tax', 'total', 'currency', 'openpay_fee',
                'openpay_customer_id', 'openpay_charge_id', 'openpay_authorization',
                'openpay_payment_method', 'openpay_card_brand', 'openpay_card_last4',
                'openpay_card_exp_month', 'openpay_card_exp_year',
                'openpay_barcode_url', 'openpay_barcode', 'openpay_payment_url',
                'openpay_expires_at', 'paid_at', 'openpay_raw_response',
                'customer_phone', 'billing_address', 'ip_address', 'device_fingerprint',
                'refunded_at', 'refund_amount', 'refund_reason', 'chargeback_at',
            ]);
        });
    }
};
