<?php

namespace App\Http\Controllers;

use App\Models\AcceptanceAttempt;
use App\Models\AcceptanceScenario;
use App\Models\AcceptanceSession;
use App\Models\AuditEvent;
use App\Models\Department;
use App\Models\User;
use App\Services\AcceptanceTesting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AcceptanceSessionController extends Controller
{
    public function __construct(private AcceptanceTesting $workflow) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AcceptanceSession::class);
        $filters = $request->validate(['status' => ['nullable', 'in:draft,testing,review,approved,cancelled']]);
        $query = AcceptanceSession::visibleTo($request->user());
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return view('uat.index', ['sessions' => $query->with('department', 'tester')->latest('id')->paginate(15)->withQueryString()]);
    }

    public function create(): View
    {
        Gate::authorize('create', AcceptanceSession::class);

        return view('uat.create', $this->options());
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', AcceptanceSession::class);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'build_reference' => ['required', 'string', 'max:100'], 'scope' => ['required', 'string', 'max:5000'], 'department_id' => ['required', 'integer', 'exists:departments,id'], 'tester_id' => ['required', 'integer', 'exists:users,id']]);
        $session = $this->workflow->create($request->user(), $data);

        return redirect()->route('uat.show', $session)->with('status', 'Sesi UAT diwujudkan bersama 10 senario rintis.');
    }

    public function show(AcceptanceSession $session): View
    {
        Gate::authorize('view', $session);
        $session->load('department', 'creator', 'tester', 'approver', 'scenarios.latestAttempt', 'scenarios.attempts.tester', 'scenarios.attempts.fixer');

        return view('uat.show', $this->options() + ['session' => $session, 'ready' => $this->workflow->ready($session), 'history' => AuditEvent::where('acceptance_session_id', $session->id)->with('actor')->latest('id')->paginate(15)]);
    }

    public function report(AcceptanceSession $session): View
    {
        Gate::authorize('view', $session);

        return view('uat.report', ['session' => $session->load('department', 'creator', 'tester', 'approver', 'scenarios.latestAttempt', 'scenarios.attempts.tester', 'scenarios.attempts.fixer')]);
    }

    public function scenario(Request $request, AcceptanceSession $session): RedirectResponse
    {
        $this->workflow->scenario($request->user(), $session, $request->validate(['module' => ['required', 'string', 'max:100'], 'title' => ['required', 'string', 'max:255'], 'steps' => ['required', 'string', 'max:5000'], 'expected' => ['required', 'string', 'max:5000']]) + $this->version($request));

        return back()->with('status', 'Senario tambahan disimpan.');
    }

    public function assign(Request $request, AcceptanceSession $session): RedirectResponse
    {
        $this->workflow->assign($request->user(), $session, $request->validate(['tester_id' => ['required', 'integer', 'exists:users,id'], 'comment' => ['required', 'string', 'max:5000']]) + $this->version($request));

        return back()->with('status', 'Pegawai penguji dikemas kini.');
    }

    public function transition(Request $request, AcceptanceSession $session): RedirectResponse
    {
        $this->workflow->transition($request->user(), $session, $request->validate(['action' => ['required', 'in:start,submit,approve,return,cancel'], 'comment' => ['required', 'string', 'max:5000']]) + $this->version($request));

        return back()->with('status', 'Status sesi UAT dikemas kini.');
    }

    public function record(Request $request, AcceptanceScenario $scenario): RedirectResponse
    {
        Gate::authorize('execute', $scenario->session);
        $data = $request->validate(['result' => ['required', 'in:passed,failed,blocked'], 'observed' => ['required', 'string', 'max:5000'], 'file' => ['nullable', 'file', 'mimes:pdf', 'max:10240']]) + $this->version($request);
        $this->workflow->record($request->user(), $scenario, $data, $request->file('file'));

        return back()->with('status', 'Keputusan ujian direkodkan.');
    }

    public function fix(Request $request, AcceptanceAttempt $attempt): RedirectResponse
    {
        $this->workflow->fix($request->user(), $attempt, $request->validate(['comment' => ['required', 'string', 'max:5000']]) + $this->version($request));

        return back()->with('status', 'Pembetulan direkodkan. Keputusan hanya berubah selepas ujian semula.');
    }

    public function download(AcceptanceAttempt $attempt): StreamedResponse
    {
        Gate::authorize('view', $attempt->scenario->session);
        abort_unless($attempt->evidence_path && Storage::disk('local')->exists($attempt->evidence_path), 404);

        return Storage::disk('local')->download($attempt->evidence_path, $attempt->evidence_name, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function version(Request $request): array
    {
        return $request->validate(['lock_version' => ['required', 'integer', 'min:0']]);
    }

    private function options(): array
    {
        return ['departments' => Department::orderBy('name')->get(), 'testers' => User::where('is_active',true)->whereNotNull('department_id')->whereIn('role',['officer', 'coordinator'])->with('department')->orderBy('name')->get()];
    }
}
