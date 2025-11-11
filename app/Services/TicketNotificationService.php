<?php

namespace App\Services;

use App\Library\Bot\InfoBot;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use App\Models\TicketComment;
use Illuminate\Support\Str;

class TicketNotificationService
{
    public function __construct(
        private readonly InfoBot $infoBot,
    ) {
    }

    public function notifyTicketCreated(Ticket $ticket): void
    {
        $ticket->loadMissing([
            'project',
            'priority',
            'creator',
            'assignees',
            'status',
            'epic',
        ]);


        $assignees = $ticket->assignees->pluck('name')->implode(', ');
        $assigneesChatIDs = $ticket->assignees->pluck('chat_id')->filter()->all();

        $projectChatId = $ticket->project?->chat_id;
        $threadId = $ticket->project?->thread_id;

        $this->notifyProjectAboutCreatedTicket($ticket, $assignees, $projectChatId, $threadId);
        $this->notifyAssigneesAboutCreatedTicket($ticket, $assigneesChatIDs);
    }

    public function notifyTicketStatusChanged(Ticket $ticket, string $oldStatus, string $newStatus): void
    {
        $ticket->loadMissing([
            'project',
            'priority',
            'creator',
            'assignees',
        ]);


        $assignees = $ticket->assignees->pluck('name')->implode(', ');
        $assigneesChatIDs = $ticket->assignees->pluck('chat_id')->filter()->all();

        $projectChatId = $ticket->project?->chat_id;
        $threadId = $ticket->project?->thread_id;

        $this->notifyProjectAboutStatusChange($ticket, $assignees, $projectChatId, $threadId, $oldStatus, $newStatus);
        $this->notifyAssigneesAboutStatusChange($ticket, $assigneesChatIDs, $newStatus);
    }

    public function notifyCommentAdded(TicketComment $comment): void
    {
        $comment->loadMissing(['user', 'ticket.project', 'ticket.assignees', 'ticket.priority', 'ticket.creator']);

        $ticket = $comment->ticket;

        if (! $ticket) {
            return;
        }

        $projectChatId = $ticket->project?->chat_id;
        $threadId = $ticket->project?->thread_id;
        $authorName = $comment->user?->name ?? 'Неизвестно';
        $commentPreview = $this->buildCommentPreview($comment);

        $assigneesChatIDs = $ticket->assignees
            ->where('id', '!=', $comment->user_id)
            ->pluck('chat_id')
            ->filter()
            ->all();


        $this->notifyProjectAboutComment($ticket, $authorName, $commentPreview, $projectChatId, $threadId);
        $this->notifyAssigneesAboutComment($ticket, $authorName, $commentPreview, $assigneesChatIDs);
    }

    private function notifyProjectAboutCreatedTicket(Ticket $ticket, string $assignees, ?string $projectChatId, ?string $threadId): void
    {
        if (empty($projectChatId)) {
            return;
        }

        $text = '🆕 Создана новая задача: ' . $ticket->name . PHP_EOL .
            '🔗 http://kanban.mo.local/admin/tickets/' . $ticket->id . PHP_EOL .
            '🆔 Проект: ' . $ticket->project->name . PHP_EOL .
            '👨‍💼 Создатель: ' . $ticket->creator->name . PHP_EOL .
            '❕ Статус: ' . $ticket->status->name . PHP_EOL .
            '🔖 Этап: ' . ($ticket->epic ? $ticket->epic->name : 'Не указан') . PHP_EOL .
            '⏰ Срок: ' . ($ticket->due_date ? $ticket->due_date->format('d.m.Y') : 'Не указан') . PHP_EOL .
            '‼️ Приоритет: ' . ($ticket->priority ? $ticket->priority->name : 'Не указан') . PHP_EOL .
            '👥 Исполнители: ' . ($assignees ?: 'Не назначены') . PHP_EOL;

        $this->infoBot->send($projectChatId, $text, $threadId);
    }

    private function notifyAssigneesAboutCreatedTicket(Ticket $ticket, array $assigneesChatIDs): void
    {
        if (empty($assigneesChatIDs)) {
            return;
        }

        foreach ($assigneesChatIDs as $assigneesChatID) {
            $text = '🆕 Вам назначена новая задача: ' . $ticket->name . PHP_EOL .
                '🔗 http://kanban.mo.local/admin/tickets/' . $ticket->id . PHP_EOL .
                '🆔 Проект: ' . $ticket->project->name . PHP_EOL .
                '👨‍💼 Создатель: ' . $ticket->creator->name . PHP_EOL .
                '❕ Статус: ' . $ticket->status->name . PHP_EOL .
                '🔖 Этап: ' . ($ticket->epic ? $ticket->epic->name : 'Не указан') . PHP_EOL .
                '⏰ Срок: ' . ($ticket->due_date ? $ticket->due_date->format('d.m.Y') : 'Не указан') . PHP_EOL .
                '‼️ Приоритет: ' . ($ticket->priority ? $ticket->priority->name : 'Не указан') . PHP_EOL;

            $this->infoBot->send($assigneesChatID, $text);
        }
    }

