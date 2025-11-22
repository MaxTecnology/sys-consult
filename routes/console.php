<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Command inspire original do Laravel
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ========================================
// CONFIGURAÇÃO DE SCHEDULE PARA AUTOMAÇÃO
// ========================================

// Executar automações a cada 5 minutos
Schedule::command('automacao:executar --force')
    ->everyFiveMinutes()
    ->withoutOverlapping(10) // Evita execuções simultâneas, timeout 10min
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/automacao.log'))
    ->description('Executar automações de consultas programadas');

// Limpeza de logs antigos (execuções antigas) - todo dia à 1h
Schedule::command('automacao:limpar-logs')
    ->dailyAt('01:00')
    ->appendOutputTo(storage_path('logs/automacao-manutencao.log'))
    ->description('Limpar logs de execução antigos');

// Verificar certificados vencendo - todo dia às 8h
Schedule::command('certificados:verificar-vencimento')
    ->dailyAt('08:00')
    ->appendOutputTo(storage_path('logs/certificados.log'))
    ->description('Verificar certificados próximos do vencimento');

// Alerta de certificados vencendo - todo dia às 08h05
Schedule::command('certificados:alerta-vencimento')
    ->dailyAt('08:05')
    ->appendOutputTo(storage_path('logs/certificados-alerta.log'))
    ->description('Enviar alertas por e-mail de certificados vencidos/vencendo');

// Reativar automações pausadas por erro - a cada hora
Schedule::command('automacao:reativar-pausadas')
    ->hourly()
    ->appendOutputTo(storage_path('logs/automacao-reativacao.log'))
    ->description('Reativar automações pausadas por erro');

// Alerta de mensagens críticas DTE - a cada hora
Schedule::command('dte:alert-critical')
    ->hourly()
    ->appendOutputTo(storage_path('logs/dte-alertas.log'))
    ->description('Enviar alertas por e-mail de mensagens críticas/não lidas');

// Alerta de falhas de automação - a cada hora
Schedule::command('automacao:alerta-falhas')
    ->hourly()
    ->appendOutputTo(storage_path('logs/automacao-falhas.log'))
    ->description('Enviar alertas por e-mail de execuções com erro/timeout');

// Limpeza de jobs failed antigos - semanal
Schedule::command('queue:flush')
    ->weekly()
    ->sundays()
    ->at('02:00')
    ->description('Limpar jobs failed antigos');

// ========================================
// COMMANDS PERSONALIZADOS VIA ARTISAN
// ========================================

// Command para testar automação específica
Artisan::command('automacao:teste {empresa_id} {tipo_consulta}', function (int $empresaId, string $tipoConsulta) {
    $this->info("Testando automação para empresa ID: {$empresaId}, tipo: {$tipoConsulta}");

    Artisan::call('automacao:executar', [
        '--empresa' => $empresaId,
        '--tipo' => $tipoConsulta,
        '--dry-run' => false
    ]);

    $this->info('Teste executado! Verifique os logs.');
})->purpose('Testar automação para empresa específica');

// Command para status rápido
Artisan::command('automacao:status', function () {
    $this->info('📊 Status das Automações:');

    $ativas = \App\Models\EmpresaAutomacao::ativas()->count();
    $prontas = \App\Models\EmpresaAutomacao::prontas()->count();
    $comErro = \App\Models\EmpresaAutomacao::comErro()->count();

    $this->line("✅ Ativas: {$ativas}");
    $this->line("⏰ Prontas para execução: {$prontas}");
    $this->line("❌ Com erro: {$comErro}");

    if ($prontas > 0) {
        $this->warn("🚨 Há {$prontas} automações prontas para execução!");
    }
})->purpose('Verificar status das automações');
