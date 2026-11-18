<?php

namespace App\Services\Garage;

use Illuminate\Http\UploadedFile;

/**
 * What the member saves for a tool (validated): its name, the typed brand,
 * their shelves, the accessories and the photos, each list in its order, and
 * the comment (null without comment). A photo is the id of a photo of this
 * tool to keep, or a new file.
 */
final readonly class ToolInput
{
    /**
     * @param  list<int>  $shelfIds
     * @param  list<string>  $accessories
     * @param  list<int|UploadedFile>  $photos
     */
    public function __construct(
        public string $name,
        public ?string $brand = null,
        public array $shelfIds = [],
        public array $accessories = [],
        public array $photos = [],
        public ?string $comment = null,
    ) {}
}
