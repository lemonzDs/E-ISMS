<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Document;
use App\Models\Risk;
use App\Models\RiskAction;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $departmentId = $this->department($request);
        $documents = $this->documents($request, $departmentId);
        $risks = $this->risks($request, $departmentId);
        $actions = $this->actions($request, $departmentId);
        $documentCounts = [];
        foreach (['draft', 'submitted', 'reviewed', 'returned', 'approved'] as $status) {
            $documentCounts[$status] = (clone $documents)->whereHas('latestVersion', fn ($version) => $version->where('status', $status))->count();
        }
        $riskCounts = [];
        foreach (['low', 'medium', 'high'] as $level) {
            $riskCounts[$level] = (clone $risks)->where('level', $level)->count();
        }
        $actionCounts = [];
        foreach (['open', 'submitted', 'returned', 'verified'] as $status) {
            $actionCounts[$status] = (clone $actions)->where('status', $status)->count();
        }
        $overdue = (clone $actions)->where('status', '!=', 'verified')->whereDate('due_date', '<', now('Asia/Kuala_Lumpur')->toDateString());

        return view('reports', [
            'departments' => Department::when($request->user()->role === 'officer', fn ($query) => $query->whereKey($request->user()->department_id))->orderBy('name')->get(),
            'departmentId' => $departmentId, 'documentCounts' => $documentCounts, 'riskCounts' => $riskCounts, 'actionCounts' => $actionCounts,
            'totals' => ['documents' => (clone $documents)->count(), 'risks' => (clone $risks)->count(), 'actions' => (clone $actions)->count(), 'overdue' => (clone $overdue)->count()],
            'overdueActions' => $overdue->with(['risk.department', 'assignee'])->orderBy('due_date')->orderBy('id')->paginate(10)->withQueryString(),
        ]);
    }

    public function export(Request $request, string $dataset): StreamedResponse
    {
        $departmentId = $this->department($request);
        abort_unless(in_array($dataset, ['documents', 'risks', 'actions'], true), 404);
        $query = match ($dataset) {
            'documents' => $this->documents($request, $departmentId)->with(['department', 'owner', 'latestVersion']),
            'risks' => $this->risks($request, $departmentId)->with(['department', 'owner']),
            'actions' => $this->actions($request, $departmentId)->with(['risk.department', 'assignee']),
        };
        $headers = match ($dataset) {
            'documents' => ['Kod', 'Tajuk', 'Bahagian', 'Pemilik', 'Versi terkini', 'Status terkini'],
            'risks' => ['Kod', 'Tajuk', 'Aset / proses', 'Bahagian', 'Pemilik', 'Skor awal', 'Tahap awal', 'Status', 'Kaedah penilaian'],
            'actions' => ['Kod risiko', 'Tindakan', 'Bahagian', 'Pelaksana', 'Tarikh sasaran', 'Status', 'Lewat', 'Kitaran'],
        };

        return response()->streamDownload(function () use ($query, $dataset, $headers) {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $headers, ',', '"', '');
            foreach ($query->lazyById(200) as $record) {
                $row = match ($dataset) {
                    'documents' => [$record->code, $record->title, $record->department?->name, $record->owner?->name, $record->latestVersion?->number, $record->latestVersion?->statusLabel()],
                    'risks' => [$record->code(), $record->title, $record->asset_process, $record->department?->name, $record->owner?->name, $record->score, $record->levelLabel(), $record->statusLabel(), $record->method['label'] ?? ''],
                    'actions' => [$record->risk->code(), $record->title, $record->risk->department?->name, $record->assignee?->name, $record->due_date->format('d/m/Y'), $record->statusLabel(), $record->overdue() ? 'Ya' : 'Tidak', $record->cycle],
                };
                fputcsv($stream, array_map($this->safeCell(...), $row), ',', '"', '');
            }
            fclose($stream);
        }, 'e-isms-'.$dataset.'-'.now('Asia/Kuala_Lumpur')->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function department(Request $request): ?int
    {
        Gate::authorize('viewAny', Risk::class);
        $data = $request->validate(['department_id' => ['nullable', 'integer', 'exists:departments,id']]);
        $departmentId = isset($data['department_id']) ? (int) $data['department_id'] : null;
        if ($request->user()->role === 'officer') {
            abort_if($departmentId && $departmentId !== $request->user()->department_id, 403);

            return $request->user()->department_id;
        }

        return $departmentId;
    }

    private function documents(Request $request, ?int $departmentId): Builder
    {
        return Document::visibleTo($request->user())->when($departmentId, fn ($query) => $query->where('department_id', $departmentId));
    }

    private function risks(Request $request, ?int $departmentId): Builder
    {
        return Risk::visibleTo($request->user())->when($departmentId, fn ($query) => $query->where('department_id', $departmentId));
    }

    private function actions(Request $request, ?int $departmentId): Builder
    {
        return RiskAction::whereHas('risk', fn ($risk) => $risk->visibleTo($request->user())->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))->where('status', 'reviewed')->whereColumn('risks.assessment_cycle', 'risk_actions.cycle'));
    }

    private function safeCell(mixed $value): string
    {
        $text = (string) ($value ?? '');

        return preg_match('/^[\s\p{Z}\p{C}]*[=+\-@]|^[\t\r\n]/u', $text) ? "'".$text : $text;
    }
}
