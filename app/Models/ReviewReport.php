<?php

namespace App\Models;

use App\Enums\ReviewReportStatus;
use Database\Factories\ReviewReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'review_id', 'user_id', 'reason_code', 'details', 'status',
    'resolved_by_user_id', 'resolved_at',
])]
class ReviewReport extends Model
{
    /** @use HasFactory<ReviewReportFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReviewReportStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * The reviewed review.
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    /**
     * The customer filing the report.
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The moderator who resolved the report.
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}
