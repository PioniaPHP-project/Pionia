<?php

namespace Pionia\Builtins\Commands;

use Pionia\Base\WebApplication;
use Pionia\Console\Command;
use Pionia\Console\Helper\Table;
use Pionia\Console\Input\InputOption;
use Pionia\Http\Request\Request;
use Pionia\Realm\AppRealm;

/**
 * In-process microbenchmarks: boot, ping handleRequest, Moonlight dispatch.
 */
class BenchCommand extends Command
{
    protected string $name = 'bench';

    protected array $aliases = ['benchmark'];

    protected string $description = 'Run in-process boot / ping / Moonlight dispatch microbenchmarks';

    protected string $help = 'Measures local latency without an HTTP server. Options: --iterations, --warmup, --json.';

    protected function getOptions(): array
    {
        return [
            ['iterations', null, InputOption::VALUE_OPTIONAL, 'Timed iterations per benchmark', '100'],
            ['warmup', null, InputOption::VALUE_OPTIONAL, 'Warmup iterations before timing', '10'],
            ['json', 'j', InputOption::VALUE_NONE, 'Emit JSON instead of a table'],
            ['service', null, InputOption::VALUE_OPTIONAL, 'Moonlight service for dispatch bench', 'auth'],
            ['action', null, InputOption::VALUE_OPTIONAL, 'Moonlight action for dispatch bench', 'list_auth'],
        ];
    }

    protected function handle(): int
    {
        $iterations = max(1, (int) $this->option('iterations'));
        $warmup = max(0, (int) $this->option('warmup'));
        $service = (string) $this->option('service');
        $action = (string) $this->option('action');

        $results = [
            $this->measure('boot (warm realm)', $iterations, $warmup, static function (): void {
                (void) realm();
            }),
            $this->measure('http ping', $iterations, $warmup, function (): void {
                $app = $this->webApp();
                $request = Request::create(apiPingPath(), 'GET');
                (void) $app->handleRequest($request);
            }),
            $this->measure("moonlight {$service}.{$action}", $iterations, $warmup, static function () use ($service, $action): void {
                (void) moonlight()->dispatch($service, $action);
            }),
        ];

        if ($this->option('json')) {
            $this->output->writeln(json_encode([
                'iterations' => $iterations,
                'warmup' => $warmup,
                'results' => $results,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return Command::SUCCESS;
        }

        $this->info('Pionia in-process benchmarks');
        $this->line("Iterations: {$iterations} · Warmup: {$warmup}");
        $this->line('');

        $table = new Table($this->output);
        $table->setHeaders(['Benchmark', 'Iterations', 'Mean (ms)', 'p50 (ms)', 'p95 (ms)', 'ops/sec']);
        foreach ($results as $row) {
            $table->addRow([
                $row['name'],
                (string) $row['iterations'],
                number_format($row['mean_ms'], 3),
                number_format($row['p50_ms'], 3),
                number_format($row['p95_ms'], 3),
                number_format($row['ops_per_sec'], 1),
            ]);
        }
        $table->render();

        return Command::SUCCESS;
    }

    /**
     * @param callable(): void $callback
     *
     * @return array{name: string, iterations: int, mean_ms: float, p50_ms: float, p95_ms: float, ops_per_sec: float}
     */
    private function measure(string $name, int $iterations, int $warmup, callable $callback): array
    {
        for ($i = 0; $i < $warmup; $i++) {
            $callback();
        }

        $samples = [];
        for ($i = 0; $i < $iterations; $i++) {
            $start = hrtime(true);
            $callback();
            $samples[] = (hrtime(true) - $start) / 1e6;
        }

        sort($samples);
        $mean = array_sum($samples) / count($samples);
        $p50 = $this->percentile($samples, 50);
        $p95 = $this->percentile($samples, 95);
        $ops = $mean > 0 ? 1000.0 / $mean : 0.0;

        return [
            'name' => $name,
            'iterations' => $iterations,
            'mean_ms' => $mean,
            'p50_ms' => $p50,
            'p95_ms' => $p95,
            'ops_per_sec' => $ops,
        ];
    }

    /**
     * @param list<float> $sortedMs
     */
    private function percentile(array $sortedMs, float $percentile): float
    {
        $count = count($sortedMs);
        if ($count === 0) {
            return 0.0;
        }
        $index = (int) ceil(($percentile / 100) * $count) - 1;

        return $sortedMs[max(0, min($count - 1, $index))];
    }

    private function webApp(): WebApplication
    {
        /** @var WebApplication $app */
        $app = app()->make(AppRealm::WEB_APP_TAG);
        if (method_exists($app, 'bootOnce')) {
            $app->bootOnce();
        }

        return $app;
    }
}
