<?php

namespace Kailyn\Console\Commands;

use Kailyn\Console\Command;

class AuthInstallCommand extends Command
{
    protected string $signature = 'auth:install {--force} {--admin} {--name=} {--email=} {--password=}';
    protected string $description = 'Install the admin panel with authentication (AdminLTE)';

    public function handle(): int
    {
        $app = $this->getApplication()->getKailynApp();
        $base = $app->basePath();
        $stubRoot = $base . '/stubs/panel';
        $force = $this->option('force');

        if (!is_dir($stubRoot)) {
            $this->error("Panel stubs not found: {$stubRoot}");
            return self::FAILURE;
        }

        $summary = [];

        // 1. Ensure database directory exists
        $databaseDir = $base . '/database';
        if (!is_dir($databaseDir)) {
            mkdir($databaseDir, 0755, true);
            $summary[] = 'Created database/ directory';
        }

        // 2. Publish users migration (only if missing)
        $migrationPath = $databaseDir . '/migrations';
        if (!is_dir($migrationPath)) {
            mkdir($migrationPath, 0755, true);
        }

        $existingMigration = glob($migrationPath . '/*_create_users_table.php');
        if (empty($existingMigration)) {
            $filename = date('Y_m_d_His') . '_create_users_table.php';
            copy($stubRoot . '/migrations/create_users_table.php', $migrationPath . '/' . $filename);
            $summary[] = "Migration created: {$filename}";
        } else {
            $summary[] = 'Migration for users table already exists (skipped)';
        }

        // 3. Publish AdminLTE assets (always overwrite — vendored build)
        $this->copyDirectory(
            $stubRoot . '/public/vendor/panel',
            $base . '/public/vendor/panel'
        );
        $summary[] = 'Panel assets published to public/vendor/panel';

        // 4. Publish views (auth + panel)
        $viewTargets = [
            'views/panel/layouts/app.html' => 'app/Views/panel/layouts/app.html',
            'views/panel/partials/navbar.html' => 'app/Views/panel/partials/navbar.html',
            'views/panel/partials/sidebar.html' => 'app/Views/panel/partials/sidebar.html',
            'views/panel/partials/footer.html' => 'app/Views/panel/partials/footer.html',
            'views/panel/dashboard.html' => 'app/Views/panel/dashboard.html',
            'views/auth/login.html' => 'app/Views/auth/login.html',
            'views/auth/register.html' => 'app/Views/auth/register.html',
        ];

        $existingViews = array_filter(
            $viewTargets,
            fn(string $target) => file_exists($base . '/' . $target)
        );

        $overwriteViews = $force;

        if (!empty($existingViews) && !$force) {
            $overwriteViews = $this->confirm(
                'Some auth/panel views already exist. Overwrite them with the panel design?',
                false
            );
        }

        foreach ($viewTargets as $source => $target) {
            $targetPath = $base . '/' . $target;

            if (file_exists($targetPath) && !$overwriteViews) {
                continue;
            }

            $dir = dirname($targetPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            copy($stubRoot . '/' . $source, $targetPath);
        }

        $summary[] = $overwriteViews
            ? 'Views published (overwritten)'
            : (empty($existingViews) ? 'Views published' : 'Existing views kept');

        // 5. Publish controllers (only if missing)
        $controllerStubs = [
            'controllers/AuthController.php' => 'app/Controllers/AuthController.php',
            'controllers/Panel/DashboardController.php' => 'app/Controllers/Panel/DashboardController.php',
        ];

        $publishedControllers = [];

        foreach ($controllerStubs as $source => $target) {
            $targetPath = $base . '/' . $target;

            if (file_exists($targetPath) && !$force) {
                continue;
            }

            $dir = dirname($targetPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            copy($stubRoot . '/' . $source, $targetPath);
            $publishedControllers[] = $target;
        }

        $summary[] = empty($publishedControllers)
            ? 'Controllers already exist (skipped, use --force to overwrite)'
            : 'Controllers published: ' . implode(', ', $publishedControllers);

        // 6. Append routes (idempotent, marker-based)
        $routesFile = $base . '/routes/web.php';
        $marker = '[kailyn:auth]';
        $routesContent = file_get_contents($routesFile);

        if (str_contains($routesContent, $marker)) {
            $summary[] = 'Auth routes already registered (skipped)';
        } else {
            $routesStub = file_get_contents($stubRoot . '/routes/auth.php');
            $routesStub = preg_replace('/^<\?php\s*/', '', $routesStub);
            file_put_contents($routesFile, $routesContent . "\n" . $routesStub);
            $summary[] = 'Auth + panel routes appended to routes/web.php';
        }

        // 7. Run migrations
        $this->newLine();
        $this->info('Running migrations...');
        $this->call('migrate');

        // 8. Admin user
        $this->newLine();

        $createAdmin = (bool) $this->option('admin')
            || $this->option('name') !== null
            || $this->option('email') !== null
            || $this->option('password') !== null;

        if (!$createAdmin) {
            $createAdmin = $this->confirm('Create an admin user now?', false);
        }

        if ($createAdmin) {
            $args = ['--create' => true];

            foreach (['name', 'email', 'password'] as $opt) {
                if ($this->option($opt) !== null) {
                    $args['--' . $opt] = $this->option($opt);
                }
            }

            $this->call('auth:user', $args);
        }

        // 9. Summary
        $this->newLine();
        $this->info('Auth + Admin Panel installed successfully.');
        $this->table(['Step', 'Result'], array_map(fn($s) => ['-', $s], $summary));

        $this->newLine();
        $this->line('  Next steps:');
        $this->line('    1. php bin/tulpar serve');
        $this->line('    2. Open /login and sign in with the admin user');

        return self::SUCCESS;
    }

    protected function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($source)) {
            return;
        }

        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $items = scandir($source);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $src = $source . '/' . $item;
            $dst = $destination . '/' . $item;

            if (is_dir($src)) {
                $this->copyDirectory($src, $dst);
            } else {
                copy($src, $dst);
            }
        }
    }
}