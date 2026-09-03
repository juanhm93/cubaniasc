<?php

namespace Tests\Unit;

use App\Services\DeploymentTarget;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class DeploymentTargetTest extends TestCase
{
    public function test_develop_branch_maps_to_staging_purphura_subdomain(): void
    {
        $target = DeploymentTarget::fromBranch('develop');

        $this->assertNotNull($target);
        $this->assertSame('staging', $target->name);
        $this->assertSame('https://cubania.purphura.com/', $target->appUrl);
        $this->assertSame('FTP_STAGING_SERVER', $target->ftpServerSecret);
        $this->assertSame('FTP_STAGING_USERNAME', $target->ftpUsernameSecret);
        $this->assertSame('FTP_STAGING_PASSWORD', $target->ftpPasswordSecret);
        $this->assertSame('FTP_STAGING_SERVER_DIR', $target->ftpServerDirSecret);
    }

    public function test_main_branch_maps_to_production_cubaniasc(): void
    {
        $target = DeploymentTarget::fromBranch('main');

        $this->assertNotNull($target);
        $this->assertSame('production', $target->name);
        $this->assertSame('https://cubaniasc.com', $target->appUrl);
        $this->assertSame('FTP_PRODUCTION_SERVER', $target->ftpServerSecret);
        $this->assertSame('FTP_PRODUCTION_USERNAME', $target->ftpUsernameSecret);
        $this->assertSame('FTP_PRODUCTION_PASSWORD', $target->ftpPasswordSecret);
        $this->assertSame('FTP_PRODUCTION_SERVER_DIR', $target->ftpServerDirSecret);
    }

    public function test_unknown_branches_are_not_deployable(): void
    {
        $this->assertNull(DeploymentTarget::fromBranch('workos'));
        $this->assertNull(DeploymentTarget::fromBranch('feature/ftp'));
    }

    public function test_github_workflow_deploys_only_develop_and_main_on_push(): void
    {
        /** @var array<string, mixed> $workflow */
        $workflow = Yaml::parseFile(base_path('.github/workflows/deploy.yml'));

        $this->assertSame(['develop', 'main'], $workflow['on']['push']['branches']);
        $this->assertArrayNotHasKey('pull_request', $workflow['on']);

        /** @var list<array<string, mixed>> $steps */
        $steps = $workflow['jobs']['laravel-tests']['steps'];
        $staging = $this->stepByName($steps, 'Deploy staging to Purphura subdomain');
        $production = $this->stepByName($steps, 'Deploy production to cubaniasc.com');

        $this->assertSame("github.ref == 'refs/heads/develop'", $staging['if']);
        $this->assertSame("github.ref == 'refs/heads/main'", $production['if']);
        $this->assertStringContainsString('secrets.FTP_STAGING_SERVER', (string) $staging['with']['server']);
        $this->assertStringContainsString('secrets.FTP_SERVER', (string) $staging['with']['server']);
        $this->assertSame('${{ secrets.FTP_PRODUCTION_SERVER }}', $production['with']['server']);
        $this->assertSame('${{ secrets.FTP_PRODUCTION_USERNAME }}', $production['with']['username']);
        $this->assertSame('${{ secrets.FTP_PRODUCTION_PASSWORD }}', $production['with']['password']);
        $this->assertSame('${{ secrets.FTP_PRODUCTION_SERVER_DIR }}', $production['with']['server-dir']);
        $this->assertStringContainsString('production', (string) $workflow['jobs']['laravel-tests']['environment']['name']);
        $this->assertStringContainsString('staging', (string) $workflow['jobs']['laravel-tests']['environment']['name']);
    }

    public function test_environment_example_files_match_deploy_urls(): void
    {
        $staging = (string) file_get_contents(base_path('.env.staging.example'));
        $production = (string) file_get_contents(base_path('.env.production.example'));

        $this->assertStringContainsString('APP_ENV=staging', $staging);
        $this->assertStringContainsString('APP_URL=https://cubania.purphura.com/', $staging);
        $this->assertStringContainsString('APP_DEBUG=false', $staging);

        $this->assertStringContainsString('APP_ENV=production', $production);
        $this->assertStringContainsString('APP_URL=https://cubaniasc.com', $production);
        $this->assertStringContainsString('APP_DEBUG=false', $production);
        $this->assertStringContainsString('SESSION_DOMAIN=.cubaniasc.com', $production);
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     * @return array<string, mixed>
     */
    private function stepByName(array $steps, string $name): array
    {
        foreach ($steps as $step) {
            if (($step['name'] ?? null) === $name) {
                return $step;
            }
        }

        $this->fail("Missing workflow step [{$name}].");
    }
}
