<?php

namespace App\Console\Commands;

use App\CommercialFactory\Services\CommercialFactoryIntakeService;
use Illuminate\Console\Command;
use Throwable;

class SiteFactoryApiTestCommand extends Command
{
    protected $signature = 'site:factory-test
        {--product=TV Digital Enterprise}
        {--client=Cliente Site Teste}
        {--plan=enterprise}
        {--email=cliente@teste.com}
        {--domain=cliente-site.tv.br}';

    protected $description = 'Testa internamente o fluxo Site → Factory sem depender de curl.';

    public function handle(CommercialFactoryIntakeService $service): int
    {
        try {
            $report = $service->intake([
                'product' => (string) $this->option('product'),
                'client' => (string) $this->option('client'),
                'plan' => (string) $this->option('plan'),
                'email' => (string) $this->option('email'),
                'domain' => (string) $this->option('domain'),
                'source' => 'site-test-command',
            ], true);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $this->info('Site → Factory Test finalizado.');
        $this->line('Status: ' . ($report['status'] ?? '-'));
        $this->line('Projeto: ' . ($report['project_slug'] ?? '-'));
        $this->line('Status comercial: ' . ($report['commercial_status'] ?? '-'));
        $this->line('Relatório: ' . ($report['path'] ?? '-'));

        return ($report['status'] ?? null) === 'finished'
            ? self::SUCCESS
            : self::FAILURE;
    }
}
