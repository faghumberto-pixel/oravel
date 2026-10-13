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
            Forms\Components\Select::make('type')->label('Tipo')->options(Absence::typeLabels())->required()->native(false),
            Forms\Components\TextInput::make('cid_code')->label('CID')->maxLength(10)->placeholder('Ex.: J11'),
            Forms\Components\DatePicker::make('start_date')->label('De')->required()->native(false),
            Forms\Components\DatePicker::make('end_date')->label('Até')->required()->native(false),
            Forms\Components\TextInput::make('reason')->label('Motivo')->required()->maxLength(191)->columnSpanFull(),
            Forms\Components\Textarea::make('details')->label('Explicação do colaborador')->rows(3)->columnSpanFull(),
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
                Tables\Columns\TextColumn::make('type')->label('Tipo')->formatStateUsing(fn (string $state) => Absence::typeLabels()[$state] ?? $state)->toggleable(),
                Tables\Columns\TextColumn::make('start_date')->label('De')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('end_date')->label('Até')->date('d/m/Y'),
                Tables\Columns\TextColumn::make('days')->label('Dias')->state(fn (Absence $r) => $r->days),
                Tables\Columns\TextColumn::make('cid_code')->label('CID')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('reason')->label('Motivo')->limit(40)->tooltip(fn (Absence $r) => $r->details),
                Tables\Columns\IconColumn::make('attachment_path')
                    ->label('Atestado')
                    ->boolean()
                    ->state(fn (Absence $record) => $record->attachmentPaths() !== []),
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
                Tables\Filters\SelectFilter::make('type')->label('Tipo')->options(Absence::typeLabels()),
                Tables\Filters\SelectFilter::make('employee_id')->label('Colaborador')->relationship('employee', 'name')->searchable()->preload(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make(collect(range(0, 4))->map(fn (int $i) => Tables\Actions\Action::make("anexo{$i}")
                    ->label('Ver anexo '.($i + 1))
                    ->icon('heroicon-o-paper-clip')
                    ->visible(fn (Absence $record) => isset($record->attachmentPaths()[$i]))
                    ->url(fn (Absence $record) => route('ausencias.anexo', [$record->id, $i]))
                    ->openUrlInNewTab())->all())->label('Atestado')->icon('heroicon-o-paper-clip')->color('gray')->button()
                    ->visible(fn (Absence $record) => $record->attachmentPaths() !== []),
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
