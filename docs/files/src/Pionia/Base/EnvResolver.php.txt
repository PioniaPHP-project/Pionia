<?php

namespace Pionia\Base;

use Pionia\Collections\Arrayable;
use Pionia\Utils\PathsTrait;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;

class EnvResolver
{
    use PathsTrait;
    /**
     * Resolved environment variables
     */
    private Arrayable $env;
    /*
     * The dotenv instance
     */
    public Dotenv $dotenv;

    /**
     * The path to the environment directory
     * @var mixed|string
     */
    private string $path;

    /**
     * All files in the environment directory
     * @var Arrayable|null
     */
    private ?Arrayable $allFiles;


    public function __construct(?string $path = 'Environment')
    {
        $this->env = new Arrayable();
        $this->dotenv = new Dotenv();
        $this->path = $path;
        // we have all files in the environment directory
        $this->allFiles = $this->all() ?? Arrayable::toArrayable([]);
        // load the environment variables
        $this->resolve();
    }

    /**
     * Load the environment variables
     */
    public function resolve(): void
    {
        // we shall resolve the .env files first
        $this->resolveDotEnv();
        // we shall resolve the ini files next
        $this->resolveIniFiles();

        $this->resolvePhpFiles();


        // if we found any section called server, we shall merge it with the environment and remove it
        if ($this->env->has('server')) {
            $this->dotenv->populate($this->env->get('SERVER'));
            $this->env->remove('server');
            $vars = str_ireplace('server', '', $_ENV['SYMFONY_DOTENV_VARS']);
            $vars = trim($vars, ',');
            $_ENV['SYMFONY_DOTENV_VARS'] = $vars;
            $_SERVER['SYMFONY_DOTENV_VARS'] = $vars;
            unset($_ENV['server']);
            unset($_SERVER['server']);
        }
        // we shall resolve the database configurations
        // auto-discover the databases in the environment
        $dbSections = [];
        $sectionCount= 0;
        $connections = [];
        $this->env->each(function ($db, $key) use (&$dbSections, &$sectionCount, &$connections) {
            if (is_array($db)) {
                $arr = Arrayable::toArrayable($db);
                if (($arr->has("database") && $arr->has("type"))
                    || ($arr->has("dsn"))
                    || ($arr->has("database_type")
                    || $arr->has("database_name"))
                ) {
                    $dbSections[$key] = $db;
                    $connections[] = $key;
                    $sectionCount++;
                    // if the developer defined the default db, we shall use this to make the default connection
                    if ($arr->has("default")) {
                        $dbSections['default'] = $key;
                    }
                }
            }
        });


        $dbSections['size'] = $sectionCount;
        $dbSections['connections'] = $connections;
        $this->dotenv->populate(['databases' => $dbSections], true);
        if (!$this->env->has('DEBUG')){
            $this->dotenv->populate(['DEBUG' => true], true);
        }
        // $this->dotenv->populate(['DEBUG' => true]);
        $this->env->merge($_ENV);
        $this->env->merge($_SERVER);
    }

    /**
     * Resolve the .env files in our environment
     * We let dotenv handle this
     * @return void
     */
    public function resolveDotEnv(): void
    {
        $iniFiles = $this->allFiles->get('env');
        if (empty($iniFiles)) {
            return;
        }
        $arr = Arrayable::toArrayable($iniFiles);
        if ($arr->isEmpty()){
            return;
        }

        $arr->each(function ($file) {
            $path = $this->envPath($file);
            $this->dotenv->loadEnv($path);
            $this->env->merge($_ENV);
        });
    }

    public function resolvePhpFiles(): void
    {
        $iniFiles = $this->allFiles->get('php');
        if (empty($iniFiles)) {
            return;
        }
        $arr = Arrayable::toArrayable($iniFiles);
        if ($arr->isEmpty()){
            return;
        }

        $arr->each(function ($file) {
            $path = $this->envPath($file);
            $settings = require $path;
            if ($settings) {
                $this->dotenv->populate($settings, true);
                $this->env->merge($_ENV);
            }
        });
    }

