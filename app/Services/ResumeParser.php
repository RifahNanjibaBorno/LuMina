<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;

class ResumeParser
{
    /** @return array<string, string> */
    public function extract(UploadedFile $file): array
    {
        try {
            $result = Process::timeout(30)->run([
                config('portfolio.python'), '-X', 'utf8', resource_path('python/resume_parser.py'),
                $file->getRealPath(), mb_strtolower($file->getClientOriginalExtension()),
            ]);
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages(['resume' => 'Resume processing is unavailable or timed out. Please try a smaller text-based PDF or DOCX.']);
        }
        if (! $result->successful()) {
            throw ValidationException::withMessages(['resume' => 'The resume could not be read. Upload a text-based PDF or DOCX, and ensure Python with pypdf is installed. Scanned PDFs need text recognition first.']);
        }
        $data = json_decode($result->output(), true);
        if (! is_array($data) || empty($data['text_found'])) {
            throw ValidationException::withMessages(['resume' => 'No readable text was found. Please upload a text-based PDF or DOCX.']);
        }

        return collect($data)->except('text_found')->filter(fn ($value): bool => is_string($value) && trim($value) !== '')->all();
    }
}
