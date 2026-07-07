<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use ZipArchive;

class RecruitmentResumeAutofillService
{
    public function parseUploadedResume(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');
        $text = match ($extension) {
            'pdf' => $this->extractPdfText($file->getRealPath()),
            'docx' => $this->extractDocxText($file->getRealPath()),
            'doc' => $this->extractDocText($file->getRealPath()),
            default => '',
        };

        return $this->buildAutofillPayload($this->normalizeText($text));
    }

    private function buildAutofillPayload(string $text): array
    {
        $lines = $this->cleanLines($text);
        $email = $this->extractEmail($text);
        $phone = $this->extractPhone($text);
        $name = $this->extractName($lines, $email, $phone);

        return [
            'candidate_name' => $name,
            'email' => $email,
            'contact_number' => $phone,
            'current_address' => $this->extractAddress($lines),
            'computer_knowledge' => $this->extractSectionText($lines, [
                'skills',
                'technical skills',
                'computer knowledge',
                'software skills',
            ], [
                'education',
                'qualification',
                'experience',
                'employment',
                'project',
                'certification',
                'language',
                'reference',
            ]),
            'languages_speak' => $this->extractLanguages($text),
            'languages_read' => $this->extractLanguages($text),
            'languages_write' => $this->extractLanguages($text),
            'qualifications' => $this->extractQualifications($lines),
            'work_experiences' => $this->extractWorkExperiences($lines),
        ];
    }

