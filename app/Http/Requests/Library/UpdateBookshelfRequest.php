<?php

namespace App\Http\Requests\Library;

use App\Models\Library\Bookshelf;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

class UpdateBookshelfRequest extends BookshelfNameRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->renamedBookshelf());
    }

    protected function renamedBookshelf(): Bookshelf
    {
        /** @var Bookshelf */
        return $this->route('bookshelf');
    }
}
