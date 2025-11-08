<?php

namespace App\Livewire;

use App\Models\Ticket;
use Livewire\Component;
use Filament\Schemas\Schema;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Concerns\InteractsWithForms;

class TicketCommentForm extends Component implements HasForms
{
    use InteractsWithForms;

    public Ticket $ticket;
    public $newComment = '';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                RichEditor::make('newComment')
                    ->label('Add a Comment')
                    ->placeholder('Write your comment here...')
                    ->toolbarButtons([
                        'blockquote',
                        'bold',
                        'bulletList',
                        'codeBlock',
                        'italic',
                        'link',
                        'orderedList',
                        'redo',
                        'strike',
                        'undo',
                    ])
                    ->required()
                    ->extraInputAttributes(['style' => 'min-height: 10rem;']),
            ]);
    }

    public function addComment()
    {
        $data = $this->form->getState();

        $this->ticket->comments()->create([
            'user_id' => auth()->id(),
            'comment' => $data['newComment']
        ]);

        auth()->user()->notifications()
            ->where('data->ticket_id', $this->ticket->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        Notification::make()
            ->title('Comment added successfully')
            ->success()
            ->send();

        $this->form->fill();

        $this->dispatch('comment-added');
    }

    public function render()
    {
        return view('livewire.ticket-comment-form');
    }
}
