<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('stripe_transfer_id')->nullable();
            $table->string('status')->default('pending')->comment('pending, processing, completed, failed');
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();
            $table->index(['store_id', 'status']);
            $table->index('created_at');
        });

        Schema::create('user_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('balance')->default(0);
            $table->integer('lifetime_earned')->default(0);
            $table->integer('lifetime_redeemed')->default(0);
            $table->timestamps();
            $table->unique('user_id');
        });

        Schema::create('points_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('points_change');
            $table->string('reason')->comment('purchase, reward_redemption, admin_adjustment, bonus');
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('reward_redemption_id')->nullable()->constrained('reward_redemptions')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type')->comment('verification_approved, verification_rejected, payout_completed');
            $table->text('body');
            $table->json('data')->nullable();
            $table->string('email_sent_at')->nullable();
            $table->boolean('read')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('points_ledger');
        Schema::dropIfExists('user_points');
        Schema::dropIfExists('payouts');
    }
};
