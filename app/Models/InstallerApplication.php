<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallerApplication extends Model
{
    protected $fillable = [
        'installer_profile_id',
        'contact_name',
        'phone',
        'email',
        'city',
        'company_name',
        'experience_years',
        'specializations',
        'message',
        'source',
        'status',
        'admin_notes',
        'telegram_message_id',
        'telegram_notified_at',
    ];

    protected $casts = [
        'specializations' => 'array',
        'experience_years' => 'integer',
        'telegram_message_id' => 'integer',
        'telegram_notified_at' => 'datetime',
    ];

    public static array $statuses = [
        'new' => 'Новая',
        'contacted' => 'Связались',
        'approved' => 'Принята',
    ];

    public static array $sourceLabels = [
        'installers-catalog' => 'Быстрая форма в каталоге',
        'become-installer' => 'Страница «Стать монтажником»',
        'outreach-messenger' => 'Рассылка Telegram / Viber',
        'category-cta' => 'Призыв в товарном каталоге',
        'partners' => 'Страница партнёров',
    ];

    public static array $specializationLabels = [
        'kotly' => 'Монтаж котлов',
        'teplovye_nasosy' => 'Тепловые насосы',
        'kaminy' => 'Камины и печи',
        'dymohody' => 'Дымоходы',
        'otoplenie' => 'Системы отопления',
        'radiatory' => 'Радиаторы',
        'tverdotoplivnye_kotly' => 'Твердотопливные котлы',
        'pelletnye_kotly' => 'Пеллетные котлы',
        'teplye_poly' => 'Тёплые полы',
        'bani' => 'Банные печи',
    ];

    public function installerProfile(): BelongsTo
    {
        return $this->belongsTo(InstallerProfile::class);
    }
}