    /**
     * Resolve the ini files in our environment
     * If we find a database configuration file, we shall mark it as the database configuration file
     * @return void
     */
    public function resolveIniFiles(): void
    {
        $environment =  $this->env->get('APP_ENV') ?? $_ENV['APP_ENV'] ?? 'development';

        $iniFiles = $this->allFiles->get('ini');
        $arr = Arrayable::toArrayable($iniFiles ?? []);

        if ($arr->isEmpty()){
            return;
        }
        $filesToResolve = new Arrayable();
        // a file that does not target any environment is considered a global file
        $arr->each(function ($file) use (&$filesToResolve, $environment) {
            // if the file targets local, we shall resolve it alone
            if (str_contains($file, 'local')) {
                $filesToResolve->add($file);
                return;
            }
            $parts = new Arrayable(explode('.', $file));
            if ($parts->isFilled()) {
                // if the file does not target any environment, we shall resolve it first
                if ($parts->size() === 2) {
                    $filesToResolve->add($file);
                } else if ($parts->has($environment)) {
                        $filesToResolve->add($file);
                }
            }
        });

        if ($filesToResolve->isFilled()) {
            $arr = $filesToResolve;
        }

        $arr->each(function ($file) {
            $path = $this->envPath($file);
            $settings = parse_ini_file($path, true);
            $toArrayable = Arrayable::toArrayable($settings);
            if ($toArrayable->isFilled()) {
                $this->dotenv->populate($toArrayable->all(), true);
                $this->env->merge($_ENV);
            }
            // mark this as the database configuration file
            if (str_contains($file, 'database')) {
                $dbPath = ['PIONIA_DATABASE_CONFIG_PATH' => $path];
                $this->dotenv->populate($dbPath, true);
                $this->env->merge($_ENV);
            }
        });
    }

    /**
     * Scan the environment directory and list all the files discovered grouped by type
     * @param string|null $path
     * @return Arrayable
     *
     */
    public function all(?string $path = null): Arrayable
    {
        $path = $path ?? $this->envPath();

        if (!is_dir($path)) {
            return Arrayable::toArrayable([]);
        }
        $files = array_diff(scandir($path), array('.', '..'));
        $envFiles = [];
        $iniFiles = [];
        $phpFiles = [];
        $otherFiles = [];
        foreach ($files as $file) {
            if (str_contains($file, '.ini')) {
                $iniFiles[] = $file;
            } else if (str_contains($file, '.php')) {
                $phpFiles[] = $file;
            } else if (str_contains($file, '.env')) {
                $envFiles[] = $file;
            } else {
                $otherFiles[] = $file;
            }
        }

        return new Arrayable([
            'env' => $envFiles,
            'ini' => $iniFiles,
            'php' => $phpFiles,
            'others' => $otherFiles
        ]);
    }

    /**
     * Get the environment file path
     *
     * @return string
     */
    public function getEnvFilePath(): string
    {
        return $this->envPath();
    }

    /**
     * Get the environment variables
     *
     * @return Arrayable
     */
    public function getEnv(): Arrayable
    {
        return $this->env;
    }

    public function getEnvKeys()
    {
        if ($this->env->has('SYMFONY_DOTENV_VARS')){
            return $this->env->get('SYMFONY_DOTENV_VARS');
        }
        return [];
    }

    private function extractFromIniFile($path): void
    {
        $fs = new Filesystem();
        if ($fs->exists($path)) {
            $settings = parse_ini_file($path, true);
            if ($settings) {
                $this->dotenv->populate(array_merge($settings, ['PIONIA_DATABASE_CONFIG_PATH' => $path]), true);
                $this->env->merge($_ENV);
            }
        }
    }

    public function resolveDatabaseConfigs(Filesystem $fs, ?string $fileName= 'database.ini'): void
    {
        // get the full file path
        $path = $this->envPath($fileName);

        // make the local file path
        $pathLocal = str_ireplace('.ini', '.local.ini', $path);

        // we priotize the local file if it exists
        if ($fs->exists($pathLocal)) {
            $this->extractFromIniFile($pathLocal);
        } else {
            if ($fs->exists($path)) {
                if (str_ends_with($path, '.ini')) {
                   $this->extractFromIniFile($path);
                }
            }
        }
//
        if ($this->env->has('APP_ENV')) {
            $env = $this->env->get('APP_ENV');
            $profilePath = str_ireplace('.ini', '.' . $env . '.ini', $path);
            $profilePathLocal = str_ireplace('.ini', '.' . $env . '.local.ini', $path);

            if ($fs->exists($profilePathLocal)) {
                $this->extractFromIniFile($profilePathLocal);
            } else {
                if ($fs->exists($profilePath)) {
                    $this->extractFromIniFile($profilePath);
                }
            }
        }

    }
}
