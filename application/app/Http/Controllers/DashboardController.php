<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Risk;
use App\Models\RiskAction;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        if ($user->role === 'admin') {
            return view('dashboard', [
                'pendingUsers' => User::where('registration_pending', true)->with('department')->orderBy('created_at')->orderBy('id')->paginate(10),
                'activeUsers' => User::where('is_active', true)->count(),
            ]);
        }

        $documents = Document::visibleTo($user)->where(function ($query) use ($user) {
            $query->where(function ($own) use ($user) {
                $own->where('owner_id', $user->id)->whereHas('latestVersion', fn ($version) => $version->whereIn('status', ['draft', 'returned']));
                if (! in_array($user->role, ['officer', 'coordinator'], true)) {
                    $own->whereRaw('1 = 0');
                }
            });
            if ($user->role === 'coordinator') {
                $query->orWhere(fn ($review) => $review->where('owner_id', '!=', $user->id)->whereHas('latestVersion', fn ($version) => $version->where('status', 'submitted')));
            } elseif ($user->role === 'approver') {
                $query->orWhere(fn ($review) => $review->where('owner_id', '!=', $user->id)->whereHas('latestVersion', fn ($version) => $version->where('status', 'reviewed')->where(fn ($reviewer) => $reviewer->whereNull('reviewer_id')->orWhere('reviewer_id', '!=', $user->id))));
            }
        })->with(['latestVersion', 'department'])->orderBy('updated_at')->orderBy('id')->paginate(8, ['*'], 'documents_page')->withQueryString();

        $risks = Risk::visibleTo($user)->where(function ($query) use ($user) {
            $query->where(fn ($own) => $own->where('owner_id', $user->id)->whereIn('status', ['draft', 'returned']));
            if ($user->role === 'coordinator') {
                $query->orWhere(fn ($review) => $review->where('owner_id', '!=', $user->id)->where('status', 'submitted'));
            }
            if ($user->role === 'approver') {
                $query->whereRaw('1 = 0');
            }
        })->with('department')->orderBy('updated_at')->orderBy('id')->paginate(8, ['*'], 'risks_page')->withQueryString();

        $today = now('Asia/Kuala_Lumpur')->startOfDay();
        $actions = RiskAction::whereHas('risk', fn ($risk) => $risk->visibleTo($user)->where('status', 'reviewed')->whereColumn('risks.assessment_cycle', 'risk_actions.cycle'))->where('status', '!=', 'verified');
        $overdue = (clone $actions)->whereDate('due_date', '<', $today->toDateString())->count();
        $soon = (clone $actions)->whereDate('due_date', '>=', $today->toDateString())->whereDate('due_date', '<=', $today->copy()->addDays(7)->toDateString())->count();
        $attention = (clone $actions)->where(fn ($query) => $query->whereDate('due_date', '<=', $today->copy()->addDays(7)->toDateString())->orWhere('status', 'submitted'));

        return view('dashboard', [
            'documents' => $documents, 'risks' => $risks, 'overdue' => $overdue, 'soon' => $soon,
            'actions' => $attention->with(['risk.department', 'assignee'])->orderBy('due_date')->orderBy('id')->paginate(10, ['*'], 'actions_page')->withQueryString(),
        ]);
    }
}
