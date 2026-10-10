<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Risk;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['filter' => ['nullable', 'in:all,unread']]);
        $query = $request->user()->notifications()->orderByDesc('created_at')->orderByDesc('id');
        if (($filters['filter'] ?? 'all') === 'unread') {
            $query->whereNull('read_at');
        }

        return view('notifications', ['notifications' => $query->paginate(15)->withQueryString(), 'unreadCount' => $request->user()->unreadNotifications()->count()]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $request->user()->notifications()->findOrFail($notification)->markAsRead();

        return back()->with('status', 'Notifikasi ditandakan dibaca.');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('status', 'Semua notifikasi anda ditandakan dibaca.');
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        $alert = $request->user()->notifications()->findOrFail($notification);
        $data = $alert->data;
        $target = (int) $data['target_id'];
        switch ($data['kind']) {
            case 'document':
                Gate::authorize('view', Document::findOrFail($target));
                $url = route('documents.show', $target);
                break;
            case 'risk':
            case 'treatment':
                Gate::authorize('view', Risk::findOrFail($target));
                $url = route($data['kind'] === 'risk' ? 'risks.show' : 'risks.treatment', $target);
                break;
            case 'registration':
                Gate::authorize('manage-users');
                User::findOrFail($target);
                $url = route('admin.users.edit', $target);
                break;
            case 'account':
                abort_unless($target === $request->user()->id, 403);
                $url = route('dashboard');
                break;
            default:
                abort(404);
        }
        $alert->markAsRead();

        return redirect($url);
    }
}
