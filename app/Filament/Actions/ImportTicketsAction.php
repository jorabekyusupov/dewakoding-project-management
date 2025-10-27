<?php

namespace App\Filament\Actions;

use App\Imports\TicketsImport;
use App\Exports\TicketTemplateExport;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Notifications\Notification;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportTicketsAction
{
    public static function make(): Action
    {
        return Action::make('import_tickets')
            ->label(__('actions.import_tickets.label'))
            ->icon('heroicon-m-arrow-up-tray')
            ->color('success')
            ->form([
                Section::make(__('actions.import_tickets.section_title'))
                    ->description(__('actions.import_tickets.section_description'))
                    ->schema([
                        Select::make('project_id')
                            ->label(__('actions.import_tickets.select_project'))
                            ->options(function () {
                                return Project::query()
                                    ->whereHas('members', function ($query) {
                                        $query->where('user_id', auth()->id());
                                    })
                                    ->orWhere(function ($query) {
                                        if (auth()->user()->hasRole('super_admin')) {
                                            $query->whereRaw('1=1');
                                        }
                                    })
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->required()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function ($state, $set) {
                                // Reset file upload when project changes
                                $set('excel_file', null);
                            }),
                        
                        Actions::make([
                            FormAction::make('download_template')
                                ->label(__('actions.import_tickets.download_template'))
                                ->icon('heroicon-m-arrow-down-tray')
                                ->color('info')
                                ->visible(fn ($get) => $get('project_id'))
                                ->action(function ($get) {
                                    $projectId = $get('project_id');
                                    if (!$projectId) {
                                        Notification::make()
                                            ->title(__('actions.import_tickets.notifications.error.title'))
                                            ->body(__('actions.import_tickets.notifications.error.body'))
                                            ->danger()
                                            ->send();
                                        return;
                                    }
                                    
                                    $project = Project::findOrFail($projectId);
                                    $filename = 'ticket-import-template-' . str($project->name)->slug() . '.xlsx';
                                    
                                    return Excel::download(
                                        new TicketTemplateExport($project),
                                        $filename
                                    );
                                })
                        ])->fullWidth(),
                        
                        FileUpload::make('excel_file')
                            ->label(__('actions.import_tickets.file.label'))
                            ->helperText(__('actions.import_tickets.file.helper'))
                            ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'])
                            ->maxSize(5120) // 5MB
                            ->required()
                            ->disk('local')
                            ->directory('temp-imports')
                            ->visibility('private')
                            ->visible(fn ($get) => $get('project_id')),
                    ]),
            ])
            ->action(function (array $data) {
                $project = Project::findOrFail($data['project_id']);
                $filePath = Storage::disk('local')->path($data['excel_file']);
                
                try {
                    $import = new TicketsImport($project);
                    Excel::import($import, $filePath);
                    
                    $importedCount = $import->getImportedCount();
                    $errors = $import->errors();
                    $failures = $import->failures();
                    
                    // Clean up uploaded file
                    Storage::disk('local')->delete($data['excel_file']);
                    
                    if ($importedCount > 0) {
                        $message = __('actions.import_tickets.notifications.success.body', [
                            'count' => $importedCount,
                            'project' => $project->name,
                        ]);
                        
                        if (count($errors) > 0 || count($failures) > 0) {
                            $message .= ' ' . __('actions.import_tickets.notifications.success.partial');
                        }
                        
                        Notification::make()
                            ->title(__('actions.import_tickets.notifications.success.title'))
                            ->body($message)
                            ->success()
                            ->send();
                    } else {
                        $errorDetails = [];
                        
                        // Collect validation errors
                        if (count($failures) > 0) {
                            $errorDetails[] = "Validation errors found in " . count($failures) . " row(s):";
                            foreach ($failures as $failure) {
                                $errorDetails[] = "Row {$failure->row()}: " . implode(', ', $failure->errors());
                            }
                        }
                        
                        // Collect general errors
                        if (count($errors) > 0) {
                            $errorDetails[] = "Processing errors:";
                            foreach ($errors as $error) {
                                $errorDetails[] = $error;
                            }
                        }
                        
                        // If no specific errors, provide general guidance
                        if (empty($errorDetails)) {
                            $errorDetails = [
                                "Common issues to check:",
                                "• Ensure the Excel file uses the correct template format",
                                "• Verify that 'Title' and 'Status' columns are filled",
                                "• Check that status names match existing project statuses",
                                "• Ensure date formats are YYYY-MM-DD",
                                "• Verify assignee emails exist and are project members"
                            ];
                        }
                        
                        Notification::make()
                            ->title(__('actions.import_tickets.notifications.failed.title'))
                            ->body(implode("\n", $errorDetails))
                            ->warning()
                            ->persistent()
                            ->send();
                    }
                    
                } catch (\Exception $e) {
                    // Clean up uploaded file
                    Storage::disk('local')->delete($data['excel_file']);
                    
                    // Provide more specific error information
                    $errorMessage = 'An error occurred during import: ' . $e->getMessage();
                    
                    // Add specific guidance based on error type
                    if (str_contains($e->getMessage(), 'file')) {
                        $errorMessage .= "\n\nFile-related issues to check:\n• Ensure the file is a valid Excel format (.xlsx or .xls)\n• Check that the file is not corrupted\n• Verify the file size is under 5MB";
                    } elseif (str_contains($e->getMessage(), 'database') || str_contains($e->getMessage(), 'SQL')) {
                        $errorMessage .= "\n\nDatabase-related issues to check:\n• Verify all required fields are provided\n• Check that referenced data (statuses, users, epics) exist\n• Ensure data types match expected formats";
                    } else {
                        $errorMessage .= "\n\nGeneral troubleshooting:\n• Download and use the latest template\n• Check that all required columns are present\n• Verify data format matches the template";
                    }
                    
                    Notification::make()
                        ->title(__('actions.import_tickets.notifications.error_generic.title'))
                        ->body($errorMessage)
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }
}
