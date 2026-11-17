<?php

namespace App\Models\Garage;

use App\Models\User;
use App\Policies\Garage\ToolShelfPolicy;
use App\Support\NormalizedName;
use Database\Factories\Garage\ToolShelfFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A shelf of the garage of a member, named freely ("Électroportatif",
 * "Manuel"…); the garage of a member is the set of their tool shelves,
 * ordered by position (1..n, unique per member). A tool will be able to sit
 * on several shelves (F10, pivot tool_tool_shelf).
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
#[UseFactory(ToolShelfFactory::class)]
#[UsePolicy(ToolShelfPolicy::class)]
class ToolShelf extends Model
{
    /** @use HasFactory<ToolShelfFactory> */
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
        return NormalizedName::of($name);
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

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}
