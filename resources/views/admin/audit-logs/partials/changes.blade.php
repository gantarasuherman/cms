@php
    $changed = array_keys(($log->new_values ?? []) + ($log->old_values ?? []));
@endphp

@if ($changed)
    <a href="{{ route('admin.audit-logs.show', $log) }}"
       class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium text-foreground hover:bg-accent">
        {{ count($changed) }} kolom
    </a>
@else
    <span class="text-sm text-muted-foreground">—</span>
@endif
