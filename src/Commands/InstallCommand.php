<?php

namespace Metrial\RBAC\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class InstallCommand extends Command
{
    protected $signature = 'rbac:install {--force : Overwrite existing files}';

    protected $description = 'Scaffold the User model with RBAC traits and publish config';

    public function handle(): int
    {
        $this->publishConfig();
        $this->publishMigrations();
        $this->scaffoldUserModel();

        $this->info('Metrial RBAC installed successfully.');
        $this->line('Run <comment>php artisan migrate</comment> to create the database tables.');

        return self::SUCCESS;
    }

    protected function publishConfig(): void
    {
        $this->callSilent('vendor:publish', [
            '--tag' => 'rbac-config',
            '--force' => $this->option('force'),
        ]);
        $this->info('Published rbac.php config.');
    }

    protected function publishMigrations(): void
    {
        $this->callSilent('vendor:publish', [
            '--tag' => 'rbac-migrations',
            '--force' => $this->option('force'),
        ]);
        $this->info('Published migrations.');
    }

    protected function scaffoldUserModel(): void
    {
        $userModel = config('rbac.user_model', config('auth.providers.users.model'));
        $path = app_path('Models/User.php');

        if (! file_exists($path)) {
            $path = app_path('User.php');
        }

        if (! file_exists($path)) {
            $this->warn("User model not found at {$path}. Please add HasRoles and HasPermissions traits manually.");

            return;
        }

        $content = file_get_contents($path);

        // Check if already scaffolded
        if (str_contains($content, 'HasRoles')) {
            $this->info('User model already has HasRoles trait.');

            return;
        }

        $content = $this->addTrait($content, 'HasRoles');
        $content = $this->addTrait($content, 'HasPermissions');
        $content = $this->addImport($content, 'Metrial\RBAC\Traits\HasRoles');
        $content = $this->addImport($content, 'Metrial\RBAC\Traits\HasPermissions');

        file_put_contents($path, $content);

        $this->info("Added HasRoles and HasPermissions traits to {$userModel}.");
    }

    protected function addTrait(string $content, string $trait): string
    {
        // Add use trait; inside the class body
        return preg_replace(
            '/(class\s+\w+\s+extends\s+[^{]+\{)/',
            "$1\n    use {$trait};\n",
            $content,
            1
        );
    }

    protected function addImport(string $content, string $import): string
    {
        // Check if import already exists
        if (str_contains($content, "use {$import};")) {
            return $content;
        }

        // Add after the opening <?php tag
        return preg_replace(
            '/(<\?php\s*\n)/',
            "$1use {$import};\n",
            $content,
            1
        );
    }
}
