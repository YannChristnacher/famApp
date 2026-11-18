<?php

namespace App\Models\Library;

use App\Models\User;
use App\Policies\Library\BookPolicy;
use Database\Factories\Library\BookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A book of a member, on zero, one or several of their bookshelves. The same
 * ISBN may appear several times, even for one member.
 *
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string $author
 * @property string|null $isbn
 * @property string|null $comment Free text of the owner, shown on the page of the book.
 * @property string|null $cover_path Path on the public disk.
 * @property array<string, mixed>|null $google_books_data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, Bookshelf> $bookshelves
 */
#[Fillable(['title', 'author', 'isbn', 'comment'])]
#[UseFactory(BookFactory::class)]
#[UsePolicy(BookPolicy::class)]
class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'google_books_data' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Bookshelf, $this>
     */
    public function bookshelves(): BelongsToMany
    {
        return $this->belongsToMany(Bookshelf::class)->orderBy('position')->orderBy('bookshelves.id');
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}
