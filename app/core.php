<?php
/**
 * Smile — noyau : configuration, base de données, session, sécurité, outils.
 */
declare(strict_types=1);

define('SMILE_ROOT', dirname(__DIR__));

$CONFIG = require SMILE_ROOT . '/config/config.php';

const ROLES = [
    'ADMINISTRATEUR'            => 'Administrateur',
    'RESPONSABLE_ATELIER'       => 'Responsable des ateliers',
    'COLLABORATEUR_RESPONSABLE' => 'Collaborateur responsable',
    'OPERATEUR'                 => 'Opérateur',
];

/* ------------------------------------------------------------------
 * En-têtes de sécurité HTTP
 * ------------------------------------------------------------------ */
function envoyer_entetes_securite(): void
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; font-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
    header('Cache-Control: no-store, max-age=0');
}

/* ------------------------------------------------------------------
 * Session durcie
 * ------------------------------------------------------------------ */
function demarrer_session(): void
{
    global $CONFIG;
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('SMILESESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();

    // Expiration après inactivité
    $limite = $CONFIG['app']['session_minutes'] * 60;
    if (isset($_SESSION['derniere_activite']) && time() - $_SESSION['derniere_activite'] > $limite) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['flash'][] = ['info', 'Votre session a expiré après ' . $CONFIG['app']['session_minutes'] . ' minutes d’inactivité. Reconnectez-vous.'];
    }
    $_SESSION['derniere_activite'] = time();

    // Liaison de la session au navigateur (limite le vol de session)
    $empreinte = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    if (isset($_SESSION['compte']) && ($_SESSION['empreinte'] ?? '') !== $empreinte) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['empreinte'] = $empreinte;
}

/* ------------------------------------------------------------------
 * Base de données (PDO, requêtes préparées uniquement)
 * ------------------------------------------------------------------ */