    private function extractPdfText(string $path): string
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            return '';
        }

        $streamText = $this->extractPdfTextFromStreams($contents);

        if (trim($streamText) !== '') {
            return $streamText;
        }

        $text = $this->extractPdfTextFromOperators($contents);

        if (trim($text) !== '') {
            return $text;
        }

        preg_match_all('/[A-Za-z0-9@.,+&()\/:\-\s]{6,}/', $contents, $chunks);

        return implode("\n", $chunks[0] ?? []);
    }

    private function extractPdfTextFromStreams(string $contents): string
    {
        if (! preg_match_all('/<<(.*?)>>\s*stream\r?\n(.*?)\r?\nendstream/s', $contents, $matches, PREG_SET_ORDER)) {
            return '';
        }

        $textBlocks = [];

        foreach ($matches as $match) {
            $dictionary = (string) ($match[1] ?? '');
            $stream = (string) ($match[2] ?? '');
            $decoded = $this->decodePdfStream($dictionary, $stream);

            if ($decoded === '') {
                continue;
            }

            $text = $this->extractPdfTextFromOperators($decoded);

            if (trim($text) !== '') {
                $textBlocks[] = $text;
            }
        }

        return trim(implode("\n", $textBlocks));
    }

    private function decodePdfStream(string $dictionary, string $stream): string
    {
        $stream = ltrim($stream, "\r\n");

        if (! preg_match('/\/Filter\b/', $dictionary)) {
            return $stream;
        }

        if (preg_match('/\/FlateDecode\b/', $dictionary)) {
            $decoded = @zlib_decode($stream);

            if ($decoded !== false && $decoded !== null) {
                return (string) $decoded;
            }

            $decoded = @gzuncompress($stream);

            if ($decoded !== false) {
                return (string) $decoded;
            }

            $decoded = @gzinflate(substr($stream, 2));

            if ($decoded !== false) {
                return (string) $decoded;
            }
        }

        return '';
    }

    private function extractPdfTextFromOperators(string $content): string
    {
        $blocks = [];

        if (preg_match_all('/BT(.*?)ET/s', $content, $matches)) {
            $blocks = $matches[1];
        } else {
            $blocks = [$content];
        }

        $result = [];

        foreach ($blocks as $block) {
            $text = $this->decodePdfTextBlock((string) $block);

            if (trim($text) !== '') {
                $result[] = $text;
            }
        }

        return trim(implode("\n", $result));
    }

    private function decodePdfTextBlock(string $block): string
    {
        $buffer = '';

        if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)\s*Tj/s', $block, $literalMatches)) {
            foreach ($literalMatches[0] as $match) {
                if (preg_match('/^\((.*)\)\s*Tj$/s', trim((string) $match), $parts)) {
                    $buffer .= $this->decodePdfLiteralString($parts[1])."\n";
                }
            }
        }

        if (preg_match_all('/<([0-9A-Fa-f\s]+)>\s*Tj/s', $block, $hexMatches)) {
            foreach ($hexMatches[1] as $hex) {
                $buffer .= $this->decodePdfHexString((string) $hex)."\n";
            }
        }

        if (preg_match_all('/\[(.*?)\]\s*TJ/s', $block, $arrayMatches)) {
            foreach ($arrayMatches[1] as $arrayBlock) {
                $buffer .= $this->decodePdfTextArray((string) $arrayBlock)."\n";
            }
        }

        if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)\s*[\'"]/s', $block, $quotedMatches)) {
            foreach ($quotedMatches[0] as $match) {
                if (preg_match('/^\((.*)\)\s*[\'"]$/s', trim((string) $match), $parts)) {
                    $buffer .= $this->decodePdfLiteralString($parts[1])."\n";
                }
            }
        }

        return trim($buffer);
    }

    private function decodePdfTextArray(string $arrayBlock): string
    {
        if (! preg_match_all('/\((?:\\\\.|[^\\\\)])*\)|<[\dA-Fa-f\s]+>|-?\d+(?:\.\d+)?/s', $arrayBlock, $tokens)) {
            return '';
        }

        $parts = [];

        foreach ($tokens[0] as $token) {
            $token = trim((string) $token);

            if ($token === '') {
                continue;
            }

            if ($token[0] === '(' && str_ends_with($token, ')')) {
                $parts[] = $this->decodePdfLiteralString(substr($token, 1, -1));
                continue;
            }

            if ($token[0] === '<' && str_ends_with($token, '>')) {
                $parts[] = $this->decodePdfHexString(substr($token, 1, -1));
                continue;
            }

            if (is_numeric($token) && (float) $token < -120) {
                $parts[] = ' ';
            }
        }

        return trim(implode('', $parts));
    }

    private function decodePdfLiteralString(string $value): string
    {
        $result = '';
        $length = strlen($value);

        for ($index = 0; $index < $length; $index++) {
            $char = $value[$index];

            if ($char !== '\\') {
                $result .= $char;
                continue;
            }

            $index++;

            if ($index >= $length) {
                break;
            }

            $escaped = $value[$index];

            if (ctype_digit($escaped)) {
                $octal = $escaped;

                for ($offset = 0; $offset < 2 && $index + 1 < $length && ctype_digit($value[$index + 1]); $offset++) {
                    $index++;
                    $octal .= $value[$index];
                }

                $result .= chr(octdec($octal));
                continue;
            }

            $result .= match ($escaped) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'b' => "\x08",
                'f' => "\f",
                '\\', '(', ')' => $escaped,
                "\n", "\r" => '',
                default => $escaped,
            };
        }

        return $result;
    }

    private function decodePdfHexString(string $value): string
    {
        $hex = preg_replace('/\s+/', '', $value) ?? '';

        if ($hex === '') {
            return '';
        }

        if (strlen($hex) % 2 !== 0) {
            $hex .= '0';
        }

        $binary = @hex2bin($hex);

        return $binary === false ? '' : $binary;
    }

    private function extractDocxText(string $path): string
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            return '';
        }

        $documentXml = $zip->getFromName('word/document.xml') ?: '';
        $zip->close();

        if ($documentXml === '') {
            return '';
        }

        $documentXml = preg_replace('/<\/w:p>/', "</w:p>\n", $documentXml);

        return trim(strip_tags((string) $documentXml));
    }

    private function extractDocText(string $path): string
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            return '';
        }

        $contents = preg_replace('/[^A-Za-z0-9@.,+&()\/:\-\s]/', ' ', $contents);

        return trim((string) $contents);
    }

    private function normalizeText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\r\n|\r/", "\n", $text);
        $text = preg_replace("/\t+/", ' ', $text);
        $text = preg_replace("/[ ]{2,}/", ' ', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim((string) $text);
    }

    private function cleanLines(string $text): array
    {
        return collect(preg_split('/\n+/', $text) ?: [])
            ->map(fn ($line): string => trim((string) $line))
            ->filter(fn ($line): bool => $line !== '')
            ->values()
            ->all();
    }

    private function extractEmail(string $text): string
    {
        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $matches)) {
            return strtolower(trim($matches[0]));
        }

        return '';
    }

    private function extractPhone(string $text): string
    {
        if (! preg_match_all('/(?:\+91[\s\-]?)?[6-9]\d[\d\s\-]{8,12}/', $text, $matches)) {
            return '';
        }

        foreach ($matches[0] as $candidate) {
            $digits = preg_replace('/\D+/', '', $candidate);
            $digits = Str::startsWith($digits, '91') && strlen($digits) > 10 ? substr($digits, -10) : $digits;

            if (strlen($digits) === 10) {
                return $digits;
            }
        }

        return '';
    }

    private function extractName(array $lines, string $email, string $phone): string
    {
        foreach (array_slice($lines, 0, 8) as $line) {
            $normalized = trim((string) $line);

            if ($normalized === '') {
                continue;
            }

            if ($email !== '' && str_contains(strtolower($normalized), strtolower($email))) {
                continue;
            }

            if ($phone !== '' && str_contains(preg_replace('/\D+/', '', $normalized), $phone)) {
                continue;
            }

            if (preg_match('/resume|curriculum|vitae|profile|contact|email|phone/i', $normalized)) {
                continue;
            }

            if (preg_match('/^[A-Za-z][A-Za-z.\s]{2,60}$/', $normalized)) {
                return Str::title(strtolower($normalized));
            }
        }

        return '';
    }

    private function extractAddress(array $lines): string
    {
        foreach ($lines as $index => $line) {
            if (! preg_match('/address|location|residence/i', $line)) {
                continue;
            }

            $value = trim(preg_replace('/^.*?:/','', $line));

            if ($value !== '') {
                return $value;
            }

            $nextLines = array_slice($lines, $index + 1, 2);
            $joined = implode(', ', array_filter($nextLines));

            if ($joined !== '') {
                return $joined;
            }
        }

        return '';
    }

    private function extractLanguages(string $text): string
    {
        if (! preg_match('/languages?[:\s\-]+([A-Za-z,\s]+)/i', $text, $matches)) {
            return '';
        }

        $value = trim($matches[1]);
        $value = preg_replace('/\s{2,}/', ' ', $value);

        return trim((string) $value);
    }

    private function extractSectionText(array $lines, array $startKeywords, array $stopKeywords): string
    {
        $capturing = false;
        $captured = [];

        foreach ($lines as $line) {
            $lower = strtolower($line);

            if (! $capturing && $this->containsKeyword($lower, $startKeywords)) {
                $capturing = true;
                $afterHeading = trim((string) preg_replace('/^.*?:/', '', $line));
                if ($afterHeading !== '' && $afterHeading !== $line) {
                    $captured[] = $afterHeading;
                }
                continue;
            }

            if ($capturing && $this->containsKeyword($lower, $stopKeywords)) {
                break;
            }

            if ($capturing) {
                $captured[] = $line;
            }
        }

        return trim(implode(', ', array_slice($captured, 0, 8)));
    }

    private function extractQualifications(array $lines): array
    {
        $degreePattern = '/\b(B\.?Tech|M\.?Tech|B\.?E|M\.?E|MBA|BBA|BCA|MCA|BCom|MCom|BA|MA|BSc|MSc|Diploma|ITI|HSC|SSC|12th|10th)\b/i';
        $rows = [];

        foreach ($lines as $line) {
            if (! preg_match($degreePattern, $line, $degreeMatch)) {
                continue;
            }

            preg_match('/(19|20)\d{2}/', $line, $yearMatch);
            preg_match('/(\d{1,2}(?:\.\d{1,2})?)\s*%/', $line, $percentageMatch);

            $rows[] = [
                'examination' => trim($degreeMatch[0] ?? ''),
                'university' => '',
                'main_subject' => '',
                'year_of_passing' => $yearMatch[0] ?? '',
                'percentage_obtained' => $percentageMatch[1] ?? '',
            ];
        }

        return array_slice($this->uniqueRows($rows, ['examination', 'year_of_passing']), 0, 5);
    }

    private function extractWorkExperiences(array $lines): array
    {
        $rows = [];

        foreach ($lines as $line) {
            if (! preg_match('/\b(company|pvt|private|limited|ltd|solutions|technologies|services|retail|gold|jewellers)\b/i', $line)) {
                continue;
            }

            $parts = preg_split('/\||,| - /', $line) ?: [];
            $company = trim((string) ($parts[0] ?? ''));
            $designation = trim((string) ($parts[1] ?? ''));
            $experience = '';

            if (preg_match('/(\d+(?:\.\d+)?)\s+(?:years?|yrs?|months?)/i', $line, $experienceMatch)) {
                $experience = $experienceMatch[1];
            }

            if ($company === '') {
                continue;
            }

            $rows[] = [
                'company_name' => $company,
                'designation' => $designation,
                'experience' => $experience,
            ];
        }

        return array_slice($this->uniqueRows($rows, ['company_name', 'designation']), 0, 5);
    }

    private function uniqueRows(array $rows, array $keys): array
    {
        $seen = [];
        $result = [];

        foreach ($rows as $row) {
            $fingerprint = implode('|', array_map(
                fn ($key): string => strtolower(trim((string) ($row[$key] ?? ''))),
                $keys
            ));

            if ($fingerprint === '' || isset($seen[$fingerprint])) {
                continue;
            }

            $seen[$fingerprint] = true;
            $result[] = $row;
        }

        return $result;
    }

    private function containsKeyword(string $value, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($value, strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }
}