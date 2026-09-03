<x-mail::message>
# Respaldo de la base de datos

Adjuntamos la copia de seguridad de **{{ config('app.name') }}**.

- **Archivo:** {{ $filename }}
- **Tamaño:** {{ $size }}
- **Generado:** {{ $generatedAt }}

Guarde el archivo en un lugar seguro. Contiene todos los exámenes emitidos,
la configuración del centro y las imágenes de la galería.

<x-mail::subcopy>
Este mensaje se genera automáticamente desde el módulo administrativo. Si no
solicitó este respaldo, revise quién tiene acceso al panel.
</x-mail::subcopy>
</x-mail::message>
