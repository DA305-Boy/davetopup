<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SellerVerification;
use App\Models\Store;

class SellerVerificationController extends Controller
{
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $verification = SellerVerification::findOrFail($id);

        if ($verification->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json(['verification' => $verification]);
    }

    // Upload document file and get encrypted URL
    public function uploadDocument(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120', // 5MB max
            'document_type' => 'required|in:passport,ssn,drivers_license,national_id',
        ]);

        $user = $request->user();
        $service = new \App\Services\DocumentStorageService();

        try {
            $encryptedPath = $service->storeDocument(
                $request->file('file'),
                $request->input('document_type'),
                $user->id
            );

            return response()->json([
                'document_url' => $encryptedPath,
                'message' => 'Document uploaded successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'store_id' => 'required|exists:stores,id',
            'document_type' => 'required|in:passport,ssn,drivers_license,national_id',
            'document_url' => 'required|string', // Encrypted URL from uploadDocument
            'verified_name' => 'required|string|max:255',
            'verified_country' => 'required|string|size:2',
        ]);

        $user = $request->user();
        $store = Store::findOrFail($request->input('store_id'));

        if ($store->owner_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $verification = SellerVerification::create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'document_type' => $request->input('document_type'),
            'document_url' => $request->input('document_url'),
            'verified_name' => $request->input('verified_name'),
            'verified_country' => $request->input('verified_country'),
            'verification_status' => 'pending',
        ]);

        return response()->json(['verification' => $verification], 201);
    }

    // Admin approve
    public function approve(Request $request, $id)
    {
        $this->authorize('create', SellerVerification::class);
        $verification = SellerVerification::findOrFail($id);

        $verification->update([
            'verification_status' => 'approved',
            'verified_at' => now(),
        ]);

        // Send email notification
        \Mail::to($verification->user->email)
            ->queue(new \App\Mail\VerificationApprovedNotification($verification));

        // Create in-app notification
        \App\Models\Notification::create([
            'user_id' => $verification->user_id,
            'type' => 'verification_approved',
            'body' => 'Your identity verification has been approved!',
            'data' => ['verification_id' => $id]
        ]);

        return response()->json(['verification' => $verification]);
    }

    // Admin reject
    public function reject(Request $request, $id)
    {
        $this->authorize('create', SellerVerification::class);
        $verification = SellerVerification::findOrFail($id);

        $verification->update([
            'verification_status' => 'rejected',
            'rejection_reason' => $request->input('reason', 'Document rejected'),
        ]);

        // Send email notification
        \Mail::to($verification->user->email)
            ->queue(new \App\Mail\VerificationRejectedNotification($verification));

        // Create in-app notification
        \App\Models\Notification::create([
            'user_id' => $verification->user_id,
            'type' => 'verification_rejected',
            'body' => 'Your identity verification was rejected. Reason: ' . $verification->rejection_reason,
            'data' => ['verification_id' => $id]
        ]);

        return response()->json(['verification' => $verification]);
    }
}
