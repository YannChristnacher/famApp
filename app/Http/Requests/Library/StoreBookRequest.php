<?php

namespace App\Http\Requests\Library;

class StoreBookRequest extends BookRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }
}
