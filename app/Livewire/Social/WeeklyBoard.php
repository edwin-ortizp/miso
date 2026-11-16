<?php

namespace App\Livewire\Social;

use App\Services\Social\FriendSnapshot;
use App\Services\Social\SocialSettings;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Tabla semanal de amigos (CHG-124): solo quien activó «visible» y «mostrar mi semana». */
#[Layout('layouts.app')]
class WeeklyBoard extends Component
{
    public function toggleLeaderboard(SocialSettings $settings): void
    {
        $settings->setLeaderboard(auth()->id(), ! $settings->leaderboard(auth()->id()));
    }

    public function toggleVisible(SocialSettings $settings): void
    {
        $settings->setVisible(auth()->id(), ! $settings->visible(auth()->id()));
    }

    public function render(FriendSnapshot $snapshot, SocialSettings $settings)
    {
        $me = auth()->user();

        return view('livewire.social.weekly-board', [
            'module' => 'social',
            'rows' => $snapshot->weekly($me),
            'visible' => $settings->visible($me->id),
            'enabled' => $settings->leaderboard($me->id),
        ]);
    }
}
