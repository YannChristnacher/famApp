<?php

namespace App\Http\Requests\Garage;

use App\Models\Garage\ToolShelf;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

class UpdateToolShelfRequest extends ToolShelfNameRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->renamedShelf());
    }

    protected function renamedShelf(): ToolShelf
    {
        /** @var ToolShelf */
        return $this->route('shelf');
    }
}
