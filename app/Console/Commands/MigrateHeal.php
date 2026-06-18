<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class MigrateHeal extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:heal {--seed=0 : Run DatabaseSeeder at the end}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-heal database by running missing migrations and retrying failures';

    public function handle(): int
    {
        $this->info('🔎 Starting migrate:heal ...');

        $this->ensureMigrationsTable();

        $expectedTables = [
            'roles',
            'users',
            'departments',
            'contract_types',
            'post_skills',
            'candidates',
            'applications',
            'application_status_history',
            'application_comments',
            'shortlists',
            'sessions',
            'events',
            'interview_reports',
            'notifications',
            'sources',
            'posts',
        ];

        $missingTables = array_values(array_filter($expectedTables, function ($t) {
            return !$this->tableExists($t);
        }));

        if (!empty($missingTables)) {
            $this->warn('🧱 Missing tables detected: ' . implode(', ', $missingTables));
        } else {
            $this->info('✅ No missing tables detected in the expected list.');
        }

        $this->runWithRetry('migrate', 2);

        // On évite migrate:refresh (qui fait drop de tables) car ça casse
        // dès qu'il y a des FK existantes et empêche le heal d'avancer.
        // À la place, on exécute simplement les migrations manquantes.
        // Si tu veux un reset complet, il faudra supprimer les contraintes manuellement.
        if (!empty($missingTables)) {
            $this->runWithRetry('migrate', 1);
        }


        if ((int) $this->option('seed') === 1) {
            $this->runWithRetry('db:seed', 1);
        }

        $this->info('🎉 migrate:heal finished.');
        return 0;
    }

    private function ensureMigrationsTable(): void
    {
        try {
            DB::table('migrations')->count();
        } catch (\Throwable $e) {
            $this->warn('ℹ️ migrations table not found yet, running migrate to create baseline...');
            Artisan::call('migrate', ['--force' => true]);
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function runWithRetry(string $command, int $retries = 2): void
    {
        $attempt = 0;
        while ($attempt <= $retries) {
            try {
                $this->info("▶️ Running: {$command} (attempt " . ($attempt + 1) . '/' . ($retries + 1) . ')');

                if ($command === 'db:seed') {
                    Artisan::call($command, ['--force' => true]);
                } else {
                    Artisan::call($command, ['--force' => true]);
                }

                $this->info("✅ {$command} succeeded");
                return;
            } catch (\Throwable $e) {
                $msg = $e->getMessage();

                // If a table already exists, it usually means the migration was partially applied.
                // We don't want to fail the whole heal process.
                if (str_contains($msg, 'already exists') || str_contains($msg, 'Base table or view already exists')) {
                    $this->warn("⚠️ {$command} failed but table already exists. Continuing. (" . $msg . ')');
                    return;
                }

                $attempt++;
                $this->error("❌ {$command} failed: " . $msg);

                if ($attempt > $retries) {
                    $this->error('⛔ No more retries. Stopping.');
                    throw $e;
                }

                $this->warn('🔁 Retrying...');
            }
        }
    }
}


