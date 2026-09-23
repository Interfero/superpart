<?php


namespace App\Http\Controllers;


use App\Models\PortalNotification;

use Illuminate\Http\RedirectResponse;

use Illuminate\Http\Request;

use Illuminate\View\View;


class PortalNotificationController extends Controller

{

    public function index(Request $request): View

    {

        $notifications = PortalNotification::query()

            ->where('user_id', $request->user()->id)

            ->orderByRaw('read_at IS NULL DESC')

            ->orderByDesc('created_at')

            ->paginate(25)

            ->withQueryString();


        return view('notifications.index', compact('notifications'));

    }


    public function markRead(Request $request, PortalNotification $notification): RedirectResponse

    {

        abort_unless((int) $notification->user_id === (int) $request->user()->id, 403);


        if (! $notification->read_at) {

            $notification->read_at = now();

            $notification->save();

        }


        return back()->with('success', 'Уведомление отмечено как прочитанное.');

    }


    public function markAllRead(Request $request): RedirectResponse

    {

        PortalNotification::query()

            ->where('user_id', $request->user()->id)

            ->whereNull('read_at')

            ->update(['read_at' => now()]);


        return back()->with('success', 'Все уведомления отмечены как прочитанные.');

    }

}
