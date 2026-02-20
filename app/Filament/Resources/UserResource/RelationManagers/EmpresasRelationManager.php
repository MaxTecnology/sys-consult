<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Actions\AttachAction;
use Filament\Tables\Actions\DetachAction;

class EmpresasRelationManager extends RelationManager
{
    protected static string $relationship = 'empresas';
    protected static ?string $recordTitleAttribute = 'razao_social';
    protected static ?string $title = 'Empresas vinculadas';

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('role')
                    ->label('Perfil')
                    ->options([
                        'owner' => 'Owner',
                        'editor' => 'Editor',
                        'viewer' => 'Viewer',
                    ])
                    ->default('viewer')
                    ->required(),
            ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('razao_social')->label('Empresa'),
                TextColumn::make('cnpj')->label('CNPJ')->limit(18),
                BadgeColumn::make('pivot.role')
                    ->label('Perfil')
                    ->colors([
                        'success' => 'owner',
                        'warning' => 'editor',
                        'gray' => 'viewer',
                    ]),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Vincular')
                    ->preloadRecordSelect()
                    ->visible(fn ($livewire) => $livewire->getOwnerRecord()->role !== 'admin')
                    ->form(fn (AttachAction $action) => [
                        $action->getRecordSelect()
                            ->label('Empresa')
                            ->searchable()
                            ->preload(),
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Alterar Perfil')
                    ->form([
                        Forms\Components\Select::make('role')
                            ->label('Perfil')
                            ->options([
                                'viewer' => 'Leitor',
                            ])
                            ->default('viewer')
                            ->required(),
                    ]),
                DetachAction::make()
                    ->label('Remover')
                    ->visible(fn ($livewire) => $livewire->getOwnerRecord()->role !== 'admin'),
            ]);
    }

}
