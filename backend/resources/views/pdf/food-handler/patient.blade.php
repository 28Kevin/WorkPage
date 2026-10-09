{{-- Datos del paciente: se escriben una vez y encabezan las cinco paginas. --}}
<table class="patient">
    <tr>
        <td style="width:50%;"><span class="k">Señor(a)</span><span class="v">{{ $record->full_name }}</span></td>
        <td style="width:25%;"><span class="k">C.C. No.</span><span class="v">{{ $record->document_number }}</span></td>
        <td style="width:25%;"><span class="k">Edad</span><span class="v">{{ $record->age }} años</span></td>
    </tr>
    <tr>
        <td><span class="k">Documento No.</span><span class="v">{{ $record->order_code }}</span></td>
        <td><span class="k">Fecha</span><span class="v">{{ $record->issue_date->format('d/m/Y') }}</span></td>
        <td><span class="k">Orden</span><span class="v">{{ $record->order_number }}</span></td>
    </tr>
</table>
