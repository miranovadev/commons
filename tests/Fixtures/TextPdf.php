<?php

namespace Tests\Fixtures;

/** Deterministic, minimal PDF fixtures; no external generator needed in CI. */
final class TextPdf
{
    public static function make(array $pages = ['Research text'], string $action = ''): string
    {
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R '.$action.' >>',
            2 => '', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>'];
        $kids = [];
        foreach ($pages as $index => $text) {
            $page = 4 + $index * 2;
            $content = $page + 1;
            $kids[] = "$page 0 R";
            $objects[$page] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R >> >> /Contents $content 0 R >>";
            $stream = $text === null ? 'q 10 0 0 10 20 20 cm BI /W 1 /H 1 /CS /RGB /BPC 8 /F /AHx ID FF0000> EI Q'
                : 'BT /F1 12 Tf 40 740 Td <'.bin2hex(mb_convert_encoding($text, 'Windows-1252', 'UTF-8')).'> Tj ET';
            $objects[$content] = '<< /Length '.strlen($stream).">>\nstream\n".$stream."\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Count '.count($pages).' /Kids ['.implode(' ', $kids).'] >>';
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "$id 0 obj\n$object\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        for ($id = 1; $id <= count($objects); $id++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$id])."\n";
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
    }
}
