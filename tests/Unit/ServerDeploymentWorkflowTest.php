<?php

namespace Tests\Unit;

use Tests\TestCase;

class ServerDeploymentWorkflowTest extends TestCase
{
    public function test_safe_deploy_installs_locked_composer_dependencies_before_artisan_runs(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/server-artisan.yml'));

        $this->assertIsString($workflow);

        $pull = strpos($workflow, 'git pull --ff-only origin main');
        $install = strpos($workflow, '"$composer_bin" install');
        $migrate = strpos($workflow, 'artisan migrate --force');

        $this->assertNotFalse($pull);
        $this->assertNotFalse($install);
        $this->assertNotFalse($migrate);
        $this->assertLessThan($install, $pull);
        $this->assertLessThan($migrate, $install);
        $this->assertStringContainsString('--no-dev', $workflow);
        $this->assertStringContainsString('--optimize-autoloader', $workflow);
    }

    public function test_pdf_diagnostic_has_a_dedicated_safe_output_mode(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/server-artisan.yml'));

        $this->assertIsString($workflow);
        $this->assertStringContainsString('verify-commercial-pdf)', $workflow);
        $this->assertStringContainsString('commercial-documents:verify-pdf', $workflow);
        $this->assertStringContainsString('safe_diagnostic_output_begin', $workflow);
        $this->assertStringContainsString('safe_diagnostic_output_end', $workflow);
    }
}
