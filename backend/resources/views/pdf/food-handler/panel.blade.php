@php
    $panel = $panels[$panelKey];
    /* Los grupos solo se titulan cuando el examen tiene mas de uno. */
    $showGroups = count($panel['groups']) > 1;
@endphp
<table class="panel">
    <tr><td class="title" colspan="2">{{ $panel['label'] }}</td></tr>

    @foreach ($panel['groups'] as $group => $block)
        @if ($showGroups)
            <tr><td class="group" colspan="2">{{ $block['label'] }}</td></tr>
        @endif

        @foreach ($block['fields'] as $field => $meta)
            @php $value = $results[$panelKey][$group][$field] ?? $meta['normal']; @endphp
            <tr>
                <td class="f">{{ $meta['label'] }}</td>
                <td class="v">{{ $value === '' ? '—' : $value }}</td>
            </tr>
        @endforeach
    @endforeach
</table>
