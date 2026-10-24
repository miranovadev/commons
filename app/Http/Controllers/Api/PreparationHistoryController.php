<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EvidenceExecution;
use App\Models\Proposition;
use App\Models\Substrate;
use App\Services\PreparationHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Observer history only: never reads an Artifact or resolves an execution. */
final class PreparationHistoryController extends Controller
{
    public function index(Request $request, Substrate $substrate, PreparationHistoryService $history): JsonResponse
    {
        Gate::authorize('view', $substrate);
        $input = $request->validate([
            'participant_id' => ['required_without:proposition_id', 'prohibits:proposition_id', 'ulid'],
            'proposition_id' => ['required_without:participant_id', 'prohibits:participant_id', 'ulid'],
            'before' => ['sometimes', 'ulid'],
        ]);
        if (isset($input['participant_id'])) {
            $substrate->participants()->findOrFail($input['participant_id']);
        } else {
            Proposition::whereHas('phase.cycle.participant', fn ($q) => $q->whereBelongsTo($substrate))
                ->findOrFail($input['proposition_id']);
        }
        $executions = EvidenceExecution::where('type', 'prepare')
            ->whereHas('cycle.participant', fn ($q) => $q->whereBelongsTo($substrate))
            ->when(isset($input['participant_id']), fn ($q) => $q->whereHas('cycle', fn ($c) => $c->where('participant_id', $input['participant_id'])))
            ->when(isset($input['proposition_id']), fn ($q) => $q->where(fn ($p) => $p
                ->where('proposition_id', $input['proposition_id'])->orWhere('resulting_proposition_id', $input['proposition_id'])))
            ->when(isset($input['before']), fn ($q) => $q->where('id', '<', $input['before']))
            ->with(['cycle.participant', 'phase', 'receipt'])->orderByDesc('id')->limit(21)->get();
        $page = $executions->take(20);

        return response()->json(['data' => [
            'items' => $page->map(fn ($execution) => $history->summary($execution))->values(),
            'next_cursor' => $executions->count() > 20 ? $page->last()->id : null,
        ]]);
    }

    public function show(Substrate $substrate, EvidenceExecution $execution, PreparationHistoryService $history): JsonResponse
    {
        Gate::authorize('view', $substrate);
        $execution->load(['cycle.participant', 'phase', 'receipt']);
        abort_unless($execution->type === 'prepare' && $execution->cycle->participant->substrate_id === $substrate->id, 404);

        return response()->json(['data' => $history->detail($execution)]);
    }
}
