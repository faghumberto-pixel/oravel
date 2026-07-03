<?php

namespace App\Filament\Resources;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\AccountPayableResource\Pages;
use App\Models\AccountPayable;
use App\Models\BillCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;
use Illuminate\Support\Str;

#[BelongsToFeature('accounts_payable')]
class AccountPayableResource extends Resource
{
    protected static ?string $model = AccountPayable::class;
    protected static ?string $tenantRelationshipName = 'accountPayables';
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationGroup = 'GESTÃO FINANCEIRA';
    protected static ?string $navigationLabel = 'Contas a Pagar';
    protected static ?string $modelLabel = 'Conta a Pagar';
    protected static ?string $pluralModelLabel = 'Contas a Pagar';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Informações da Conta')
                ->schema([
                    TextInput::make('description')->label('Descrição')->required()->maxLength(255),
                    
                    Select::make('bill_category_id')
                        ->label('Categoria')
                        ->relationship('billCategory', 'name', fn ($query) => $query->where('tenant_id', \App\Support\Tenancy::current()?->id))
                        ->required()->searchable()->preload()
                        ->createOptionForm([
                            TextInput::make('name')->required()->live(onBlur: true)->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state))),
                            TextInput::make('slug')->disabled()->dehydrated()->required(),
                        ])
                        ->createOptionUsing(fn ($data) => BillCategory::create(array_merge($data, ['tenant_id' => \App\Support\Tenancy::current()?->id]))->id),

                    TextInput::make('amount')->label('Valor')->numeric()->prefix('R$')->required(),
                    DatePicker::make('due_date')->label('Vencimento')->required()->live(),
                    DatePicker::make('payment_date')->label('Pagamento')->visible(fn ($get) => $get('status') === 'pago'),
                    
                    Select::make('status')
                        ->options(['pendente' => 'Pendente', 'pago' => 'Pago', 'atrasado' => 'Atrasado'])
                        ->required()->default('pendente')->live()->native(false),
                ])->columns(2),

            Section::make('Alocação e Origem')
                ->schema([
                    Select::make('branch_id')
                        ->label('Filial')
                        ->relationship('branch', 'name', fn ($query) => $query->where('tenant_id', \App\Support\Tenancy::current()?->id))
                        ->searchable()->preload(),

                    Select::make('cost_center_id')
                        ->label('Centro de Custo')
                        ->relationship('costCenter', 'name', fn ($query) => $query->where('tenant_id', \App\Support\Tenancy::current()?->id))
                        ->searchable()->preload(),

                    Select::make('asset_id')
                        ->label('Ativo/Equipamento')
                        ->relationship('asset', 'name', fn ($query) => $query->where('tenant_id', \App\Support\Tenancy::current()?->id))
                        ->searchable()->preload(),
                ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('description')->label('Descrição')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('billCategory.name')->label('Categoria'),
                Tables\Columns\TextColumn::make('branch.name')->label('Filial'),
                Tables\Columns\TextColumn::make('amount')->label('Valor')->money('BRL'),
                Tables\Columns\TextColumn::make('due_date')->label('Vencimento')->date('d/m/Y'),
                Tables\Columns\TextColumn::make('status')->badge()->colors([
                    'warning' => 'pendente', 'success' => 'pago', 'danger' => 'atrasado',
                ])->formatStateUsing(fn ($state) => ucfirst($state)),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(fn ($record) => self::sendNotification($record, 'atualizada')),
                Tables\Actions\DeleteAction::make()
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->after(fn ($record) => self::sendNotification($record, 'registrada'))
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])
            ]);
    }

    protected static function sendNotification($record, $action): void
    {
        Notification::make()
            ->title("Conta {$action}")
            ->body("A conta '{$record->description}' foi {$action} com sucesso.")
            ->success()
            ->sendToDatabase(auth()->user())
            ->send();
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAccountPayables::route('/')];
    }
}