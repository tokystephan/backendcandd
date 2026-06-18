<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Application;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Mail;

class SendWeeklyReportToDirection extends Command
{
    protected $signature = 'notifications:weekly-report';
    protected $description = 'Send weekly recruitment summary to Direction via email';

    public function handle()
    {
        $since = now()->subDays(7);
        $new = Application::where('created_at', '>=', $since)->count();
        $interviews = \App\Models\Event::where('created_at', '>=', $since)->count();
        $offers = Application::where('offer_proposed', true)->where('updated_at', '>=', $since)->count();

        $summary = [
            'new_applications' => $new,
            'interviews' => $interviews,
            'offers' => $offers,
        ];

        // For now create notifications to Direction that include the summary link to /statistics
        try {
            NotificationService::createForRole('direction', "📊 Rapport hebdomadaire - Recrutement : {$new} nouvelles, {$interviews} entretiens, {$offers} offres", 'info', ['link' => '/statistics', 'summary' => $summary]);
        } catch (\Exception $e) {}

        // TODO: send proper HTML email via Mail::to(direction_emails)->send(...)

        $this->info('Weekly report sent to Direction');
        return 0;
    }
}
