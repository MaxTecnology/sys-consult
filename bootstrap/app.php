<?php

use App\Console\Commands\AutomacaoCommand;
use App\Console\Commands\LimparLogsAutomacaoCommand;
use App\Console\Commands\ReativarAutomacoesPausadasCommand;
use App\Console\Commands\VerificarVencimentoCertificadosCommand;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withCommands([
        AutomacaoCommand::class,
        LimparLogsAutomacaoCommand::class,
        ReativarAutomacoesPausadasCommand::class,
        VerificarVencimentoCertificadosCommand::class,
    ])->create();
