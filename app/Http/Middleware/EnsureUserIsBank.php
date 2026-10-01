<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsBank
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isBankUser() || ! $user->isActiveAccount()) {
            abort(403);
        }

        $bank = $user->primaryBank();
        if ($bank === null || ! $bank->isActive()) {
            abort(403, 'Your bank account is not active.');
        }

        $request->attributes->set('bank', $bank);

        return $next($request);
    }
}
