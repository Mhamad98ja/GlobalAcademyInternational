<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('books.manage') ?? false;
    }

    public function rules(): array
    {
        $book = $this->route('book');

        return [
            'sku' => ['required', 'string', 'max:80', Rule::unique('books', 'sku')->ignore($book)],
            'isbn' => ['nullable', 'string', 'max:30'],
            'barcode' => ['required', 'string', 'max:100', Rule::unique('books', 'barcode')->ignore($book)],
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'edition' => ['nullable', 'string', 'max:100'],
            'subject' => ['nullable', 'string', 'max:100'],
            'language' => ['nullable', 'string', 'max:30'],
            'category' => ['nullable', 'string', 'max:100'],
            'media_format' => ['nullable', 'in:print,digital,print_and_digital'],
            'default_audience' => ['nullable', 'in:student,teacher,both'],
            'selling_price' => ['required', 'decimal:0,2', 'min:0'],
            'tax_rate' => ['nullable', 'decimal:0,2', 'min:0', 'max:100'],
            'minimum_stock' => ['nullable', 'integer', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'shelf_location' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
