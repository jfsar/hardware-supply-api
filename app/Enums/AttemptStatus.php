<?php

namespace App\Enums;

/**
 * Outcome of a single gateway attempt row (FR-PAY-005). Attempts are
 * append-only history; only the newest row may be non-terminal.
 */
enum AttemptStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    /**
     * Whether this attempt can no longer change.
     */
    public function isTerminal(): bool
    {
        return $this !== self::Pending;
    }
}
