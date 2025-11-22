<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AutomacaoExecucaoResource\Pages;
use App\Models\AutomacaoExecucao;
use Illuminate\Support\Facades\Gate;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;

class AutomacaoExecucaoResource extends Resource
{
    protected static ?string $model = AutomacaoExecucao::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Execuções';
    protected static ?string $modelLabel = 'Execução';
    protected static ?string $pluralModelLabel = 'Execuções';
    protected static ?int $navigationSort = 98;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('empresaAutomacao.empresa.razao_social')
                    ->label('Empresa')
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('empresaAutomacao.automacaoTipo.nome_exibicao')
                    ->label('Tipo')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => 'sucesso',
                        'warning' => 'iniciada',
                        'danger' => 'erro',
                        'gray' => 'cancelada',
                    ]),
                TextColumn::make('request_id')
                    ->label('Request ID')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(30),
                TextColumn::make('iniciada_em')
                    ->label('Iniciada')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                TextColumn::make('finalizada_em')
                    ->label('Finalizada')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                TextColumn::make('duracao_ms')
                    ->label('Duração (ms)')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'iniciada' => 'Iniciada',
                        'sucesso' => 'Sucesso',
                        'erro' => 'Erro',
                        'timeout' => 'Timeout',
                        'cancelada' => 'Cancelada',
                    ]),
            ])
            ->defaultSort('iniciada_em', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAutomacaoExecucaos::route('/'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Gate::allows('viewAutomacaoExecucoes');
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('viewAutomacaoExecucoes');
    }

    public static function canView($record): bool
    {
        return Gate::allows('viewAutomacaoExecucoes');
    }
}
