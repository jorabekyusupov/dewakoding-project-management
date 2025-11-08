<div>
    <form wire:submit="addComment">
        {{ $this->form }}

        <div class="flex justify-end mt-3">
            <x-filament::button type="submit" size="sm">
                Post Comment
            </x-filament::button>
        </div>
    </form>
</div>
