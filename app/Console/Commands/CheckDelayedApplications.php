<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Application;
use App\Services\NotificationService;

class CheckDelayedApplications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:check-delayed-applications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check applications with no status change for > 15 days and notify Direction';

    public function handle()
    {
        $threshold = now()->subDays(15);
        $apps = Application::where('updated_at', '<', $threshold)->get();

        foreach ($apps as $app) {
            try {
                $candidateName = $app->candidate ? $app->candidate->full_name : '';
                NotificationService::createForRole('direction', "⚠️ Alerte recrutement - délai pour " . $candidateName . " ({$app->id})", 'warning', [
                    'application_id' => $app->id,
                    'link' => "/applications/{$app->id}",
                ]);
            } catch (\Exception $e) {
                // ignore
            }
        }

        $this->info('Delayed applications checked: ' . $apps->count());
        return 0;
    }
}
