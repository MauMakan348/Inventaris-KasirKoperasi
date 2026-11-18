<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Stock is intentionally absent so the field is ignored on update.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'sku' => [
                'required',
                'string',
                'max:30',
                Rule::unique('items', 'sku')->ignore($this->route('id')),
            ],
            'name' => ['required', 'string', 'max:150'],
            'price' => ['required', 'integer', 'min:1', 'max:100000000'],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sku.required' => 'SKU wajib diisi.',
            'sku.string' => 'SKU harus berupa teks.',
            'sku.max' => 'SKU maksimal 30 karakter.',
            'sku.unique' => 'SKU sudah digunakan.',
            'name.required' => 'Nama barang wajib diisi.',
            'name.string' => 'Nama barang harus berupa teks.',
            'name.max' => 'Nama barang maksimal 150 karakter.',
            'price.required' => 'Harga wajib diisi.',
            'price.integer' => 'Harga harus berupa bilangan bulat.',
            'price.min' => 'Harga harus lebih dari 0.',
            'price.max' => 'Harga maksimal 100000000.',
        ];
    }
}
