<?php

namespace App\Http\Requests;

use App\Models\OverheadCost;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OverheadCostRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'outlet_id'  => ['required', 'uuid', 'exists:outlets,id'],
            'name'       => ['required', 'string', 'max:255'],
            'amount'     => ['required', 'numeric', 'min:0'],
            'type'       => [
                'required',
                'string',
                Rule::in(OverheadCost::getAvailableTypes())
            ],
            'started_at' => [
                'required_unless:type,' . OverheadCost::TYPE_PER_PORSI,
                'nullable',
                'date'
            ],
            'is_active'  => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'started_at.required_unless' => 'Tanggal mulai wajib diisi jika tipe beban bukan per porsi.',
            'type.in'                    => 'Tipe overhead yang dipilih tidak valid.',
        ];
    }

    protected function passedValidation(): void
    {
        // Pastikan kolom started_at benar-benar kosong (null) di sistem
        // jika tipe yang dikirimkan adalah "per_porsi".
        // Ini mencegah tanggal tersimpan jika user secara tidak sengaja mengirimkan
        // value dari sisa form (misal pergantian dari bulanan ke per porsi).

        if ($this->input('type') === OverheadCost::TYPE_PER_PORSI) {
            $this->merge([
                'started_at' => null,
            ]);
        }
    }
}
