<?php

namespace App\Filament\Pages;

use App\Models\Absence;
use App\Models\Employee;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\WithFileUploads;

/**
 * Autoatendimento: colaborador registra a propria falta/ausencia, com
 * atestado opcional anexado. Nasce sempre com status Pendente -- aprovacao
 * e' feita pelo RH em App\Filament\Resources\AbsenceResource.
 *
 * Livewire puro (sem Filament\Forms) de proposito: o layout
 * layouts.checklist-mobile (compartilhado com TechnicianDailyTasks/
 * time-clock-offline) nao carrega o bundle JS do Filament, entao
 * componentes como DatePicker/FileUpload ficam quebrados nele (Alpine
 * "dateTimePickerFormComponent is not defined" etc) -- achado 27/09/2026.
 */
#[Layout('layouts.checklist-mobile')]
class MinhasFaltas extends Page
{
    use WithFileUploads;

    protected static string $view = 'filament.pages.minhas-faltas';

    protected static bool $shouldRegisterNavigation = false;

    public string $start_date = '';

    public string $end_date = '';

    public string $reason = '';

    public $attachment = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', Absence::class);
    }

    public function mount(): void
    {
        $this->end_date = now()->toDateString();
    }

    protected function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:191'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function getMinhasFaltasProperty()
    {
        $employee = $this->employee();

        if (! $employee) {
            return collect();
        }

        return Absence::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('created_at')
            ->get();
    }

    public function enviar(): void
    {
        $employee = $this->employee();

        if (! $employee) {
            Notification::make()
                ->title('Nenhum colaborador do Departamento Pessoal vinculado a este usuário.')
                ->danger()
                ->send();

            return;
        }

        $this->validate();

        $attachmentPath = $this->attachment?->store('absences', 'local');

        Absence::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'reason' => $this->reason,
            'attachment_path' => $attachmentPath,
            'status' => Absence::STATUS_PENDENTE,
        ]);

        $this->reset(['start_date', 'reason', 'attachment']);
        $this->end_date = now()->toDateString();

        Notification::make()
            ->title('Falta registrada, aguardando aprovação do RH.')
            ->success()
            ->send();
    }

    private function employee(): ?Employee
    {
        return Employee::where('user_id', Auth::id())->first();
    }
}
