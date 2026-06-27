<?php

namespace Pionia\Builtins\Commands;

use Pionia\Console\BaseCommand;
use Pionia\Http\Monitoring\RequestMetrics;
use Pionia\Http\Pages\DeveloperStatsCollector;
use Pionia\Http\Request\Request;
use Pionia\Console\Command;
use Pionia\Console\Input\InputOption;
use Pionia\Console\Helper\Table;

class ViewStats extends BaseCommand
{
    protected string $name = 'stats:view';

    protected array $aliases = ['viewstats', 'stats'];

    protected string $description = 'View developer stats and request performance metrics in the terminal';

    protected string $help = 'Shows health, runtime, and request metrics (traffic and latency by service/action).';

    protected function getOptions(): array
    {
        return [
            ['json', 'j', InputOption::VALUE_NONE, 'Output full stats payload as JSON'],
            ['top', 't', InputOption::VALUE_OPTIONAL, 'Number of endpoints to list', '10'],
            ['reset', null, InputOption::VALUE_NONE, 'Clear recorded request metrics'],
        ];
    }

    protected function handle(): int
    {
        if ($this->option('reset')) {
            RequestMetrics::reset();
            $this->info('Request metrics cleared.');

            return Command::SUCCESS;
        }

        $request = Request::create('/stats', 'GET');
        $payload = (new DeveloperStatsCollector(realm(), $request))->collect();

        if ($this->option('json')) {
            $this->output->writeln(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return Command::SUCCESS;
        }

        $this->renderSummary($payload);
        $this->renderRequestMetrics($payload['request_metrics'] ?? [], (int) $this->option('top'));

        return Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderSummary(array $payload): void
    {
        $health = $payload['health'] ?? [];
        $app = $payload['application'] ?? [];
        $runtime = $payload['runtime'] ?? [];

        $this->output->writeln('<info>' . ($app['name'] ?? 'Application') . ' — developer stats</info>');
        $this->output->writeln('Generated: ' . ($payload['generated_at'] ?? ''));
        $this->output->writeln('Health: ' . ($health['overall'] ?? 'unknown'));
        $this->output->writeln('PHP: ' . ($runtime['php_version'] ?? ''));
        $this->output->writeln('Runtime mode: ' . ($runtime['runtime_mode'] ?? ''));
        $this->output->writeln('');
    }

    /**
     * @param array<string, mixed> $metrics
     */
    private function renderRequestMetrics(array $metrics, int $top): void
    {
        $this->output->writeln('<comment>Request performance</comment>');
        $this->output->writeln(sprintf(
            '  Total: %d · Unique endpoints: %d · Avg: %s ms · API: %d · HTTP: %d',
            (int) ($metrics['total_requests'] ?? 0),
            (int) ($metrics['unique_endpoints'] ?? 0),
            (string) ($metrics['avg_duration_ms'] ?? 0),
            (int) ($metrics['api_requests'] ?? 0),
            (int) ($metrics['http_requests'] ?? 0),
        ));
        $this->output->writeln('  Log: ' . ($metrics['log_path'] ?? RequestMetrics::logPath()));
        $this->output->writeln('');

        $this->renderEndpointTable('Heaviest endpoints (avg ms)', array_slice($metrics['heavy_endpoints'] ?? [], 0, $top));
        $this->renderEndpointTable('Highest traffic', array_slice($metrics['high_traffic_endpoints'] ?? [], 0, $top));
        $this->renderEndpointTable('API by traffic', array_slice($metrics['api_by_traffic'] ?? [], 0, $top));
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function renderEndpointTable(string $title, array $rows): void
    {
        $this->output->writeln('<comment>' . $title . '</comment>');

        if ($rows === []) {
            $this->output->writeln('  (no data yet — send traffic to the app)');
            $this->output->writeln('');

            return;
        }

        $table = new Table($this->output);
        $table->setHeaders(['Endpoint', 'Hits', 'Avg ms', 'Max ms', 'Errors']);
        foreach ($rows as $row) {
            $table->addRow([
                (string) ($row['label'] ?? ''),
                (string) ($row['count'] ?? 0),
                (string) ($row['avg_ms'] ?? 0),
                (string) ($row['max_ms'] ?? 0),
                (string) ($row['errors'] ?? 0),
            ]);
        }
        $table->render();
        $this->output->writeln('');
    }
}
