<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentRequest;
use App\Http\Requests\TransitionRequest;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Services\DocumentWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:draft,submitted,reviewed,returned,approved']]);
        $query = Document::visibleTo($request->user())->with(['department', 'owner', 'latestVersion']);
        if ($search = $filters['q'] ?? null) {
            $query->where(fn ($q) => $q->where('title', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%'));
        }
        if ($status = $filters['status'] ?? null) {
            $query->whereHas('latestVersion', fn ($q) => $q->where('status', $status));
        }
        $counts = ['total' => Document::visibleTo($request->user())->count()];
        foreach (['submitted', 'reviewed', 'returned'] as $status) {
            $counts[$status] = Document::visibleTo($request->user())->whereHas('latestVersion', fn ($q) => $q->where('status', $status))->count();
        }

        return view('documents.index', ['documents' => $query->orderByDesc('updated_at')->orderByDesc('id')->paginate(15)->withQueryString(), 'counts' => $counts]);
    }

    public function create(): View
    {
        Gate::authorize('create', Document::class);

        return view('documents.create');
    }

    public function store(DocumentRequest $request, DocumentWorkflow $workflow): RedirectResponse
    {
        $document = $workflow->create($request->user(), $request->validated(), $request->file('file'));

        return redirect()->route('documents.show', $document)->with('status', 'Dokumen disimpan sebagai draf.');
    }

    public function show(Document $document): View
    {
        Gate::authorize('view', $document);
        $document->load(['department', 'owner', 'versions' => fn ($q) => $q->orderByDesc('number'), 'auditEvents' => fn ($q) => $q->with('actor')->orderByDesc('id')]);

        return view('documents.show', ['document' => $document, 'latest' => $document->versions->first()]);
    }

    public function update(DocumentRequest $request, DocumentVersion $version, DocumentWorkflow $workflow): RedirectResponse
    {
        $workflow->update($request->user(), $version, $request->validated('title'), (int) $request->validated('lock_version'), $request->file('file'));

        return redirect()->route('documents.show', $version->document_id)->with('status', 'Draf dikemas kini.');
    }

    public function newVersion(DocumentRequest $request, Document $document, DocumentWorkflow $workflow): RedirectResponse
    {
        $workflow->newVersion($request->user(), $document, $request->file('file'));

        return redirect()->route('documents.show', $document)->with('status', 'Versi baharu disimpan sebagai draf. Versi diluluskan dikekalkan.');
    }

    public function transition(TransitionRequest $request, DocumentVersion $version, DocumentWorkflow $workflow): RedirectResponse
    {
        $workflow->transition($request->user(), $version, $request->validated('action'), (int) $request->validated('lock_version'), $request->validated('comment'));

        return redirect()->route('documents.show', $version->document_id)->with('status', 'Keputusan direkodkan dalam jejak audit.');
    }

    public function download(DocumentVersion $version): StreamedResponse
    {
        Gate::authorize('download', $version);
        abort_unless(Storage::disk('local')->exists($version->path), 404, 'Fail tidak ditemui. Hubungi pentadbir.');

        return Storage::disk('local')->download($version->path, $version->original_name, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
