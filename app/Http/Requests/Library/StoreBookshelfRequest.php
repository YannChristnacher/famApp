<?php

namespace App\Http\Requests\Library;

use App\Models\Library\Bookshelf;

class StoreBookshelfRequest extends BookshelfNameRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function renamedBookshelf(): ?Bookshelf
    {
        return null;
    }
}
