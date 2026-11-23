<?php

namespace App\Services\Shipping;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;

class VamExpressPortal
{
    private const BASE = 'https://vamexpress.by';

    private CookieJar $cookies;

    private string $listHtml = '';

    private float $lastRequest = 0;

    private float $requestInterval = 1.25;

    public function __construct(private VamExpressHtml $parser)
    {
        $this->cookies = new CookieJar;
    }

    public function draftReference(): array
    {
        $context = (new VamExpressDraftReference)->parse($this->request('GET', '/UserUI/TicketEdit.aspx'));
        $raw = $this->request('POST', '/AppServices/AutocompliteAPI.asmx/GetAvaliableuserProfilesForUserJson', [
            'query' => '', 'appkey' => $context['lookup_key'], 'id' => $context['user_id'],
        ]);
        $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        $profiles = [];
        foreach ($data['suggestions'] ?? [] as $profile) {
            $id = (string) ($profile['data'] ?? '');
            if (isset($context['profiles'][$id]) && is_string($profile['value'] ?? null)) {
                $profiles[$id] = strip_tags($profile['value']);
            }
        }
        if (count($profiles) !== count($context['profiles'])) {
            throw new VamExpressException('Не удалось проверить список профилей текущего аккаунта.');
        }
        $context['profiles'] = $profiles;

        return $context;
    }

    public function searchCities(string $query, string $lookupKey): string
    {
        return $this->request('POST', '/AppServices/AutocompliteAPI.asmx/GetCityForAutocompliteJSON', ['query' => $query, 'appkey' => $lookupKey]);
    }

