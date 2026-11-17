<?php

namespace App\Models\Library;

use App\Models\User;
use App\Policies\Library\BookshelfPolicy;
use Database\Factories\Library\BookshelfFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A shelf a member names freely; the library of a member is the set of their
 * bookshelves, ordered by position (1..n).
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['name'])]
#[UseFactory(BookshelfFactory::class)]
#[UsePolicy(BookshelfPolicy::class)]
class Bookshelf extends Model
{
    /** @use HasFactory<BookshelfFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}
