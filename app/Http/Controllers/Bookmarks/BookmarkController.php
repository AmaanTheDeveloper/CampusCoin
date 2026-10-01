<?php

namespace App\Http\Controllers\Bookmarks;

use App\Http\Controllers\Controller;
use App\Models\Bookmark;
use App\Services\Bookmarks\BookmarkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BookmarkController extends Controller
{
    public function index(Request $request, BookmarkService $bookmarks): Response
    {
        $this->authorize('viewAny', Bookmark::class);

        return Inertia::render('bookmarks/index', [
            'bookmarks' => $bookmarks->listForUser($request->user()),
        ]);
    }

    public function toggle(Request $request, BookmarkService $bookmarks): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(['insight', 'tip'])],
            'id' => ['required', 'integer'],
        ]);

        $result = $bookmarks->toggle($request->user(), $validated['type'], (int) $validated['id']);

        return back()->with('success', $result['bookmarked'] ? 'Bookmarked.' : 'Bookmark removed.');
    }

    public function destroy(Request $request, Bookmark $bookmark, BookmarkService $bookmarks): RedirectResponse
    {
        $this->authorize('delete', $bookmark);
        $bookmarks->remove($request->user(), $bookmark);

        return back()->with('success', 'Bookmark removed.');
    }
}
