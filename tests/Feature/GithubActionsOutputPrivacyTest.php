<?php

namespace Tests\Feature;

use Tests\TestCase;

class GithubActionsOutputPrivacyTest extends TestCase
{
    public function test_server_workflows_publish_only_aggregate_diagnostics(): void
    {
        $manual = file_get_contents(base_path('.github/workflows/server-artisan.yml'));
        $queued = file_get_contents(base_path('.github/workflows/server-artisan-queue.yml'));

        foreach ([$manual, $queued] as $workflow) {
            $this->assertStringContainsString('detail_log="storage/logs/github-actions-${TASK}.log"', $workflow);
            $this->assertStringContainsString(') > "$detail_log" 2>&1', $workflow);
            $this->assertStringContainsString('output_lines=$(wc -l < "$detail_log"', $workflow);
            $this->assertStringContainsString('error_lines=$(grep -Eic', $workflow);
            $this->assertStringContainsString('Personal-data arguments are forbidden', $workflow);
            $this->assertStringNotContainsString('tail -n ', $workflow);
            $this->assertStringNotContainsString('ps -ef | grep artisan', $workflow);
        }

        $this->assertStringContainsString(
            "grep -E '^(task|status|output_lines|output_bytes|error_lines|warning_lines|detail_log)=",
            $queued,
        );
        $this->assertStringNotContainsString('Artisan args:', $queued);
        $this->assertStringNotContainsString('Log file:', $queued);
        $this->assertStringNotContainsString('tail -c ', $queued);
        $this->assertStringNotContainsString('curl -sS -I', $queued);
    }

    public function test_tracked_result_contains_no_raw_http_or_session_data(): void
    {
        $result = strtolower(file_get_contents(base_path('.github/server-artisan-result.md')));

        $this->assertStringContainsString('only aggregate fields', $result);
        $this->assertStringNotContainsString('set-cookie:', $result);
        $this->assertStringNotContainsString('xsrf-token=', $result);
        $this->assertStringNotContainsString('kotlov-session=', $result);
        $this->assertStringNotContainsString('artisan args:', $result);
    }
}
