<?php

namespace App\Observers;

use App\Models\ContextHorizon;
use App\Models\Cycle;
use App\Models\Participant;
use App\Models\Phase;
use App\Services\RealtimePublisher;
use Illuminate\Database\Eloquent\Model;

final class RealtimeObserver
{
    public function __construct(private readonly RealtimePublisher $realtime) {}

    public function saved(Model $model): void
    {
        if (config('broadcasting.default') === 'null') {
            return;
        }
        if ($model instanceof ContextHorizon) {
            $this->realtime->horizons($model->substrate_id);
        } elseif ($model instanceof Phase) {
            $this->realtime->participant($model->cycle->participant_id);
            $this->realtime->graphMetrics($model->cycle->participant->substrate_id);
        } elseif ($model instanceof Cycle) {
            $this->realtime->participant($model->participant_id);
        } elseif ($model instanceof Participant) {
            if ($model->wasChanged('current_proposition_id')) {
                $this->realtime->participant($model->getKey());
            }
            if ($model->wasRecentlyCreated || $model->wasChanged(['current_proposition_id', 'callsign'])) {
                $this->realtime->graphParticipant($model->id, $model->substrate_id);
            }
        }
    }

    public function deleted(Model $model): void
    {
        if ($model instanceof ContextHorizon) {
            $this->realtime->horizons($model->substrate_id);
        } elseif ($model instanceof Participant) {
            $this->realtime->graphParticipant($model->id, $model->substrate_id);
        }
    }
}
