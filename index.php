<?php
/**
 * Smile — point d'entrée unique (routeur)
 * Projet Agile des logiciels, TWIN 3, ESATIC
 */
declare(strict_types=1);

require __DIR__ . '/app/core.php';
require __DIR__ . '/app/layout.php';
require __DIR__ . '/app/crud.php';
require __DIR__ . '/app/modules.php';
require __DIR__ . '/app/metier.php';
require __DIR__ . '/app/admin.php';

envoyer_entetes_securite();
demarrer_session();

// Base non installée : on renvoie vers l'installation
try {
    db()->query('SELECT 1 FROM comptes LIMIT 1');
} catch (Throwable $t) {
    rediriger('install.php');
}

$page = preg_replace('/[^a-z_]/', '', (string)($_GET['p'] ?? 'tableau'));
$action = preg_replace('/[^a-z_]/', '', (string)($_GET['a'] ?? 'liste'));
$id = mb_substr((string)($_GET['id'] ?? ''), 0, 40);

try {
    // Pages publiques
    if ($page === 'connexion') {
        if (utilisateur()) rediriger(url('tableau'));
        page_connexion();
        exit;
    }
    if ($page === 'deconnexion') {
        deconnexion();
    }

    // Tout le reste exige une session authentifiée
    if (!utilisateur()) {
        rediriger(url('connexion'));
    }

    // Le compte a pu être désactivé pendant la session
    $actif = q_val('SELECT actif FROM comptes WHERE id = ?', [utilisateur()['id']]);
    if ((int)$actif !== 1) {
        $_SESSION = [];
        session_regenerate_id(true);
        flash('erreur', 'Votre compte a été désactivé. Contactez l’administrateur.');
        rediriger(url('connexion'));
    }

    switch ($page) {
        case 'tableau':
            tableau_de_bord();
            exit;
        case 'profil':
            profil();
            exit;
        case 'comptes':
            comptes($action, $id);
            exit;
        case 'journal':
            journal();
            exit;
    }

    $modules = modules();
    if (!isset($modules[$page])) {
        http_response_code(404);
        flash('erreur', 'Cette page n’existe pas.');
        rediriger(url('tableau'));
    }
    $m = $modules[$page];

    switch ($action) {
        case 'liste':
            crud_liste($page, $m);
            break;
        case 'voir':
            voir($page, $id);
            break;
        case 'nouveau':
            // Pré-remplissage depuis une fiche (ex. ajouter une ligne à un atelier)
            $pre = [];
            foreach ($m['champs'] as $nom => $f) {
                if (empty($f['pk']) && isset($_GET[$nom]) && is_string($_GET[$nom])) $pre[$nom] = $_GET[$nom];
            }
            crud_formulaire($page, $m, null, $pre);
            break;
        case 'modifier':
            if (!empty($m['pas_de_modification'])) rediriger(url($page));
            crud_formulaire($page, $m, $id);
            break;
        case 'enregistrer':
            if (!est_post()) rediriger(url($page));
            crud_enregistrer($page, $m, $id !== '' ? $id : null);
            break;
        case 'supprimer':
            crud_supprimer($page, $m, $id);
            break;
        default:
            // Actions métier : toujours en POST avec jeton CSRF
            if (!est_post()) rediriger(url($page));
            verifier_csrf();
            action_metier($page, $action, $id);
    }
} catch (PDOException $ex) {
    error_log('[Smile] ' . $ex->getMessage());
    http_response_code(500);
    flash('erreur', 'L’opération n’a pas pu être enregistrée : une contrainte de la base de données l’a refusée. Aucune donnée n’a été modifiée.');
    rediriger(utilisateur() ? url('tableau') : url('connexion'));
}
