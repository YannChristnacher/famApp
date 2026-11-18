<?php

namespace App\Services\Garage;

use App\Models\Garage\Brand;
use App\Models\Garage\Tool;
use App\Models\Garage\ToolPhoto;
use App\Models\Garage\ToolShelf;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Tools of a member. New photo files are written before the transaction and
 * removed if it fails; the files of removed photos are deleted only once the
 * transaction is committed. The brand is resolved first, outside the
 * transaction: a brand is shared and never deleted.
 */
class ToolService
{
    public function __construct(
        private readonly ToolPhotoService $photos,
        private readonly BrandService $brands,
    ) {}

    /**
     * The tools of the member, by name, optionally on one of their shelves
     * and/or of one brand, with what the list shows.
     *
     * @return Collection<int, Tool>
     */
    public function listFor(User $user, ?ToolShelf $shelf = null, ?Brand $brand = null): Collection
    {
        return $user->tools()
            ->when($shelf, fn ($query, ToolShelf $shelf) => $query->whereRelation('shelves', 'tool_shelves.id', $shelf->id))
            ->when($brand, fn ($query, Brand $brand) => $query->where('brand_id', $brand->id))
            ->with(['brand', 'shelves', 'thumbnail'])
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * The brands of the tools of the member, by name, for the filter.
     *
     * @return Collection<int, Brand>
     */
    public function brandsOf(User $user): Collection
    {
        return Brand::query()
            ->whereHas('tools', fn ($query) => $query->where('user_id', $user->id))
            ->orderBy('normalized_name')
            ->orderBy('id')
            ->get();
    }

    public function create(User $user, ToolInput $input): Tool
    {
        $brand = $this->brands->resolve($input->brand);
        $photos = $this->storeNewPhotos($input->photos);

        return $this->withNewPhotos($photos, fn () => DB::transaction(function () use ($user, $input, $brand, $photos) {
            $tool = $user->tools()->make(['name' => $input->name]);
            $this->saveDetails($tool, $input, $brand);
            $this->savePhotos($tool, $photos, collect());

            return $tool;
        }));
    }

    public function update(Tool $tool, ToolInput $input): Tool
    {
        $brand = $this->brands->resolve($input->brand);
        $photos = $this->storeNewPhotos($input->photos);

        $removedPaths = $this->withNewPhotos($photos, fn () => DB::transaction(function () use ($tool, $input, $brand, $photos) {
            // Two saves of the same tool are written one after the other.
            Tool::query()->whereKey($tool->id)->lockForUpdate()->firstOrFail();
            $tool->fill(['name' => $input->name]);
            $this->saveDetails($tool, $input, $brand);

            return $this->savePhotos($tool, $photos, $tool->photos()->get()->keyBy('id')->toBase());
        }));

        $this->photos->delete($removedPaths);

        return $tool;
    }

    public function delete(Tool $tool): void
    {
        $paths = $tool->photos()->pluck('path')->all();
        $tool->delete();
        $this->photos->delete($paths);
    }

    private function saveDetails(Tool $tool, ToolInput $input, ?Brand $brand): void
    {
        $tool->brand()->associate($brand);
        $tool->save();
        $tool->shelves()->sync($input->shelfIds);

        $tool->accessories()->delete();
        $tool->accessories()->createMany(array_map(
            fn (string $name, int $index) => ['name' => $name, 'position' => $index + 1],
            $input->accessories,
            array_keys($input->accessories),
        ));
    }

    /**
     * Applies the order of the photos: kept ones (ids of this tool only),
     * new ones (stored paths), and deletes the rows of the others.
     *
     * @param  list<int|string>  $photos  Ids of kept photos and paths of new files, in order.
     * @param  SupportCollection<int, ToolPhoto>  $existing  Photos of the tool, by id.
     * @return list<string> Paths of the removed photos, to delete after the commit.
     */
    private function savePhotos(Tool $tool, array $photos, SupportCollection $existing): array
    {
        $ordered = array_values(array_filter($photos, fn (int|string $photo) => is_string($photo) || $existing->has($photo)));
        $keptIds = array_filter($ordered, is_int(...));
        $removed = $existing->reject(fn (ToolPhoto $photo) => in_array($photo->id, $keptIds, true));
        $tool->photos()->whereKey($removed->pluck('id')->all())->delete();

        foreach ($ordered as $index => $photo) {
            if (is_string($photo)) {
                $tool->photos()->create(['path' => $photo, 'position' => $index + 1]);
            } else {
                $existing[$photo]->update(['position' => $index + 1]);
            }
        }

        return $removed->pluck('path')->values()->all();
    }

    /**
     * Stores the new files; kept photo ids are returned as they are.
     *
     * @param  list<int|UploadedFile>  $photos
     * @return list<int|string>
     */
    private function storeNewPhotos(array $photos): array
    {
        $stored = [];
        try {
            foreach ($photos as $photo) {
                $stored[] = $photo instanceof UploadedFile ? $this->photos->store($photo) : $photo;
            }
        } catch (Throwable $exception) {
            $this->photos->delete(array_filter($stored, is_string(...)));

            throw $exception;
        }

        return $stored;
    }

    /**
     * Removes the freshly stored files if the write fails.
     *
     * @template T
     *
     * @param  list<int|string>  $photos
     * @param  callable(): T  $write
     * @return T
     */
    private function withNewPhotos(array $photos, callable $write): mixed
    {
        try {
            return $write();
        } catch (Throwable $exception) {
            $this->photos->delete(array_filter($photos, is_string(...)));

            throw $exception;
        }
    }
}
