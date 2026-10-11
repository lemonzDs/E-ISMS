<?php

namespace App\Http\Controllers;

use App\Models\AuditChecklistItem;
use App\Models\AuditEvent;
use App\Models\AuditEvidence;
use App\Models\AuditFinding;
use App\Models\Department;
use App\Models\InternalAudit;
use App\Models\User;
use App\Services\InternalAuditWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InternalAuditController extends Controller
{
    public function __construct(private InternalAuditWorkflow $workflow) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', InternalAudit::class);
        $filters = $request->validate(['status' => ['nullable', 'in:draft,scheduled,ongoing,closed']]);
        $query = InternalAudit::visibleTo($request->user());
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return view('audits.index', ['audits' => $query->with('department', 'auditors')->latest('id')->paginate(15)->withQueryString()]);
    }

    public function create(): View
    {
        Gate::authorize('create', InternalAudit::class);

        return view('audits.form', $this->options() + ['audit' => new InternalAudit]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', InternalAudit::class);
        $audit = $this->workflow->create($request->user(), $this->planData($request));

        return redirect()->route('audits.show', $audit)->with('status', 'Pelan audit diwujudkan. Sediakan senarai semak sebelum menjadualkan audit.');
    }

    public function edit(InternalAudit $audit): View
    {
        Gate::authorize('manage', $audit);
        abort_unless($audit->status === 'draft', 409);

        return view('audits.form', $this->options() + ['audit' => $audit->load('auditors')]);
    }

    public function update(Request $request, InternalAudit $audit): RedirectResponse
    {
        $this->workflow->plan($request->user(), $audit, $this->planData($request) + $this->version($request));

        return redirect()->route('audits.show', $audit)->with('status', 'Pelan audit dikemas kini.');
    }

    public function show(InternalAudit $audit): View
    {
        Gate::authorize('view', $audit);
        $audit->load('department', 'creator', 'auditors', 'checklist', 'findings.owner', 'findings.reviewer', 'evidence.uploader');

        return view('audits.show', $this->options() + ['audit' => $audit, 'owners' => User::where('is_active', true)->where('department_id', $audit->department_id)->whereIn('role', ['officer', 'coordinator'])->orderBy('name')->get(), 'history' => AuditEvent::where('internal_audit_id', $audit->id)->with('actor')->latest('id')->paginate(15)]);
    }

    public function report(InternalAudit $audit): View
    {
        Gate::authorize('view', $audit);

        return view('audits.report', ['audit' => $audit->load('department', 'creator', 'auditors', 'checklist', 'findings.owner', 'findings.reviewer', 'evidence.uploader')]);
    }

    public function transition(Request $request, InternalAudit $audit): RedirectResponse
    {
        $this->workflow->transition($request->user(), $audit, $request->validate(['action' => ['required', 'in:schedule,start,close,reopen'], 'comment' => ['required', 'string', 'max:5000']]) + $this->version($request));

        return back()->with('status', 'Status audit dikemas kini.');
    }

    public function team(Request $request, InternalAudit $audit): RedirectResponse
    {
        $this->workflow->assignTeam($request->user(), $audit, $this->teamData($request) + $this->version($request) + $request->validate(['comment' => ['required', 'string', 'max:5000']]));

        return back()->with('status', 'Pasukan audit dikemas kini.');
    }

    public function checklist(Request $request, InternalAudit $audit): RedirectResponse
    {
        $this->workflow->checklist($request->user(), $audit, $request->validate(['requirement' => ['required', 'string', 'max:5000'], 'reference' => ['required', 'string', 'max:255']]) + $this->version($request));

        return back()->with('status', 'Pemeriksaan ditambah.');
    }

    public function check(Request $request, AuditChecklistItem $item): RedirectResponse
    {
        $this->workflow->checklist($request->user(), $item->audit, $request->validate(['result' => ['required', 'in:conform,nonconform,not_applicable'], 'notes' => ['required', 'string', 'max:5000']]) + $this->version($request), $item);

        return back()->with('status', 'Keputusan pemeriksaan disimpan.');
    }

    public function finding(Request $request, InternalAudit $audit): RedirectResponse
    {
        $data = $request->validate(['audit_checklist_item_id' => ['required', 'integer', 'exists:audit_checklist_items,id'], 'title' => ['required', 'string', 'max:255'], 'category' => ['required', 'in:nonconformity,observation,improvement'], 'severity' => ['required', 'in:low,medium,high'], 'description' => ['required', 'string', 'max:5000']]) + $this->ownerData($request) + $this->version($request);
        $this->workflow->finding($request->user(), $audit, $data);

        return back()->with('status', 'Dapatan audit ditugaskan kepada pegawai.');
    }

    public function assign(Request $request, AuditFinding $finding): RedirectResponse
    {
        $this->workflow->assignOwner($request->user(), $finding, $this->ownerData($request) + $this->version($request) + $request->validate(['comment' => ['required', 'string', 'max:5000']]));

        return back()->with('status', 'Penugasan dapatan dikemas kini.');
    }

    public function inspection(Request $request, InternalAudit $audit): RedirectResponse
    {
        Gate::authorize('conduct', $audit);
        $data = $request->validate(['audit_checklist_item_id' => ['required', 'integer', 'exists:audit_checklist_items,id']]) + $this->fileData($request) + $this->version($request);
        $this->workflow->evidence($request->user(), $audit, $data, $request->file('file'));

        return back()->with('status', 'Bukti pemeriksaan disimpan secara persendirian.');
    }

    public function submit(Request $request, AuditFinding $finding): RedirectResponse
    {
        Gate::authorize('view', $finding->audit);
        $data = $request->validate(['root_cause' => ['required', 'string', 'max:5000'], 'correction' => ['required', 'string', 'max:5000'], 'corrective_action' => ['required', 'string', 'max:5000'], 'effectiveness_measure' => ['required', 'string', 'max:5000']]) + $this->fileData($request) + $this->version($request);
        $this->workflow->evidence($request->user(), $finding->audit, $data, $request->file('file'), $finding);

        return back()->with('status', 'Tindakan dihantar untuk semakan keberkesanan.');
    }

    public function decide(Request $request, AuditFinding $finding): RedirectResponse
    {
        $this->workflow->decide($request->user(), $finding, $request->validate(['action' => ['required', 'in:close,return,reopen'], 'comment' => ['required', 'string', 'max:5000']]) + $this->version($request));

        return back()->with('status', 'Keputusan semakan dapatan disimpan.');
    }

    public function download(AuditEvidence $evidence): StreamedResponse
    {
        Gate::authorize('view', $evidence->audit);
        abort_unless(Storage::disk('local')->exists($evidence->path), 404);

        return Storage::disk('local')->download($evidence->path, $evidence->original_name, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function version(Request $request): array
    {
        return $request->validate(['lock_version' => ['required', 'integer', 'min:0']]);
    }

    private function ownerData(Request $request): array
    {
        return $request->validate(['owner_id' => ['required', 'integer', 'exists:users,id'], 'due_date' => ['required', 'date_format:Y-m-d']]);
    }

    private function fileData(Request $request): array
    {
        return $request->validate(['summary' => ['required', 'string', 'max:5000'], 'file' => ['required', 'file', 'mimes:pdf', 'max:10240']]);
    }

    private function teamData(Request $request): array
    {
        return $request->validate(['lead_id' => ['required', 'integer', 'exists:users,id'], 'auditor_ids' => ['nullable', 'array', 'max:20'], 'auditor_ids.*' => ['integer', 'distinct', 'exists:users,id']]);
    }

    private function planData(Request $request): array
    {
        return $request->validate(['title' => ['required', 'string', 'max:255'], 'scope' => ['required', 'string', 'max:5000'], 'criteria' => ['required', 'string', 'max:5000'], 'department_id' => ['required', 'integer', 'exists:departments,id'], 'start_date' => ['required', 'date_format:Y-m-d'], 'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date']]) + $this->teamData($request);
    }

    private function options(): array
    {
        return ['departments' => Department::orderBy('name')->get(), 'auditors' => User::where('is_active', true)->whereNotNull('department_id')->where('role', 'coordinator')->with('department')->orderBy('name')->get()];
    }
}
