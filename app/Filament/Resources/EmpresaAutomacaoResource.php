<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmpresaAutomacaoResource\Pages;
use App\Models\EmpresaAutomacao;
use App\Models\AutomacaoTipo;
use App\Models\Empresa;
use App\Models\Certificado;
use App\Jobs\ProcessarAutomacaoJob;
use App\Models\AutomacaoTipo as Tipo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class EmpresaAutomacaoResource extends Resource
{
    protected static ?string $model = EmpresaAutomacao::class;
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Automação';
    protected static ?string $modelLabel = 'Configuração de Automação';
    protected static ?string $pluralModelLabel = 'Automações';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Configuração da Automação')
                    ->schema([
                        Forms\Components\Select::make('empresa_id')
                            ->label('Empresa')
                            ->relationship('empresa', 'razao_social')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('razao_social')->required(),
                                Forms\Components\TextInput::make('cnpj')->required(),
                                Forms\Components\TextInput::make('inscricao_estadual')->required(),
                            ]),

                        Forms\Components\Select::make('tipo_consulta')
                            ->label('Tipo de Consulta')
                            ->options(fn () => AutomacaoTipo::ativas()->habilitadas()->pluck('nome_exibicao', 'tipo_consulta'))
                            ->preload()
                            ->searchable()
                            ->required()
                            ->reactive()
                            ->rules([
                                fn (Get $get, ?EmpresaAutomacao $record) => Rule::unique('empresa_automacao', 'tipo_consulta')
                                    ->where(fn ($query) => $query->where('empresa_id', $get('empresa_id')))
                                    ->ignore($record?->id),
                            ])
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                $exists = EmpresaAutomacao::where('empresa_id', $get('empresa_id'))
                                    ->where('tipo_consulta', $state)
                                    ->when($get('id'), fn ($q) => $q->where('id', '!=', $get('id')))
                                    ->exists();

                                if ($exists) {
                                    Notification::make()
                                        ->title('Tipo de consulta já configurado para esta empresa')
                                        ->body('Escolha outro tipo ou edite a automação existente.')
                                        ->danger()
                                        ->send();

                                    $set('tipo_consulta', null);
                                }
                            })
                            ->afterStateUpdated(fn ($state, Forms\Set $set) =>
                                $set('configuracoes_tipo', AutomacaoTipo::where('tipo_consulta', $state)->first()?->toArray())
                            ),

                        Forms\Components\Select::make('certificado_id')
                            ->label('Certificado')
                            ->relationship('certificado', 'nome')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->getOptionLabelFromRecordUsing(fn ($record) =>
                                $record->nome . ' (' . ($record->vencido ? 'VENCIDO' : 'Válido') . ')'
                            ),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Configurações de Execução')
                    ->schema([
                        Forms\Components\Toggle::make('ativa')
                            ->label('Automação Ativa')
                            ->default(false)
                            ->helperText('Ativar/desativar a execução automática'),

                        Forms\Components\Select::make('frequencia')
                            ->label('Frequência')
                            ->options([
                                'diaria' => 'Diária',
                                'semanal' => 'Semanal',
                                'quinzenal' => 'Quinzenal',
                                'mensal' => 'Mensal',
                                'personalizada' => 'Personalizada',
                            ])
                            ->default('semanal')
                            ->required()
                            ->reactive(),

                        Forms\Components\TextInput::make('dias_personalizados')
                            ->label('Dias (Personalizada)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(365)
                            ->visible(fn (Forms\Get $get) => $get('frequencia') === 'personalizada'),

                        Forms\Components\Select::make('dia_semana')
                            ->label('Dia da Semana')
                            ->options([
                                1 => 'Domingo',
                                2 => 'Segunda-feira',
                                3 => 'Terça-feira',
                                4 => 'Quarta-feira',
                                5 => 'Quinta-feira',
                                6 => 'Sexta-feira',
                                7 => 'Sábado',
                            ])
                            ->default(2)
                            ->visible(fn (Forms\Get $get) => in_array($get('frequencia'), ['semanal', 'quinzenal'])),

                        Forms\Components\TextInput::make('dia_mes')
                            ->label('Dia do Mês')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(28)
                            ->default(1)
                            ->visible(fn (Forms\Get $get) => $get('frequencia') === 'mensal'),

                        Forms\Components\TimePicker::make('horario')
                            ->label('Horário de Execução')
                            ->default('02:00')
                            ->required(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Observações')
                    ->schema([
                        Forms\Components\Textarea::make('observacoes')
                            ->label('Observações')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('empresa.razao_social')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('automacaoTipo.nome_exibicao')
                    ->label('Tipo de Consulta')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->getStateUsing(fn ($record) => $record->status_formatado)
                    ->colors([
                        'success' => fn ($state) => $state === 'Ativa',
                        'warning' => fn ($state) => $state === 'Pausada',
                        'danger' => fn ($state) => $state === 'Com Erro',
                        'secondary' => fn ($state) => $state === 'Desabilitada',
                    ]),

                Tables\Columns\TextColumn::make('frequencia_formatada')
                    ->label('Frequência')
                    ->sortable('frequencia'),

                Tables\Columns\TextColumn::make('proxima_execucao_formatada')
                    ->label('Próxima Execução')
                    ->sortable('proxima_execucao')
                    ->color(fn ($record) => $record->dias_ate_proxima_execucao <= 0 ? 'warning' : 'success'),

                Tables\Columns\TextColumn::make('ultima_execucao_formatada')
                    ->label('Última Execução')
                    ->sortable('ultima_execucao')
                    ->placeholder('Nunca executada'),

                Tables\Columns\TextColumn::make('certificado.nome')
                    ->label('Certificado')
                    ->limit(20)
                    ->tooltip(function ($record) {
                        $cert = $record->certificado;
                        return $cert ? "Válido até: " . $cert->validade?->format('d/m/Y') : '';
                    }),

                Tables\Columns\BadgeColumn::make('ativo')
                    ->label('Ativo')
                    ->getStateUsing(fn ($record) => $record->ativo ? 'Sim' : 'Não')
                    ->colors(fn ($record) => [
                        'success' => $record->ativo,
                        'danger' => !$record->ativo,
                    ]),

                Tables\Columns\TextColumn::make('mensagens_pendentes')
                    ->label('Msgs pendentes')
                    ->getStateUsing(function ($record) {
                        $empresa = $record->empresa;
                        if (!$empresa) {
                            return 0;
                        }
                        $mailboxes = $empresa->dteMailboxes;
                        if (!$mailboxes) {
                            return 0;
                        }
                        return $mailboxes->sum(fn ($m) => $m->mensagensNaoLidas()->count());
                    })
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success')
                    ->icon(fn ($state) => $state > 0 ? 'heroicon-m-exclamation-triangle' : null),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'ativa' => 'Ativa',
                        'pausada' => 'Pausada',
                        'erro' => 'Com Erro',
                        'desabilitada' => 'Desabilitada',
                    ]),

                Tables\Filters\SelectFilter::make('tipo_consulta')
                    ->label('Tipo de Consulta')
                    ->relationship('automacaoTipo', 'nome_exibicao'),

                Tables\Filters\Filter::make('ativas')
                    ->label('Apenas Ativas')
                    ->query(fn (Builder $query) => $query->ativas()),

                Tables\Filters\Filter::make('apenas_ativas_soft')
                    ->label('Somente ativo (flag)')
                    ->query(fn (Builder $query) => $query->where('ativo', true)),

                Tables\Filters\Filter::make('prontas')
                    ->label('Prontas para Execução')
                    ->query(fn (Builder $query) => $query->prontas()),
            ])
            ->actions([
                Tables\Actions\Action::make('executar_agora')
                    ->label('Executar Agora')
                    ->icon('heroicon-o-play')
                    ->action(function (EmpresaAutomacao $record) {
                        $user = auth()->user();
                        if (!$user?->hasEmpresa($record->empresa_id)) {
                            Notification::make()
                                ->title('Sem permissão para esta empresa')
                                ->danger()
                                ->send();
                            return;
                        }

                        try {
                            ProcessarAutomacaoJob::dispatch(
                                $record->tipo_consulta,
                                $record->empresa_id
                            );

                            Notification::make()
                                ->title('Execução iniciada!')
                                ->body('A consulta foi adicionada à fila de processamento.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Erro ao executar')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->ativa && $record->status === 'ativa')
                    ->color('success'),

                Tables\Actions\Action::make('pausar')
                    ->label('Pausar')
                    ->icon('heroicon-o-pause')
                    ->action(function (EmpresaAutomacao $record) {
                        $user = auth()->user();
                        if (!$user?->hasEmpresa($record->empresa_id, ['owner', 'editor'])) {
                            Notification::make()
                                ->title('Sem permissão para pausar esta automação')
                                ->danger()
                                ->send();
                            return;
                        }
                        $record->pausar();
                    })
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'ativa')
                    ->color('warning'),

                Tables\Actions\Action::make('reativar')
                    ->label('Reativar')
                    ->icon('heroicon-o-play')
                    ->action(function (EmpresaAutomacao $record) {
                        $user = auth()->user();
                        if (!$user?->hasEmpresa($record->empresa_id, ['owner', 'editor'])) {
                            Notification::make()
                                ->title('Sem permissão para reativar esta automação')
                                ->danger()
                                ->send();
                            return;
                        }
                        $record->reativar();
                    })
                    ->visible(fn ($record) => in_array($record->status, ['pausada', 'erro']))
                    ->color('success'),

                Tables\Actions\EditAction::make(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('executar_todas_prontas')
                    ->label('Executar Todas Prontas')
                    ->icon('heroicon-o-rocket-launch')
                    ->action(function () {
                        $count = EmpresaAutomacao::prontas()->count();

                        if ($count === 0) {
                            Notification::make()
                                ->title('Nenhuma automação pronta')
                                ->warning()
                                ->send();
                            return;
                        }

                        ProcessarAutomacaoJob::dispatch();

                        Notification::make()
                            ->title('Execução em massa iniciada!')
                            ->body("{$count} automações foram adicionadas à fila.")
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->color('primary'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('pausar_selecionadas')
                        ->label('Pausar Selecionadas')
                        ->icon('heroicon-o-pause')
                        ->action(function ($records) {
                            $user = auth()->user();
                            $records->each(function ($record) use ($user) {
                                if ($user?->hasEmpresa($record->empresa_id, ['owner', 'editor'])) {
                                    $record->pausar();
                                }
                            });
                        })
                        ->requiresConfirmation()
                        ->color('warning'),

                    Tables\Actions\BulkAction::make('reativar_selecionadas')
                        ->label('Reativar Selecionadas')
                        ->icon('heroicon-o-play')
                        ->action(function ($records) {
                            $user = auth()->user();
                            $records->each(function ($record) use ($user) {
                                if ($user?->hasEmpresa($record->empresa_id, ['owner', 'editor'])) {
                                    $record->reativar();
                                }
                            });
                        })
                        ->color('success'),

                ]),
            ])
            ->defaultSort('proxima_execucao', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmpresaAutomacaos::route('/'),
            'create' => Pages\CreateEmpresaAutomacao::route('/create'),
            'edit' => Pages\EditEmpresaAutomacao::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::ativas()->count();
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        return $user && $user->isAdmin();
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user && $user->isAdmin();
    }
}
