<?php

namespace App\Livewire\ChannelMapping;

use App\Models\ChannelFieldDefinition;
use App\Models\ChannelFieldMapping;
use App\Models\ChannelValueList;
use App\Models\SyncAccount;
use App\Services\ChannelMapping\ChannelFieldDiscoveryService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * 🎛️ CHANNEL MAPPER DASHBOARD
 *
 * Centralized management interface for:
 * - Field discovery and sync status
 * - Channel mapping overview
 * - Health monitoring and recommendations
 * - Discovery operations and statistics
 */
#[Layout('components.layouts.app')]
class ChannelMapperDashboard extends Component
{
    use WithPagination;

    public string $activeTab = 'overview';

    public string $selectedChannel = '';

    public string $selectedSyncAccount = '';

    public bool $showDiscoveryModal = false;

    public bool $isDiscovering = false;

    public array $discoveryResults = [];

    protected ChannelFieldDiscoveryService $discoveryService;

    public function boot(ChannelFieldDiscoveryService $discoveryService)
    {
        $this->discoveryService = $discoveryService;
    }

    /**
     * 📊 COMPUTED: System Statistics
     */
    #[Computed]
    public function systemStats(): array
    {
        return $this->discoveryService->getDiscoveryStatistics();
    }

    /**
     * 📊 COMPUTED: Active Sync Accounts
     */
    #[Computed]
    public function syncAccounts()
    {
        return SyncAccount::where('is_active', true)
            ->with(['channelFieldMappings'])
            ->get();
    }

    /**
     * 📊 COMPUTED: Field Definitions Summary
     */
    #[Computed]
    public function fieldDefinitions()
    {
        $query = ChannelFieldDefinition::active()
            ->with(['fieldMappings']);

        if ($this->selectedChannel) {
            $query->where('channel_type', $this->selectedChannel);
        }

        return $query->paginate(20);
    }

    /**
     * 📊 COMPUTED: Recent Mappings
     */
    #[Computed]
    public function recentMappings()
    {
        $query = ChannelFieldMapping::active()
            ->with(['syncAccount', 'product'])
            ->orderBy('updated_at', 'desc');

        if ($this->selectedSyncAccount) {
            $query->where('sync_account_id', $this->selectedSyncAccount);
        }

        return $query->take(10)->get();
    }

    /**
     * 📊 COMPUTED: Value Lists Summary
     */
    #[Computed]
    public function valueLists()
    {
        $query = ChannelValueList::active();

        if ($this->selectedChannel) {
            $query->where('channel_type', $this->selectedChannel);
        }

        return $query->orderBy('values_count', 'desc')
            ->take(15)
            ->get();
    }

