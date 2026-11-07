<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;
use ZipArchive;

class ResumeParser
{
    /** @return array<string, string> */
    public function extract(UploadedFile $file): array
    {
        try {
            $text = match (mb_strtolower($file->getClientOriginalExtension())) {
                'pdf' => $this->pdfText($file->getPathname()),
                'docx' => $this->docxText($file->getPathname()),
                default => throw new RuntimeException('Unsupported format.'),
            };
        } catch (\Exception $exception) {
            throw ValidationException::withMessages(['resume' => 'The resume could not be read. Upload a valid, unlocked text-based PDF or DOCX.']);
        }

        if (trim($text) === '') {
            throw ValidationException::withMessages(['resume' => 'No readable text was found. Upload a text-based PDF or DOCX. Scanned PDFs need text recognition first.']);
        }

        return $this->parse($text);
    }

    private function pdfText(string $path): string
    {
        $config = new Config;
        $config->setDecodeMemoryLimit(10 * 1024 * 1024);
        $document = (new Parser([], $config))->parseFile($path);
        if (count($document->getPages()) > 30) {
            throw new RuntimeException('Resume exceeds 30 pages.');
        }

        return $document->getText();
    }

    private function docxText(string $path): string
    {
        $archive = new ZipArchive;
        if ($archive->open($path) !== true) {
            throw new RuntimeException('Invalid DOCX archive.');
        }
        try {
            $entry = $archive->statName('word/document.xml');
            if ($entry === false || $entry['size'] > 5_000_000) {
                throw new RuntimeException('Missing or oversized document content.');
            }
            $xml = $archive->getFromName('word/document.xml');
        } finally {
            $archive->close();
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            if ($xml === false || ! $document->loadXML($xml, LIBXML_NONET) || $document->doctype !== null) {
                throw new RuntimeException('Invalid document XML.');
            }
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $paragraphs = [];
            foreach ($xpath->query('//w:p') as $paragraph) {
                $paragraphs[] = $paragraph->textContent;
            }

            return implode("\n", $paragraphs);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /** @return array<string, string> */
    private function parse(string $text): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $text)), fn (string $line): bool => $line !== ''));
        $headings = [
            'education' => 'education', 'academic background' => 'education',
            'educational qualification' => 'education', 'educational qualifications' => 'education',
            'skills' => 'skills', 'technical skills' => 'skills', 'core skills' => 'skills',
            'projects' => 'projects', 'academic projects' => 'projects', 'personal projects' => 'projects',
            'certifications' => 'certifications', 'certificates' => 'certifications',
            'achievements' => 'achievements', 'awards' => 'achievements', 'honors' => 'achievements',
            'experience' => 'experience', 'work experience' => 'experience', 'employment history' => 'experience',
            'summary' => 'bio', 'profile' => 'bio', 'objective' => 'bio', 'about me' => 'bio',
        ];
        $sections = [];
        $current = null;
        foreach ($lines as $line) {
            $normalized = trim(preg_replace('/[^a-z ]/', '', mb_strtolower($line)));
            $parts = explode(':', $line, 2);
            $inlineHeading = mb_strtolower(trim($parts[0]));
            if (isset($headings[$normalized])) {
                $current = $headings[$normalized];
                $sections[$current] ??= [];
            } elseif (count($parts) === 2 && isset($headings[$inlineHeading])) {
                $current = $headings[$inlineHeading];
                $sections[$current][] = trim($parts[1]);
            } elseif ($current !== null) {
                $sections[$current][] = $line;
            }
        }
        $data = [];
        foreach ($sections as $key => $content) {
            $data[$key] = mb_substr(implode("\n", $content), 0, in_array($key, ['bio', 'skills'], true) ? 2000 : 6000);
        }
        $first = preg_replace('/^name\s*[:\-]\s*/i', '', $lines[0] ?? '');
        $wordCount = count(preg_split('/\s+/u', $first));
        if ($wordCount >= 2 && $wordCount <= 6 && ! preg_match('/[@\d]|resume|curriculum|vitae/i', $first)) {
            $data['name'] = mb_substr($first, 0, 100);
        }
        if (preg_match('/[\w.+-]+@[\w.-]+\.[a-zA-Z]{2,}/u', $text, $match)) {
            $data['email'] = $match[0];
        }
        if (preg_match('/(?:\+?880[\s-]?)?0?1[3-9](?:[\s-]?\d){8}\b/', $text, $match)) {
            $data['phone'] = $match[0];
        }
        foreach (['github' => 'github.com', 'linkedin' => 'linkedin.com/in'] as $key => $domain) {
            if (preg_match('~(?:https?://)?(?:www\.)?'.preg_quote($domain, '~').'/[\w./-]+~', $text, $match)) {
                $url = rtrim($match[0], './');
                $data[$key] = str_starts_with($url, 'http') ? $url : 'https://'.$url;
            }
        }

        return array_filter($data, fn (string $value): bool => trim($value) !== '');
    }
}
