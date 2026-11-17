<?php
/**
 * Smile — configuration
 *
 * Avec XAMPP / WAMP : laisser 'driver' => 'mysql' et adapter
 * l'utilisateur et le mot de passe MySQL si besoin.
 */
return [
    'app' => [
        'nom'            => 'Smile',
        'usine'          => 'Usine Smile — Zone industrielle de Yopougon',
        'session_minutes'=> 30,          // déconnexion après 30 min d'inactivité
        'max_tentatives' => 5,           // tentatives de connexion avant blocage
        'blocage_minutes'=> 15,          // durée du blocage
        'seuil_stock'    => 20,          // en dessous : alerte de stock
    ],
    'db' => [
        'driver'   => getenv('SMILE_DB_DRIVER') ?: 'mysql',   // 'mysql' ou 'sqlite'
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'database' => 'smile_usine',
        'user'     => 'root',
        'password' => '',
        'sqlite'   => __DIR__ . '/../storage/smile.sqlite',
    ],
];
