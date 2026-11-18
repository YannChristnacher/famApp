<?php

namespace App\Http\Requests\Garage;

use App\Models\Garage\Brand;
use App\Models\Garage\ToolShelf;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Optional filters of the list of tools, combinable: `?shelf=<id>`, one of
 * the tool shelves of the member, and `?brand=<id>`, the brand of one of
 * their tools; anything else answers 404.
 */
class ListToolsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }

    public function shelfFilter(): ?ToolShelf
    {
        $id = $this->filterId('shelf', ToolShelf::class);

        return $id === null ? null : $this->member()->toolShelves()->whereKey($id)->firstOrFail();
    }

    public function brandFilter(): ?Brand
    {
        $id = $this->filterId('brand', Brand::class);
        if ($id === null) {
            return null;
        }

        $member = $this->member();

        return Brand::query()
            ->whereKey($id)
            ->whereHas('tools', fn ($query) => $query->where('user_id', $member->id))
            ->firstOrFail();
    }

    /**
     * @param  class-string<ToolShelf|Brand>  $model
     */
    private function filterId(string $parameter, string $model): ?int
    {
        if (! $this->has($parameter)) {
            return null;
        }

        $id = $this->query($parameter);
        if (! is_string($id) || ! ctype_digit($id)) {
            throw (new ModelNotFoundException)->setModel($model);
        }

        return (int) $id;
    }

    private function member(): User
    {
        /** @var User */
        return $this->user();
    }
}
