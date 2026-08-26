<?php

namespace App\Enums;

/**
 * Inbound provider webhook processing state (SRS §53). The ingestion
 * controller writes pending rows; the queue consumer moves them forward.
 */
enum WebhookProcessingStatus: string
{
    case Pending = 'pending';
    case Processed = 'processed';
    case Failed = 'failed';
}
