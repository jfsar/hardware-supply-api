<?php

namespace App\Enums;

/**
 * Lifecycle of an asynchronous report export (Phase 8). The job moves
 * pending → processing → completed|failed; expiration is represented by
 * the row's expires_at + the purge sweep deleting the stored file.
 */
enum ReportExportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * Whether the export is still being worked and may not be downloaded.
     */
    public function isTerminal(): bool
    {
        return $this !== self::Pending && $this !== self::Processing;
    }
}
