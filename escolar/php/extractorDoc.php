<?php

class extractorDoc{
    public function __construct() {
        
    }

    public function getDataPdf($file){
        $json = [];
        
        $parser = new Parser();
        $imagenesExtraidas = [];
        
        try {
            $pdf = $parser->parseFile($file);
            $textoExtraido = trim($pdf->getText());
            $textoLimpio = preg_replace('/\s+/', '', $textoExtraido);
        
            $imagick = new Imagick();
            $imagick->setResolution(150, 150);
            $imagick->readImage($file);
        
            $outputDir = __DIR__ . "/../../../audios/";
            if (!is_dir($outputDir)) mkdir($outputDir, 0777, true);
        
            foreach ($imagick as $i => $pagina) {
                $nombreImg = 'pagina_' . $i . '_' . time() . '.jpg';
                $rutaImg = $outputDir . $nombreImg;
        
                $pagina->setImageFormat('jpeg');
                $pagina->writeImage($rutaImg);
        
                $imagenesExtraidas[] = $rutaImg;
            }
        
            $imagick->clear();
            $imagick->destroy();
        
            if (strlen($textoLimpio) > 20) {
                $json["status"] = "ok";
                $json["text"] = $textoExtraido;
                $json["image_urls"] = $imagenesExtraidas;
                $json["requires_ocr"] = false;
                
            } else {
                $json["status"] = "ok";
                $json["requires_ocr"] = true;
                $json["answer"] = "No se pudo extraer texto legible. El archivo requiere OCR.";
                $json["image_urls"] = $imagenesExtraidas;
            }
        } catch (Exception $e) {
            $json["status"] = "error";
            $json["answer"] = "Error al procesar el PDF";
            $json["error"] = $e->getMessage();
            $json["code"] = 500;
        }

        return $json;
        
    }
    public function getDataDocx($file){
        $json = array(
            "status"        => "ok",
            "requires_ocr"  => false,
            "text"          => "",
            "image_urls"    => array(),
            "meta"          => array()
        );
    
        try {
            if (!is_file($file)) {
                throw new Exception("El archivo DOCX no existe: " . $file);
            }
            if (!class_exists('ZipArchive')) {
                throw new Exception("La extensión ZipArchive no está habilitada.");
            }
    
            $zip = new ZipArchive();
            if ($zip->open($file) !== true) {
                throw new Exception("No se pudo abrir el DOCX como ZIP.");
            }
    
            // ¿DOCX cifrado? (paquete OOXML encriptado no expone word/document.xml)
            if ($zip->locateName('EncryptedPackage') !== false) {
                throw new Exception("El DOCX está protegido/cifrado. No es legible sin clave.");
            }
    
            // -------- Helpers --------
    
            // PHP 7.2: helper ends_with (reemplaza str_ends_with)
            $ends_with = function($haystack, $needle) {
                if ($needle === '') return true;
                $len = strlen($needle);
                if ($len === 0) return true;
                if (strlen($haystack) < $len) return false;
                return substr($haystack, -$len) === $needle;
            };
    
            $readEntry = function($path) use ($zip) {
                // Buscar por ruta exacta primero
                $idx = $zip->locateName($path);
                if ($idx === false) {
                    // Fallback: sólo nombre de archivo, ignorando directorios y mayúsculas
                    $idx = $zip->locateName(basename($path), ZipArchive::FL_NODIR | ZipArchive::FL_NOCASE);
                }
                if ($idx === false) return null;
                $data = $zip->getFromIndex($idx);
                return ($data === false) ? null : $data;
            };
    
            $listWordXmlEntries = function() use ($zip, $ends_with) {
                $out = array();
                for ($i=0; $i<$zip->numFiles; $i++) {
                    $stat = $zip->statIndex($i);
                    if (!$stat || !isset($stat['name'])) continue;
                    $name = $stat['name'];
                    if (strpos($name, 'word/') === 0 && $ends_with($name, '.xml') && !$ends_with($name, '.rels')) {
                        $out[] = $name;
                    }
                }
                return $out;
            };
    
            $makeXPath = function($xml) {
                $dom = new DOMDocument();
                $dom->preserveWhiteSpace = false;
                $dom->formatOutput = false;
    
                // Parser “duro”: sin cargar entidades externas
                $old = libxml_use_internal_errors(true);
                $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
                libxml_clear_errors();
                libxml_use_internal_errors($old);
                if (!$ok) return null;
    
                $xp = new DOMXPath($dom);
                $xp->registerNamespace('w',   'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                $xp->registerNamespace('cp',  'http://schemas.openxmlformats.org/package/2006/metadata/core-properties');
                $xp->registerNamespace('dc',  'http://purl.org/dc/elements/1.1/');
                $xp->registerNamespace('dcterms', 'http://purl.org/dc/terms/');
                $xp->registerNamespace('wps','http://schemas.microsoft.com/office/word/2010/wordprocessingShape');
                $xp->registerNamespace('mc','http://schemas.openxmlformats.org/markup-compatibility/2006');
                $xp->registerNamespace('v','urn:schemas-microsoft-com:vml');
                // DrawingML (formas/rotulación en Word)
                $xp->registerNamespace('a','http://schemas.openxmlformats.org/drawingml/2006/main');
                $xp->registerNamespace('wp','http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing');
                return $xp;
            };
    
            $normalize_line = function($s) {
                $s = preg_replace('/\s+/u', ' ', $s);
                return trim($s);
            };
    
            // Extrae texto de una parte XML (document.xml, headers, footers, etc.)
            $extract_with_dom = function($xml) use ($makeXPath, $normalize_line) {
                $xp = $makeXPath($xml);
                if (!$xp) return array();
    
                $chunks = array();
    
                // 1) Párrafos (incluye tablas, sdt, hyperlinks, etc.)
                $paragraphs = $xp->query('//w:p');
                if ($paragraphs && $paragraphs->length) {
                    for ($i=0; $i<$paragraphs->length; $i++) {
                        $p = $paragraphs->item($i);
                        $xpP = new DOMXPath($p->ownerDocument);
                        $xpP->registerNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                        $xpP->registerNamespace('wps','http://schemas.microsoft.com/office/word/2010/wordprocessingShape');
                        $xpP->registerNamespace('v','urn:schemas-microsoft-com:vml');
                        $xpP->registerNamespace('a','http://schemas.openxmlformats.org/drawingml/2006/main');
    
                        // Unir en orden de documento varios tipos de nodos de texto
                        $nodes = $xpP->query('.//w:t | .//w:instrText | .//a:t | .//wps:txbxContent//w:t | .//v:textbox//w:txbxContent//w:t', $p);
                        $line = '';
                        if ($nodes && $nodes->length) {
                            for ($j=0; $j<$nodes->length; $j++) {
                                $line .= $nodes->item($j)->textContent;
                            }
                        }
    
                        // Saltos de línea explícitos en el párrafo => añade un \n al final
                        $brs = $xpP->query('.//w:br | .//w:cr', $p);
                        if ($brs && $brs->length) $line .= "\n";
    
                        // Tabs explícitos: añade un espacio
                        $tabs = $xpP->query('.//w:tab', $p);
                        if ($tabs && $tabs->length) {
                            $line = str_replace("\t", ' ', $line);
                        }
    
                        $line = $normalize_line($line);
                        if ($line !== '') $chunks[] = $line;
                    }
                }
    
                // 2) Si no hubo párrafos, intenta recoger texto suelto (p.ej. dentro de dibujos fuera de w:p)
                if (!count($chunks)) {
                    $nodes = $xp->query('//w:t | //a:t | //wps:txbxContent//w:t | //v:textbox//w:txbxContent//w:t');
                    if ($nodes && $nodes->length) {
                        $buf = '';
                        for ($i=0; $i<$nodes->length; $i++) $buf .= $nodes->item($i)->textContent.' ';
                        $buf = $normalize_line($buf);
                        if ($buf !== '') $chunks[] = $buf;
                    }
                }
    
                return $chunks;
            };
    
            $extract_with_regex = function($xml) use ($normalize_line) {
                // Sustituir saltos/tabs explícitos
                $xml = preg_replace('#<w:br[^>]*/>#i', "\n", $xml);
                $xml = preg_replace('#<w:cr[^>]*/>#i', "\n", $xml);
                $xml = preg_replace('#<w:tab[^>]*/>#i', "\t", $xml);
    
                $out = array();
                // w:t (Word) y a:t (DrawingML)
                $buf = '';
                if (preg_match_all('#<w:t[^>]*>(.*?)</w:t>#si', $xml, $m1)) {
                    foreach ($m1[1] as $piece) {
                        $buf .= html_entity_decode($piece, ENT_QUOTES | ENT_XML1, 'UTF-8') . ' ';
                    }
                }
                if (preg_match_all('#<a:t[^>]*>(.*?)</a:t>#si', $xml, $m2)) {
                    foreach ($m2[1] as $piece) {
                        $buf .= html_entity_decode($piece, ENT_QUOTES | ENT_XML1, 'UTF-8') . ' ';
                    }
                }
                $buf = $normalize_line($buf);
                if ($buf !== '') $out[] = $buf;
    
                return $out;
            };
    
            $gather_part = function($path) use ($readEntry, $extract_with_dom, $extract_with_regex) {
                $xml = $readEntry($path);
                if ($xml === null) return array();
                $chunks = $extract_with_dom($xml);
                if (!count($chunks)) {
                    // Fallback por regex si DOM no trajo nada
                    $chunks = $extract_with_regex($xml);
                }
                return $chunks;
            };
    
            $allChunks = array();
    
            // 1) Cuerpo principal
            $allChunks = array_merge($allChunks, $gather_part('word/document.xml'));
    
            // 2) Headers/Footers detectados por relationships (no asumimos 1..9)
            $relsXml = $readEntry('word/_rels/document.xml.rels');
            if ($relsXml !== null) {
                $domR = new DOMDocument();
                $okR = @$domR->loadXML($relsXml);
                if ($okR) {
                    $xpR = new DOMXPath($domR);
                    $xpR->registerNamespace('rel','http://schemas.openxmlformats.org/package/2006/relationships');
    
                    // NO pasar false como segundo argumento (contexto)
                    $rels = $xpR->query('//rel:Relationship');
                    if ($rels && $rels->length) {
                        for ($i=0; $i<$rels->length; $i++) {
                            $type   = $rels->item($i)->getAttribute('Type');
                            $target = $rels->item($i)->getAttribute('Target');
                            if (!$target) continue;
    
                            // Normaliza target relativo a 'word/'
                            if (strpos($target, '/') === 0) {
                                $part = ltrim($target, '/');
                            } else {
                                $part = 'word/' . $target;
                            }
    
                            // Headers / Footers
                            if (stripos($type, '/header') !== false || stripos($type, '/footer') !== false) {
                                $allChunks = array_merge($allChunks, $gather_part($part));
                            }
                        }
                    } else {
                        // Fallback si no hay relationships útiles
                        for ($n=1; $n<=9; $n++) {
                            $allChunks = array_merge($allChunks, $gather_part('word/header'.$n.'.xml'));
                            $allChunks = array_merge($allChunks, $gather_part('word/footer'.$n.'.xml'));
                        }
                    }
                } else {
                    // Fallback si el XML de rels no se pudo parsear
                    for ($n=1; $n<=9; $n++) {
                        $allChunks = array_merge($allChunks, $gather_part('word/header'.$n.'.xml'));
                        $allChunks = array_merge($allChunks, $gather_part('word/footer'.$n.'.xml'));
                    }
                }
            } else {
                // Fallback si no existe document.xml.rels
                for ($n=1; $n<=9; $n++) {
                    $allChunks = array_merge($allChunks, $gather_part('word/header'.$n.'.xml'));
                    $allChunks = array_merge($allChunks, $gather_part('word/footer'.$n.'.xml'));
                }
            }
    
            // 3) Footnotes / Endnotes / Comments
            $allChunks = array_merge($allChunks, $gather_part('word/footnotes.xml'));
            $allChunks = array_merge($allChunks, $gather_part('word/endnotes.xml'));
            $allChunks = array_merge($allChunks, $gather_part('word/comments.xml'));
            // (Opcional) commentsExtended.xml si existe
            $allChunks = array_merge($allChunks, $gather_part('word/commentsExtended.xml'));
    
            // 4) altChunk (HTML/extra contenido importado)
            for ($i=0; $i<$zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (!$stat || !isset($stat['name'])) continue;
                $name = $stat['name'];
    
                if (preg_match('#^word/(afchunk|altChunk)\d+\.(html?|xml)$#i', $name)) {
                    $data = $zip->getFromIndex($i);
                    if ($data === false) continue;
    
                    if (preg_match('#\.html?$#i', $name)) {
                        $text = strip_tags($data);
                        $text = trim(preg_replace('/\s+/u', ' ', $text));
                        if ($text !== '') $allChunks[] = $text;
                    } else {
                        $chunks = $extract_with_dom($data);
                        if (!count($chunks)) $chunks = $extract_with_regex($data);
                        if (count($chunks)) $allChunks = array_merge($allChunks, $chunks);
                    }
                }
            }
    
            // 4.bis) Fallback fuerte: peina TODOS los XML de /word por w:t y a:t
            if (!count(array_filter($allChunks, function($x) { return $x !== ''; }))) {
                foreach ($listWordXmlEntries() as $xmlPath) {
                    $allChunks = array_merge($allChunks, $gather_part($xmlPath));
                }
            }
    
            // Consolidar texto
            $json['text'] = trim(implode("\n", array_values(array_unique(array_filter($allChunks, function($x) {
                return $x !== '';
            })))));
    
    
            // 5) Metadatos
            $core = $readEntry('docProps/core.xml');
            if ($core !== null) {
                $xpCore = $makeXPath($core);
                if ($xpCore) {
                    $get1 = function($xp, $q) {
                        $n = $xp->query($q);
                        return ($n && $n->length) ? $n->item(0)->textContent : null;
                    };
                    $meta = array();
                    $meta['title']    = $get1($xpCore, '//dc:title');
                    $meta['subject']  = $get1($xpCore, '//dc:subject');
                    $meta['creator']  = $get1($xpCore, '//dc:creator');
                    $meta['keywords'] = $get1($xpCore, '//cp:keywords');
                    $meta['created']  = $get1($xpCore, '//dcterms:created');
                    $meta['modified'] = $get1($xpCore, '//dcterms:modified');
    
                    $json['meta'] = array_filter($meta, function ($v) {
                        return $v !== null && $v !== '';
                    });
                }
            }
    
            // 6) Imágenes -> audios/ (lo dejo tal cual lo tenías)
            $outputDir = __DIR__ . "/../../../audios/";
            if (!is_dir($outputDir)) {
                @mkdir($outputDir, 0777, true);
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (!$stat || !isset($stat['name'])) continue;
    
                $name = $stat['name'];
                if (strpos($name, 'word/media/') === 0) {
                    $data = $zip->getFromIndex($i);
                    if ($data === false) continue;
    
                    $basename = basename($name);
                    $destFs   = $outputDir . $basename;
                    @file_put_contents($destFs, $data);
    
                    $json['image_urls'][] = "audios/" . $basename;
                }
            }
    
            $zip->close();
    
            // Si no hubo texto pero sí imágenes, sugiere OCR
            if ($json['text'] === '' && count($json['image_urls']) > 0) {
                $json['requires_ocr'] = true;
            }
    
        } catch (Exception $e) {
            $json = array(
                "status" => "error",
                "answer" => "Error al procesar el DOCX",
                "error"  => $e->getMessage(),
                "code"   => 500
            );
        }
    
        return $json;
    }
    function getDataXml($file) {
        $json = [];
    
        try {
            if (!is_file($file)) {
                throw new Exception("El archivo XML no existe: " . $file);
            }
    
            // Estado inicial
            $json['status'] = "ok";
    
            // Cargar XML de forma segura con DOM
            $dom = new DOMDocument();
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = false;
    
            $old = libxml_use_internal_errors(true);
            $ok = $dom->load($file, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            libxml_clear_errors();
            libxml_use_internal_errors($old);
    
            if (!$ok) {
                throw new Exception("Error al parsear el XML");
            }
    
            // ------ Texto ------
            $text = $dom->textContent;
            $text = preg_replace('/\s+/u', ' ', $text);
            $json['text'] = trim($text);
    
            // ------ Meta ------
            $meta = [];
    
            if (!empty($dom->xmlVersion))  $meta['xml_version']  = $dom->xmlVersion;
            if (!empty($dom->encoding))    $meta['encoding']     = $dom->encoding;
    
            $root = $dom->documentElement;
            if ($root instanceof DOMElement) {
                $meta['root_name'] = $root->nodeName;
    
                // Atributos del root
                if ($root->hasAttributes()) {
                    $attrs = array();
                    foreach ($root->attributes as $attr) {
                        $attrs[$attr->nodeName] = $attr->value;
                    }
                    if (!empty($attrs)) {
                        $meta['root_attributes'] = $attrs;
                    }
                }
    
                // Namespaces
                $namespaces = array();
                if ($root->hasAttributes()) {
                    foreach ($root->attributes as $attr) {
                        if (strpos($attr->nodeName, 'xmlns') === 0) {
                            $namespaces[$attr->nodeName] = $attr->value;
                        }
                    }
                }
                if (!empty($namespaces)) {
                    $meta['namespaces'] = $namespaces;
                }
            }
    
            $meta['element_count'] = $dom->getElementsByTagName('*')->length;
            $meta['text_length']   = strlen($json['text']);
    
            $json['meta'] = $meta;
    
        } catch (Exception $e) {
            $json = [];
            $json['status'] = "error";
            $json['answer'] = "Error al procesar el XML";
            $json['error']  = $e->getMessage();
            $json['code']   = 500;
        }
    
        return $json;
    }
    


}

?>