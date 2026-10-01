<?php

namespace App\Http\Requests\DocumentScan;

use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;

class ProcessDocumentScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Transaction::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = (int) config('campuscoin.document_scan.max_size_kb', 8192);

        return [
            'image' => [
                'required',
                'file',
                'max:'.$maxKb,
                'mimes:jpg,jpeg,png,webp',
            ],
            'mode' => ['sometimes', 'nullable', 'string', 'in:card,document'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required' => 'Choose a receipt or bill photo to scan.',
            'image.mimes' => 'Upload a JPG, PNG, or WebP image.',
            'image.max' => 'Image exceeds the maximum allowed size.',
        ];
    }
}
