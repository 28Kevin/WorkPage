@php
    $panel = $panels[$panelKey];
    /* Los grupos solo se titulan cuando el examen tiene mas de uno. */
    $showGroups = count($panel['groups']) > 1;
@endphp
<table class="panel">
    <tr><td class="title">{{ $panel['label'] }}</td></tr>

    @foreach ($panel['groups'] as $group => $block)
        @if ($showGroups)
            <tr><td class="group">{{ $block['label'] }}</td></tr>
        @endif

        @php
            /*
             * Dos columnas: el uroanalisis trae 19 campos y en una sola fila
             * por campo no cabia en la pagina, ademas de desperdiciar la mitad
             * del ancho. La primera columna se queda con el sobrante impar.
             */
            $fields = $block['fields'];
            $half = (int) ceil(count($fields) / 2);
            $columns = [array_slice($fields, 0, $half, true), array_slice($fields, $half, null, true)];
        @endphp

        <tr>
            <td class="cols">
                <table class="grid">
                    <tr>
                        @foreach ($columns as $column)
                            <td class="col">
                                <table>
                                    @foreach ($column as $field => $meta)
                                        @php $value = $results[$panelKey][$group][$field] ?? $meta['normal']; @endphp
                                        <tr>
                                            <td class="f">{{ $meta['label'] }}</td>
                                            <td class="v">{{ $value === '' ? '—' : $value }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            </td>
                        @endforeach
                    </tr>
                </table>
            </td>
        </tr>
    @endforeach
</table>
