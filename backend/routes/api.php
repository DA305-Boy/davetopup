<?php
// Backend setup guide - routes/api.php for Laravel

Route::middleware('api')->prefix('api')->group(function () {
    // ===== Auth (Sanctum for sellers/admins) =====
    Route::post('/auth/login', 'SanctumAuthController@login');
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', 'SanctumAuthController@me');
        Route::post('/auth/logout', 'SanctumAuthController@logout');
    });

    // ===== Order Endpoints =====
    Route::post('/orders', 'OrderController@store');
    Route::get('/orders/{id}', 'OrderController@show');
    Route::get('/orders/{id}/status', 'OrderController@getStatus');

    // ===== Payment Endpoints =====
    Route::post('/payments/card', 'PaymentController@processCard');
    Route::post('/payments/paypal', 'PaymentController@initiatePayPal');
    Route::post('/payments/paypal/capture', 'PaymentController@capturePayPal');
    Route::post('/payments/binance', 'PaymentController@initiateBinance');
    Route::post('/payments/voucher', 'PaymentController@redeemVoucher');

    // ===== Webhook Endpoints =====
    Route::post('/webhooks/stripe', 'WebhookController@handleStripe')->withoutMiddleware('auth:api');
    Route::post('/webhooks/paypal', 'WebhookController@handlePayPal')->withoutMiddleware('auth:api');
    Route::post('/webhooks/binance', 'WebhookController@handleBinance')->withoutMiddleware('auth:api');

    // ===== Admin Endpoints =====
    Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
        Route::get('/orders', 'Admin\OrderController@index');
        Route::post('/orders/{id}/refund', 'Admin\OrderController@refund');
        Route::post('/orders/{id}/mark-delivered', 'Admin\OrderController@markDelivered');
        Route::get('/webhooks/logs', 'Admin\WebhookController@logs');
        Route::post('/vouchers/validate', 'Admin\VoucherController@validate');
    });
    
    // ===== Store Endpoints (multi-tenant) =====
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/stores', 'StoreController@store');
        Route::put('/stores/{id}', 'StoreController@update');
    });
    Route::get('/stores/{slug}', 'StoreController@show');
    
    // Chat endpoints
    Route::get('/chat/messages', 'ChatController@index');
    Route::post('/chat/messages', 'ChatController@store');

    // ===== Payment Methods (seller wallets) =====
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/payment-methods', 'PaymentMethodController@index');
        Route::post('/payment-methods', 'PaymentMethodController@store');
        Route::post('/payment-methods/{id}/set-default', 'PaymentMethodController@setDefault');
        Route::delete('/payment-methods/{id}', 'PaymentMethodController@destroy');
    });

    // ===== Seller Verification (ID, SSN, Drivers License, Passport) =====
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/verifications/{id}', 'SellerVerificationController@show');
        Route::post('/verifications/upload-document', 'SellerVerificationController@uploadDocument');
        Route::post('/verifications', 'SellerVerificationController@store');
    });
    Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {
        Route::post('/verifications/{id}/approve', 'SellerVerificationController@approve');
        Route::post('/verifications/{id}/reject', 'SellerVerificationController@reject');
    });

    // ===== Payment Links (shareable checkout links) =====
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/payment-links', 'PaymentLinkController@index');
        Route::post('/payment-links', 'PaymentLinkController@store');
    });
    Route::get('/payment-links/public/{token}', 'PaymentLinkController@publicShow');

    // Rewards
    Route::get('/rewards', 'RewardController@index');
    Route::post('/rewards', 'RewardController@store')->middleware('auth:sanctum');
    Route::post('/rewards/{id}/redeem', 'RewardController@redeem')->middleware('auth:sanctum');

    // Admin area
    Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
        Route::get('/orders', 'AdminController@orders');
        Route::post('/orders/{id}/refund', 'AdminController@refund');
        Route::post('/orders/{id}/mark-delivered', 'AdminController@markDelivered');
    });

    // Store cashout (owner-initiated)
    Route::post('/stores/{id}/cashout', 'StoreController@cashout')->middleware('auth:sanctum');
    Route::get('/stores/{id}/payout-history', 'StoreController@payoutHistory')->middleware('auth:sanctum');

    // Admin dashboard endpoints
    Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
        Route::get('/overview', 'Admin\DashboardController@overview');
        Route::get('/orders', 'Admin\DashboardController@orders');
        // Admin: create seller (user + store)
        Route::post('/stores', 'Admin\\StoreController@create');
        Route::get('/sellers', 'Admin\DashboardController@sellers');
        Route::get('/payouts', 'Admin\DashboardController@payouts');
        Route::get('/verifications', 'Admin\DashboardController@verifications');
    });
    
    // ===== OAuth/Web Auth (redirects handled via web routes) =====
    // Note: Socialite web routes are typically defined in routes/web.php. Kept here as comments for clarity.
    // Route::get('/auth/redirect/{provider}', 'AuthController@redirectToProvider');
    // Route::get('/auth/callback/{provider}', 'AuthController@handleProviderCallback');
});