    /**
     * 📊 COMPUTED: Health Alerts
     */
    #[Computed]
    public function healthAlerts(): array
    {
        $stats = $this->systemStats;
        $health = $stats['discovery_health'] ?? [];
        $alerts = [];

        // Field health alerts
        if (($health['field_health']['health_score'] ?? 0) < 70) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Field Definitions Need Update',
                'message' => "Only {$health['field_health']['recently_verified']} of {$health['field_health']['total']} fields verified recently",
                'action' => 'discover-fields',
                'action_label' => 'Run Discovery',
            ];
        }

        // Value list health alerts
        if (($health['value_list_health']['health_score'] ?? 0) < 70) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Value Lists Need Sync',
                'message' => "Only {$health['value_list_health']['synced']} of {$health['value_list_health']['total']} value lists synced",
                'action' => 'sync-value-lists',
                'action_label' => 'Sync Lists',
            ];
        }

        // Overall health alerts
        $overallScore = $health['overall_health']['score'] ?? 0;
        if ($overallScore < 50) {
            $alerts[] = [
                'type' => 'error',
                'title' => 'System Health Critical',
                'message' => "Overall health score: {$overallScore}%",
                'action' => 'full-discovery',
                'action_label' => 'Full Sync',
            ];
        }

        return $alerts;
    }

    /**
     * 🔄 Run Field Discovery
     */
    public function runFieldDiscovery(): void
    {
        $this->isDiscovering = true;
        $this->showDiscoveryModal = true;

        try {
            $this->discoveryResults = $this->discoveryService->discoverAllChannels();

            $this->dispatch('discovery-completed', [
                'message' => "Discovery completed! Processed {$this->discoveryResults['processed_accounts']} accounts",
                'type' => 'success',
            ]);
        } catch (\Exception $e) {
            $this->discoveryResults = [
                'success' => false,
                'error' => $e->getMessage(),
            ];

            $this->dispatch('discovery-failed', [
                'message' => "Discovery failed: {$e->getMessage()}",
                'type' => 'error',
            ]);
        } finally {
            $this->isDiscovering = false;
        }
    }

    /**
     * 🔄 Sync Value Lists
     */
    public function syncValueLists(): void
    {
        try {
            $results = $this->discoveryService->syncOutdatedValueLists();

            $this->dispatch('sync-completed', [
                'message' => "Synced {$results['summary']['successful']} value lists",
                'type' => 'success',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('sync-failed', [
                'message' => "Sync failed: {$e->getMessage()}",
                'type' => 'error',
            ]);
        }
    }

    /**
     * 🔄 Run Full Discovery
     */
    public function runFullDiscovery(): void
    {
        $this->runFieldDiscovery();
        $this->syncValueLists();
    }

    /**
     * 📋 Handle Alert Actions
     */
    public function handleAlertAction(string $action): void
    {
        match ($action) {
            'discover-fields' => $this->runFieldDiscovery(),
            'sync-value-lists' => $this->syncValueLists(),
            'full-discovery' => $this->runFullDiscovery(),
            default => null,
        };
    }

    /**
     * 🎯 Set Active Tab
     */
    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    /**
     * 🎯 Set Channel Filter
     */
    public function setChannelFilter(string $channel): void
    {
        $this->selectedChannel = $channel === $this->selectedChannel ? '' : $channel;
        $this->resetPage();
    }

    /**
     * 🎯 Set Sync Account Filter
     */
    public function setSyncAccountFilter(string $syncAccountId): void
    {
        $this->selectedSyncAccount = $syncAccountId === $this->selectedSyncAccount ? '' : $syncAccountId;
        $this->resetPage();
    }

    /**
     * 🎯 Close Discovery Modal
     */
    public function closeDiscoveryModal(): void
    {
        $this->showDiscoveryModal = false;
        $this->discoveryResults = [];
    }

    /**
     * 📊 Get Channel Summary
     */
    public function getChannelSummary(string $channelType): array
    {
        return [
            'fields' => ChannelFieldDefinition::where('channel_type', $channelType)->count(),
            'mappings' => ChannelFieldMapping::whereHas('syncAccount', function ($query) use ($channelType) {
                $query->where('marketplace_type', $channelType);
            })->count(),
            'value_lists' => ChannelValueList::where('channel_type', $channelType)->count(),
            'sync_accounts' => SyncAccount::where('marketplace_type', $channelType)->where('is_active', true)->count(),
        ];
    }

    /**
     * 🎨 Get Health Status Color
     */
    public function getHealthStatusColor(float $score): string
    {
        return match (true) {
            $score >= 90 => 'green',
            $score >= 70 => 'blue',
            $score >= 50 => 'yellow',
            default => 'red',
        };
    }

    /**
     * 🎨 Get Health Status Text
     */
    public function getHealthStatusText(float $score): string
    {
        return match (true) {
            $score >= 90 => 'Excellent',
            $score >= 70 => 'Good',
            $score >= 50 => 'Fair',
            default => 'Poor',
        };
    }

    public function render()
    {
        return view('livewire.channel-mapping.channel-mapper-dashboard');
    }
}
