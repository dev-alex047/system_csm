<?php 

use Shuchkin\SimpleXLSX; 

class extractDocument{
    function getDataCSV($ruta_csv) {
        $json = [];
    
        if (!file_exists($ruta_csv)) {
            $json["status"] = "error";
            $json["answer"] = "El archivo no fue encontrado.";
            $json["code"] = 404;
        } elseif (($handle = fopen($ruta_csv, "r")) === false) {
            $json["status"] = "error";
            $json["answer"] = "Error al abrir el archivo.";
            $json["code"] = 500;
        } else {
            $data = [];
            $headers = [];
            $fila = fgetcsv($handle);
    
            if ($fila === false) {
                $json["status"] = "error";
                $json["answer"] = "El archivo está vacío.";
                $json["code"] = 204;
                fclose($handle);
                return $json;
            }
    
            $esEncabezado = true;
            foreach ($fila as $celda) {
                if ($celda === '' || is_numeric($celda)) {
                    $esEncabezado = false;
                    break;
                }
            }
    
            if ($esEncabezado) {
                $headers = $fila;
            } else {
                $headers = [];
                for ($i = 0; $i < count($fila); $i++) {
                    $headers[] = "col" . ($i + 1);
                }
                $data[] = array_combine($headers, $fila);
            }
    
            while (($row = fgetcsv($handle)) !== false) {
                $data[] = array_combine($headers, $row);
            }
    
            fclose($handle);
    
            $json["status"] = "ok";
            $json["code"] = 200;
            $json["data"] = $data;
        }
    
        return $json;
    }

    public function getDataExcel($ruta_excel) {
        $json = [];
    
        if (!file_exists($ruta_excel)) {
            $json["status"] = "error";
            $json["answer"] = "El archivo no fue encontrado.";
            $json["code"] = 404;
            return $json;
        }
        //Shuchkin\SimpleXLSX
        if (!$xlsx = SimpleXLSX::parse($ruta_excel)) {
            $json["status"] = "error";
            $json["answer"] = "Error al leer el archivo: " . SimpleXLSX::parseError();
            $json["code"] = 500;
            return $json;
        }
        
        $filas = $xlsx->rows();

        if (empty($filas)) {
            $json["status"] = "error";
            $json["answer"] = "El archivo está vacío.";
            $json["code"] = 204;
            return $json;
        }

        $indiceEncabezado = null;
        foreach ($filas as $i => $fila) {
            $noVacias = array_filter($fila, function($celda) {
                return trim($celda) !== '';
            });
            $noNumericas = array_filter($noVacias, function($celda) {
                return !is_numeric($celda);
            });
            if (count($noNumericas) >= 2) {
                $indiceEncabezado = $i;
                break;
            }
        }

        if ($indiceEncabezado === null) {
            $json["status"] = "error";
            $json["answer"] = "No se encontró una fila de encabezado válida.";
            $json["code"] = 422;
            return $json;
        }
    
        $headers = array_map('trim', $filas[$indiceEncabezado]);
        $data = [];
    
        for ($j = $indiceEncabezado + 1; $j < count($filas); $j++) {
            $fila = array_pad($filas[$j], count($headers), '');
            $data[] = array_combine($headers, $fila);
        }

        $json["status"] = "ok";
        $json["code"] = 200;
        $json["data"] = $data;
        return $json;    
    }

