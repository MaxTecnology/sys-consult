{{-- resources/views/filament/pages/dashboard-automacao.blade.php --}}
<div class="space-y-6">
    {{-- Cards de Estatísticas --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <!-- Automações Ativas -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-gradient-to-r from-green-500 to-green-600 rounded-lg flex items-center justify-center shadow-lg">
                        <x-heroicon-o-check-circle class="w-6 h-6 text-white" />
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Automações Ativas</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ $stats['automacoes_ativas'] }}</p>
                </div>
            </div>
        </div>

        <!-- Prontas para Execução -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-gradient-to-r from-amber-500 to-yellow-600 rounded-lg flex items-center justify-center shadow-lg">
                        <x-heroicon-o-clock class="w-6 h-6 text-white" />
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Prontas para Execução</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ $stats['automacoes_prontas'] }}</p>
                </div>
            </div>
        </div>

        <!-- Com Erro -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-gradient-to-r from-red-500 to-red-600 rounded-lg flex items-center justify-center shadow-lg">
                        <x-heroicon-o-x-circle class="w-6 h-6 text-white" />
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Com Erro</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ $stats['automacoes_erro'] }}</p>
                </div>
            </div>
        </div>

        <!-- Gasto Hoje -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg flex items-center justify-center shadow-lg">
                        <x-heroicon-o-banknotes class="w-6 h-6 text-white" />
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Gasto Hoje</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white">R$ {{ number_format($stats['custo_hoje'], 2, ',', '.') }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Seção de Métricas --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Execuções Hoje -->
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Execuções Hoje</h3>
                <x-heroicon-o-chart-bar class="w-6 h-6 text-purple-200" />
            </div>
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-purple-100">Total:</span>
                    <span class="text-2xl font-bold">{{ $stats['execucoes_hoje'] }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-purple-100">Sucessos:</span>
                    <span class="text-xl font-semibold text-green-300">{{ $stats['sucesso_hoje'] }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-purple-100">Erros:</span>
                    <span class="text-xl font-semibold text-red-300">{{ $stats['erro_hoje'] }}</span>
                </div>
            </div>
        </div>

        <!-- Próximas Execuções -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Próximas Execuções</h3>
                <x-heroicon-o-calendar class="w-6 h-6 text-gray-400" />
            </div>
            <div class="space-y-3 max-h-48 overflow-y-auto">
                @forelse($proximasExecucoes->take(5) as $automacao)
                    <div class="flex items-center p-3 rounded-lg bg-gray-50 dark:bg-gray-700">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center">
                                <x-heroicon-o-building-office class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                            </div>
                        </div>
                        <div class="ml-3 flex-1">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ Str::limit($automacao->empresa->razao_social, 20) }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $automacao->automacaoTipo->nome_exibicao }}
                            </p>
                            <p class="text-xs text-blue-600 dark:text-blue-400 font-medium">
                                {{ $automacao->proxima_execucao->format('d/m H:i') }}
                            </p>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8">
                        <x-heroicon-o-calendar-days class="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-2" />
                        <p class="text-gray-500 dark:text-gray-400 text-sm">Nenhuma execução programada</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Automações com Erro -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-red-600 dark:text-red-400">Requer Atenção</h3>
                <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-red-500" />
            </div>
            <div class="space-y-3 max-h-48 overflow-y-auto">
                @forelse($automacoesComErro as $automacao)
                    <div class="flex items-center p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 bg-red-100 dark:bg-red-900 rounded-lg flex items-center justify-center">
                                <x-heroicon-o-x-mark class="w-5 h-5 text-red-600 dark:text-red-400" />
                            </div>
                        </div>
                        <div class="ml-3 flex-1">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ Str::limit($automacao->empresa->razao_social, 20) }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $automacao->automacaoTipo->nome_exibicao }}
                            </p>
                            <p class="text-xs text-red-600 dark:text-red-400 font-medium">
                                {{ $automacao->tentativas_consecutivas_erro }} tentativa(s)
                            </p>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8">
                        <x-heroicon-o-check-circle class="w-12 h-12 text-green-300 dark:text-green-600 mx-auto mb-2" />
                        <p class="text-green-600 dark:text-green-400 text-sm font-medium">✅ Tudo funcionando!</p>
                        <p class="text-gray-500 dark:text-gray-400 text-xs">Nenhuma automação com erro</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Últimas Execuções --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Últimas Execuções</h3>
            <div class="flex items-center space-x-2">
                <x-heroicon-o-clock class="w-5 h-5 text-gray-400" />
                <span class="text-sm text-gray-500 dark:text-gray-400">Tempo real</span>
            </div>
        </div>

        <div class="overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Empresa</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tipo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Iniciada em</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Duração</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($ultimasExecucoes as $execucao)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-4 py-4 text-sm font-medium text-gray-900 dark:text-white">
                                {{ Str::limit($execucao->empresaAutomacao->empresa->razao_social, 30) }}
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ $execucao->empresaAutomacao->automacaoTipo->nome_exibicao }}
                            </td>
                            <td class="px-4 py-4">
                                @if($execucao->foi_sucesso)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                            <x-heroicon-s-check class="w-3 h-3 mr-1" />
                                            Sucesso
                                        </span>
                                @elseif($execucao->tem_erro)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                            <x-heroicon-s-x-mark class="w-3 h-3 mr-1" />
                                            Erro
                                        </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                            <x-heroicon-s-clock class="w-3 h-3 mr-1" />
                                            Em execução
                                        </span>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ $execucao->iniciada_em_formatada }}
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ $execucao->duracao_formatada ?? '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <x-heroicon-o-document-text class="w-12 h-12 text-gray-300 dark:text-gray-600 mb-4" />
                                    <p class="text-gray-500 dark:text-gray-400 text-sm">Nenhuma execução registrada ainda</p>
                                    <p class="text-gray-400 dark:text-gray-500 text-xs mt-1">As execuções aparecerão aqui conforme forem processadas</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Auto-refresh da página a cada 30 segundos --}}
<script>
    setTimeout(function() {
        window.location.reload();
    }, 30000);
</script>
