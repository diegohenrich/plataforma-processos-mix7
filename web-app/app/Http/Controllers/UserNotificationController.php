<?php

namespace App\Http\Controllers;

use App\Models\Demand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserNotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $request->user()->notifications()->latest()->paginate(25),
            'unreadCount' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();
        $demandId = filter_var($item->data['demand_id'] ?? null, FILTER_VALIDATE_INT);

        abort_unless($demandId, 404);
        $demand = Demand::query()->findOrFail($demandId);
        $this->authorize('view', $demand);

        return to_route('demands.show', $demand);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return to_route('notifications.index')->with('success', 'Notificações marcadas como lidas.');
    }
}
