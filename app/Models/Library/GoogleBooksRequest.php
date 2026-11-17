<?php

namespace App\Models\Library;

use App\Enums\Library\GoogleBooksRequestStatus;
use App\Enums\Library\GoogleBooksRequestType;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One call to Google Books, written by GoogleBooksJournal.
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
class GoogleBooksRequest extends Model
{
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
