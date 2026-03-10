<?php

namespace App\Support;

class SimplePdfBuilder
{
    /**
     * @param array<int,string> $lines
     */
    public static function fromLines(string $title, array $lines): string
    {
        $maxLines = 48;
        $rows = array_slice($lines, 0, $maxLines);

        $y = 800;
        $streamLines = [];
        $streamLines[] = 'BT';
        $streamLines[] = '/F1 14 Tf';
        $streamLines[] = '50 820 Td';
        $streamLines[] = '(' . self::escape($title) . ') Tj';
        $streamLines[] = '/F1 10 Tf';

        foreach ($rows as $line) {
            $streamLines[] = '1 0 0 1 50 ' . $y . ' Tm';
            $streamLines[] = '(' . self::escape($line) . ') Tj';
            $y -= 14;
        }

        $streamLines[] = 'ET';
        $stream = implode("\n", $streamLines);
        $length = strlen($stream);

        $objects = [];
        $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $objects[] = "2 0 obj\n<< /Type /Pages /Count 1 /Kids [3 0 R] >>\nendobj\n";
        $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n";
        $objects[] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
        $objects[] = "5 0 obj\n<< /Length {$length} >>\nstream\n{$stream}\nendstream\nendobj\n";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xrefPosition = strlen($pdf);
        $totalObjects = count($objects) + 1;
        $pdf .= "xref\n0 {$totalObjects}\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i < $totalObjects; $i++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$i]) . "\n";
        }

        $pdf .= "trailer\n<< /Size {$totalObjects} /Root 1 0 R >>\nstartxref\n{$xrefPosition}\n%%EOF";

        return $pdf;
    }

    private static function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
