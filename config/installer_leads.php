<?php

return [
    // Off switch for the whole mailing.
    'enabled' => (bool) env('INSTALLER_LEADS_ENABLED', true),

    // Automatic mailing of every new request from the sources below; off — managers send a request with the
    // «Монтажникам» button in the admin.
    'auto' => (bool) env('INSTALLER_LEADS_AUTO', false),

    // While there are few installers every one gets every request; false = only their region and kind of work.
    'send_to_all' => (bool) env('INSTALLER_LEADS_SEND_TO_ALL', true),

    // How many installers may take one request and see the client's contacts.
    'max_takers' => 3,

    // At most this many installers are offered one request (the best-ranked first).
    'max_recipients' => 200,

    // Sources of the requests offered to installers; landings of our own sales stay with the managers.
    'sources' => ['installers_page'],

    // A request older than this is not offered any more (e.g. the scheduler was down).
    'fresh_hours' => 72,

    // The personal link in the e-mail works this long.
    'link_days' => 7,
];
