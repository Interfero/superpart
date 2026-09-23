<?php

namespace App\Http\Controllers;

use App\Models\FeedbackTicket;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortalAttachmentController extends Controller
{
    public function feedback(Request $request, FeedbackTicket $feedback, int $index): StreamedResponse
    {
        $user = $request->user();
        abort_unless(
            $user->hasElevatedAccess() || (int) $feedback->user_id === (int) $user->id,
            403
        );

        return $this->streamFromList($feedback->attachments ?? [], $index, 'attachment');
    }

    public function reviewPhoto(Request $request, Review $review, int $index): StreamedResponse
    {
        $user = $request->user();
        abort_unless(
            Review::query()->forPortalUser($user)->whereKey($review->id)->exists(),
            403
        );

        return $this->streamFromList($review->photos ?? [], $index, 'inline');
    }

    /**
     * @param  array<int, array{path?: string, name?: string, mime?: string, disk?: string}>  $items
     */
    private function streamFromList(array $items, int $index, string $disposition): StreamedResponse
    {
        abort_unless(isset($items[$index]['path']), 404);

        $item = $items[$index];
        $path = (string) $item['path'];
        $disk = (string) ($item['disk'] ?? $this->guessDisk($path));
        $storage = Storage::disk($disk);

        if (! $storage->exists($path) && $disk !== 'public') {
            $storage = Storage::disk('public');
            abort_unless($storage->exists($path), 404);
        } else {
            abort_unless($storage->exists($path), 404);
        }

        $name = (string) ($item['name'] ?? basename($path));
        $mime = (string) ($item['mime'] ?? $storage->mimeType($path) ?: 'application/octet-stream');

        return $storage->response(
            $path,
            $name,
            ['Content-Type' => $mime],
            $disposition === 'inline' ? 'inline' : 'attachment'
        );
    }

    private function guessDisk(string $path): string
    {
        // Новые файлы пишем на local; старые лежат в public.
        if (Storage::disk('local')->exists($path)) {
            return 'local';
        }

        return 'public';
    }
}
