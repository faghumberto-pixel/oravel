<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AbsenceResource\Pages;
use App\Models\Absence;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AbsenceResource extends BaseResource
{
    protected static ?string $model = Absence::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Departamento Pessoal';

    protected static ?string $navigationLabel = 'Faltas e Ausências';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Falta/Ausência';

    protected static ?string $pluralModelLabel = 'Faltas e Ausências';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('employee_id')
                ->label('Colaborador')
                ->relationship('employee', 'name')
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\DatePicker::make('start_date')->label('De')->required()->native(false),
            Forms\Components\DatePicker::make('end_date')->label('Até')->required()->native(false),
            Forms\Components\TextInput::make('reason')->label('Motivo')->required()->maxLength(191)->columnSpanFull(),
            Forms\Components\Select::make('status')
                ->label('Status')
                ->options(Absence::statusLabels())
                ->default(Absence::STATUS_PENDENTE)
                ->required()
                ->native(false),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.name')->label('Colaborador')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('start_date')->label('De')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('end_date')->label('Até')->date('d/m/Y'),
                Tables\Columns\TextColumn::make('reason')->label('Motivo')->limit(40),
                Tables\Columns\IconColumn::make('attachment_path')
                    ->label('Atestado')
                    ->boolean()
                    ->state(fn (Absence $record) => filled($record->attachment_path)),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Absence::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        Absence::STATUS_APROVADO => 'success',
                        Absence::STATUS_REJEITADO => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('created_at')->label('Registrado em')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Status')->options(Absence::statusLabels()),
            ])
            ->actions([
                Tables\Actions\Action::make('baixarAtestado')
                    ->label('Ver atestado')
                    ->icon('heroicon-o-paper-clip')
                    ->color('gray')
                    ->visible(fn (Absence $record) => filled($record->attachment_path))
                    ->url(fn (Absence $record) => Storage::disk('local')->url($record->attachment_path))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('aprovar')
                    ->label('Aprovar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Absence $record) => $record->status === Absence::STATUS_PENDENTE)
                    ->action(function (Absence $record) {
                        $record->update([
                            'status' => Absence::STATUS_APROVADO,
                            'reviewed_by' => Auth::id(),
                            'reviewed_at' => now(),
                        ]);

                        Notification::make()->title('Falta aprovada')->success()->send();
                    }),
                Tables\Actions\Action::make('rejeitar')
                    ->label('Rejeitar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Absence $record) => $record->status === Absence::STATUS_PENDENTE)
                    ->form([
                        Forms\Components\Textarea::make('review_notes')->label('Motivo da rejeição')->required(),
                    ])
                    ->action(function (Absence $record, array $data) {
                        $record->update([
                            'status' => Absence::STATUS_REJEITADO,
                            'reviewed_by' => Auth::id(),
                            'reviewed_at' => now(),
                            'review_notes' => $data['review_notes'],
                        ]);

                        Notification::make()->title('Falta rejeitada')->danger()->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAbsences::route('/')];
    }
}
