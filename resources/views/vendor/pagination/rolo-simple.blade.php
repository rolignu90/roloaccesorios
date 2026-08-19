@if ($paginator->hasPages())
    <nav class="pagination" role="navigation" aria-label="Paginación">
        <ul class="pagination-links">
            @if ($paginator->onFirstPage())
                <li><span class="pagination-btn is-disabled" aria-disabled="true">Anterior</span></li>
            @else
                <li><a class="pagination-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">Anterior</a></li>
            @endif

            @if ($paginator->hasMorePages())
                <li><a class="pagination-btn" href="{{ $paginator->nextPageUrl() }}" rel="next">Siguiente</a></li>
            @else
                <li><span class="pagination-btn is-disabled" aria-disabled="true">Siguiente</span></li>
            @endif
        </ul>
    </nav>
@endif
