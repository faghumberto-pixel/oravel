<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages;
use App\Filament\Resources\EmployeeResource\RelationManagers;
use App\Models\Employee;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class EmployeeResource extends BaseResource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Departamento Pessoal';

    protected static ?string $navigationLabel = 'Colaboradores';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Dados do Colaborador')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome')
                        ->required()
                        ->maxLength(191),
                    Forms\Components\TextInput::make('cpf')
                        ->label('CPF')
                        ->required()
                        ->length(11)
                        ->numeric(),
                    Forms\Components\Select::make('department_id')
                        ->label('Setor')
                        ->relationship('department', 'name')
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('role_title')
                        ->label('Cargo')
                        ->maxLength(191),
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options(Employee::statusLabels())
                        ->default(Employee::STATUS_ATIVO)
                        ->required()
                        ->native(false),
                    Forms\Components\DatePicker::make('admission_date')
                        ->label('Data de Admissão'),
                    Forms\Components\Select::make('user_id')
                        ->label('Usuário do painel vinculado')
                        ->helperText('Só preencher se este colaborador também faz login no Oravel.')
                        ->relationship('user', 'name')
                        ->searchable()
                        ->preload()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nome')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('cpf')->label('CPF')->searchable(),
                Tables\Columns\TextColumn::make('department.name')->label('Setor')->searchable(),
                Tables\Columns\TextColumn::make('role_title')->label('Cargo')->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Employee::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        Employee::STATUS_ATIVO => 'success',
                        Employee::STATUS_AFASTADO => 'warning',
                        Employee::STATUS_DESLIGADO => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('certificacoes_alerta')
                    ->label('Certificações')
                    ->state(function (Employee $record) {
                        $vencidas = $record->certifications()->get()->filter(fn ($c) => $c->isVencida())->count();
                        $proximas = $record->certifications()->get()->filter(fn ($c) => $c->isProximoVencimento())->count();

                        if ($vencidas) {
                            return "{$vencidas} vencida(s)";
                        }
                        if ($proximas) {
                            return "{$proximas} vencendo";
                        }

                        return 'Em dia';
                    })
                    ->badge()
                    ->color(function (Employee $record) {
                        $vencidas = $record->certifications()->get()->filter(fn ($c) => $c->isVencida())->count();
                        if ($vencidas) {
                            return 'danger';
                        }
                        $proximas = $record->certifications()->get()->filter(fn ($c) => $c->isProximoVencimento())->count();

                        return $proximas ? 'warning' : 'success';
                    }),
                Tables\Columns\TextColumn::make('admission_date')->label('Admissão')->date('d/m/Y')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Status')->options(Employee::statusLabels()),
                Tables\Filters\SelectFilter::make('department_id')->label('Setor')->relationship('department', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\CertificationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
