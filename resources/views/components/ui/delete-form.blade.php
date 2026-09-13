@props(['action', 'label' => 'Hapus', 'confirm' => 'Hapus data ini? Tindakan ini tidak dapat dibatalkan.'])

{{-- Destructive actions are POST+DELETE with CSRF; the confirm() is a
     convenience, never the protection. --}}
<form method="POST" action="{{ $action }}" class="inline" onsubmit="return confirm('{{ $confirm }}')">
    @csrf
    @method('DELETE')
    <button type="submit"
            class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium text-destructive transition-colors hover:bg-destructive/10">
        <x-icon name="trash-2" class="h-4 w-4" />
        <span>{{ $label }}</span>
    </button>
</form>
