@if ($paginator->hasPages())
<div style="display:flex;gap:4px;align-items:center">
    @if ($paginator->onFirstPage())
        <span style="padding:5px 9px;border-radius:7px;font-size:12.5px;color:var(--text-muted);cursor:default">‹</span>
    @else
        <a href="{{ $paginator->previousPageUrl() }}" style="padding:5px 9px;border-radius:7px;font-size:12.5px;color:var(--text-secondary);text-decoration:none;font-weight:500" onmouseover="this.style.background='var(--surface-sunken)'" onmouseout="this.style.background=''">‹</a>
    @endif

    @foreach ($elements as $element)
        @if (is_string($element))
            <span style="padding:5px 9px;color:var(--text-muted);font-size:12.5px">…</span>
        @endif
        @if (is_array($element))
            @foreach ($element as $page => $url)
                @if ($page == $paginator->currentPage())
                    <span style="padding:5px 10px;border-radius:7px;font-size:12.5px;background:var(--accent);color:#fff;font-weight:600">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" style="padding:5px 10px;border-radius:7px;font-size:12.5px;color:var(--text-secondary);text-decoration:none;font-weight:500" onmouseover="this.style.background='var(--surface-sunken)'" onmouseout="this.style.background=''">{{ $page }}</a>
                @endif
            @endforeach
        @endif
    @endforeach

    @if ($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" style="padding:5px 9px;border-radius:7px;font-size:12.5px;color:var(--text-secondary);text-decoration:none;font-weight:500" onmouseover="this.style.background='var(--surface-sunken)'" onmouseout="this.style.background=''">›</a>
    @else
        <span style="padding:5px 9px;border-radius:7px;font-size:12.5px;color:var(--text-muted);cursor:default">›</span>
    @endif
</div>
@endif