function db(): PDO
{
    static $pdo = null;
    global $CONFIG;
    if ($pdo) {
        return $pdo;
    }
    $c = $CONFIG['db'];
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    if ($c['driver'] === 'sqlite') {
        $pdo = new PDO('sqlite:' . $c['sqlite'], null, null, $options);
        $pdo->exec('PRAGMA foreign_keys = ON');
    } else {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], $c['port'], $c['database']);
        $pdo = new PDO($dsn, $c['user'], $c['password'], $options);
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function q_all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function q_one(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function q_val(string $sql, array $params = [])
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

function maintenant(): string
{
    return date('Y-m-d H:i:s');
}

/* ------------------------------------------------------------------
 * Échappement et affichage
 * ------------------------------------------------------------------ */
function e($v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fcfa($v): string
{
    return number_format((float)$v, 0, ',', ' ') . ' F CFA';
}

function nombre($v): string
{
    return number_format((float)$v, 0, ',', ' ');
}

function date_fr(?string $d, bool $heure = false): string
{
    if (!$d) {
        return '';
    }
    $t = strtotime($d);
    return $t ? date($heure ? 'd/m/Y H:i' : 'd/m/Y', $t) : e($d);
}

function duree(int $minutes): string
{
    if ($minutes < 60) {
        return $minutes . ' min';
    }
    return intdiv($minutes, 60) . ' h ' . str_pad((string)($minutes % 60), 2, '0', STR_PAD_LEFT);
}

function url(string $page, array $params = []): string
{
    return 'index.php?' . http_build_query(['p' => $page] + $params);
}

function rediriger(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = [$type, $message];
}

function lire_flashs(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ------------------------------------------------------------------
 * Protection CSRF
 * ------------------------------------------------------------------ */
function jeton_csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function champ_csrf(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(jeton_csrf()) . '">';
}

function verifier_csrf(): void
{
    $envoye = $_POST['_csrf'] ?? '';
    if (!is_string($envoye) || !hash_equals(jeton_csrf(), $envoye)) {
        http_response_code(400);
        flash('erreur', 'Le formulaire a expiré. Rechargez la page puis recommencez.');
        rediriger(url('tableau'));
    }
}

/* ------------------------------------------------------------------
 * Authentification et droits
 * ------------------------------------------------------------------ */
function utilisateur(): ?array
{
    return $_SESSION['compte'] ?? null;
}

function role(): ?string
{
    return utilisateur()['role'] ?? null;
}

function ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

/** Droits par module : [lecture, écriture] */
function droits(): array
{
    return [
        'tableau'        => [['*'], []],
        'ateliers'       => [['ADMINISTRATEUR', 'RESPONSABLE_ATELIER', 'COLLABORATEUR_RESPONSABLE'], ['ADMINISTRATEUR', 'RESPONSABLE_ATELIER']],
        'lignes'         => [['ADMINISTRATEUR', 'RESPONSABLE_ATELIER', 'COLLABORATEUR_RESPONSABLE', 'OPERATEUR'], ['ADMINISTRATEUR', 'RESPONSABLE_ATELIER']],
        'produits'       => [['ADMINISTRATEUR', 'RESPONSABLE_ATELIER', 'COLLABORATEUR_RESPONSABLE'], ['ADMINISTRATEUR', 'RESPONSABLE_ATELIER']],
        'lots'           => [['ADMINISTRATEUR', 'RESPONSABLE_ATELIER', 'COLLABORATEUR_RESPONSABLE'], ['ADMINISTRATEUR', 'RESPONSABLE_ATELIER']],
        'operateurs'     => [['ADMINISTRATEUR', 'RESPONSABLE_ATELIER', 'COLLABORATEUR_RESPONSABLE'], ['ADMINISTRATEUR', 'COLLABORATEUR_RESPONSABLE', 'RESPONSABLE_ATELIER']],
        'equipes'        => [['ADMINISTRATEUR', 'RESPONSABLE_ATELIER', 'COLLABORATEUR_RESPONSABLE'], ['ADMINISTRATEUR', 'COLLABORATEUR_RESPONSABLE']],
        'incidents'      => [['ADMINISTRATEUR', 'RESPONSABLE_ATELIER', 'COLLABORATEUR_RESPONSABLE'], ['ADMINISTRATEUR', 'COLLABORATEUR_RESPONSABLE']],
        'types_incident' => [['ADMINISTRATEUR', 'RESPONSABLE_ATELIER', 'COLLABORATEUR_RESPONSABLE'], ['ADMINISTRATEUR', 'COLLABORATEUR_RESPONSABLE']],
        'machines'       => [['ADMINISTRATEUR', 'RESPONSABLE_ATELIER', 'OPERATEUR'], ['ADMINISTRATEUR', 'OPERATEUR']],
        'maintenances'   => [['ADMINISTRATEUR', 'RESPONSABLE_ATELIER', 'OPERATEUR'], ['ADMINISTRATEUR', 'OPERATEUR']],
        'pieces'         => [['ADMINISTRATEUR', 'OPERATEUR'], ['ADMINISTRATEUR', 'OPERATEUR']],
        'achats'         => [['ADMINISTRATEUR', 'OPERATEUR'], ['ADMINISTRATEUR', 'OPERATEUR']],
        'fournisseurs'   => [['ADMINISTRATEUR', 'OPERATEUR'], ['ADMINISTRATEUR']],
        'collaborateurs' => [['ADMINISTRATEUR'], ['ADMINISTRATEUR']],
        'comptes'        => [['ADMINISTRATEUR'], ['ADMINISTRATEUR']],
        'journal'        => [['ADMINISTRATEUR'], []],
        'profil'         => [['*'], ['*']],
    ];
}

function peut(string $module, string $mode = 'lire'): bool
{
    $r = role();
    if (!$r || !isset(droits()[$module])) {
        return false;
    }
    [$lecture, $ecriture] = droits()[$module];
    $liste = $mode === 'ecrire' ? $ecriture : $lecture;
    return in_array('*', $liste, true) || in_array($r, $liste, true);
}

function exiger(string $module, string $mode = 'lire'): void
{
    if (!peut($module, $mode)) {
        http_response_code(403);
        journaliser('acces_refuse', "Module $module ($mode)");
        flash('erreur', 'Votre rôle ne donne pas accès à cette fonction.');
        rediriger(url('tableau'));
    }
}

function journaliser(string $action, string $details): void
{
    $u = utilisateur();
    try {
        q('INSERT INTO journal (compte_id, identifiant, action, details, ip, date_action) VALUES (?,?,?,?,?,?)', [
            $u['id'] ?? null, $u['identifiant'] ?? 'anonyme', $action, mb_substr($details, 0, 255), ip(), maintenant(),
        ]);
    } catch (Throwable $t) {
        // le journal ne doit jamais bloquer l'application
    }
}

/* ------------------------------------------------------------------
 * Connexion / anti force brute
 * ------------------------------------------------------------------ */
function connexion_bloquee(string $identifiant): int
{
    global $CONFIG;
    $depuis = date('Y-m-d H:i:s', time() - $CONFIG['app']['blocage_minutes'] * 60);
    $n = (int)q_val('SELECT COUNT(*) FROM tentatives_connexion WHERE (identifiant = ? OR ip = ?) AND date_tentative > ?', [$identifiant, ip(), $depuis]);
    return $n >= $CONFIG['app']['max_tentatives'] ? $n : 0;
}

function tenter_connexion(string $identifiant, string $mdp): string
{
    if (connexion_bloquee($identifiant)) {
        journaliser('connexion_bloquee', $identifiant);
        return 'bloque';
    }
    $c = q_one('SELECT * FROM comptes WHERE identifiant = ?', [$identifiant]);
    // Vérification à temps constant même si le compte n'existe pas
    $hash = $c['mot_de_passe_hache'] ?? '$2y$10$ewL/Nd5bI2LtCVXNHgx/b..iIjCcVVa78AemAMii12Bihf95RPbxO';
    $ok = password_verify($mdp, $hash) && $c && (int)$c['actif'] === 1;
    if (!$ok) {
        q('INSERT INTO tentatives_connexion (identifiant, ip, date_tentative) VALUES (?,?,?)', [$identifiant, ip(), maintenant()]);
        $_SESSION['compte'] = null;
        journaliser('connexion_echec', $identifiant);
        return 'echec';
    }
    if (password_needs_rehash($c['mot_de_passe_hache'], PASSWORD_DEFAULT)) {
        q('UPDATE comptes SET mot_de_passe_hache = ? WHERE id = ?', [password_hash($mdp, PASSWORD_DEFAULT), $c['id']]);
    }
    session_regenerate_id(true);
    $_SESSION['compte'] = [
        'id' => (int)$c['id'], 'identifiant' => $c['identifiant'], 'role' => $c['role'],
        'operateur_matricule' => $c['operateur_matricule'], 'collaborateur_id' => $c['collaborateur_id'],
        'nom_affiche' => nom_affiche($c),
    ];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    q('DELETE FROM tentatives_connexion WHERE identifiant = ?', [$identifiant]);
    q('UPDATE comptes SET derniere_connexion = ? WHERE id = ?', [maintenant(), $c['id']]);
    journaliser('connexion', 'Connexion réussie');
    return 'ok';
}

function nom_affiche(array $c): string
{
    if ($c['operateur_matricule']) {
        $o = q_one('SELECT prenom, nom FROM operateurs WHERE matricule = ?', [$c['operateur_matricule']]);
        if ($o) return $o['prenom'] . ' ' . $o['nom'];
    }
    if ($c['collaborateur_id']) {
        $o = q_one('SELECT prenom, nom FROM collaborateurs WHERE id = ?', [$c['collaborateur_id']]);
        if ($o) return $o['prenom'] . ' ' . $o['nom'];
    }
    return $c['identifiant'];
}

/** Politique de mot de passe : 10 caractères, une majuscule, une minuscule, un chiffre */
function mot_de_passe_faible(string $mdp): ?string
{
    if (mb_strlen($mdp) < 10) return 'Le mot de passe doit contenir au moins 10 caractères.';
    if (!preg_match('/[A-Z]/', $mdp)) return 'Le mot de passe doit contenir au moins une majuscule.';
    if (!preg_match('/[a-z]/', $mdp)) return 'Le mot de passe doit contenir au moins une minuscule.';
    if (!preg_match('/\d/', $mdp)) return 'Le mot de passe doit contenir au moins un chiffre.';
    return null;
}

/* ------------------------------------------------------------------
 * Lecture sûre des entrées
 * ------------------------------------------------------------------ */
function entree(string $cle, string $defaut = ''): string
{
    $v = $_POST[$cle] ?? $_GET[$cle] ?? $defaut;
    return is_string($v) ? trim($v) : $defaut;
}

function entree_tableau(string $cle): array
{
    $v = $_POST[$cle] ?? [];
    return is_array($v) ? array_values(array_filter($v, 'is_string')) : [];
}

function est_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}