    public function getDataExcel_new($ruta_excel, $opts = []) {
        $json = [];
    
        // -------- opciones por defecto --------
        $opts = array_merge([
            'skip_blank_rows'          => true, // no incluir filas totalmente vacías
            'min_non_empty'            => 1,    // mínimo de celdas con contenido para considerar "fila con datos"
            'stop_after_n_empty'       => 200,  // corta si hay muchos vacíos seguidos (cola)
            'backfill_merged_headers'  => true, // si un encabezado está vacío, usar el anterior (celdas combinadas)
            'generate_placeholder_hdr' => true, // si aún queda vacío, genera colN
            'header_min_non_numeric'   => 1,    // al menos X encabezados no puramente numéricos en la fila candidata
            'header_min_non_empty'     => 2     // al menos X celdas no vacías en la fila candidata
        ], $opts);
    
        if (!file_exists($ruta_excel)) {
            $json["status"] = "error";
            $json["answer"] = "El archivo no fue encontrado.";
            $json["code"]   = 404;
            return $json;
        }
    
        if (!$xlsx = SimpleXLSX::parse($ruta_excel)) {
            $json["status"] = "error";
            $json["answer"] = "Error al leer el archivo: " . SimpleXLSX::parseError();
            $json["code"]   = 500;
            return $json;
        }
    
        $filas = $xlsx->rows();
        if (empty($filas)) {
            $json["status"] = "error";
            $json["answer"] = "El archivo está vacío.";
            $json["code"]   = 204;
            return $json;
        }
    
        // -------- localizar fila de encabezados con heurística configurable --------
        $indiceEncabezado = null;
        foreach ($filas as $i => $fila) {
            $noVacias     = array_filter($fila, function($c){ return trim((string)$c) !== ''; });
            $noNumericas  = array_filter($noVacias, function($c){ return !is_numeric($c); });
    
            if (count($noVacias) >= $opts['header_min_non_empty'] &&
                count($noNumericas) >= $opts['header_min_non_numeric']) {
                $indiceEncabezado = $i;
                break;
            }
        }
    
        if ($indiceEncabezado === null) {
            $json["status"] = "error";
            $json["answer"] = "No se encontró una fila de encabezado válida.";
            $json["code"]   = 422;
            return $json;
        }
    
        // -------- normalizar encabezados --------
        $headersRaw   = $filas[$indiceEncabezado];
        $headers      = [];
        $seen         = [];
        $lastNonEmpty = null;
    
        foreach ($headersRaw as $idx => $h) {
            $h = trim(preg_replace('/\s+/', ' ', (string)$h)); // compactar espacios
    
            // Propagar encabezado anterior si está vacío (útil con celdas combinadas)
            if ($h === '' && $opts['backfill_merged_headers'] && $lastNonEmpty !== null) {
                $h = $lastNonEmpty;
            }
    
            // Si sigue vacío, generar colN
            if ($h === '' && $opts['generate_placeholder_hdr']) {
                $h = 'col'.($idx+1);
            }
    
            // Unicidad: clave, clave_2, clave_3...
            $base = $h;
            $k = 2;
            while ($h !== '' && isset($seen[$h])) {
                $h = $base . '_' . $k;
                $k++;
            }
            if ($h !== '') $seen[$h] = true;
    
            $headers[] = $h;
            if ($h !== '') $lastNonEmpty = $h;
        }
    
        // -------- construir data --------
        $data        = [];
        $blankStreak = 0;
    
        for ($j = $indiceEncabezado + 1; $j < count($filas); $j++) {
            $fila = array_pad($filas[$j], count($headers), '');
    
            // ¿fila totalmente vacía?
            $nonEmpty = 0;
            foreach ($fila as $c) {
                if (trim((string)$c) !== '') { $nonEmpty++; }
            }
    
            if ($opts['skip_blank_rows'] && $nonEmpty < $opts['min_non_empty']) {
                $blankStreak++;
                if ($blankStreak >= $opts['stop_after_n_empty']) break; // cortar cola de vacíos
                continue; // saltar fila vacía
            }
            $blankStreak = 0;
    
            $data[] = array_combine($headers, $fila);
        }
    
        $json["status"]  = "ok";
        $json["code"]    = 200;
        $json["headers"] = $headers; // útil para debug
        $json["data"]    = $data;
        return $json;
    }

