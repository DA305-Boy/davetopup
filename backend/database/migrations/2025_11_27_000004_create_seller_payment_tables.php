<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type')->comment('card, bank, paypal, binance, crypto');
            $table->string('provider')->nullable()->comment('stripe, paypal, binance_pay');
            $table->string('external_id')->nullable()->comment('provider token or account id');
            $table->json('metadata')->nullable()->comment('card last4, bank account type, etc');
            $table->boolean('is_default')->default(false);
            $table->boolean('verified')->default(false);
            $table->timestamps();
        });

        Schema::create('seller_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('document_type')->comment('passport, ssn, drivers_license, national_id');
            $table->string('document_url')->nullable();
            $table->string('verification_status')->default('pending')->comment('pending, approved, rejected');
            $table->text('rejection_reason')->nullable();
            $table->string('verified_name')->nullable();
            $table->string('verified_country')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('payment_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('token', 64)->unique()->comment('public share token');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->json('items')->nullable()->comment('pre-defined items, if any');
            $table->string('status')->default('active')->comment('active, archived');
            $table->integer('usage_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->decimal('balance', 15, 2)->default(0);
            $table->decimal('pending_payout', 15, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('payment_links');
        Schema::dropIfExists('seller_verifications');
        Schema::dropIfExists('payment_methods');
    }
};
