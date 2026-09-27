<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages;
use App\Filament\Resources\EmployeeResource\RelationManagers;
use App\Models\Employee;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmployeeResource extends BaseResource
{
    protected static ?string $model = Employee::class;

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    // Fundido com UserResource 27/09/2026 (pedido do usuário: "não quero
    // funcionário, vamos usar colaborador e fundir") -- UserResource é
    // agora A tela "Colaboradores" (login + ficha de RH via toggle "Ativar
    // ficha de RH"). Este Resource continua existindo só pra editar a
    // ficha de RH completa de quem NÃO tem login (ex: motorista/técnico
    // sem acesso ao painel) -- por isso nasce como sub-item, não duplica
    // o nome "Colaboradores" no menu.
    protected static ?string $navigationGroup = 'Equipe';

    protected static ?string $navigationParentItem = 'Colaboradores';

    protected static ?string $navigationLabel = 'Ficha de RH (sem login)';

    // Sem isso, o Filament deriva o rotulo do nome da classe (Employee) e
    // toda tela/breadcrumb/botao aparece em ingles ("Employees", "Criar
    // employee") -- exatamente a ambiguidade que gerou a duvida
    // "colaborador != funcionario".
    protected static ?string $modelLabel = 'Colaborador';

    protected static ?string $pluralModelLabel = 'Colaboradores';

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
                    Forms\Components\Select::make('job_role_id')
                        ->label('Tipo/Função')
                        ->helperText('Usado pra filtrar e agrupar colaboradores (ex: vincular rastreamento GPS só a vendedores/técnicos). Gerencie em Equipe → Funções e Cargos.')
                        ->relationship('jobRole', 'name')
                        ->searchable()
                        ->preload()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')->label('Nome da função')->required()->maxLength(191),
                        ])
                        ->native(false),
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options(Employee::statusLabels())
                        ->default(Employee::STATUS_ATIVO)
                        ->required()
                        ->native(false),
                    Forms\Components\DatePicker::make('admission_date')
                        ->label('Data de Admissão'),
                    Forms\Components\TextInput::make('daily_work_hours')
                        ->label('Jornada diária (h)')
                        ->helperText('Usado pra calcular horas extras em "Minhas Horas".')
                        ->numeric()
                        ->step(0.5)
                        ->default(8)
                        ->required(),
                    Forms\Components\Select::make('user_id')
                        ->label('Usuário do painel vinculado')
                        ->helperText('Só preencher se este colaborador também faz login no Oravel.')
                        ->relationship(
                            name: 'user',
                            titleAttribute: 'name',
                            modifyQueryUsing: function (Builder $query) {
                                $tenant = Tenancy::current();

                                return $query->when($tenant, fn (Builder $q) => $q->where('tenant_id', $tenant->id));
                            },
                        )
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
                Tables\Columns\TextColumn::make('jobRole.name')
                    ->label('Tipo/Função')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Employee::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        Employee::STATUS_ATIVO => 'success',
                        Employee::STATUS_AFASTADO => 'warning',
                        Employee::STATUS_DESLIGADO => 'gray',
                        Employee::STATUS_INCOMPLETO => 'danger',
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
                Tables\Filters\SelectFilter::make('job_role_id')->label('Tipo/Função')->relationship('jobRole', 'name'),
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
            RelationManagers\EpiDeliveriesRelationManager::class,
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
