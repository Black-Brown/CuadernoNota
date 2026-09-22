<?php

namespace Tests\Feature;

use Tests\TestCase;

class VercelDeploymentTest extends TestCase
{
    public function test_vercel_startup_does_not_run_database_mutations(): void
    {
        $script = file_get_contents(base_path('docker/vercel/entrypoint.sh'));
        $this->assertStringNotContainsString('artisan migrate', $script);
        $this->assertStringNotContainsString('artisan db:seed', $script);
        $this->assertStringNotContainsString('artisan optimize:clear', $script);
        $this->assertStringContainsString('${APP_KEY:?', $script);
        $this->assertStringContainsString('exec "$@"', $script);
    }

    public function test_vercel_image_uses_shared_state_and_temporary_storage(): void
    {
        $dockerfile = file_get_contents(base_path('Dockerfile.vercel'));
        $this->assertStringContainsString('CACHE_STORE=database SESSION_DRIVER=database', $dockerfile);
        $this->assertStringContainsString('LARAVEL_STORAGE_PATH=/tmp/laravel-storage', $dockerfile);
        $this->assertStringContainsString('APP_CONFIG_CACHE=/tmp/laravel-config.php', $dockerfile);
        $this->assertStringContainsString('pdo_pgsql', $dockerfile);
        $this->assertStringNotContainsString('php -S', $dockerfile);
        $nginx = file_get_contents(base_path('docker/vercel/nginx.conf.template'));
        $this->assertStringContainsString('location = /index.php', $nginx);
        foreach (['client_body', 'fastcgi', 'proxy', 'uwsgi', 'scgi'] as $module) {
            $this->assertStringContainsString($module.'_temp_path /tmp/nginx/', $nginx);
        }
        $this->assertStringContainsString('location ~ \\.php$ { return 404; }', $nginx);
    }
}
