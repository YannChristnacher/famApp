<?php

namespace App\Http\Requests\Garage;

use App\Models\Garage\ToolShelf;

class StoreToolShelfRequest extends ToolShelfNameRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function renamedShelf(): ?ToolShelf
    {
        return null;
    }
}
