<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Orders table
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 100)->unique()->index();
            $table->string('email');
            $table->string('phone', 20)->nullable();
            $table->string('player_uid', 50);
            $table->string('player_nickname', 50);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax', 10, 2);
            $table->decimal('total', 10, 2);
            $table->enum('status', [
                'pending',
                'payment_confirmed',
                'delivered',
                'failed',
                'refunded'
            ])->default('pending')->index();
            $table->uuid('idempotency_key')->nullable()->unique();
            $table->timestamps();

            $table->index(['email', 'created_at']);
            $table->index(['player_uid', 'created_at']);
            $table->index('status');
        });

        // Order items table
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->string('product_id', 100);
            $table->string('name', 255);
            $table->string('game', 100);
            $table->decimal('price', 10, 2);
            $table->integer('quantity')->default(1);
            $table->timestamps();

            $table->index('order_id');
            $table->index('product_id');
        });

        // Transactions table
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->string('transaction_id', 255)->nullable()->unique();
            $table->enum('payment_method', [
                'card',
                'paypal',
                'binance',
                'voucher'
            ]);
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3)->default('USD');
            $table->enum('status', [
                'pending',
                'completed',
                'failed',
                'requires_3d_secure',
                'requires_verification'
            ])->default('pending')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('transaction_id');
            $table->index(['status', 'created_at']);
            $table->index(['payment_method', 'created_at']);
        });

        // Vouchers table
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->index();
            $table->decimal('amount', 10, 2);
            $table->integer('used_count')->default(0);
            $table->integer('max_uses')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_reusable')->default(false);
            $table->timestamps();

            $table->index('is_active');
            $table->index(['is_active', 'expires_at']);
        });

        // Webhook logs table
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50)->index();
            $table->string('event_type', 100);
            $table->json('payload');
            $table->integer('response_status')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['provider', 'created_at']);
            $table->index(['event_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
