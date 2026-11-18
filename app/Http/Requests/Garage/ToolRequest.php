<?php

namespace App\Http\Requests\Garage;

use App\Models\User;
use App\Services\Garage\ToolInput;
use App\Support\ImageUploadRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\File;

/**
 * Fields of a tool, shared by the creation and the edition: name, brand typed
 * freely (matched on the shared brands by the server), optional comment
 * (trimmed, null when blank), shelves of the member,
 * accessories (a list of names) and photos: an ordered list whose items are
 * either {file} (a new image) or {id} (a photo to keep, allowed only by the
 * edition, for a photo of the edited tool).
 */
abstract class ToolRequest extends FormRequest
{
    public const NAME_MAX_LENGTH = 255;

    public const BRAND_MAX_LENGTH = 100;

    public const COMMENT_MAX_LENGTH = 2000;

    public const ACCESSORY_MAX_LENGTH = 100;

    public const MAX_ACCESSORIES = 30;

    public const MAX_PHOTOS = 8;

    /**
     * Rule of photos.*.id: a photo of the edited tool, or nothing at creation.
     *
     * @return array<int, string|Exists>
     */
    abstract protected function keptPhotoRules(): array;

    /**
     * @return array<string, array<int, string|ValidationRule|Exists|File|Dimensions>>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        return [
            'name' => ['required', 'string', 'max:'.self::NAME_MAX_LENGTH],
            'brand' => ['nullable', 'string', 'max:'.self::BRAND_MAX_LENGTH],
            'comment' => ['nullable', 'string', 'max:'.self::COMMENT_MAX_LENGTH],
            'shelf_ids' => ['array'],
            'shelf_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('tool_shelves', 'id')->where('user_id', $user->id),
            ],
            'accessories' => ['array', 'list', 'max:'.self::MAX_ACCESSORIES],
            'accessories.*' => ['required', 'string', 'max:'.self::ACCESSORY_MAX_LENGTH],
            'photos' => ['array', 'list', 'max:'.self::MAX_PHOTOS],
            'photos.*' => ['required', 'array:id,file'],
            'photos.*.id' => ['required_without:photos.*.file', 'prohibits:photos.*.file', ...$this->keptPhotoRules()],
            'photos.*.file' => ['required_without:photos.*.id', 'nullable', ...ImageUploadRules::rules()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'shelf_ids.*.exists' => 'Une des étagères choisies n’existe pas.',
            'photos.max' => 'Un outil ne peut pas avoir plus de '.self::MAX_PHOTOS.' photos.',
            'accessories.max' => 'Un outil ne peut pas avoir plus de '.self::MAX_ACCESSORIES.' accessoires.',
            'photos.*.file.dimensions' => 'La photo ne doit pas dépasser '.ImageUploadRules::MAX_PIXELS_PER_SIDE.' pixels de côté.',
            'photos.*.id.exists' => 'Une des photos n’appartient pas à cet outil.',
            'photos.*.id.prohibits' => 'Une photo est soit une photo existante, soit un nouveau fichier.',
            'photos.*.id.distinct' => 'Une photo apparaît deux fois.',
        ];
    }

    public function toolInput(): ToolInput
    {
        /** @var array{name: string, brand?: string|null, comment?: string|null} $validated */
        $validated = $this->safe()->only(['name', 'brand', 'comment']);
        /** @var list<string> $accessories */
        $accessories = $this->validated('accessories', []);
        /** @var list<int|string> $shelfIds */
        $shelfIds = $this->validated('shelf_ids', []);

        return new ToolInput(
            name: $validated['name'],
            brand: $validated['brand'] ?? null,
            shelfIds: array_map(intval(...), $shelfIds),
            accessories: $accessories,
            photos: $this->photos(),
            comment: $validated['comment'] ?? null,
        );
    }

    /**
     * @return list<int|UploadedFile>
     */
    private function photos(): array
    {
        /** @var list<array{id?: int|string|null}> $photos */
        $photos = $this->validated('photos', []);
        $ordered = [];
        foreach ($photos as $index => $photo) {
            $file = $this->file("photos.{$index}.file");
            $ordered[] = $file instanceof UploadedFile ? $file : (int) $photo['id'];
        }

        return $ordered;
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'brand'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }

        if (is_string($this->input('comment'))) {
            $comment = trim($this->input('comment'));
            $this->merge(['comment' => $comment === '' ? null : $comment]);
        }

        $accessories = $this->input('accessories');
        if (is_array($accessories)) {
            $this->merge(['accessories' => array_map(
                fn (mixed $accessory) => is_string($accessory) ? trim($accessory) : $accessory,
                $accessories,
            )]);
        }
    }
}
