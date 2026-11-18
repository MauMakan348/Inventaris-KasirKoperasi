<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockOpnameRequest extends FormRequest
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
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'actual_stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:255'],
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
            'actual_stock.required' => 'Stok fisik wajib diisi.',
            'actual_stock.integer' => 'Stok fisik harus berupa bilangan bulat.',
            'actual_stock.min' => 'Stok fisik tidak boleh negatif.',
            'actual_stock.max' => 'Stok fisik maksimal 1000000.',
            'note.string' => 'Catatan harus berupa teks.',
            'note.max' => 'Catatan maksimal 255 karakter.',
        ];
    }
}
