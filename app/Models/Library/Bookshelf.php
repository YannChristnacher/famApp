<?php

namespace App\Models\Library;

use App\Models\User;
use App\Policies\Library\BookshelfPolicy;
use Database\Factories\Library\BookshelfFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A shelf a member names freely; the library of a member is the set of their
 * bookshelves, ordered by position (1..n, unique per member).
 *
 * The name is unique per member once normalized (normalized_name, kept in
 * sync by the name mutator): case and repeated spaces are ignored, accents
 * count.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $normalized_name
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

    public static function normalizeName(string $name): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($name)));
    }

    /**
     * @return Attribute<string, string>
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => ['name' => $value, 'normalized_name' => self::normalizeName($value)],
        );
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Deleting the bookshelf removes these links, never the books.
     *
     * @return BelongsToMany<Book, $this>
     */
    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class)->orderBy('title')->orderBy('books.id');
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}
