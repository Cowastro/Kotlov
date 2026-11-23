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

    public function __construct(private VamExpressHtml $parser) {}

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

        return array_values(array_filter($this->parser->rows($html), fn (array $row): bool => strlen($id) === 32
            ? $row['remote_id'] === strtolower($id)
            : substr($row['remote_id'], 0, 7) === $short));
    }

    private function request(string $method, string $path, array $data = []): string
    {
        $pause = 1.25 - (microtime(true) - $this->lastRequest);
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
