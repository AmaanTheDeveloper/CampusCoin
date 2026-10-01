<?php

namespace App\Services\DocumentScan;

use App\Contracts\DocumentScanExtractor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Local Tesseract OCR fallback — server-side only, no browser secrets.
 */
class LocalTesseractDocumentScanExtractor implements DocumentScanExtractor
{
    private ?bool $availableCache = null;

    public function __construct(
        private readonly DocumentOcrTextParser $parser = new DocumentOcrTextParser,
    ) {}

    public function isAvailable(): bool
    {
        if ($this->availableCache !== null) {
            return $this->availableCache;
        }

        if (! (bool) config('campuscoin.document_scan.fallback_enabled', true)) {
            return $this->availableCache = false;
        }

        $binary = $this->binary();
        if ($binary === '') {
            return $this->availableCache = false;
        }

        try {
            $result = Process::timeout(5)->run([$binary, '--version']);
            $this->availableCache = $result->successful();
        } catch (Throwable) {
            $this->availableCache = false;
        }

        return $this->availableCache;
    }

    public function name(): string
    {
        return 'tesseract';
    }

    public function extract(string $imageBinary, string $mimeType, array $allowedCategoryNames = [], ?string $mode = null): ?array
    {
        if (! $this->isAvailable() || $imageBinary === '') {
            return null;
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (! in_array($mimeType, $allowedMimes, true)) {
            return null;
        }

        $disk = (string) config('campuscoin.document_scan.disk', 'local');
        $directory = trim((string) config('campuscoin.document_scan.directory', 'document-scans'), '/');
        $ext = match ($mimeType) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $relative = $directory.'/ocr-tmp/'.Str::uuid()->toString().'.'.$ext;

        Storage::disk($disk)->put($relative, $imageBinary);
        $absolute = Storage::disk($disk)->path($relative);

        try {
            $text = $this->runTesseract($absolute);
            if ($text === null || trim($text) === '') {
                return null;
            }

            return $this->parser->parse($text, $allowedCategoryNames, $mode);
        } catch (Throwable $e) {
            Log::warning('document_scan.tesseract.failure', [
                'message' => class_basename($e),
            ]);

            return null;
        } finally {
            Storage::disk($disk)->delete($relative);
        }
    }

    private function binary(): string
    {
        return trim((string) config('campuscoin.document_scan.tesseract_binary', 'tesseract'));
    }

    private function runTesseract(string $absolutePath): ?string
    {
        $binary = $this->binary();
        $lang = (string) config('campuscoin.document_scan.tesseract_lang', 'eng');
        $timeout = max(5, (int) config('campuscoin.document_scan.tesseract_timeout_seconds', 20));

        // stdout: write text to stdout instead of a .txt sidecar file.
        $result = Process::timeout($timeout)->run([
            $binary,
            $absolutePath,
            'stdout',
            '-l',
            $lang,
            '--psm',
            '6',
        ]);

        if (! $result->successful()) {
            Log::warning('document_scan.tesseract.exit', [
                'exit_code' => $result->exitCode(),
            ]);

            return null;
        }

        $output = trim($result->output());

        return $output === '' ? null : $output;
    }
}
