<?php

namespace App\Http\Controllers;

use App\Models\Risk;
use App\Models\RiskAction;
use App\Models\RiskEvidence;
use App\Models\User;
use App\Services\RiskTreatment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RiskTreatmentController extends Controller
{
    public function show(Request $request, Risk $risk, RiskTreatment $workflow): View
    {
        Gate::authorize('view', $risk);
        $actions = $risk->actions()->with(['assignee', 'evidence'])->orderByDesc('cycle')->orderBy('due_date')->get();
        $current = $actions->where('cycle', $risk->assessment_cycle);

        return view('risks.treatment', ['risk' => $risk, 'actions' => $actions, 'workflow' => $workflow, 'current' => $current, 'residuals' => $risk->residualAssessments()->with('assessor')->latest('id')->get(), 'events' => $risk->auditEvents()->where('action', 'like', 'treatment.%')->with('actor')->latest('id')->paginate(15), 'assignees' => $workflow->canManage($request->user(), $risk) ? User::where('department_id', $risk->department_id)->where('is_active', true)->whereIn('role', ['officer', 'coordinator'])->orderBy('name')->get() : collect()]);
    }

    public function store(Request $request, Risk $risk, RiskTreatment $workflow): RedirectResponse
    {
        Gate::authorize('view', $risk);
        abort_unless($workflow->canManage($request->user(), $risk), 403);
        $workflow->create($request->user(), $risk, $this->actionData($request));

        return $this->backTo($risk, 'Tindakan rawatan ditambah.');
    }

    public function update(Request $request, RiskAction $action, RiskTreatment $workflow): RedirectResponse
    {
        Gate::authorize('view', $action->risk);
        abort_unless($workflow->canManage($request->user(), $action->risk), 403);
        $data = $this->actionData($request);
        $data += $request->validate(['comment' => ['required', 'string', 'max:5000']]);
        $workflow->update($request->user(), $action, $data);

        return $this->backTo($action->risk, 'Tindakan dikemas kini; sebab perubahan direkodkan.');
    }

    public function submit(Request $request, RiskAction $action, RiskTreatment $workflow): RedirectResponse
    {
        Gate::authorize('view', $action->risk);
        $data = $request->validate(['lock_version' => ['required', 'integer', 'min:0'], 'summary' => ['required', 'string', 'max:5000'], 'file' => ['required', 'file', 'mimes:pdf', 'max:10240']]);
        $workflow->submit($request->user(), $action, $data, $request->file('file'));

        return $this->backTo($action->risk, 'Bukti dihantar untuk pengesahan.');
    }

    public function decide(Request $request, RiskAction $action, RiskTreatment $workflow): RedirectResponse
    {
        Gate::authorize('view', $action->risk);
        $data = $request->validate(['lock_version' => ['required', 'integer', 'min:0'], 'action' => ['required', 'in:verify,return,reopen'], 'comment' => ['required', 'string', 'max:5000']]);
        $workflow->decide($request->user(), $action, $data);

        return $this->backTo($action->risk, 'Keputusan tindakan direkodkan.');
    }

    public function residual(Request $request, Risk $risk, RiskTreatment $workflow): RedirectResponse
    {
        Gate::authorize('view', $risk);
        $data = $request->validate(['lock_version' => ['required', 'integer', 'min:0'], 'likelihood' => ['required', 'integer', 'between:1,5'], 'impact' => ['required', 'integer', 'between:1,5'], 'controls' => ['required', 'string', 'max:5000'], 'rationale' => ['required', 'string', 'max:5000']]);
        $workflow->residual($request->user(), $risk, $data);

        return $this->backTo($risk, 'Penilaian risiko baki disimpan. Ini belum merupakan penerimaan risiko.');
    }

    public function download(RiskEvidence $evidence): StreamedResponse
    {
        Gate::authorize('view', $evidence->action->risk);
        abort_unless(Storage::disk('local')->exists($evidence->path), 404);

        return Storage::disk('local')->download($evidence->path, $evidence->original_name, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    private function actionData(Request $request): array
    {
        return $request->validate(['title' => ['required', 'string', 'max:255'], 'description' => ['required', 'string', 'max:5000'], 'assignee_id' => ['required', 'integer', 'exists:users,id'], 'due_date' => ['required', 'date_format:Y-m-d'], 'lock_version' => ['required', 'integer', 'min:0']]);
    }

    private function backTo(Risk $risk, string $message): RedirectResponse
    {
        return redirect()->route('risks.treatment',$risk)->with('status',$message);
    }
}
