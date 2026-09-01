<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * One-off diagnostic: inspect the TLS certificate a remote host actually
 * presents to THIS server (not to a browser), to distinguish a genuine
 * expired/misconfigured cert from an anti-bot WAF serving a different
 * cert to non-browser clients (common TLS-fingerprint-based blocking).
 *
 *   php artisan debug:ssl-check --host=belkomin.com
 */
class DebugSslCheckCommand extends Command
{
    protected $signature = 'debug:ssl-check {--host=belkomin.com} {--port=443}';

    protected $description = 'Dump the TLS certificate a remote host presents to this server';

    public function handle(): int
    {
        $host = (string) $this->option('host');
        $port = (int) $this->option('port');

        $this->info("Connecting to {$host}:{$port} ...");

        // Attempt 1: with verification, capturing the actual error
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false, // capture cert even if invalid, for inspection only
                'verify_peer_name' => false,
                'SNI_enabled' => true,
                'peer_name' => $host,
            ],
        ]);

        $errno = 0;
        $errstr = '';
        $client = @stream_socket_client(
            "ssl://{$host}:{$port}",
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (! $client) {
            $this->error("Connection failed: [{$errno}] {$errstr}");
            return self::FAILURE;
        }

        $params = stream_context_get_params($client);
        $cert = $params['options']['ssl']['peer_certificate'] ?? null;

        if (! $cert) {
            $this->error('No certificate captured.');
            fclose($client);
            return self::FAILURE;
        }

        $info = openssl_x509_parse($cert);
        fclose($client);

        $this->info('Certificate presented by server:');
        $this->line('  subject CN: ' . ($info['subject']['CN'] ?? '?'));
        $this->line('  subject O: ' . ($info['subject']['O'] ?? '?'));
        $this->line('  issuer CN: ' . ($info['issuer']['CN'] ?? '?'));
        $this->line('  issuer O: ' . ($info['issuer']['O'] ?? '?'));
        $this->line('  valid from: ' . date('Y-m-d H:i:s', $info['validFrom_time_t'] ?? 0));
        $this->line('  valid to: ' . date('Y-m-d H:i:s', $info['validTo_time_t'] ?? 0));
        $this->line('  now: ' . date('Y-m-d H:i:s'));
        $this->line('  expired: ' . (($info['validTo_time_t'] ?? 0) < time() ? 'YES' : 'no'));
        $altNames = $info['extensions']['subjectAltName'] ?? '(none)';
        $this->line('  SAN: ' . $altNames);

        // Now try a real verified connection like the sync command does, to
        // capture the EXACT verify-failure reason.
        $this->newLine();
        $this->info('Now attempting a verified fetch (like the sync command)...');
        $verifiedContext = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Mozilla/5.0 (compatible; KotlovBot/1.0)\r\n",
                'timeout' => 15,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents("https://{$host}/", false, $verifiedContext);
        if ($body === false) {
            $err = error_get_last();
            $this->error('Verified fetch FAILED: ' . ($err['message'] ?? 'unknown'));
        } else {
            $this->info('Verified fetch SUCCEEDED, ' . strlen($body) . ' bytes, first 200 chars:');
            $this->line(mb_substr(strip_tags($body), 0, 200));
        }

        // Also try via cURL with verbose-ish info, since curl may use a
        // different CA bundle / TLS stack than PHP streams.
        $this->newLine();
        $this->info('Now attempting via cURL...');
        $ch = curl_init("https://{$host}/");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; KotlovBot/1.0)',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $curlBody = curl_exec($ch);
        $curlErr = curl_error($ch);
        $curlErrno = curl_errno($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlBody === false) {
            $this->error("cURL FAILED: [{$curlErrno}] {$curlErr}");
        } else {
            $this->info("cURL SUCCEEDED, HTTP {$httpCode}, " . strlen($curlBody) . ' bytes');
        }

        return self::SUCCESS;
    }
}
