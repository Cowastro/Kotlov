<?php

namespace App\Notifications;

use App\Models\InstallRequest;
use Illuminate\Notifications\Notification;

class NewInstallRequestNotification extends Notification
{
    public function __construct(private InstallRequest $installRequest) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $request = $this->installRequest;

        $specializations = [
            'heating'       => 'Монтаж котла',
            'heatpump'      => 'Монтаж теплового насоса',
            'fireplace'     => 'Монтаж камина',
            'chimney'       => 'Монтаж дымохода',
            'sauna'         => 'Монтаж банной печи',
            'service'       => 'Сервис',
            'commissioning' => 'Пусконаладка',
            'other'         => 'Другое',
        ];

        $specialization = $specializations[$request->specialization] ?? $request->specialization ?? '—';

        $installer = null;
        if ($request->installerProfile) {
            $installer = $request->installerProfile->name ?? 'ID ' . $request->installer_profile_id;
        }

        return [
            'install_request_id' => $request->id,
            'customer_name'      => $request->customer_name,
            'customer_phone'     => $request->customer_phone,
            'city'               => $request->city ?? '—',
            'specialization'     => $specialization,
            'installer'          => $installer,
            'url'                => url('/admin/install-requests/' . $request->id),
        ];
    }
}
