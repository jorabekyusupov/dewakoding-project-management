<?php

namespace App\Filament\Actions;

use Filament\Schemas\Components\Section;
use App\Exports\TicketsExport;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportTicketsAction
{
    public static function make(): Action
    {
        return Action::make('export_tickets')
            ->label(__('actions.export_tickets.label'))
            ->icon('heroicon-m-arrow-down-tray')
            ->color('success')
            ->schema([
                Section::make(__('actions.export_tickets.section_title'))
                    ->description(__('actions.export_tickets.section_description'))
                    ->schema([
                        CheckboxList::make('columns')
                            ->label(__('actions.export_tickets.columns.label'))
                            ->options([
                                'uuid' => __('actions.export_tickets.columns.uuid'),
                                'name' => __('actions.export_tickets.columns.name'),
                                'description' => __('actions.export_tickets.columns.description'),
                                'status' => __('actions.export_tickets.columns.status'),
                                'assignee' => __('actions.export_tickets.columns.assignee'),
                                'project' => __('actions.export_tickets.columns.project'),
                                'epic' => __('actions.export_tickets.columns.epic'),
                                'due_date' => __('actions.export_tickets.columns.due_date'),
                                'created_at' => __('actions.export_tickets.columns.created_at'),
                                'updated_at' => __('actions.export_tickets.columns.updated_at'),
                            ])
                            ->default(['uuid', 'name', 'status', 'assignee', 'due_date', 'created_at'])
                            ->required()
                            ->minItems(1)
                            ->columns(2)
                            ->gridDirection('row')
                    ])
            ])
            ->action(function (array $data, $livewire): void {
                $livewire->exportTickets($data['columns'] ?? []);
            });
    }
}