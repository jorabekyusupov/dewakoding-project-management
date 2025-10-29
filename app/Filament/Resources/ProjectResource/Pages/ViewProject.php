<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Grid;
use Filament\Support\Enums\FontWeight;

class ViewProject extends ViewRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('board')
                ->label(__('resources.project.view.actions.project_board'))
                ->icon('heroicon-o-view-columns')
                ->color('info')
                ->url(fn () => \App\Filament\Pages\ProjectBoard::getUrl(['project_id' => $this->record->id])),
            Actions\Action::make('external_access')
                ->label(__('resources.project.view.actions.external_dashboard'))
                ->icon('heroicon-o-globe-alt')
                ->color('success')
                ->visible(fn () => auth()->user()->hasRole('super_admin'))
                ->modalHeading(__('resources.project.view.actions.external_modal_heading'))
                ->modalDescription(__('resources.project.view.actions.external_modal_description'))
                ->modalContent(function () {
                    $record = $this->record;
                    $externalAccess = $record->externalAccess;
                
                    if (!$externalAccess) {
                        $externalAccess = $record->generateExternalAccess();
                    }
                
                    $dashboardUrl = url('/external/' . $externalAccess->access_token);
                
                    return view('filament.components.external-access-modal', [
                        'dashboardUrl' => $dashboardUrl,
                        'password' => $externalAccess->password,
                        'lastAccessed' => $externalAccess->last_accessed_at ? $externalAccess->last_accessed_at->format('d/m/Y H:i') : null,
                        'isActive' => $externalAccess->is_active,
                    ]);
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('resources.project.view.actions.external_modal_close')),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make(__('resources.project.view.sections.information'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('name')
                                    ->label(__('resources.project.view.fields.name'))
                                    ->weight(FontWeight::Bold)
                                    ->size('lg'),
                                TextEntry::make('ticket_prefix')
                                    ->label(__('resources.project.view.fields.ticket_prefix'))
                                    ->badge()
                                    ->color('primary'),
                            ]),
                        TextEntry::make('description')
                            ->label(__('resources.project.view.fields.description'))
                            ->html()
                            ->columnSpanFull(),
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('start_date')
                                    ->label(__('resources.project.view.fields.start_date'))
                                    ->date('d/m/Y')
                                    ->placeholder(__('resources.project.view.values.not_set')),
                                TextEntry::make('end_date')
                                    ->label(__('resources.project.view.fields.end_date'))
                                    ->date('d/m/Y')
                                    ->placeholder(__('resources.project.view.values.not_set')),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('remaining_days')
                                    ->label(__('resources.project.view.fields.remaining_days'))
                                    ->getStateUsing(function ($record): ?string {
                                        if (!$record->end_date) {
                                            return __('resources.project.view.values.not_set');
                                        }
                                        return trans_choice('resources.project.view.values.remaining_days_text', $record->remaining_days, [
                                            'count' => $record->remaining_days,
                                        ]);
                                    })
                                    ->badge()
                                    ->color(fn ($record): string => 
                                        !$record->end_date ? 'gray' :
                                        ($record->remaining_days <= 0 ? 'danger' : 
                                        ($record->remaining_days <= 7 ? 'warning' : 'success'))
                                    ),
                                TextEntry::make('pinned_date')
                                    ->label(__('resources.project.view.fields.pinned_status'))
                                    ->getStateUsing(function ($record): string {
                                        return $record->pinned_date
                                            ? __('resources.project.view.values.pinned_on', ['date' => $record->pinned_date->format('d/m/Y H:i')])
                                            : __('resources.project.view.values.not_pinned');
                                    })
                                    ->badge()
                                    ->color(fn ($record): string => $record->pinned_date ? 'success' : 'gray'),
                            ]),
                    ]),
                
                Section::make(__('resources.project.view.sections.statistics'))
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('members_count')
                                    ->label(__('resources.project.view.fields.total_members'))
                                    ->getStateUsing(fn ($record) => $record->members()->count())
                                    ->badge()
                                    ->color('info'),
                                TextEntry::make('tickets_count')
                                    ->label(__('resources.project.view.fields.total_tickets'))
                                    ->getStateUsing(fn ($record) => $record->tickets()->count())
                                    ->badge()
                                    ->color('primary'),
                                TextEntry::make('epics_count')
                                    ->label(__('resources.project.view.fields.total_epics'))
                                    ->getStateUsing(fn ($record) => $record->epics()->count())
                                    ->badge()
                                    ->color('warning'),
                                TextEntry::make('statuses_count')
                                    ->label(__('resources.project.view.fields.statuses_count'))
                                    ->getStateUsing(fn ($record) => $record->ticketStatuses()->count())
                                    ->badge()
                                    ->color('success'),
                            ]),
                    ]),
                    
                Section::make(__('resources.project.view.sections.timestamps'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label(__('resources.project.view.fields.created_at'))
                                    ->dateTime('d/m/Y H:i'),
                                TextEntry::make('updated_at')
                                    ->label(__('resources.project.view.fields.updated_at'))
                                    ->dateTime('d/m/Y H:i'),
                            ]),
                    ])
                    ->collapsible(),
            ]);
    }
}
