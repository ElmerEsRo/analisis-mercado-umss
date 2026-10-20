<?php
/**
 * Lector XLSX mínimo sin Composer.
 * Funciona aun cuando la extensión PHP ZipArchive NO está habilitada.
 * Lee la primera hoja del libro y soporta archivos XLSX estándar (ZIP/Deflate).
 */
class PurePhpZipReader {
    private string $data;
    private array $entries = [];

    public function __construct(string $file) {
        $data = @file_get_contents($file);
        if ($data === false || strlen($data) < 22) {
            throw new RuntimeException('No se pudo abrir el archivo XLSX.');
        }
        $this->data = $data;
        $this->readCentralDirectory();
    }

    public function getFromName(string $name) {
        $name = str_replace('\\', '/', $name);
        if (!isset($this->entries[$name])) return false;
        $e = $this->entries[$name];
        $offset = $e['offset'];
        if (substr($this->data, $offset, 4) !== "PK\x03\x04") return false;

        $h = unpack('vversion/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vusize/vnlen/velen', substr($this->data, $offset + 4, 26));
        if (!$h) return false;
        $start = $offset + 30 + $h['nlen'] + $h['elen'];
        $compressed = substr($this->data, $start, $e['csize']);

        if ($e['method'] === 0) return $compressed; // stored
        if ($e['method'] === 8) { // deflate
            $out = @gzinflate($compressed);
            if ($out === false) {
                // fallback for some PHP/zlib builds
                $out = @gzuncompress($compressed);
            }
            return $out;
        }
        throw new RuntimeException('El XLSX usa un método de compresión no compatible. Guárdelo nuevamente como .xlsx desde Excel.');
    }

    private function readCentralDirectory(): void {
        $len = strlen($this->data);
        $searchStart = max(0, $len - 65557); // EOCD + max comment
        $pos = strrpos(substr($this->data, $searchStart), "PK\x05\x06");
        if ($pos === false) throw new RuntimeException('El archivo no parece ser un XLSX válido.');
        $pos += $searchStart;

        $eocd = unpack('vdisk/vcdisk/ventriesDisk/ventries/VcdSize/VcdOffset/vcommentLen', substr($this->data, $pos + 4, 18));
        if (!$eocd) throw new RuntimeException('No se pudo leer la estructura interna del XLSX.');

        $p = $eocd['cdOffset'];
        for ($i = 0; $i < $eocd['entries']; $i++) {
            if (substr($this->data, $p, 4) !== "PK\x01\x02") break;
            $h = unpack(
                'vverMade/vverNeed/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vusize/vnlen/velen/vclen/vdisk/vintAttr/VextAttr/Voffset',
                substr($this->data, $p + 4, 42)
            );
            if (!$h) break;
            $name = substr($this->data, $p + 46, $h['nlen']);
            $name = str_replace('\\', '/', $name);
            $this->entries[$name] = [
                'method' => $h['method'],
                'csize' => $h['csize'],
                'usize' => $h['usize'],
                'offset' => $h['offset'],
            ];
            $p += 46 + $h['nlen'] + $h['elen'] + $h['clen'];
        }
    }
}

class SimpleXlsxReader {
    public static function read(string $file): array {
        // Si ZipArchive está disponible, se usa; si no, se usa el lector ZIP puro PHP.
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($file) !== true) throw new RuntimeException('No se pudo abrir el archivo XLSX.');
            $get = static function(string $name) use ($zip) { return $zip->getFromName($name); };
            $close = static function() use ($zip) { $zip->close(); };
        } else {
            $zip = new PurePhpZipReader($file);
            $get = static function(string $name) use ($zip) { return $zip->getFromName($name); };
            $close = static function() {};
        }

        // XLSX estándar: primera hoja. No requiere SimpleXML/DOM.
        $shared = [];
        $sharedXml = $get('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            if (preg_match_all('~<si\\b[^>]*>(.*?)</si>~si', $sharedXml, $items)) {
                foreach ($items[1] as $item) {
                    $text = '';
                    if (preg_match_all('~<t\\b[^>]*>(.*?)</t>~si', $item, $ts)) {
                        foreach ($ts[1] as $part) $text .= self::xmlText($part);
                    }
                    $shared[] = $text;
                }
            }
        }

        $xml = $get('xl/worksheets/sheet1.xml');
        $close();
        if ($xml === false) throw new RuntimeException('No se encontró la primera hoja del XLSX.');

        $rows = [];
        if (!preg_match_all('~<row\\b[^>]*>(.*?)</row>~si', $xml, $rowMatches)) return $rows;
        foreach ($rowMatches[1] as $rowXml) {
            $out = [];
            if (preg_match_all('~<c\\b([^>]*)>(.*?)</c>~si', $rowXml, $cells, PREG_SET_ORDER)) {
                foreach ($cells as $cell) {
                    $attrs = $cell[1];
                    $body = $cell[2];
                    $ref = self::attr($attrs, 'r');
                    preg_match('/([A-Z]+)(\\d+)/i', $ref ?: 'A1', $m);
                    $idx = self::colIndex(strtoupper($m[1] ?? 'A'));
                    $type = self::attr($attrs, 't');
                    $value = '';

                    if ($type === 'inlineStr') {
                        if (preg_match_all('~<t\\b[^>]*>(.*?)</t>~si', $body, $ts)) {
                            foreach ($ts[1] as $part) $value .= self::xmlText($part);
                        }
                    } else {
                        $raw = '';
                        if (preg_match('~<v\\b[^>]*>(.*?)</v>~si', $body, $vm)) $raw = self::xmlText($vm[1]);
                        if ($type === 's') $value = $shared[(int)$raw] ?? '';
                        elseif ($type === 'b') $value = ($raw === '1') ? 'VERDADERO' : 'FALSO';
                        else $value = $raw;
                    }
                    $out[$idx] = $value;
                }
            }
            if ($out) {
                ksort($out);
                $max = max(array_keys($out));
                $dense = [];
                for ($i=0; $i<=$max; $i++) $dense[] = $out[$i] ?? '';
                $rows[] = $dense;
            }
        }
        return $rows;
    }

    private static function attr(string $attrs, string $name): string {
        if (preg_match('/(?:^|\\s)'.preg_quote($name,'/').'\\s*=\\s*(["\\\'])(.*?)\\1/si', $attrs, $m)) {
            return self::xmlText($m[2]);
        }
        return '';
    }

    private static function xmlText(string $s): string {
        return html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function colIndex(string $letters): int {
        $n = 0;
        for ($i=0; $i<strlen($letters); $i++) $n = $n * 26 + (ord($letters[$i]) - 64);
        return $n - 1;
    }
}
