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
        } elseif ($model instanceof Cycle) {
            $this->realtime->participant($model->participant_id);
        } elseif ($model instanceof Participant && $model->wasChanged('current_proposition_id')) {
            $this->realtime->participant($model->getKey());
        }
    }

    public function deleted(ContextHorizon $horizon): void
    {
        $this->realtime->horizons($horizon->substrate_id);
    }
}
