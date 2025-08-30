<?php

namespace App\Livewire;

use App\Services\LogParserService;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * 📊 LOG DASHBOARD COMPONENT
 *
 * Simple dashboard for viewing application logs and performance metrics
 */
#[Title('Log Dashboard')]
class LogDashboard extends Component
{
    public string $activeTab = 'overview';

    public int $refreshInterval = 30; // seconds

    public bool $autoRefresh = false;

    protected LogParserService $logParser;

    public function mount(): void
    {
        // Authorize access to system logs
        $this->authorize('view-system-logs');
    }

    public function boot(LogParserService $logParser): void
    {
        $this->logParser = $logParser;
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function toggleAutoRefresh(): void
    {
        $this->autoRefresh = ! $this->autoRefresh;
    }

    public function refreshData(): void
    {
        $this->dispatch('$refresh');
    }

    public function render()
    {
        $data = [
            'metrics' => $this->logParser->getPerformanceMetrics(),
            'recentRequests' => $this->logParser->getRecentRequests(30),
            'slowestEndpoints' => $this->logParser->getSlowestEndpoints(10),
            'recentErrors' => $this->logParser->getRecentErrors(15),
            'logSizes' => $this->logParser->getLogFileSizes(),
        ];

        return view('livewire.log-dashboard', $data);
    }
}
