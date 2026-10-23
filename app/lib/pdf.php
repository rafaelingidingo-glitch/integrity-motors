<?php
/**
 * IacPdf — minimal single-file PDF writer (no external dependencies).
 * Supports: A4 pages, Helvetica base-14 fonts (WinAnsi), text, letter-spacing,
 * filled/stroked rectangles, lines, and JPEG/PNG images (PNG auto-converted
 * via GD). Output is uncompressed PDF 1.4 — fine for single-page invoices.
 *
 * Coordinates: origin at TOP-LEFT of the page (y grows downward), units = pt.
 */

declare(strict_types=1);

final class IacPdf
{
    public const PAGE_W = 595.28; // A4
    public const PAGE_H = 841.89;

    private array $pages = [];          // content streams (strings)
    private array $images = [];         // path => ['name' => '/Im1', 'w' =>, 'h' =>, 'data' =>]
    private string $buffer = '';        // current page content
    private string $font = '/F1';       // current font
    private float $size = 10;
    private float $charSpace = 0;
    private array $fillColor = [0, 0, 0];

    public function __construct()
    {
        $this->addPage();
    }

    public function addPage(): void
    {
        if ($this->buffer !== '') {
            $this->pages[] = $this->buffer;
        }
        $this->buffer = '';
    }

    // ------------------------------------------------------------------ state
    public function setFont(string $family, float $size, float $charSpace = 0): void
    {
        $this->font = match ($family) {
            'bold'   => '/F2',
            'italic' => '/F3',
            default  => '/F1',
        };
        $this->size      = $size;
        $this->charSpace = $charSpace;
    }

    public function setColor(float $r, float $g, float $b): void
    {
        $this->fillColor = [$r / 255, $g / 255, $b / 255];
    }

