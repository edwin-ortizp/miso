<?php

namespace App\Livewire\Notifications;

use App\Models\UserNotification;
use App\Services\Notifications\NotificationPresentation;
use Illuminate\Database\QueryException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class NotificationBell extends Component
{
    #[On('android-notification-received')]
    public function refreshAndroidNotice(): void {}

    use WithPagination;

    public bool $open = false;

    #[Locked]
    public string $reading = 'all';

    #[Locked]
    public string $type = '';

    public string $draftReading = 'all';

    public string $draftType = '';

    public const TYPES = ['birthday' => 'Cumpleaños', 'task_due' => 'Tareas', 'plan_due' => 'Planes', 'reminder' => 'Recordatorios', 'guardian_nudge' => 'Guardián', 'weekly_insights' => 'Resumen semanal'];

    public const READING = ['all' => 'Todas', 'unread' => 'Sin leer', 'read' => 'Leídas'];

    public function prepareFilters(): void
    {
        $this->draftReading = $this->reading;
        $this->draftType = $this->type;
    }

    public function clearFilters(): void
    {
        $this->reset('reading', 'type', 'draftReading', 'draftType');
        $this->resetPage('notificationsPage');
    }

    public function applyFilters(): void
    {
        $this->validate(['draftReading' => 'required|in:all,unread,read', 'draftType' => 'nullable|in:'.implode(',', array_keys(self::TYPES))]);
        $this->reading = $this->draftReading;
        $this->type = $this->draftType;
        $this->resetPage('notificationsPage');
    }

    public function removeFilter(string $filter): void
    {
        if ($filter === 'reading') {
            $this->reading = $this->draftReading = 'all';
        }
        if ($filter === 'type') {
            $this->type = $this->draftType = '';
        }
        $this->resetPage('notificationsPage');
    }

    private function available()
    {
        abort_unless(auth()->check(), 403);

        return UserNotification::query()->where('user_id', auth()->id())
            ->whereNull('cancelled_at')->where('scheduled_for', '<=', now('UTC'));
    }

    public function updated($property): void
    {
        if (in_array($property, ['reading', 'type'])) {
            $this->resetPage('notificationsPage');
        }
    }

    public function markRead(string $id): void
    {
        $this->available()->whereKey($id)->whereNull('read_at')->update(['read_at' => now('UTC')]);
    }

    public function markAllRead(): void
    {
        $this->filtered()->whereNull('read_at')->update(['read_at' => now('UTC')]);
    }

    private function filtered()
    {
        return $this->available()
            ->when($this->reading === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->when($this->reading === 'read', fn ($q) => $q->whereNotNull('read_at'))
            ->when(in_array($this->type, ['birthday', 'task_due', 'plan_due', 'reminder', 'guardian_nudge', 'weekly_insights']), fn ($q) => $q->where('type', $this->type));
    }

    public function render()
    {
        $notifications = null;
        $presentation = [];
        $unread = 0;
        $attentionTone = 'neutral';
        $loadError = false;
        try {
            $unread = $this->available()->whereNull('read_at')->count();
            $presenter = app(NotificationPresentation::class);
            // Only dated pending types can require attention. Batch sources for the whole
            // unread set, not just the currently visible page or selected filters.
            if ($unread) {
                $this->available()->whereNull('read_at')->whereIn('type', ['task_due', 'plan_due', 'reminder'])
                    ->chunkById(200, function ($notices) use ($presenter, &$attentionTone) {
                        foreach ($presenter->forNotices($notices, (int) auth()->id()) as $row) {
                            if ($row['tone'] === 'danger') {
                                $attentionTone = 'danger';

                                return false;
                            }
                            if ($row['tone'] === 'warning') {
                                $attentionTone = 'warning';
                            }
                        }
                    });
            }
            if ($this->open) {
                $notifications = $this->filtered()->orderByDesc('scheduled_for')->orderByDesc('id')->paginate(15, pageName: 'notificationsPage');
                // Reading the last item on a filtered page should not strand the user
                // on an empty page while earlier matches still exist.
                if ($notifications->currentPage() > $notifications->lastPage()) {
                    $this->setPage($notifications->lastPage(), 'notificationsPage');
                    $notifications = $this->filtered()->orderByDesc('scheduled_for')->orderByDesc('id')->paginate(15, pageName: 'notificationsPage');
                }
                $presentation = $presenter->forNotices($notifications->getCollection(), (int) auth()->id());
            }
        } catch (QueryException $error) {
            report($error);
            $loadError = true;
        }

        return view('livewire.notifications.bell', [
            'unread' => $unread, 'notifications' => $notifications,
            'presentation' => $presentation, 'attentionTone' => $attentionTone, 'loadError' => $loadError,
            'filterCount' => (int) ($this->reading !== 'all') + (int) ($this->type !== ''),
        ]);
    }
}
