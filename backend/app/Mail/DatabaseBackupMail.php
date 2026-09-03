<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Envía el volcado de la base como archivo adjunto. */
class DatabaseBackupMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $path,
        public readonly string $filename,
        public readonly int $bytes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                'Respaldo de la base de datos · %s · %s',
                config('app.name'),
                now()->format('d/m/Y H:i'),
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.database-backup',
            with: [
                'filename' => $this->filename,
                'size' => $this->readableSize(),
                'generatedAt' => now()->translatedFormat('d \d\e F \d\e Y, h:i a'),
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->path)
                ->as($this->filename)
                ->withMime('application/zip'),
        ];
    }

    private function readableSize(): string
    {
        return $this->bytes >= 1048576
            ? number_format($this->bytes / 1048576, 1, ',', '.').' MB'
            : number_format($this->bytes / 1024, 0, ',', '.').' KB';
    }
}
