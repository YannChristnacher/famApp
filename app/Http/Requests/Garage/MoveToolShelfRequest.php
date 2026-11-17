<?php

namespace App\Http\Requests\Garage;

use App\Enums\Garage\ToolShelfMoveDirection;
use App\Models\Garage\ToolShelf;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class MoveToolShelfRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('move', $this->shelf());
    }

    /**
     * @return array<string, array<int, string|ValidationRule|Enum>>
     */
    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::enum(ToolShelfMoveDirection::class)],
        ];
    }

    public function shelf(): ToolShelf
    {
        /** @var ToolShelf */
        return $this->route('shelf');
    }

    public function direction(): ToolShelfMoveDirection
    {
        return ToolShelfMoveDirection::from($this->string('direction')->value());
    }
}
