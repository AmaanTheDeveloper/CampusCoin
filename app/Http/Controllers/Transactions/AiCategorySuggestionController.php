<?php

namespace App\Http\Controllers\Transactions;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\Ai\AiCategorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AiCategorySuggestionController extends Controller
{
    public function __invoke(Request $request, AiCategorizationService $ai): JsonResponse
    {
        $this->authorize('create', Transaction::class);

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::enum(TransactionType::class)],
            'description' => ['required', 'string', 'max:255'],
        ]);

        $type = TransactionType::from($validated['type']);
        $result = $ai->suggestForUser($request->user(), $type, $validated['description']);

        return response()->json([
            'available' => $result['available'],
            'suggestion' => $result['suggestion'],
            'error' => $result['error'],
            'request_echo' => [
                'type' => $type->value,
                'description' => trim($validated['description']),
            ],
        ]);
    }
}
