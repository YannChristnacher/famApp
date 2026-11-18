<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Text typed in the search palette of the header (trimmed by the global
 * TrimStrings middleware).
 */
class SearchRequest extends FormRequest
{
    public const MIN_LENGTH = 2;

    public const MAX_LENGTH = 100;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:'.self::MIN_LENGTH, 'max:'.self::MAX_LENGTH],
        ];
    }

    public function searchedText(): string
    {
        return (string) $this->validated('q');
    }
}
