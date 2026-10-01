<?php

namespace App\Http\Requests\Intelligence;

use Illuminate\Foundation\Http\FormRequest;

class AssistantAskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $max = (int) config('ai.assistant_max_message_length', 500);

        return [
            'message' => ['required', 'string', 'max:'.$max],
            'history' => ['sometimes', 'array', 'max:8'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:800'],
        ];
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    public function history(): array
    {
        $history = $this->validated('history') ?? [];
        if (! is_array($history)) {
            return [];
        }

        $clean = [];
        foreach (array_slice($history, -8) as $turn) {
            if (! is_array($turn)) {
                continue;
            }
            $role = (string) ($turn['role'] ?? '');
            if (! in_array($role, ['user', 'assistant'], true)) {
                continue;
            }
            $content = mb_substr(trim(strip_tags((string) ($turn['content'] ?? ''))), 0, 800);
            if ($content === '') {
                continue;
            }
            $clean[] = ['role' => $role, 'content' => $content];
        }

        return $clean;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.required' => 'Enter a question about your Campus Coin finances.',
            'message.max' => 'Please keep your question under :max characters.',
        ];
    }
}
