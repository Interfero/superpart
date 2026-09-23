<?php

namespace App\Http\Controllers;

use App\Models\News;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NewsController extends Controller
{
    public function index()
    {
        $news = News::query()
            ->latest()
            ->paginate(15);

        return view('news.index', compact('news'));
    }

    public function show(int $id)
    {
        $newsItem = News::findOrFail($id);

        return view('news.show', compact('newsItem'));
    }

    public function create()
    {
        abort_unless(Auth::user()?->hasElevatedAccess(), 403);

        return view('news.create');
    }

    public function store(Request $request)
    {
        abort_unless(Auth::user()?->hasElevatedAccess(), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ], [
            'title.required' => 'Введите тему новости.',
            'body.required' => 'Введите текст новости.',
        ]);

        $news = News::create([
            'user_id' => Auth::id(),
            'title' => $validated['title'],
            'body' => $validated['body'],
        ]);

        return redirect()
            ->route('news.show', $news->id)
            ->with('status', 'Новость создана.');
    }

    public function destroy(int $id)
    {
        abort_unless(Auth::user()?->hasElevatedAccess(), 403);

        News::findOrFail($id)->delete();

        return redirect()
            ->route('news.index')
            ->with('status', 'Новость удалена.');
    }
}