    public function estimate(array $context, array $payload): string
    {
        return $this->request('POST', '/WebServices/UserAPI/CalculatorAPI.asmx/Calculate', [
            'userid' => $context['calculator_user_id'], 'apikey' => $context['calculator_key'],
            'jsonString' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function login(string $username, string $password): void
    {
        $this->cookies = new CookieJar;
        $html = $this->request('GET', '/');
        $fields = $this->parser->hidden($html);
        if (! str_contains($html, 'ctl00$MainContent$tbUserPass')) {
            throw new VamExpressException('Форма входа ВАМ Экспресс изменилась. Автоматический вход остановлен.');
        }
        $this->request('POST', '/', array_merge($fields, [
            'ctl00$MainContent$tbUserLogin' => $username,
            'ctl00$MainContent$tbUserPass' => $password,
            'ctl00$MainContent$bSubmit' => 'Войти',
        ]));
        $this->listHtml = $this->request('GET', '/UserUI/TicketView.aspx');
        $this->parser->rows($this->listHtml);
    }

    public function recent(int $pages = 4): array
    {
        $html = $this->listHtml;
        $rows = [];
        for ($page = 1; $page <= min(4, max(1, $pages)); $page++) {
            foreach ($this->parser->rows($html) as $row) {
                $rows[$row['remote_id']] = $row;
            }
            $target = $this->parser->nextPageTarget($html, $page + 1);
            if (! $target || $page === $pages) {
                break;
            }
            $html = $this->request('POST', '/UserUI/TicketView.aspx', array_merge($this->parser->hidden($html), [
                '__EVENTTARGET' => $target, '__EVENTARGUMENT' => '',
            ]));
        }

        return array_values($rows);
    }

    public function find(string $id): array
    {
        return array_values(array_filter($this->parser->rows($this->findHtml($id)), fn (array $row): bool => strlen($id) === 32
            ? $row['remote_id'] === strtolower($id)
            : substr($row['remote_id'], 0, 7) === strtolower($id)));
    }

    private function findHtml(string $id): string
    {
        if (! preg_match('/^(?:[a-f0-9]{7}|[a-f0-9]{32})$/i', $id)) {
            throw new VamExpressException('Некорректный ID ВАМ Экспресс.');
        }
        // Search accepts the short ID shown in the portal, not the internal full key.
        $short = substr(strtolower($id), 0, 7);
        $html = $this->request('POST', '/UserUI/TicketView.aspx', array_merge($this->parser->hidden($this->listHtml), [
            'ctl00$MainContent$stbID' => $short,
            'ctl00$MainContent$sddlStatus' => '',
            'ctl00$MainContent$btnSearch' => 'Искать',
            '__EVENTTARGET' => '', '__EVENTARGUMENT' => '',
        ]));

        $this->parser->rows($html);

        return $html;
    }

    public function label(string $remoteId, string $format): string
    {
        if (! preg_match('/^[a-f0-9]{32}$/', $remoteId) || ! in_array($format, ['a4', '78'], true)) {
            throw new VamExpressException('Некорректная заявка или формат этикетки.');
        }
        $html = $this->findHtml($remoteId);
        $xpath = $this->parser->xpath($html);
        $checkboxes = $xpath->query('//a[@href="TicketEdit.aspx?id='.$remoteId.'"]/ancestor::tr[1]//input[@type="checkbox"][@name]');
        if ($checkboxes->length !== 1 || ! preg_match('/^ctl00\$MainContent\$lvAllTickets\$ctrl\d+\$cbSelect$/', $checkboxes->item(0)->getAttribute('name'))) {
            throw new VamExpressException('Заявка не найдена в кабинете ВАМ Экспресс. Обновите её по ID.');
        }
        $button = $format === 'a4' ? 'btnPrintVinil' : 'btnPrintVinilForVinilPrinter';
        $input = $xpath->query('//input[@name="ctl00$MainContent$'.$button.'"]');
        if ($input->length !== 1) {
            throw new VamExpressException('Форма печати ВАМ Экспресс изменилась.');
        }
        $label = $this->request('POST', '/UserUI/TicketView.aspx', array_merge($this->parser->hidden($html), [
            '__EVENTTARGET' => '', '__EVENTARGUMENT' => '',
            $checkboxes->item(0)->getAttribute('name') => 'on',
            'ctl00$MainContent$'.$button => $input->item(0)->getAttribute('value'),
        ]));

        return (new VamExpressLabelHtml)->extract($label, substr($remoteId, 0, 7));
    }

    public function senderProfile(string $remoteId): string
    {
        if (! preg_match('/^[a-f0-9]{32}$/', $remoteId)) {
            throw new VamExpressException('Некорректный ID заявки ВАМ Экспресс.');
        }
        $this->requestInterval = 2.5;

        return $this->parser->senderProfile($this->request('GET', '/UserUI/TicketEdit.aspx?id='.$remoteId));
    }

    private function request(string $method, string $path, array $data = []): string
    {
        $pause = $this->requestInterval - (microtime(true) - $this->lastRequest);
        if ($pause > 0 && ! app()->runningUnitTests()) {
            usleep((int) ($pause * 1000000));
        }
        $this->lastRequest = microtime(true);
        try {
            $client = Http::asForm()->connectTimeout(10)->timeout(25)->withOptions([
                'cookies' => $this->cookies,
                'allow_redirects' => ['max' => 4, 'protocols' => ['https'], 'on_redirect' => function ($request, $response, $uri): void {
                    if ($uri->getHost() !== 'vamexpress.by') {
                        throw new VamExpressException('Unexpected carrier redirect');
                    }
                }],
            ]);
            $response = $method === 'GET' ? $client->get(self::BASE.$path) : $client->post(self::BASE.$path, $data);
            if (! $response->successful() || strlen($response->body()) > 5_000_000) {
                throw new VamExpressException('Carrier unavailable');
            }

            return $response->body();
        } catch (\Throwable) {
            // Never expose credentials, cookies, response bodies or request payloads in logs/UI.
            throw new VamExpressException('ВАМ Экспресс недоступен. Повторная автоматическая попытка — не раньше чем через 30 минут.');
        }
    }
}
