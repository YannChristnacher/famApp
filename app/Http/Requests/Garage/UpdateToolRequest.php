<?php

namespace App\Http\Requests\Garage;

use App\Models\Garage\Tool;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class UpdateToolRequest extends ToolRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->tool());
    }

    public function tool(): Tool
    {
        /** @var Tool */
        return $this->route('tool');
    }

    /**
     * Only a photo of the edited tool can be kept (or moved).
     *
     * @return array<int, string|Exists>
     */
    protected function keptPhotoRules(): array
    {
        return [
            'nullable',
            'integer',
            'distinct',
            Rule::exists('tool_photos', 'id')->where('tool_id', $this->tool()->id),
        ];
    }
}
