<?php

namespace App\Http\Controllers\Tips;

use App\Http\Controllers\Controller;
use App\Models\SavingTip;
use App\Services\Tips\SavingTipsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SavingTipController extends Controller
{
    public function dismiss(Request $request, SavingTip $savingTip, SavingTipsService $tips): RedirectResponse
    {
        $this->authorize('update', $savingTip);
        $tips->dismiss($request->user(), $savingTip);

        return back()->with('success', 'Tip dismissed.');
    }
}