    public function getDataCSV_new($ruta_csv, $opts = []) {
        $json = [];
    
        $opts = array_merge([
            'encoding'                 => 'UTF-8',
            'delimiters'               => [",",";","\t","|"],
            'enclosure'                => '"',
            'escape'                   => "\\",
            'skip_blank_rows'          => true,
            'min_non_empty'            => 1,
            'header_min_non_empty'     => 2,
            'header_min_non_numeric'   => 1,
            'generate_placeholder_hdr' => true,
            'backfill_merged_headers'  => false,
            'trim_cells'               => true,
            'stop_after_n_empty'       => 500,
        ], $opts);
    
        if (!file_exists($ruta_csv)) {
            $json["status"] = "error";
            $json["answer"] = "El archivo no fue encontrado.";
            $json["code"]   = 404;
            return $json;
        }
    
        // Detectar delimitador
        $detectDelimiter = function($path, $candidates) {
            $fh = fopen($path, 'r');
            if (!$fh) return ",";
    
            $first = fgets($fh);
            if ($first === false) {
                fclose($fh);
                return ",";
            }
    
            if (substr($first, 0, 3) === "\xEF\xBB\xBF") {
                $first = substr($first, 3);
            }
    
            $sample = [$first];
            for ($i = 0; $i < 4; $i++) {
                $line = fgets($fh);
                if ($line === false) break;
                $sample[] = $line;
            }
            fclose($fh);
    
            $best = ",";
            $bestScore = -1;
    
            foreach ($candidates as $d) {
                $score = 0;
                $colsRef = null;
                foreach ($sample as $ln) {
                    $ln = rtrim($ln, "\r\n");
                    if ($ln === '') continue;
                    $parts = str_getcsv($ln, $d, '"', "\\");
                    $cnt   = count($parts);
                    if ($cnt > 1) $score += 1;
                    if ($colsRef === null) $colsRef = $cnt;
                    elseif ($cnt === $colsRef) $score += 1;
                }
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $d;
                }
            }
            return $best;
        };
    
        $delimiter = $detectDelimiter($ruta_csv, $opts['delimiters']);
    
        $handle = fopen($ruta_csv, "r");
        if ($handle === false) {
            $json["status"] = "error";
            $json["answer"] = "Error al abrir el archivo.";
            $json["code"]   = 500;
            return $json;
        }
    
        $pos = ftell($handle);
        $firstBytes = fread($handle, 3);
        if ($firstBytes !== "\xEF\xBB\xBF") {
            fseek($handle, $pos);
        }
    
        $headersRow = fgetcsv($handle, 0, $delimiter, $opts['enclosure'], $opts['escape']);
        if ($headersRow === false) {
            fclose($handle);
            $json["status"] = "error";
            $json["answer"] = "El archivo está vacío.";
            $json["code"]   = 204;
            return $json;
        }
    
        $noVacias = array_filter($headersRow, function($c) {
            return trim((string)$c) !== '';
        });
    
        $noNumericas = array_filter($noVacias, function($c) {
            return !is_numeric($c);
        });
    
        $esEncabezado = (count($noVacias) >= $opts['header_min_non_empty'] &&
                         count($noNumericas) >= $opts['header_min_non_numeric']);
    
        $headersRaw = $headersRow;
        $data = [];
    
        if (!$esEncabezado) {
            $headers = [];
            for ($i = 0; $i < count($headersRow); $i++) {
                $headers[] = "col" . ($i + 1);
            }
            $data[] = array_combine($headers, $headersRow);
        } else {
            $headers = [];
            $seen = [];
            $lastNonEmpty = null;
    
            foreach ($headersRaw as $idx => $h) {
                $h = (string)$h;
                if ($opts['trim_cells']) {
                    $h = preg_replace('/\s+/', ' ', trim($h));
                }
                if ($h === '' && $opts['backfill_merged_headers'] && $lastNonEmpty !== null) {
                    $h = $lastNonEmpty;
                }
                if ($h === '' && $opts['generate_placeholder_hdr']) {
                    $h = 'col' . ($idx + 1);
                }
    
                $base = $h;
                $k = 2;
                while ($h !== '' && isset($seen[$h])) {
                    $h = $base . '_' . $k;
                    $k++;
                }
                if ($h !== '') $seen[$h] = true;
                $headers[] = $h;
                if ($h !== '') $lastNonEmpty = $h;
            }
        }
    
        $blankStreak = 0;
        while (($row = fgetcsv($handle, 0, $delimiter, $opts['enclosure'], $opts['escape'])) !== false) {
            if (count($row) < count($headers)) {
                $row = array_pad($row, count($headers), '');
            } elseif (count($row) > count($headers)) {
                $row = array_slice($row, 0, count($headers));
            }
    
            if ($opts['trim_cells']) {
                foreach ($row as &$c) {
                    $c = preg_replace('/\s+/', ' ', trim((string)$c));
                }
                unset($c);
            }
    
            $nonEmpty = 0;
            foreach ($row as $c) {
                if ($c !== '') { $nonEmpty++; }
            }
    
            if ($opts['skip_blank_rows'] && $nonEmpty < $opts['min_non_empty']) {
                $blankStreak++;
                if ($blankStreak >= $opts['stop_after_n_empty']) break;
                continue;
            }
            $blankStreak = 0;
    
            $data[] = array_combine($headers, $row);
        }
    
