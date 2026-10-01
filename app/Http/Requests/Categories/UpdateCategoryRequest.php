<?php

namespace App\Http\Requests\Categories;

use App\Enums\TransactionType;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Category $category */
        $category = $this->route('category');

        return $this->user()?->can('update', $category) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name') && is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Category $category */
        $category = $this->route('category');

        return [
            'type' => ['required', 'string', Rule::enum(TransactionType::class)],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categories', 'name')
                    ->ignore($category->id)
                    ->where(fn ($query) => $query
                        ->where('user_id', $this->user()->id)
                        ->where('type', $this->input('type'))
                    ),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $name = $this->input('name');
            $type = $this->input('type');

            if (! is_string($name) || $name === '' || ! is_string($type)) {
                return;
            }

            $matchesDefault = Category::query()
                ->whereNull('user_id')
                ->where('type', $type)
                ->where('name', $name)
                ->exists();

            if ($matchesDefault) {
                $validator->errors()->add('name', 'A system category with this name already exists.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated($key, $default);

        if (is_array($validated)) {
            unset($validated['user_id'], $validated['is_default'], $validated['id']);
        }

        return $validated;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'You already have a category with this name for the selected type.',
            'name.required' => 'Enter a category name.',
        ];
    }
}
