<?php
// Exemplo de configuração de mapa e chaves de API
// Copie este arquivo para config/keys.php e personalize suas configurações

// Provedor de Mapa: 'leaflet' (gratuito via OpenStreetMap) ou 'google_maps' (Google Maps API)
if (!defined('MAP_PROVIDER')) {
    define('MAP_PROVIDER', 'leaflet');
}

if (!defined('GOOGLE_MAPS_API_KEY')) {
    define('GOOGLE_MAPS_API_KEY', 'SUA_CHAVE_GOOGLE_MAPS_AQUI');
}
