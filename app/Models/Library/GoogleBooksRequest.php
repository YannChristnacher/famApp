<?php

namespace App\Models\Library;

use App\Enums\Library\GoogleBooksRequestStatus;
use App\Enums\Library\GoogleBooksRequestType;
use App\Models\User;
use Database\Factories\Library\GoogleBooksRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One call to Google Books, written by GoogleBooksJournal. Kept for
 * services.google_books.journal_retention_days, then pruned (model:prune).
 *
 * @property int $id
 * @property int|null $user_id
 * @property GoogleBooksRequestType $type
 * @property string $query
 * @property GoogleBooksRequestStatus $status
 * @property int|null $http_status
 * @property int $duration_ms
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
#[Fillable(['type', 'query', 'status', 'http_status', 'duration_ms'])]
#[UseFactory(GoogleBooksRequestFactory::class)]
class GoogleBooksRequest extends Model
{
    /** @use HasFactory<GoogleBooksRequestFactory> */
    use HasFactory, MassPrunable;

    /**
     * Calls older than the retention period.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $retentionDays = config()->integer('services.google_books.journal_retention_days');

        return static::query()->where('created_at', '<', now()->subDays($retentionDays));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'type' => GoogleBooksRequestType::class,
            'status' => GoogleBooksRequestStatus::class,
            'http_status' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
