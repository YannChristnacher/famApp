<?php

namespace App\Models\Garage;

use App\Support\NormalizedName;
use Database\Factories\Garage\BrandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A brand of tools (Bosch, Makita…), shared by the whole family: the first
 * member who types it chooses its case. Unique once normalized
 * (normalized_name, kept in sync by the name mutator); never deleted.
 *
 * @property int $id
 * @property string $name
 * @property string $normalized_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name'])]
#[UseFactory(BrandFactory::class)]
class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory;

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
     * @return HasMany<Tool, $this>
     */
    public function tools(): HasMany
    {
        return $this->hasMany(Tool::class);
    }
}
