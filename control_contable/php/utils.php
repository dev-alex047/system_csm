<?php 
class utils{

    public function __construct() {
    }

    
    
    
    public function getPaisesEstados(array $data = []): array {
        try {
            $nivel = isset($data['nivel']) && is_scalar($data['nivel'])
                ? trim((string)$data['nivel'])
                : '';

            if (!in_array($nivel, ['paises', 'estados'], true)) {
                throw new InvalidArgumentException(
                    'Selecciona una consulta válida.'
                );
            }

            $archivo = __DIR__ . '/recursos/paises_estados.json';

            if (!is_readable($archivo)) {
                throw new RuntimeException(
                    'No se encuentra el archivo paises_estados.json.'
                );
            }

            $contenido = file_get_contents($archivo);
            $catalogo = json_decode($contenido, true);

            if (
                json_last_error() !== JSON_ERROR_NONE ||
                !is_array($catalogo) ||
                !isset($catalogo['countries']) ||
                !isset($catalogo['states'])
            ) {
                throw new RuntimeException(
                    'El archivo de países y estados no es válido.'
                );
            }

            if ($nivel === 'paises') {
                $resultados = $catalogo['countries'];

            } else {
                $pais = isset($data['pais']) && is_scalar($data['pais'])
                    ? trim((string)$data['pais'])
                    : '';

                if ($pais === '' || strlen($pais) > 100) {
                    throw new InvalidArgumentException(
                        'Selecciona un país válido.'
                    );
                }

                $pais = str_replace(
                    ['México', 'méxico'],
                    'Mexico',
                    $pais
                );

                $codigo = '';

                foreach ($catalogo['countries'] as $item) {
                    if (
                        strcasecmp(
                            (string)$item['name'],
                            $pais
                        ) === 0 ||
                        strcasecmp(
                            (string)$item['iso2'],
                            $pais
                        ) === 0
                    ) {
                        $codigo = (string)$item['iso2'];
                        break;
                    }
                }

                if ($codigo === '') {
                    throw new InvalidArgumentException(
                        'El país seleccionado no existe.'
                    );
                }

                $resultados = $catalogo['states'][$codigo] ?? [];
            }

            return [
                'status' => 'ok',
                'code' => 200,
                'answer' => 'Catálogo geográfico consultado.',
                'results' => array_values($resultados)
            ];

        } catch (InvalidArgumentException $e) {
            return [
                'status' => 'warning',
                'code' => 400,
                'answer' => $e->getMessage()
            ];

        } catch (Throwable $e) {
            

            return [
                'status' => 'error',
                'code' => 500,
                'answer' => 'No se pudo leer el catálogo geográfico.',
                'error' => 'getPaisesEstados: ' . $e->getMessage()
            ];
        }
    }

