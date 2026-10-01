<?php

namespace App\Http\Controllers\DocumentScan;

use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentScan\ConfirmDocumentScanRequest;
use App\Http\Requests\DocumentScan\ProcessDocumentScanRequest;
use App\Services\DocumentScan\DocumentScanException;
use App\Services\DocumentScan\DocumentScanService;
use Illuminate\Http\JsonResponse;

class DocumentScanController extends Controller
{
    public function process(ProcessDocumentScanRequest $request, DocumentScanService $scans): JsonResponse
    {
        $mode = $request->validated('mode');
        $mode = is_string($mode) && in_array($mode, ['card', 'document'], true) ? $mode : null;

        try {
            $result = $scans->process($request->user(), $request->file('image'), $mode);
        } catch (DocumentScanException $e) {
            return response()->json([
                'ok' => false,
                'error_code' => $e->errorCode,
                'message' => $e->getMessage(),
                'errors' => [
                    'image' => [$e->getMessage()],
                ],
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'scan_token' => $result['scan_token'],
            'extraction' => $result['extraction'],
            'categories' => $result['categories'],
            'today' => $result['today'],
            'available_balance' => $result['available_balance'],
        ]);
    }

    public function confirm(ConfirmDocumentScanRequest $request, DocumentScanService $scans): JsonResponse
    {
        $validated = $request->validated();

        $result = $scans->confirm($request->user(), (string) $validated['scan_token'], [
            'type' => $validated['type'],
            'category_id' => $validated['category_id'],
            'amount' => $validated['amount'],
            'description' => $validated['description'] ?? null,
            'transaction_date' => $validated['transaction_date'],
            'acknowledge_duplicate' => (bool) ($validated['acknowledge_duplicate'] ?? false),
            'confirm_budget_overrun' => (bool) ($validated['confirm_budget_overrun'] ?? false),
        ]);

        if (! empty($result['possible_duplicate'])) {
            return response()->json([
                'ok' => false,
                'possible_duplicate' => true,
                'message' => $result['message'] ?? 'Possible duplicate detected.',
                'matches' => $result['matches'] ?? [],
            ], 409);
        }

        if (! empty($result['budget_overrun'])) {
            return response()->json([
                'ok' => false,
                'budget_overrun' => true,
                'message' => $result['message'] ?? 'Budget limit exceeded.',
                'budget_impact' => $result['budget_impact'] ?? null,
            ], 409);
        }

        return response()->json([
            'ok' => true,
            'message' => $result['message'] ?? 'Transaction added successfully',
            'transaction_id' => $result['transaction_id'],
            'spending_alert' => $result['spending_alert'] ?? null,
        ]);
    }
}
