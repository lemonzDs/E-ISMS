<?php

namespace App\Http\Controllers;

use App\Http\Requests\RiskRequest;
use App\Models\Risk;
use App\Services\RiskWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RiskController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Risk::class);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:draft,submitted,returned,reviewed'], 'level' => ['nullable', 'in:low,medium,high']]);
        $base = Risk::visibleTo($request->user());
        $counts = ['total' => (clone $base)->count(), 'high' => (clone $base)->where('level', 'high')->count(), 'submitted' => (clone $base)->where('status', 'submitted')->count(), 'returned' => (clone $base)->where('status', 'returned')->count()];
        $query = (clone $base)->with(['owner', 'department']);
        if ($search = $filters['q'] ?? null) {
            $query->where(function ($query) use ($search) {
                $query->where('title', 'like', '%'.$search.'%')->orWhere('asset_process', 'like', '%'.$search.'%');
                if (preg_match('/^(?:RSK-)?0*(\d+)$/i', $search, $match)) {
                    $query->orWhere('id', $match[1]);
                }
            });
        }
        foreach (['status', 'level'] as $field) {
            if ($value = $filters[$field] ?? null) {
                $query->where($field, $value);
            }
        }

        return view('risks.index', ['risks' => $query->orderByDesc('updated_at')->orderByDesc('id')->paginate(15)->withQueryString(), 'counts' => $counts]);
    }

    public function create(): View
    {
        Gate::authorize('create', Risk::class);

        return view('risks.form', ['risk' => new Risk]);
    }

    public function store(RiskRequest $request, RiskWorkflow $workflow): RedirectResponse
    {
        $risk = $workflow->create($request->user(), $request->validated());

        return redirect()->route('risks.show', $risk)->with('status', 'Risiko disimpan sebagai draf.');
    }

    public function show(Risk $risk): View
    {
        Gate::authorize('view', $risk);
        $risk->load(['owner', 'department']);

        return view('risks.show', ['risk' => $risk, 'events' => $risk->auditEvents()->where('action', 'like', 'risk.%')->with('actor')->orderByDesc('id')->paginate(10)]);
    }

    public function edit(Risk $risk): View
    {
        Gate::authorize('update', $risk);

        return view('risks.form', ['risk' => $risk]);
    }

    public function update(RiskRequest $request, Risk $risk, RiskWorkflow $workflow): RedirectResponse
    {
        $workflow->update($request->user(), $risk, $request->validated());

        return redirect()->route('risks.show', $risk)->with('status', 'Penilaian dikemas kini.');
    }

    public function transition(Request $request, Risk $risk, RiskWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('view', $risk);
        $data = $request->validate(['action' => ['required', 'in:submit,review,return,reassess'], 'lock_version' => ['required', 'integer', 'min:0'], 'comment' => ['required_if:action,return,reassess', 'nullable', 'string', 'max:5000']]);
        $workflow->transition($request->user(), $risk, $data['action'], (int) $data['lock_version'], $data['comment'] ?? null);

        return redirect()->route('risks.show', $risk)->with('status', 'Tindakan direkodkan dalam sejarah risiko.');
    }
}