    public function getPaisesEstados_old(array $data = []): array {
        try {
            $nivel = trim((string)($data['nivel'] ?? ''));
            $pais = trim((string)($data['pais'] ?? ''));

            if (!in_array($nivel, ['paises', 'estados'], true)) {
                throw new InvalidArgumentException(
                    'Consulta geográfica inválida.'
                );
            }

            if (
                $nivel === 'estados' &&
                ($pais === '' || strlen($pais) > 100)
            ) {
                throw new InvalidArgumentException(
                    'Selecciona un país válido.'
                );
            }

            $base = 'https://cdn.jsdelivr.net/gh/' .
                'srestre/world-countries-cities-db@main';

            $carpeta = sys_get_temp_dir();

            $obtenerJson = function(
                string $ruta,
                string $archivo
            ) use ($base): array {

                if (is_file($archivo)) {
                    $guardado = json_decode(
                        (string)file_get_contents($archivo),
                        true
                    );

                    if (is_array($guardado)) {
                        return $guardado;
                    }
                }

                if (!function_exists('curl_init')) {
                    throw new RuntimeException(
                        'PHP no tiene habilitado cURL.'
                    );
                }

                $curl = curl_init($base . $ruta);

                curl_setopt_array($curl, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_TIMEOUT => 25,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_USERAGENT => 'ControlContable/1.0',
                    CURLOPT_ENCODING => ''
                ]);

                $respuesta = curl_exec($curl);

                $http = (int)curl_getinfo(
                    $curl,
                    CURLINFO_HTTP_CODE
                );

                $errorCurl = curl_error($curl);

                curl_close($curl);

                if (
                    $respuesta === false ||
                    $http !== 200 ||
                    strlen((string)$respuesta) > 12000000
                ) {
                    throw new RuntimeException(
                        'Catálogo HTTP ' . $http .
                        ': ' . $errorCurl
                    );
                }

                $json = json_decode($respuesta, true);

                if (!is_array($json)) {
                    throw new RuntimeException(
                        'El JSON geográfico no es válido.'
                    );
                }

                if (
                    @file_put_contents(
                        $archivo,
                        $respuesta,
                        LOCK_EX
                    ) === false
                ) {
                    
                }

                return $json;
            };

            $indice = $obtenerJson(
                '/metadata/index.json',
                $carpeta . '/csm_geo_indice_v2.json'
            );

            $resultados = [];

            if ($nivel === 'paises') {

                foreach ($indice as $iso => $info) {
                    if (
                        !is_array($info) ||
                        !preg_match('/^[A-Z]{2}$/', (string)$iso)
                    ) {
                        continue;
                    }

                    $nombre = trim((string)(
                        $info['name_es'] ??
                        $info['name'] ?? ''
                    ));

                    if ($nombre === '') {
                        continue;
                    }

                    $resultados[] = [
                        'name' => $nombre,
                        'iso2' => $iso
                    ];
                }

            } else {

                $iso = strtoupper($pais);

                if (!isset($indice[$iso])) {
                    $iso = '';

                    foreach ($indice as $codigo => $info) {
                        if (!is_array($info)) {
                            continue;
                        }

                        if (
                            strcasecmp(
                                $pais,
                                (string)($info['name'] ?? '')
                            ) === 0 ||

                            strcasecmp(
                                $pais,
                                (string)($info['name_es'] ?? '')
                            ) === 0 ||

                            (
                                $codigo === 'MX' &&
                                strcasecmp($pais, 'Mexico') === 0
                            )
                        ) {
                            $iso = $codigo;
                            break;
                        }
                    }
                }

                if (
                    !preg_match('/^[A-Z]{2}$/', $iso) ||
                    !isset($indice[$iso])
                ) {
                    throw new InvalidArgumentException(
                        'El país seleccionado no existe.'
                    );
                }

                $detalle = $obtenerJson(
                    '/countries/' . $iso . '.json',
                    $carpeta . '/csm_geo_' . $iso . '_v2.json'
                );

                $datosPais = isset($detalle[0])
                    ? $detalle[0] : $detalle;

                $estados = $datosPais['states'] ?? [];

                if (!is_array($estados)) {
                    throw new RuntimeException(
                        'No se recibió un catálogo de estados válido.'
                    );
                }

                foreach ($estados as $estado) {
                    if (!is_array($estado)) {
                        continue;
                    }

                    $nombre = trim((string)(
                        $estado['name'] ?? ''
                    ));

                    if ($nombre === '') {
                        continue;
                    }

                    $resultados[] = [
                        'name' => $nombre,
                        'iso2' => ''
                    ];
                }
            }

            return [
                'status' => 'ok',
                'code' => 200,
                'answer' => 'Catálogo geográfico consultado.',
                'results' => $resultados
            ];

        } catch (InvalidArgumentException $e) {
            return [
                'status' => 'warning',
                'code' => 400,
                'answer' => $e->getMessage()
            ];

        } catch (Throwable $e) {
           

            return [
                'status' => 'error',
                'code' => 503,
                'answer' => 'No se pudo consultar el catálogo geográfico.',
                'razon' => $e->getMessage()
            ];
        }
    }




}
?>