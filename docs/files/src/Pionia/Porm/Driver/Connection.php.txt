<?php

namespace Pionia\Porm\Driver;

use InvalidArgumentException;
use PDO;
use PDOException;
use Pionia\Base\PioniaApplication;

class Connection implements DatabaseDriverInterface
{
    protected ?PioniaApplication $application;

    protected ?PDO $pdo;

    protected ?string $dsn = null;

    protected string $type = 'mysql';

    protected ?string $prefix = null;

    protected bool $testMode = false;

    protected bool $logging = false;


    private function __construct(?PioniaApplication $application, null|string|array|PDO $connection = 'default')
    {
        $this->application = $application ?? app();

        if (is_null($connection)){
            // set up the default connection
            if ($this->application->getDiscoveredConnections()->isEmpty()){
                $this->application->logger->error("No Porm connections to connect to!");
            }
            $options = $this->application->defaultOrFirst();
        } else if (is_array($connection)){
            $options = $connection;
        } else {
            $options = $this->application->defaultOrFirst($connection);
        }

        if (!$options){
            logger()->info("Failed to establish the database connection, have you defined any connections in your settings?");
            return;
        }

        if (isset($options['prefix'])) {
            $this->prefix = $options['prefix'];
        }

        if (isset($options['testMode']) && $options['testMode']) {
            $this->testMode = true;
            return;
        }

        $options['type'] = $options['type'] ?? $options['database_type'];

        if (!isset($options['pdo'])) {
            $options['database'] = $options['database'] ?? $options['database_name'];

            if (!isset($options['socket'])) {
                $options['host'] = $options['host'] ?? $options['server'] ?? false;
            }
        }

        if (isset($options['type'])) {
            $this->type = strtolower($options['type']);

            if ($this->type === 'mariadb') {
                $this->type = 'mysql';
            }
        }

        if (isset($options['logging']) && is_bool($options['logging'])) {
            $this->logging = $options['logging'];
        }

        if($this->application->getEnv('LOG_QUERIES') || $this->application->getEnv('SHOW_QUERIES')){
            $this->logging = true;
        }

        $option = $options['option'] ?? [];
        $commands = [];

        switch ($this->type) {
            case 'mysql':
                // Make MySQL using standard quoted identifier.
                $commands[] = 'SET SQL_MODE=ANSI_QUOTES';
                break;
            case 'mssql':
                // Keep MSSQL QUOTED_IDENTIFIER is ON for standard quoting.
                $commands[] = 'SET QUOTED_IDENTIFIER ON';
                // Make ANSI_NULLS is ON for NULL value.
                $commands[] = 'SET ANSI_NULLS ON';
                break;
        }

        if (isset($options['pdo'])) {
            if (!$options['pdo'] instanceof PDO) {
                $this->application->logger->error('Invalid PDO object supplied.');
                throw new InvalidArgumentException('Invalid PDO object supplied.');
            }
            $this->pdo = $options['pdo'];
            foreach ($commands as $value) {
                $this->pdo->exec($value);
            }
            return;
        }

        if (isset($options['dsn'])) {
            if (is_array($options['dsn']) && isset($options['dsn']['driver'])) {
                $attr = $options['dsn'];
            } else {
                $this->application->logger->error('Invalid DSN option supplied.');
                throw new InvalidArgumentException('Invalid DSN option supplied.');
            }
        } else {
            if (
                isset($options['port']) &&
                is_int($options['port'] * 1)
            ) {
                $port = $options['port'];
            }

            $isPort = isset($port);

            switch ($this->type) {

                case 'mysql':
                    $attr = [
                        'driver' => 'mysql',
                        'dbname' => $options['database']
                    ];

                    if (isset($options['socket'])) {
                        $attr['unix_socket'] = $options['socket'];
                    } else {
                        $attr['host'] = $options['host'];

                        if ($isPort) {
                            $attr['port'] = $port;
                        }
                    }

                    break;

                case 'pgsql':
                    $attr = [
                        'driver' => 'pgsql',
                        'host' => $options['host'],
                        'dbname' => $options['database']
                    ];

                    if ($isPort) {
                        $attr['port'] = $port;
                    }

                    break;

                case 'sybase':
                    $attr = [
                        'driver' => 'dblib',
                        'host' => $options['host'],
                        'dbname' => $options['database']
                    ];

                    if ($isPort) {
                        $attr['port'] = $port;
                    }

                    break;

                case 'oracle':
                    $attr = [
                        'driver' => 'oci',
                        'dbname' => $options['host'] ?
                            '//' . $options['host'] . ($isPort ? ':' . $port : ':1521') . '/' . $options['database'] :
                            $options['database']
                    ];

                    if (isset($options['charset'])) {
                        $attr['charset'] = $options['charset'];
                    }

                    break;

                case 'mssql':
                    if (isset($options['driver']) && $options['driver'] === 'dblib') {
                        $attr = [
                            'driver' => 'dblib',
                            'host' => $options['host'] . ($isPort ? ':' . $port : ''),
                            'dbname' => $options['database']
                        ];

                        if (isset($options['appname'])) {
                            $attr['appname'] = $options['appname'];
                        }

                        if (isset($options['charset'])) {
                            $attr['charset'] = $options['charset'];
                        }
                    } else {
                        $attr = [
                            'driver' => 'sqlsrv',
                            'Server' => $options['host'] . ($isPort ? ',' . $port : ''),
                            'CDatabase' => $options['database']
                        ];

                        if (isset($options['appname'])) {
                            $attr['APP'] = $options['appname'];
                        }

                        $config = [
                            'ApplicationIntent',
                            'AttachDBFileName',
                            'Authentication',
                            'ColumnEncryption',
                            'ConnectionPooling',
                            'Encrypt',
                            'Failover_Partner',
                            'KeyStoreAuthentication',
                            'KeyStorePrincipalId',
                            'KeyStoreSecret',
                            'LoginTimeout',
                            'MultipleActiveResultSets',
                            'MultiSubnetFailover',
                            'Scrollable',
                            'TraceFile',
                            'TraceOn',
                            'TransactionIsolation',
                            'TransparentNetworkIPResolution',
                            'TrustServerCertificate',
                            'WSID',
                        ];

                        foreach ($config as $value) {
                            $keyname = strtolower(preg_replace(['/([a-z\d])([A-Z])/', '/([^_])([A-Z][a-z])/'], '$1_$2', $value));

                            if (isset($options[$keyname])) {
                                $attr[$value] = $options[$keyname];
                            }
                        }
                    }

                    break;

                case 'sqlite':
                    $attr = [
                        'driver' => 'sqlite',
                        $options['database']
                    ];

                    break;
            }
        }

        if (!isset($attr)) {
            $this->application->logger->error('Incorrect connection options.');
            throw new InvalidArgumentException('Incorrect connection options.');
        }

        $driver = $attr['driver'];

        if (!in_array($driver, PDO::getAvailableDrivers())) {
            $this->application->logger->error("Unsupported PDO driver: {$driver}.");
            throw new InvalidArgumentException("Unsupported PDO driver: {$driver}.");
        }

        unset($attr['driver']);

        $stack = [];

        foreach ($attr as $key => $value) {
            $stack[] = is_int($key) ? $value : $key . '=' . $value;
        }

        $dsn = $driver . ':' . implode(';', $stack);

        if (
            in_array($this->type, ['mysql', 'pgsql', 'sybase', 'mssql']) &&
            isset($options['charset'])
        ) {
            $commands[] = "SET NAMES '{$options['charset']}'" . (
                $this->type === 'mysql' && isset($options['collation']) ?
                    " COLLATE '{$options['collation']}'" : ''
                );
        }

        $this->dsn = $dsn;

        try {
            $this->pdo = new PDO(
                $dsn,
                $options['username'] ?? null,
                $options['password'] ?? null,
                $option
            );

            if (isset($options['error'])) {
                $this->pdo->setAttribute(
                    PDO::ATTR_ERRMODE,
                    in_array($options['error'], [
                        PDO::ERRMODE_SILENT,
                        PDO::ERRMODE_WARNING,
                        PDO::ERRMODE_EXCEPTION
                    ]) ?
                        $options['error'] :
                        PDO::ERRMODE_SILENT
                );
            }

            if (isset($options['command']) && is_array($options['command'])) {
                $commands = array_merge($commands, $options['command']);
            }

            foreach ($commands as $value) {
                $this->pdo->exec($value);
            }
        } catch (PDOException $e) {
            $this->application->logger->error($e->getMessage());
            throw new PDOException($e->getMessage());
        }
    }

    public function getApplication(): ?PioniaApplication
    {
        return $this->application;
    }

    public function setApplication(?PioniaApplication $application): void
    {
        $this->application = $application;
    }

    public function getPdo(): ?PDO
    {
        return $this->pdo;
    }

    public function setPdo(?PDO $pdo): void
    {
        $this->pdo = $pdo;
    }

    public function getDsn(): string
    {
        return $this->dsn;
    }

    public function setDsn(string $dsn): void
    {
        $this->dsn = $dsn;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getPrefix(): ?string
    {
        return $this->prefix;
    }

    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
    }

    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    public function setTestMode(bool $testMode): void
    {
        $this->testMode = $testMode;
    }

    public function isLogging(): bool
    {
        return $this->logging;
    }

    public function setLogging(bool $logging): void
    {
        $this->logging = $logging;
    }

    public static function connect(?PioniaApplication $application, null|string|array|PDO $connection = 'default'): static
    {
        return new Connection($application, $connection);
    }
}
