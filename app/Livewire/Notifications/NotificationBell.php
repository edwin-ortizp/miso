<?php

namespace App\Livewire\Notifications;

use App\Models\UserNotification;
use Livewire\Component;
use Livewire\WithPagination;

class NotificationBell extends Component
{
    use WithPagination;

    public bool $open = false;

    public string $reading = 'all';

    public string $type = '';

    public function clearFilters(): void
    {
        $this->reset('reading', 'type');
        $this->resetPage('notificationsPage');
    }

    public function applyFilters(): void
    {
        $this->resetPage('notificationsPage');
    }

    private function available()
    {
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
            ->when(in_array($this->type, ['birthday', 'task_due', 'plan_due', 'reminder']), fn ($q) => $q->where('type', $this->type));
    }

    public function render()
    {
        return view('livewire.notifications.bell', [
            'unread' => $this->available()->whereNull('read_at')->count(),
            'notifications' => $this->open ? $this->filtered()->orderByDesc('scheduled_for')->orderByDesc('id')->paginate(15, pageName: 'notificationsPage') : null,
        ]);
    }
}
