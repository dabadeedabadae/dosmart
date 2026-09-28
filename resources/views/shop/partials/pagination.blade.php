@if($paginator->hasPages())
<nav class="pagination" aria-label="Страницы">
@if($paginator->previousPageUrl())<a class="btn btn-ghost" href="{{ $paginator->previousPageUrl() }}">← Назад</a>@endif
<span>{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
@if($paginator->nextPageUrl())<a class="btn btn-ghost" href="{{ $paginator->nextPageUrl() }}">Далее →</a>@endif
</nav>
@endif
