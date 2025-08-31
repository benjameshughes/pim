<?php

namespace App\Livewire;

use App\UI\Components\Tab;
use App\UI\Components\TabSet;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * 📊 LOG DASHBOARD COMPONENT
 *
 * Organized dashboard with routable tabs for logs, activity, and performance
 */
#[Title('Log Dashboard')]
class LogDashboard extends Component
{
    public function mount(): void
    {
        // Authorize access to system logs
        $this->authorize('view-system-logs');
    }

    public function getLogDashboardTabsProperty()
    {
        return TabSet::make()
            ->baseRoute('log-dashboard')
            ->wireNavigate(true)
            ->tabs([
                Tab::make('overview')
                    ->label('Overview')
                    ->icon('home'),

                Tab::make('activity')
                    ->label('Activity')
                    ->icon('document-text'),

                Tab::make('performance')
                    ->label('Performance')
                    ->icon('chart-bar'),

                Tab::make('errors')
                    ->label('Errors')
                    ->icon('exclamation-triangle'),
            ]);
    }

    public function render()
    {
        return view('livewire.log-dashboard');
    }
}
