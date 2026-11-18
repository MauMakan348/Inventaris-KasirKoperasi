<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
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
     * There is deliberately no exists rule for item_id: a missing item must
     * become a 404 from the service, not a 422 from the validator.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*' => ['required', 'array'],
            'items.*.item_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'payment_method' => ['required', Rule::in(['CASH', 'TRANSFER'])],
            'amount_paid' => ['nullable', 'integer', 'min:0', 'required_if:payment_method,CASH'],
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
            'items.required' => 'Keranjang tidak boleh kosong.',
            'items.array' => 'Keranjang tidak valid.',
            'items.min' => 'Keranjang tidak boleh kosong.',
            'items.max' => 'Maksimal 50 baris barang.',
            'items.*.array' => 'Setiap baris barang harus berupa objek.',
            'items.*.item_id.required' => 'Barang wajib dipilih.',
            'items.*.item_id.integer' => 'ID barang harus berupa bilangan bulat.',
            'items.*.item_id.min' => 'ID barang tidak valid.',
            'items.*.quantity.required' => 'Jumlah barang wajib diisi.',
            'items.*.quantity.integer' => 'Jumlah barang harus berupa bilangan bulat.',
            'items.*.quantity.min' => 'Jumlah barang minimal 1.',
            'items.*.quantity.max' => 'Jumlah barang maksimal 1000.',
            'payment_method.required' => 'Metode pembayaran wajib diisi.',
            'payment_method.in' => 'Metode pembayaran harus CASH atau TRANSFER.',
            'amount_paid.integer' => 'Jumlah bayar harus berupa bilangan bulat.',
            'amount_paid.min' => 'Jumlah bayar tidak boleh negatif.',
            'amount_paid.required' => 'Jumlah bayar wajib diisi untuk pembayaran tunai.',
            'amount_paid.required_if' => 'Jumlah bayar wajib diisi untuk pembayaran tunai.',
        ];
    }
}
