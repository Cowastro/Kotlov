<?php

namespace App\Console\Commands;

use App\Services\Shipping\VamExpressException;
use App\Services\Shipping\VamExpressPortal;
use App\Services\Shipping\VamExpressProfiles;
use App\Services\Shipping\VamExpressSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncVamExpressProfilesCommand extends Command
{
    protected $signature = 'shipping:sync-vamexpress-profiles {--limit=10}';

    protected $description = 'Прочитать профили отправителей из существующих заявок ВАМ Экспресс';

    public function handle(VamExpressSettings $settings, VamExpressPortal $portal, VamExpressProfiles $profiles): int
    {
        if (! $settings->configured()) {
            $this->warn('Сначала включите подключение ВАМ Экспресс.');

            return self::FAILURE;
        }
        $lock = Cache::lock('vam-express-sync', 600);
        if (! $lock->get()) {
            $this->warn('Сейчас выполняется обмен ВАМ Экспресс. Повторите позже.');

            return self::FAILURE;
        }
        try {
            $credentials = $settings->get();
            $portal->login($credentials['username'], $credentials['password']);
            $this->info('Профилей обновлено: '.$profiles->fill($portal, (int) $this->option('limit')));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e instanceof VamExpressException ? $e->getMessage() : 'Не удалось обновить профили ВАМ Экспресс.');

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
