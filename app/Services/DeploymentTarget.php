<?php

declare(strict_types=1);

namespace App\Services;

final readonly class DeploymentTarget
{
    public function __construct(
        public string $name,
        public string $branch,
        public string $appUrl,
        public string $ftpServerDir,
        public string $ftpServerSecret,
        public string $ftpUsernameSecret,
        public string $ftpPasswordSecret,
        public string $ftpServerDirSecret,
    ) {}

    /**
     * @return array<string, self>
     */
    public static function all(): array
    {
        /** @var array<string, array{branch: string, app_url: string, ftp_server_dir: string, secrets: array{server: string, username: string, password: string, server_dir: string}}> $environments */
        $environments = config('deploy.environments');
        $targets = [];

        foreach ($environments as $name => $environment) {
            $targets[$name] = new self(
                name: $name,
                branch: $environment['branch'],
                appUrl: $environment['app_url'],
                ftpServerDir: $environment['ftp_server_dir'],
                ftpServerSecret: $environment['secrets']['server'],
                ftpUsernameSecret: $environment['secrets']['username'],
                ftpPasswordSecret: $environment['secrets']['password'],
                ftpServerDirSecret: $environment['secrets']['server_dir'],
            );
        }

        return $targets;
    }

    public static function fromBranch(string $branch): ?self
    {
        foreach (self::all() as $target) {
            if ($target->branch === $branch) {
                return $target;
            }
        }

        return null;
    }
}
