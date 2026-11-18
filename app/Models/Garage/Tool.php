<?php

namespace App\Models\Garage;

use App\Models\User;
use App\Policies\Garage\ToolPolicy;
use Database\Factories\Garage\ToolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A tool of a member, on zero, one or several of their tool shelves, with an
 * optional shared brand, ordered photos (the first one is the thumbnail) and
 * ordered accessories. The owner and the brand are never mass assigned.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property int|null $brand_id
 * @property string|null $comment Free text of the owner, shown on the page of the tool.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Brand|null $brand
 * @property-read Collection<int, ToolShelf> $shelves
 * @property-read Collection<int, ToolPhoto> $photos
 * @property-read Collection<int, ToolAccessory> $accessories
 * @property-read ToolPhoto|null $thumbnail
 */
#[Fillable(['name', 'comment'])]
#[UseFactory(ToolFactory::class)]
#[UsePolicy(ToolPolicy::class)]
class Tool extends Model
{
    /** @use HasFactory<ToolFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'brand_id' => 'integer',
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
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsToMany<ToolShelf, $this>
     */
    public function shelves(): BelongsToMany
    {
        return $this->belongsToMany(ToolShelf::class)->orderBy('position')->orderBy('tool_shelves.id');
    }

    /**
     * @return HasMany<ToolPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(ToolPhoto::class)->orderBy('position')->orderBy('id');
    }

    /**
     * The first photo, eager loadable in one query for a whole list.
     *
     * @return HasOne<ToolPhoto, $this>
     */
    public function thumbnail(): HasOne
    {
        return $this->hasOne(ToolPhoto::class)->oldestOfMany('position');
    }

    /**
     * @return HasMany<ToolAccessory, $this>
     */
    public function accessories(): HasMany
    {
        return $this->hasMany(ToolAccessory::class)->orderBy('position')->orderBy('id');
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}
