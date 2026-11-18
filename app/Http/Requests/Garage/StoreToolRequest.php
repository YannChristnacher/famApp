<?php

namespace App\Http\Requests\Garage;

use Illuminate\Validation\Rules\Exists;

class StoreToolRequest extends ToolRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * A new tool has no photo to keep.
     *
     * @return array<int, string|Exists>
     */
    protected function keptPhotoRules(): array
    {
        return ['prohibited'];
    }
}
