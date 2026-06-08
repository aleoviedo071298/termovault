<?php

namespace Tests\Feature;

use App\Services\FileSignatureGuard;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * FIX [008] — Validación de magic bytes en uploads.
 *
 * Verifica que FileSignatureGuard detecta contenido ejecutable/script aunque
 * el archivo tenga una extensión inocente, y que NO genera falsos positivos
 * sobre contenido legítimo (PDF, ZIP, IS2 propietario, contenido vacío).
 */
class FileSignatureGuardTest extends TestCase
{
    private FileSignatureGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new FileSignatureGuard();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function dangerousSignaturesProvider(): array
    {
        return [
            'Windows PE (MZ)' => ["MZ\x90\x00\x03\x00\x00\x00", 'evil.is2'],
            'Linux ELF'       => ["\x7fELF\x02\x01\x01\x00", 'evil.zip'],
            'Mach-O 64-bit'   => ["\xcf\xfa\xed\xfe\x07\x00\x00\x01", 'evil.pdf'],
            'Java .class'     => ["\xca\xfe\xba\xbe\x00\x00\x00\x34", 'evil.is2'],
            'Shell shebang'   => ["#!/bin/bash\nrm -rf /", 'evil.is2'],
        ];
    }

    /**
     * @dataProvider dangerousSignaturesProvider
     */
    public function test_detects_dangerous_executable_content(string $content, string $name): void
    {
        $file = UploadedFile::fake()->createWithContent($name, $content);

        $this->assertNotNull(
            $this->guard->detectDangerous($file),
            "Debería detectar contenido peligroso en {$name}"
        );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function safeContentProvider(): array
    {
        return [
            'PDF real'        => ["%PDF-1.7\n%\xe2\xe3\xcf\xd3", 'informe.pdf'],
            'ZIP / OOXML'     => ["PK\x03\x04\x14\x00\x00\x00", 'paquete.zip'],
            'IS2 Fluke (ZIP)' => ["PK\x03\x04\x0a\x00\x00\x00", 'captura.is2'],
            'OLE doc/xls'     => ["\xd0\xcf\x11\xe0\xa1\xb1\x1a\xe1", 'informe.doc'],
            'JPEG'            => ["\xff\xd8\xff\xe0\x00\x10JF", 'foto.jpg'],
            'Texto plano'     => ['contenido de texto normal', 'nota.txt'],
        ];
    }

    /**
     * @dataProvider safeContentProvider
     */
    public function test_allows_safe_content(string $content, string $name): void
    {
        $file = UploadedFile::fake()->createWithContent($name, $content);

        $this->assertNull(
            $this->guard->detectDangerous($file),
            "No debería marcar como peligroso un {$name} legítimo"
        );
    }

    public function test_empty_file_is_not_flagged(): void
    {
        $file = UploadedFile::fake()->createWithContent('vacio.is2', '');

        $this->assertNull($this->guard->detectDangerous($file));
    }

    public function test_first_threat_returns_offending_file_metadata(): void
    {
        $safe = UploadedFile::fake()->createWithContent('ok.is2', "PK\x03\x04");
        $evil = UploadedFile::fake()->createWithContent('malware.is2', "MZ\x90\x00");

        $threat = $this->guard->firstThreat([$safe, $evil]);

        $this->assertNotNull($threat);
        $this->assertSame('malware.is2', $threat['name']);
        $this->assertStringContainsString('Windows', $threat['threat']);
    }

    public function test_first_threat_returns_null_when_all_safe(): void
    {
        $a = UploadedFile::fake()->createWithContent('a.is2', "PK\x03\x04");
        $b = UploadedFile::fake()->createWithContent('b.zip', "PK\x03\x04");

        $this->assertNull($this->guard->firstThreat([$a, $b]));
    }
}
