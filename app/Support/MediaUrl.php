<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class MediaUrl
{
    /**
     * Resolve a public URL for stored media.
     *
     * Object-storage disks (R2/S3) serve through temporary signed URLs; local
     * disks only support plain URLs. Any adapter without signing support
     * falls back to its base URL so payloads stay renderable everywhere.
     */
    public static function for(string $disk, string $path): string
    {
        $filesystem = Storage::disk($disk);

        try {
            if (Str::contains(config("filesystems.disks.$disk.driver"), 's3')) {
                return $filesystem->temporaryUrl(
                    $path,
                    now()->addMinutes((int) config('catalog.signed_url_minutes', 15)),
                );
            }

            return $filesystem->url($path);
        } catch (RuntimeException) {
            return $filesystem->url($path);
        }
    }
}
