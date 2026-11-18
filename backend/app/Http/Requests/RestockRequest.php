<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestockRequest extends FormRequest
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
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
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
            'quantity.required' => 'Jumlah restock wajib diisi.',
            'quantity.integer' => 'Jumlah restock harus berupa bilangan bulat.',
            'quantity.min' => 'Jumlah restock minimal 1.',
            'quantity.max' => 'Jumlah restock maksimal 1000000.',
            'note.string' => 'Catatan harus berupa teks.',
            'note.max' => 'Catatan maksimal 255 karakter.',
        ];
    }
}
