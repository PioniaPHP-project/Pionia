<?php

namespace Pionia\Pionia\Base\Utils;

use Pionia\Pionia\Utilities\Arrayable;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;

class EnvResolver
{
    use PathsTrait;
    /**
     * Resolved environment variables
     */
    private Arrayable $env;
    public Dotenv $dotenv;

    /**
     * @var mixed|string
     */
    private string $path;


    public function __construct(?string $path = 'environment')
    {
        $this->env = new Arrayable();

        $this->dotenv = new Dotenv();
        $this->dotenv->usePutenv();

        $this->path = $path;
        $this->loadEnv();
    }

    /**
     * Load the environment variables
     */
    public function loadEnv(?string $src = '.env'): void
    {
        $src = $src ?? '.env';

        $path = $this->envPath($src);

        $fs = new Filesystem();
        if ($fs->exists($path)) {
            $this->dotenv->loadEnv($path);
        }

        $this->env->merge($_ENV);

        $this->resolveDatabaseConfigs($fs);
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

    public function resolveDatabaseConfigs(Filesystem $fs, ?string $fileName= 'database.ini'): void
    {
        $path = $this->envPath($fileName);

        $pathLocal = str_ireplace('.ini', '.local.ini', $path);
//
        if ($fs->exists($pathLocal)) {
            $settings = parse_ini_file($path . '.local', true);
            $this->dotenv->populate(array_merge($settings, ['PIONIA_DATABASE_CONFIG_PATH' => $pathLocal]), true);
            $this->env->merge($_ENV);
        } else {
            if ($fs->exists($path)) {
                if (str_ends_with($path, '.ini')) {
                    $settings = parse_ini_file($path, true);
                    $this->dotenv->populate(array_merge($settings, ['PIONIA_DATABASE_CONFIG_PATH' => $path]), true);
                    $this->env->merge($_ENV);
                }
            }
        }
//
        if ($this->env->has('APP_ENV')) {
            $env = $this->env->get('APP_ENV');
            $profilePath = str_ireplace('.ini', '.' . $env . '.ini', $path);
            $profilePathLocal = str_ireplace('.ini', '.' . $env . '.local.ini', $path);

            if ($fs->exists($profilePathLocal)) {
                $settings = parse_ini_file($profilePathLocal, true);
                $this->dotenv->populate($settings);
                $this->env->merge($_ENV);
            } else {
                if ($fs->exists($profilePath)) {
                    $settings = parse_ini_file($profilePath, true);
                    $this->dotenv->populate($settings, true);
                    $this->env->merge($_ENV);
                }
            }
        }
//        // auto-discover the databases in the environment
        $dbSections = [];
        $this->env->each(function ($db, $key) use (&$dbSections) {
            if (is_array($db)) {
                $arr = Arrayable::toArrayable($db);
                if (($arr->has("database") && $arr->has("type"))) {
                    $dbSections[] = $key;
                }
            }
        });

       $arrToPopulate = new Arrayable([
           'DBS_CONNECTIONS_SIZE' => count($dbSections),
        ]);
       if (count($dbSections) > 0) {
           $arrToPopulate->merge(['DBS_CONNECTIONS' => $dbSections]);
       }

    $this->dotenv->populate($arrToPopulate->all());

    $this->env->merge($_ENV);
    }
}
