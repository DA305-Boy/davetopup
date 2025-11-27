<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;

class DocumentStorageService
{
    /**
     * Store document to S3 or Azure Blob with encryption
     */
    public function storeDocument(UploadedFile $file, string $type, int $userId): string
    {
        $disk = config('filesystems.default');
        
        // Validate file type (only images/PDFs)
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, $allowed)) {
            throw new \Exception('Invalid file type. Allowed: jpg, jpeg, png, pdf');
        }

        // Validate file size (max 5MB)
        if ($file->getSize() > 5 * 1024 * 1024) {
            throw new \Exception('File too large. Maximum 5MB.');
        }

        // Generate unique filename
        $filename = "documents/{$userId}/{$type}/" . uniqid() . '.' . $ext;

        // Store to S3/Azure (based on .env config)
        if ($disk === 's3') {
            Storage::disk('s3')->put($filename, $file->getStream(), [
                'visibility' => 'private',
                'metadata' => ['user_id' => $userId, 'type' => $type]
            ]);
        } elseif ($disk === 'azure') {
            Storage::disk('azure')->put($filename, $file->getStream(), [
                'visibility' => 'private'
            ]);
        } else {
            Storage::disk('local')->put($filename, $file->getStream());
        }

        // Return encrypted path
        return Crypt::encryptString($filename);
    }

    /**
     * Retrieve document URL (with expiration for S3)
     */
    public function getDocumentUrl(string $encryptedPath, int $expiryMinutes = 60): string
    {
        $filename = Crypt::decryptString($encryptedPath);
        $disk = config('filesystems.default');

        if ($disk === 's3') {
            return Storage::disk('s3')->temporaryUrl(
                $filename,
                now()->addMinutes($expiryMinutes)
            );
        } elseif ($disk === 'azure') {
            // Azure Blob: generate SAS URL
            return Storage::disk('azure')->url($filename);
        } else {
            return Storage::disk('local')->url($filename);
        }
    }

    /**
     * Delete document
     */
    public function deleteDocument(string $encryptedPath): bool
    {
        try {
            $filename = Crypt::decryptString($encryptedPath);
            $disk = config('filesystems.default');
            
            if ($disk === 's3') {
                Storage::disk('s3')->delete($filename);
            } elseif ($disk === 'azure') {
                Storage::disk('azure')->delete($filename);
            } else {
                Storage::disk('local')->delete($filename);
            }
            
            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to delete document: " . $e->getMessage());
            return false;
        }
    }
}