    private function notifyProjectAboutStatusChange(Ticket $ticket, string $assignees, ?string $projectChatId, ?string $threadId, string $oldStatus, string $newStatus): void
    {
        if (empty($projectChatId)) {
            return;
        }

        $text = '🔧 Статус задачи обновлен!: ' . $newStatus . PHP_EOL .
            '🆔 Задача: ' . $ticket->name . PHP_EOL .
            '👨‍💼 Кто изменил: ' . auth()->user()->name . PHP_EOL .
            '❕ Предыдущий статус: ' . $oldStatus . PHP_EOL .
            '🆔 Проект: ' . $ticket->project->name . PHP_EOL .
            '👥 Исполнители: ' . ($assignees ?: 'Не назначены') . PHP_EOL .
            '⏰ Время изменения: ' . now()->format('d.m.Y H:i') . PHP_EOL .
            '🔗 http://kanban.mo.local/admin/tickets/' . $ticket->id . PHP_EOL;

        $this->infoBot->send($projectChatId, $text, $threadId);
    }

    private function notifyAssigneesAboutStatusChange(Ticket $ticket, array $assigneesChatIDs, string $newStatus): void
    {
        if (empty($assigneesChatIDs)) {
            return;
        }

        foreach ($assigneesChatIDs as $assigneesChatID) {
            $text = '🔧 Статус задачи обновлен: ' . $newStatus . PHP_EOL .
                '🆔 Задача: ' . $ticket->name . PHP_EOL .
                '👨‍💼 Кто изменил: ' . auth()->user()->name . PHP_EOL .
                '🔗 http://kanban.mo.local/admin/tickets/' . $ticket->id . PHP_EOL .
                '🆔 Проект: ' . $ticket->project->name . PHP_EOL .
                '⏰ Время изменения: ' . now()->format('d.m.Y H:i') . PHP_EOL;

            $this->infoBot->send($assigneesChatID, $text);
        }
    }

    private function notifyProjectAboutComment(
        Ticket $ticket,
        string $authorName,
        string $commentPreview,
        ?string $projectChatId,
        ?string $threadId
    ): void {
        if (empty($projectChatId)) {
            return;
        }

        $text = '💬 Новый комментарий в задаче: ' . $ticket->name . PHP_EOL .
            '👤 Автор: ' . $authorName . PHP_EOL .
            '📝 Комментарий: ' . $commentPreview . PHP_EOL .
            '📎 http://kanban.mo.local/admin/tickets/' . $ticket->id . PHP_EOL;

        $this->infoBot->send($projectChatId, $text, $threadId);
    }

    private function notifyAssigneesAboutComment(
        Ticket $ticket,
        string $authorName,
        string $commentPreview,
        array $assigneesChatIDs
    ): void {
        if (empty($assigneesChatIDs)) {
            return;
        }

        foreach ($assigneesChatIDs as $assigneesChatID) {
            $text = '💬 В задаче ' . $ticket->name . ' новый комментарий.' . PHP_EOL .
                '👤 Автор: ' . $authorName . PHP_EOL .
                '📝 ' . $commentPreview . PHP_EOL .
                '📎 http://kanban.mo.local/admin/tickets/' . $ticket->id . PHP_EOL;

            $this->infoBot->send($assigneesChatID, $text);
        }
    }

    private function buildCommentPreview(TicketComment $comment): string
    {
        $plain = trim(strip_tags($comment->comment ?? ''));
        $plain = preg_replace('/\s+/u', ' ', $plain ?? '');

        if ($plain === '') {
            $plain = 'Без текста';
        }

        return Str::limit($plain, 200);
    }

    public function notifyProjectMemberAdded(Project $project, User $member): void
    {
        if (empty($member->chat_id)) {
            return;
        }

        $message = '🆕 Вам добавлен новый участник в проект: ' . $project->name . PHP_EOL;

        if (!empty($project->start_date) && !empty($project->end_date)) {
            $message .= '📅 Дата начала: ' . $this->formatDate($project->start_date) . PHP_EOL;
            $message .= '📅 Дата окончания: ' . $this->formatDate($project->end_date) . PHP_EOL;
        }

        $this->infoBot->send($member->chat_id, $message);
    }

    private function formatDate(mixed $date): string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('d/m/Y');
        }

        if (!empty($date)) {
            try {
                return \Carbon\Carbon::parse($date)->format('d/m/Y');
            } catch (\Throwable) {
                // ignore parsing issue and fall through to default label
            }
        }

        return 'Не указана';
    }
}