        fclose($handle);
    
        $json["status"]    = "ok";
        $json["code"]      = 200;
        $json["delimiter"] = $delimiter;
        $json["headers"]   = $headers;
        $json["data"]      = $data;
        return $json;
    }
    
    //DOCX
    //use PhpOffice\PhpWord\IOFactory;

    public function getDataDOCX($ruta_docx, $opts = []) {
        $json = [];
    
        $opts = array_merge([
            'trim_cells'           => true,
            'join_cell_separator'  => ' ',   // cómo unir múltiples runs dentro de una celda
            'detect_header'        => true,  // heurística para primera fila como encabezado
            'header_min_non_empty' => 2,
            'header_min_non_numeric'=> 1,
            'parse_key_value_lines'=> true,  // intentar detectar "campo: valor" en párrafos
            'kv_min_pairs'         => 2,     // mínimo de pares para considerar útil
        ], $opts);
    
        if (!file_exists($ruta_docx)) {
            $json['status'] = 'error';
            $json['answer'] = 'El archivo no fue encontrado.';
            $json['code']   = 404;
            return $json;
        }
    
        try {
            $phpWord = IOFactory::load($ruta_docx, 'Word2007');
        } catch (Exception $e) {
            $json['status'] = 'error';
            $json['answer'] = 'Error al leer el DOCX: ' . $e->getMessage();
            $json['code']   = 500;
            return $json;
        }
    
        $tables      = [];  // arreglo de tablas normalizadas [{headers:[], rows:[{...},...]}, ...]
        $paragraphs  = [];  // texto plano por párrafo
        $lists       = [];  // items de listas
        $kv_candidates = []; // pares detectados en texto plano
    
        // --- helpers ---
        $getTextFromElement = function($el) use (&$getTextFromElement, $opts) {
            $txt = '';
            $sep = $opts['join_cell_separator'];
    
            $class = get_class($el);
            // Text simple
            if ($class === 'PhpOffice\\PhpWord\\Element\\Text') {
                $txt = $el->getText();
            }
            // TextRun (conjunto de Text/Break/Link/etc.)
            else if ($class === 'PhpOffice\\PhpWord\\Element\\TextRun') {
                $parts = [];
                foreach ($el->getElements() as $child) {
                    $t = $getTextFromElement($child);
                    if ($t !== '') $parts[] = $t;
                }
                $txt = implode($sep, $parts);
            }
            // Link
            else if ($class === 'PhpOffice\\PhpWord\\Element\\Link') {
                $txt = trim($el->getText() ?: $el->getSource());
            }
            // ListItem
            else if ($class === 'PhpOffice\\PhpWord\\Element\\ListItem') {
                $parts = [];
                foreach ($el->getElements() as $child) {
                    $t = $getTextFromElement($child);
                    if ($t !== '') $parts[] = $t;
                }
                $txt = implode($sep, $parts);
            }
            // Footnote/Endnote/… (opcional)
            else if (method_exists($el, 'getText')) {
                $txt = (string)$el->getText();
            }
    
            if ($opts['trim_cells']) {
                $txt = preg_replace('/\s+/', ' ', trim((string)$txt));
            }
            return $txt;
        };
    
        $normalizeTable = function($table) use ($opts, $getTextFromElement) {
            $rows = [];
            foreach ($table->getRows() as $row) {
                $cellsText = [];
                foreach ($row->getCells() as $cell) {
                    $cellParts = [];
                    foreach ($cell->getElements() as $child) {
                        $t = $getTextFromElement($child);
                        if ($t !== '') $cellParts[] = $t;
                    }
                    $cellText = implode($opts['join_cell_separator'], $cellParts);
                    if ($opts['trim_cells']) {
                        $cellText = preg_replace('/\s+/', ' ', trim($cellText));
                    }
                    $cellsText[] = $cellText;
                }
                $rows[] = $cellsText;
            }
    
            if (empty($rows)) {
                return ['headers'=>[], 'rows'=>[]];
            }
    
            // Heurística de encabezado
            $headers = [];
            $dataRows = [];
            if ($opts['detect_header'] && !empty($rows)) {
                $hdr = $rows[0];
                $noVacias    = array_filter($hdr, function($c){ return trim((string)$c) !== ''; });
                $noNumericas = array_filter($noVacias, function($c){ return !is_numeric($c); });
                $esEncabezado = (count($noVacias) >= $opts['header_min_non_empty'] &&
                                 count($noNumericas) >= $opts['header_min_non_numeric']);
    
                if ($esEncabezado) {
                    $seen = [];
                    foreach ($hdr as $i=>$h) {
                        $h = preg_replace('/\s+/', ' ', trim((string)$h));
                        if ($h === '') $h = 'col'.($i+1);
                        $base = $h; $k = 2;
                        while (isset($seen[$h])) { $h = $base.'_'.$k; $k++; }
                        $seen[$h] = true;
                        $headers[] = $h;
                    }
                    for ($i=1; $i<count($rows); $i++) {
                        $row = $rows[$i];
                        // igualar longitudes
                        if (count($row) < count($headers)) {
                            $row = array_pad($row, count($headers), '');
                        } elseif (count($row) > count($headers)) {
                            $row = array_slice($row, 0, count($headers));
                        }
                        $dataRows[] = array_combine($headers, $row);
                    }
                } else {
                    // sin encabezado → generar colN
                    $colCount = max(array_map('count', $rows));
                    for ($i=0; $i<$colCount; $i++) { $headers[] = 'col'.($i+1); }
                    foreach ($rows as $r) {
                        if (count($r) < $colCount) $r = array_pad($r, $colCount, '');
                        $dataRows[] = array_combine($headers, $r);
                    }
                }
            } else {
                // no detectar encabezado → colN
                $colCount = max(array_map('count', $rows));
                for ($i=0; $i<$colCount; $i++) { $headers[] = 'col'.($i+1); }
                foreach ($rows as $r) {
                    if (count($r) < $colCount) $r = array_pad($r, $colCount, '');
                    $dataRows[] = array_combine($headers, $r);
                }
            }
    
            // filtrar filas totalmente vacías
            $cleanRows = [];
            foreach ($dataRows as $r) {
                $nonEmpty = 0;
                foreach ($r as $v) { if (trim((string)$v) !== '') { $nonEmpty++; } }
                if ($nonEmpty > 0) $cleanRows[] = $r;
            }
    
            return ['headers'=>$headers, 'rows'=>$cleanRows];
        };
    
        // --- recorrer documento ---
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $el) {
                $class = get_class($el);
    
                if ($class === 'PhpOffice\\PhpWord\\Element\\Table') {
                    $tables[] = $normalizeTable($el);
                }
                else if ($class === 'PhpOffice\\PhpWord\\Element\\ListItem') {
                    $text = $getTextFromElement($el);
                    if ($text !== '') $lists[] = $text;
                }
                else {
                    // Parrafos, TextRun, Link, etc.
                    $text = $getTextFromElement($el);
                    if ($text !== '') $paragraphs[] = $text;
                }
            }
        }
    
        // Heurística KV en párrafos (campo: valor)
        if ($opts['parse_key_value_lines'] && !empty($paragraphs)) {
            $pairs = [];
            foreach ($paragraphs as $line) {
                // formatos soportados: "campo: valor" | "campo - valor" | "campo = valor"
                if (preg_match('/^\s*([^:\-=]{2,}?)\s*[:=\-]\s*(.+)$/u', $line, $m)) {
                    $k = preg_replace('/\s+/', ' ', trim($m[1]));
                    $v = preg_replace('/\s+/', ' ', trim($m[2]));
                    if ($k !== '' && $v !== '') {
                        $pairs[$k] = $v;
                    }
                }
            }
            if (count($pairs) >= $opts['kv_min_pairs']) {
                $kv_candidates = $pairs;
            }
        }
    
        $json['status']        = 'ok';
        $json['code']          = 200;
        $json['tables']        = $tables;       // cada item: ['headers'=>[], 'rows'=>[...]]
        $json['paragraphs']    = $paragraphs;   // texto por párrafo/elemento
        $json['lists']         = $lists;        // items de listas
        $json['kv_candidates'] = $kv_candidates;// pares "campo: valor" detectados (si aplica)
    
        return $json;
    }

    
    
}

?>