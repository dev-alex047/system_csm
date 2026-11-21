<?php 
class utils{

    public function __construct() {
    }

    
    public function getPaisesEstados(array $data = []): array {
        try {
            $nivel = trim((string)($data['nivel'] ?? ''));

            if (!in_array($nivel, ['paises', 'estados'], true)) {
                throw new InvalidArgumentException(
                    'Tipo de consulta geográfica inválido.'
                );
            }

            $pais = trim((string)($data['pais'] ?? ''));

            if ($nivel === 'estados' &&
                ($pais === '' || strlen($pais) > 100)) {
                throw new InvalidArgumentException(
                    'Selecciona un país válido.'
                );
            }

            $cache = sys_get_temp_dir() . '/csm_geo_' .
                sha1($nivel . '|' . $pais) . '.json';

            if (is_file($cache) &&
                (time() - filemtime($cache)) < 86400) {

                $guardado = json_decode(
                    file_get_contents($cache), true
                );

                if (is_array($guardado)) {
                    return [
                        'status' => 'ok',
                        'code' => 200,
                        'answer' => 'Catálogo consultado.',
                        'results' => $guardado
                    ];
                }
            }

            if (!function_exists('curl_init')) {
                throw new RuntimeException(
                    'PHP no tiene habilitada la extensión cURL.'
                );
            }

            $url = 'https://countriesnow.space/api/v0.1/countries/' .
                ($nivel === 'paises' ? 'iso' : 'states');

            $curl = curl_init($url);

            if ($curl === false) {
                throw new RuntimeException(
                    'No se pudo inicializar cURL.'
                );
            }

            $opciones = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json'
                ]
            ];

            if ($nivel === 'estados') {
                $opciones[CURLOPT_POST] = true;

                $opciones[CURLOPT_POSTFIELDS] = json_encode(
                    ['country' => $pais],
                    JSON_UNESCAPED_UNICODE
                );

                $opciones[CURLOPT_HTTPHEADER] = [
                    'Accept: application/json',
                    'Content-Type: application/json'
                ];
            }

            curl_setopt_array($curl, $opciones);

            $respuesta = curl_exec($curl);
            $codigoHttp = (int)curl_getinfo(
                $curl,
                CURLINFO_HTTP_CODE
            );

            $errorCurl = curl_error($curl);
            curl_close($curl);

            if ($respuesta === false ||
                $codigoHttp !== 200 ||
                strlen((string)$respuesta) > 3000000) {
                throw new RuntimeException(
                    'CountriesNow HTTP ' . $codigoHttp .
                    ': ' . $errorCurl
                );
            }

            $json = json_decode($respuesta, true);

            if (!is_array($json) ||
                ($json['error'] ?? true) !== false) {
                throw new RuntimeException(
                    'CountriesNow devolvió una respuesta inválida: ' .
                    (string)($json['msg'] ?? '')
                );
            }

            $filas = $nivel === 'paises'
                ? ($json['data'] ?? [])
                : ($json['data']['states'] ?? []);

            if (!is_array($filas)) {
                throw new RuntimeException(
                    'El catálogo geográfico no tiene el formato esperado.'
                );
            }

            $resultados = [];

            foreach ($filas as $fila) {
                if (!is_array($fila)) {
                    continue;
                }

                $nombre = trim((string)($fila['name'] ?? ''));

                if ($nombre === '') {
                    continue;
                }

                $resultados[] = [
                    'name' => $nombre,
                    'iso2' => $nivel === 'paises'
                        ? strtoupper((string)(
                            $fila['Iso2'] ?? $fila['iso2'] ?? ''
                        ))
                        : ''
                ];
            }

            @file_put_contents(
                $cache,
                json_encode(
                    $resultados,
                    JSON_UNESCAPED_UNICODE
                ),
                LOCK_EX
            );

            return [
                'status' => 'ok',
                'code' => 200,
                'answer' => 'Ubicaciones consultadas correctamente.',
                'results' => $resultados
            ];

        } catch (InvalidArgumentException $e) {
            return [
                'status' => 'warning',
                'code' => 400,
                'answer' => $e->getMessage()
            ];

        } catch (Throwable $e) {
            error_log(
                'getPaisesEstados: ' . $e->getMessage()
            );

            return [
                'status' => 'error',
                'code' => 503,
                'answer' => 'No se pudo consultar el catálogo geográfico.'
                'razon' => 'razo ' . $e->getMessage()
            ]; 
        }
    }


}
?>