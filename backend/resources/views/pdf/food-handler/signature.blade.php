{{-- Firma de la pagina. Sin imagen ni nombre queda la linea para firmar a mano. --}}
<div class="sign">
    @if ($images[$role])
        <img src="{{ $images[$role] }}" alt="">
    @endif

    <div class="rule"></div>
    <div class="who">{{ $signatureName[$role] ?: $blank }}</div>
    <div class="role">{{ $signatureLabels[$role] }}</div>
</div>
