<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmpresaResource\Pages;
use App\Models\Empresa;
use App\Services\ConsultaEmpresaService;
use App\Services\DteMessageSyncService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class EmpresaResource extends Resource
{
    protected static ?string $model = Empresa::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationLabel = 'Empresas';
    protected static ?string $modelLabel = 'Empresa';
    protected static ?string $pluralModelLabel = 'Empresas';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados Principais')
                    ->schema([
                        Forms\Components\TextInput::make('razao_social')
                            ->label('Razão Social')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('nome_fantasia')
                            ->label('Nome Fantasia')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('cnpj')
                            ->label('CNPJ')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->mask('99.999.999/9999-99')
                            ->maxLength(18),

                        Forms\Components\TextInput::make('inscricao_estadual')
                            ->label('Inscrição Estadual')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('inscricao_municipal')
                            ->label('Inscrição Municipal')
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Contato')
                    ->schema([
                        Forms\Components\TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('telefone')
                            ->label('Telefone')
                            ->tel()
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Endereço')
                    ->schema([
                        Forms\Components\TextInput::make('cep')
                            ->label('CEP')
                            ->mask('99999-999')
                            ->maxLength(10),

                        Forms\Components\TextInput::make('endereco')
                            ->label('Endereço')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('numero')
                            ->label('Número')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('complemento')
                            ->label('Complemento')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('bairro')
                            ->label('Bairro')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('cidade')
                            ->label('Cidade')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('uf')
                            ->label('UF')
                            ->maxLength(2)
                            ->length(2),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('ativo')
                            ->label('Ativo (soft)')
                            ->helperText('Desative para esconder sem remover registros.')
                            ->default(true),

                        Forms\Components\DateTimePicker::make('ultima_consulta_api')
                            ->label('Última Consulta API')
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('razao_social')
                    ->label('Razão Social')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('nome_fantasia')
                    ->label('Nome Fantasia')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('cnpj_formatado')
                    ->label('CNPJ')
                    ->searchable(query: fn ($query, $search) => $query->where('cnpj', 'like', '%' . preg_replace('/\D/', '', $search) . '%'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('inscricao_estadual')
                    ->label('Inscrição Estadual')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('cidade')
                    ->label('Cidade')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('uf')
                    ->label('UF')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('ativo')
                    ->label('Ativo')
                    ->getStateUsing(fn ($record) => $record->ativo ? 'Sim' : 'Não')
                    ->colors(fn ($record) => [
                        'success' => $record->ativo,
                        'danger' => !$record->ativo,
                    ]),

                Tables\Columns\TextColumn::make('ultima_consulta_api')
                    ->label('Última Consulta')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('ativo')
                    ->label('Status')
                    ->options([
                        1 => 'Ativo',
                        0 => 'Inativo',
                    ]),

                Tables\Filters\SelectFilter::make('uf')
                    ->label('UF')
                    ->options(function () {
                        return Empresa::distinct('uf')
                            ->whereNotNull('uf')
                            ->pluck('uf', 'uf')
                            ->toArray();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('consultar_api')
                    ->label('Nova Consulta')
                    ->icon('heroicon-o-magnifying-glass')
                    ->form([
                        Forms\Components\Select::make('certificado_id')
                            ->label('Certificado')
                            ->options(\App\Models\Certificado::ativos()->pluck('nome', 'id'))
                            ->required(),

                        Forms\Components\Select::make('tipo_consulta')
                            ->label('Tipo de Consulta')
                            ->options([
                                'caixa-postal' => 'Caixa Postal SEFAZ/AL',
                            ])
                            ->default('caixa-postal')
                            ->required(),
                    ])
                    ->action(function (Empresa $record, array $data) {
                        if (!$record->inscricao_estadual) {
                            Notification::make()
                                ->title('Empresa sem Inscrição Estadual')
                                ->body('Esta empresa não possui inscrição estadual cadastrada.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $certificado = \App\Models\Certificado::find($data['certificado_id']);
                        $service = new \App\Services\InfoSimplesService();
                        $syncService = app(DteMessageSyncService::class);

                        try {
                            $consulta = $service->consultarEmpresaCaixaPostal($record, $certificado);
                            $resultadoSync = $syncService->syncFromConsulta($consulta);
                            $importadas = $resultadoSync['importadas'] ?? 0;
                            $atualizadas = $resultadoSync['atualizadas'] ?? 0;

                            if ($consulta->sucesso) {
                                Notification::make()
                                    ->title('Consulta realizada com sucesso!')
                                    ->body("Código: {$consulta->response_code} | Mensagens importadas: {$importadas} / atualizadas: {$atualizadas}")
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Consulta retornou erro')
                                    ->body("Código: {$consulta->response_code} - {$consulta->code_message}")
                                    ->warning()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Erro ao realizar consulta')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->color('info'),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('consultar_api_bulk')
                        ->label('Consultar API (Selecionados)')
                        ->icon('heroicon-o-magnifying-glass')
                        ->action(function ($records) {
                            $service = new ConsultaEmpresaService();
                            $sucessos = 0;

                            foreach ($records as $empresa) {
                                if ($service->atualizarEmpresaComApi($empresa)) {
                                    $sucessos++;
                                }
                            }

                            Notification::make()
                                ->title("{$sucessos} empresas atualizadas com sucesso!")
                                ->success()
                                ->send();
                        })
                        ->color('info'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmpresas::route('/'),
            'create' => Pages\CreateEmpresa::route('/create'),
            'edit' => Pages\EditEmpresa::route('/{record}/edit'),
        ];
    }
}
