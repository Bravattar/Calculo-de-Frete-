<?php
// Configuracoes base do calculo de frete.
define('LOJA_LAT', -23.55052);
define('LOJA_LNG', -46.633308);
define('PRECO_POR_KM', 1.25);
define('VALOR_MINIMO', 11.00);
define('CUPOM_FRETE_GRATIS', 'FRETEFREE');

/**
 * Calcula distancia entre dois pontos em km (formula de Haversine).
 */
function calcularDistanciaMatematica(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $raioTerraKm = 6371;

    $deltaLat = deg2rad($lat2 - $lat1);
    $deltaLng = deg2rad($lng2 - $lng1);

    $lat1 = deg2rad($lat1);
    $lat2 = deg2rad($lat2);

    $a = sin($deltaLat / 2) ** 2
        + cos($lat1) * cos($lat2) * sin($deltaLng / 2) ** 2;

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $raioTerraKm * $c;
}
