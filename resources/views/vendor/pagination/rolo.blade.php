@if ($paginator->hasPages())
    <nav class="pagination" role="navigation" aria-label="Paginación">
        <p class="pagination-meta muted">
            @if ($paginator->firstItem())
                Mostrando {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de {{ $paginator->total() }}
            @else
                {{ $paginator->count() }} resultados
            @endif
        </p>
        <ul class="pagination-links">
            @if ($paginator->onFirstPage())
                <li><span class="pagination-btn is-disabled" aria-disabled="true">Anterior</span></li>
            @else
                <li><a class="pagination-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">Anterior</a></li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="pagination-btn is-disabled">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li><span class="pagination-btn is-current" aria-current="page">{{ $page }}</span></li>
                        @else
                            <li><a class="pagination-btn" href="{{ $url }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li><a class="pagination-btn" href="{{ $paginator->nextPageUrl() }}" rel="next">Siguiente</a></li>
            @else
                <li><span class="pagination-btn is-disabled" aria-disabled="true">Siguiente</span></li>
            @endif
        </ul>
    </nav>
@endif
