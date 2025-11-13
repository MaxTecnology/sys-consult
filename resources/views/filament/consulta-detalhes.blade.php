{{-- resources/views/filament/consulta-detalhes.blade.php --}}
<div class="space-y-6">
    {{-- Informações Gerais --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4">Informações da Consulta</h3>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <span class="font-medium text-gray-700">Empresa:</span>
                <span class="ml-2">{{ $record->empresa->razao_social }}</span>
            </div>
            <div>
                <span class="font-medium text-gray-700">IE:</span>
                <span class="ml-2">{{ $record->empresa->inscricao_estadual }}</span>
            </div>
            <div>
                <span class="font-medium text-gray-700">Certificado:</span>
                <span class="ml-2">{{ $record->certificado->nome }}</span>
            </div>
            <div>
                <span class="font-medium text-gray-700">Data/Hora:</span>
                <span class="ml-2">{{ $record->consultado_em->format('d/m/Y H:i:s') }}</span>
            </div>
            <div>
                <span class="font-medium text-gray-700">Status:</span>
                <span class="ml-2 px-2 py-1 rounded text-sm {{ $record->sucesso ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                    {{ $record->sucesso ? 'Sucesso' : 'Erro' }}
                </span>
            </div>
            <div>
                <span class="font-medium text-gray-700">Código:</span>
                <span class="ml-2">{{ $record->response_code }}</span>
            </div>
            @if($record->preco)
                <div>
                    <span class="font-medium text-gray-700">Preço:</span>
                    <span class="ml-2">R$ {{ number_format($record->preco, 2, ',', '.') }}</span>
                </div>
            @endif
            @if($record->tempo_resposta_ms)
                <div>
                    <span class="font-medium text-gray-700">Tempo de Resposta:</span>
                    <span class="ml-2">{{ number_format($record->tempo_resposta_ms) }}ms</span>
                </div>
            @endif
        </div>
    </div>

    {{-- Erros --}}
    @if(!$record->sucesso && $record->errors)
        <div class="bg-red-50 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4 text-red-800">Erros</h3>
            <ul class="list-disc list-inside space-y-1">
                @foreach($record->errors as $erro)
                    <li class="text-red-700">{{ $erro }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Mensagens da Caixa Postal --}}
    @if($record->sucesso && $record->resposta_data)
        @php
            $service = new \App\Services\InfoSimplesService();
            $estatisticas = $service->getEstatisticasConsulta($record);
            $mensagens = $service->getTodasMensagens($record);
        @endphp

        <div class="bg-blue-50 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4 text-blue-800">Estatísticas</h3>
            <div class="grid grid-cols-3 gap-4">
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600">{{ $estatisticas['total_mensagens'] }}</div>
                    <div class="text-sm text-gray-600">Total de Mensagens</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600">{{ $estatisticas['total_lidas'] }}</div>
                    <div class="text-sm text-gray-600">Mensagens Lidas</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-orange-600">{{ $estatisticas['total_nao_lidas'] }}</div>
                    <div class="text-sm text-gray-600">Não Lidas</div>
                </div>
            </div>
        </div>

        @if(count($mensagens) > 0)
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Mensagens da Caixa Postal</h3>
                <div class="space-y-4">
                    @foreach($mensagens as $mensagem)
                        <div class="border rounded-lg p-4 {{ $mensagem['lida'] ? 'bg-gray-50' : 'bg-yellow-50 border-yellow-200' }}">
                            <div class="flex justify-between items-start mb-2">
                                <div class="flex-1">
                                    <h4 class="font-semibold {{ $mensagem['lida'] ? 'text-gray-800' : 'text-yellow-800' }}">
                                        {{ $mensagem['assunto'] }}
                                    </h4>
                                    <p class="text-sm text-gray-600">
                                        De: {{ $mensagem['remetente'] }} |
                                        Enviado em: {{ \Carbon\Carbon::parse($mensagem['data_envio'])->format('d/m/Y H:i') }}
                                        @if($mensagem['lida'] && isset($mensagem['datahora_leitura']))
                                            | Lido em: {{ \Carbon\Carbon::parse($mensagem['datahora_leitura'])->format('d/m/Y H:i') }}
                                        @endif
                                    </p>
                                </div>
                                <span class="px-2 py-1 rounded text-xs {{ $mensagem['lida'] ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800' }}">
                            {{ $mensagem['lida'] ? 'Lida' : 'Não Lida' }}
                        </span>
                            </div>
                            <div class="text-sm text-gray-700 mt-2">
                                {{ $mensagem['conteudo_texto'] }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif

    {{-- Comprovantes --}}
    @if($record->site_receipts && count($record->site_receipts) > 0)
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Comprovantes</h3>
            <div class="space-y-2">
                @foreach($record->site_receipts as $index => $url)
                    <a href="{{ $url }}" target="_blank"
                       class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                            </path>
                        </svg>
                        Comprovante {{ $index + 1 }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Dados Técnicos (Header) --}}
    @if($record->resposta_header)
        <div class="bg-gray-50 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Dados Técnicos</h3>
            <div class="grid grid-cols-2 gap-4 text-sm">
                @foreach($record->resposta_header as $key => $value)
                    @if(!is_array($value))
                        <div>
                            <span class="font-medium text-gray-700">{{ ucfirst(str_replace('_', ' ', $key)) }}:</span>
                            <span class="ml-2">{{ $value }}</span>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
</div>
