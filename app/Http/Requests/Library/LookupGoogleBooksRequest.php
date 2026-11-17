<?php

namespace App\Http\Requests\Library;

use App\Rules\Library\Isbn;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * ISBN searched on Google Books from the book form (normalized like the
 * book's own ISBN).
 */
class LookupGoogleBooksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'isbn' => ['required', 'string', new Isbn],
        ];
    }

    public function isbn(): string
    {
        return (string) $this->validated('isbn');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->query('isbn'))) {
            $this->merge(['isbn' => Isbn::normalize($this->query('isbn'))]);
        }
    }
}
