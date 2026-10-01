<?php

namespace App\Console\Commands;

use App\Models\InstallerApplication;
use App\Services\InstallerApplicationConverter;
use Illuminate\Console\Command;

class ConvertInstallerApplications extends Command
{
    protected $signature = 'installer:convert-applications
                            {--application=* : ID конкретных заявок}
                            {--publish : Сразу опубликовать профили после проверки согласия}';

    protected $description = 'Создать профили монтажников из принятых заявок без дублей';

    public function handle(InstallerApplicationConverter $converter): int
    {
        $ids = collect($this->option('application'))->filter()->map(fn ($id) => (int) $id);

        $query = InstallerApplication::query()
            ->where('status', 'approved')
            ->whereNull('installer_profile_id')
            ->orderBy('id');

        if ($ids->isNotEmpty()) {
            $query->whereIn('id', $ids);
        }

        $applications = $query->get();

        if ($applications->isEmpty()) {
            $this->info('Нет принятых заявок без профиля.');

            return self::SUCCESS;
        }

        foreach ($applications as $application) {
            $profile = $converter->convert($application, (bool) $this->option('publish'));
            $this->line("#{$application->id} {$application->contact_name} -> профиль #{$profile->id} {$profile->slug}");
        }

        $this->info("Создано или связано профилей: {$applications->count()}");

        return self::SUCCESS;
    }
}