    /** Hex helper: pdf_set_color($pdf, '#d0202e') */
    public function setColorHex(string $hex): void
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $this->setColor((float) hexdec(substr($hex, 0, 2)), (float) hexdec(substr($hex, 2, 2)), (float) hexdec(substr($hex, 4, 2)));
    }

    // ------------------------------------------------------------------- text
    /** Escaped WinAnsi text (translates UTF-8 → CP1252, escapes specials). */
    private static function encode(string $text): string
    {
        $cp1252 = @mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
        if ($cp1252 === false) {
            $cp1252 = $text;
        }
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $cp1252);
    }

    public function text(float $x, float $y, string $text, ?array $color = null): void
    {
        $c = $color ?? $this->fillColor;
        $cs = $this->charSpace > 0 ? sprintf(' %.2f Tc', $this->charSpace) : '';
        $this->buffer .= sprintf(
            "BT %s rg /%s %.2f Tf%s 1 0 0 1 %.2f %.2f Tm (%s) Tj ET\n",
            sprintf('%.3f %.3f %.3f', $c[0], $c[1], $c[2]),
            ltrim($this->font, '/'),
            $this->size,
            $cs,
            $x,
            self::PAGE_H - $y, // convert to bottom-left origin
            self::encode($text)
        );
    }

    /** Approximate width of a string in current font/size (real Helvetica AFM widths). */
    public function textWidth(string $text): float
    {
        $w = 0.0;
        $len = mb_strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($text, $i, 1);
            $cp = mb_ord($ch, 'UTF-8');
            $w += self::charWidth($cp);
        }
        return $w / 1000 * $this->size + $this->charSpace * $len;
    }

    /** Helvetica / Helvetica-Bold AFM advance width (per mille) for a codepoint. */
    private function charWidth(int $cp): float
    {
        static $normal = null, $bold = null;
        if ($normal === null) {
            $normal = self::afmNormal();
            $bold = self::afmBold();
        }
        $table = $this->font === '/F2' ? $bold : $normal;
        return $table[chr($cp)] ?? 556;
    }

    private static function afmNormal(): array
    {
        return [
            ' ' => 278, '!' => 278, '"' => 355, '#' => 556, '$' => 556, '%' => 889, '&' => 667, "'" => 191,
            '(' => 333, ')' => 333, '*' => 389, '+' => 584, ',' => 278, '-' => 333, '.' => 278, '/' => 278,
            '0' => 556, '1' => 556, '2' => 556, '3' => 556, '4' => 556, '5' => 556, '6' => 556, '7' => 556,
            '8' => 556, '9' => 556, ':' => 278, ';' => 278, '<' => 584, '=' => 584, '>' => 584, '?' => 556,
            '@' => 1015, 'A' => 667, 'B' => 667, 'C' => 722, 'D' => 722, 'E' => 667, 'F' => 611, 'G' => 778,
            'H' => 722, 'I' => 278, 'J' => 500, 'K' => 667, 'L' => 556, 'M' => 833, 'N' => 722, 'O' => 778,
            'P' => 667, 'Q' => 778, 'R' => 722, 'S' => 667, 'T' => 611, 'U' => 722, 'V' => 667, 'W' => 944,
            'X' => 667, 'Y' => 667, 'Z' => 611, '[' => 278, '\\' => 278, ']' => 278, '^' => 469, '_' => 556,
            '`' => 333, 'a' => 556, 'b' => 556, 'c' => 500, 'd' => 556, 'e' => 556, 'f' => 278, 'g' => 556,
            'h' => 556, 'i' => 222, 'j' => 222, 'k' => 500, 'l' => 222, 'm' => 833, 'n' => 556, 'o' => 556,
            'p' => 556, 'q' => 556, 'r' => 333, 's' => 500, 't' => 278, 'u' => 556, 'v' => 500, 'w' => 722,
            'x' => 500, 'y' => 500, 'z' => 500, '{' => 334, '|' => 260, '}' => 334, '~' => 584,
        ];
    }

    private static function afmBold(): array
    {
        return [
            ' ' => 278, '!' => 333, '"' => 474, '#' => 556, '$' => 556, '%' => 889, '&' => 722, "'" => 238,
            '(' => 333, ')' => 333, '*' => 389, '+' => 584, ',' => 278, '-' => 333, '.' => 278, '/' => 278,
            '0' => 556, '1' => 556, '2' => 556, '3' => 556, '4' => 556, '5' => 556, '6' => 556, '7' => 556,
            '8' => 556, '9' => 556, ':' => 333, ';' => 333, '<' => 584, '=' => 584, '>' => 584, '?' => 611,
            '@' => 975, 'A' => 722, 'B' => 722, 'C' => 722, 'D' => 722, 'E' => 667, 'F' => 611, 'G' => 778,
            'H' => 722, 'I' => 278, 'J' => 556, 'K' => 722, 'L' => 611, 'M' => 833, 'N' => 722, 'O' => 778,
            'P' => 667, 'Q' => 778, 'R' => 722, 'S' => 667, 'T' => 611, 'U' => 722, 'V' => 667, 'W' => 944,
            'X' => 667, 'Y' => 667, 'Z' => 611, '[' => 333, '\\' => 278, ']' => 333, '^' => 584, '_' => 556,
            '`' => 333, 'a' => 556, 'b' => 611, 'c' => 556, 'd' => 611, 'e' => 556, 'f' => 333, 'g' => 611,
            'h' => 611, 'i' => 278, 'j' => 278, 'k' => 556, 'l' => 278, 'm' => 889, 'n' => 611, 'o' => 611,
            'p' => 611, 'q' => 611, 'r' => 389, 's' => 556, 't' => 333, 'u' => 611, 'v' => 556, 'w' => 778,
            'x' => 556, 'y' => 556, 'z' => 500, '{' => 389, '|' => 280, '}' => 389, '~' => 584,
        ];
    }

    /** Split text into lines that fit $maxWidth in the current font/size. */
    public function wrapText(string $text, float $maxWidth): array
    {
        $lines = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) as $paragraph) {
            $paragraph = (string) $paragraph;
            if ($paragraph === '') {
                $lines[] = '';
                continue;
            }
            $current = '';
            foreach (explode(' ', $paragraph) as $word) {
                $candidate = $current === '' ? $word : $current . ' ' . $word;
                if ($this->textWidth($candidate) <= $maxWidth) {
                    $current = $candidate;
                } else {
                    if ($current !== '') {
                        $lines[] = $current;
                    }
                    // hard-break words longer than a full line
                    while ($this->textWidth($word) > $maxWidth && $word !== '') {
                        $cut = $word;
                        while ($cut !== '' && $this->textWidth($cut) > $maxWidth) {
                            $cut = mb_substr($cut, 0, mb_strlen($cut) - 1);
                        }
                        $lines[] = $cut;
                        $word = mb_substr($word, mb_strlen($cut));
                    }
                    $current = $word;
                }
            }
            if ($current !== '') {
                $lines[] = $current;
            }
        }
        return $lines;
    }

    // ------------------------------------------------------------------ shapes
    public function rect(float $x, float $y, float $w, float $h, ?array $color = null): void
    {
        $c = $color ?? $this->fillColor;
        $this->buffer .= sprintf(
            "%.3f %.3f %.3f rg %.2f %.2f %.2f %.2f re f\n",
            $c[0], $c[1], $c[2],
            $x, self::PAGE_H - $y - $h, $w, $h
        );
    }

    public function line(float $x1, float $y1, float $x2, float $y2, float $width = 0.5, ?array $color = null): void
    {
        $c = $color ?? $this->fillColor;
        $this->buffer .= sprintf(
            "%.3f %.3f %.3f RG %.2f w %.2f %.2f m %.2f %.2f l S\n",
            $c[0], $c[1], $c[2], $width, $x1, self::PAGE_H - $y1, $x2, self::PAGE_H - $y2
        );
    }

    // ------------------------------------------------------------------ images
    /** Draw an image file (JPEG native; PNG/GIF/WebP flattened to RGB PNG via GD). */
    public function image(string $path, float $x, float $y, float $w, float $h): bool
    {
        if (!is_file($path)) {
            return false;
        }
        $key = realpath($path) ?: $path;
        if (!isset($this->images[$key])) {
            [$bytes, $imgW, $imgH, $filter, $decodeParms] = self::imageBytes($path);
            if ($bytes === null) {
                return false;
            }
            $this->images[$key] = [
                'name'        => '/Im' . (count($this->images) + 1),
                'w'           => $imgW,
                'h'           => $imgH,
                'data'        => $bytes,
                'filter'      => $filter,
                'decodeParms' => $decodeParms,
            ];
        }
        $img = $this->images[$key];
        $this->buffer .= sprintf(
            "q %.2f 0 0 %.2f %.2f %.2f cm %s Do Q\n",
            $w, $h, $x, self::PAGE_H - $y - $h, $img['name']
        );
        return true;
    }

    /**
     * @return array{0:?string,1:int,2:int,3:string,4:?string} bytes, px w/h, PDF filter, DecodeParms
     */
    private static function imageBytes(string $path): array
    {
        $info = @getimagesize($path);
        if ($info === false) {
            return [null, 0, 0, '', null];
        }
        [$w, $h] = $info;
        $type = $info[2] ?? 0;

        // JPEG embeds natively — no GD required.
        if ($type === IMAGETYPE_JPEG) {
            $bytes = file_get_contents($path);
            return [$bytes === false ? null : $bytes, $w, $h, '/DCTDecode', null];
        }

        if (!function_exists('imagecreatefromstring') || !function_exists('imagepng')) {
            return [null, 0, 0, '', null];
        }
        $src = @imagecreatefromstring((string) file_get_contents($path));
        if ($src === false) {
            return [null, 0, 0, '', null];
        }

        // Flatten possible alpha onto white → truecolor RGB PNG (color type 2)
        $canvas = imagecreatetruecolor($w, $h);
        $white  = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopy($canvas, $src, 0, 0, 0, 0, $w, $h);
        ob_start();
        imagepng($canvas, null, 6);
        $png = (string) ob_get_clean();
        imagedestroy($src);
        imagedestroy($canvas);

        // Extract the concatenated IDAT stream (zlib + PNG row filters).
        $idat = self::pngIdatStream($png);
        if ($idat === null) {
            return [null, 0, 0, '', null];
        }
        $decodeParms = "<< /Predictor 15 /Colors 3 /BitsPerComponent 8 /Columns $w >>";
        return [$idat, $w, $h, '/FlateDecode', $decodeParms];
    }

    /** Concatenated IDAT payload of a PNG byte string, or null. */
    private static function pngIdatStream(string $png): ?string
    {
        if (strlen($png) < 57 || substr($png, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            return null;
        }
        $idat = '';
        $pos  = 8;
        $len  = strlen($png);
        while ($pos + 8 <= $len) {
            $chunkLen = unpack('N', substr($png, $pos, 4))[1];
            $type     = substr($png, $pos + 4, 4);
            $data     = substr($png, $pos + 8, $chunkLen);
            if ($type === 'IDAT') {
                $idat .= $data;
            }
            if ($type === 'IEND') {
                break;
            }
            $pos += 12 + $chunkLen; // len + type + data + crc
        }
        return $idat !== '' ? $idat : null;
    }

    // ----------------------------------------------------------------- output
    public function output(): string
    {
        if ($this->buffer !== '') {
            $this->pages[] = $this->buffer;
            $this->buffer  = '';
        }

        $objects = []; // object number => body string (1-based)

        // 1: catalog, 2: pages, 3-5: fonts, 6+: page + content pairs, then images
        $fontDefs = [
            3 => 'Helvetica',
            4 => 'Helvetica-Bold',
            5 => 'Helvetica-Oblique',
        ];
        foreach ($fontDefs as $num => $base) {
            $objects[$num] = "<< /Type /Font /Subtype /Type1 /BaseFont /$base /Encoding /WinAnsiEncoding >>";
        }

        $pageCount = count($this->pages);
        $nextObj   = 6;
        $pageObjNums = [];
        $pageObjs  = [];

        foreach ($this->pages as $i => $content) {
            $pageNum = $nextObj++;
            $contNum = $nextObj++;
            $pageObjNums[] = $pageNum;
            $pageObjs[$pageNum] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 " . self::PAGE_W . ' ' . self::PAGE_H . "] "
                . "/Resources << /Font << /F1 3 0 R /F2 4 0 R /F3 5 0 R >> >> /Contents $contNum 0 R >>";
            $pageObjs[$contNum] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";
        }

        foreach ($this->images as $img) {
            $num = $nextObj++;
            $data = $img['data'];
            $decodeParms = $img['decodeParms'] !== null ? ' /DecodeParms ' . $img['decodeParms'] : '';
            $pageObjs[$num] = "<< /Type /XObject /Subtype /Image /Width {$img['w']} /Height {$img['h']} "
                . "/ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter {$img['filter']}{$decodeParms} /Length " . strlen($data) . " >>\nstream\n" . $data . "\nendstream";
            // reference the image in every page's /Resources (NOT inside /Font)
            foreach ($pageObjNums as $pn) {
                $pageObjs[$pn] = str_replace(
                    '/Font << /F1 3 0 R /F2 4 0 R /F3 5 0 R >> >>',
                    "/Font << /F1 3 0 R /F2 4 0 R /F3 5 0 R >> /XObject << {$img['name']} $num 0 R >> >>",
                    $pageObjs[$pn]
                );
            }
        }

        $kids = implode(' ', array_map(fn ($n) => "$n 0 R", $pageObjNums));
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = "<< /Type /Pages /Kids [$kids] /Count $pageCount >>";
        foreach ($pageObjs as $num => $body) {
            $objects[$num] = $body;
        }

        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($pdf);
            $pdf .= "$num 0 obj\n$body\nendobj\n";
        }
        $xrefPos = strlen($pdf);
        $max = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $max; $i++) {
            $pdf .= isset($offsets[$i])
                ? sprintf("%010d 00000 n \n", $offsets[$i])
                : "0000000000 65535 f \n";
        }
        $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\nstartxref\n$xrefPos\n%%EOF";
        return $pdf;
    }

    public function send(string $filename, bool $attachment): never
    {
        $pdf = $this->output();
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . ($attachment ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
        header('Cache-Control: no-store');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }
}
