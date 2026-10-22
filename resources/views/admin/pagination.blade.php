@if ($paginator->hasPages())
    <nav class="pager" aria-label="Страницы">
        @if ($paginator->onFirstPage())
            <span class="page disabled">←</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev">←</a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="page disabled">{{ $element }}</span>
            @else
                @foreach ($element as $page => $url)
                    @if ($page === $paginator->currentPage())
                        <span class="page current" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next">→</a>
        @else
            <span class="page disabled">→</span>
        @endif
    </nav>
@endif
