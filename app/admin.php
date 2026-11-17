<?php
/**
 * Smile — connexion, tableau de bord, comptes, journal, profil
 */

/* ==================================================================
 * Connexion (cas d'utilisation « S'authentifier »)
 * ================================================================== */
function page_connexion(): void
{
    global $CONFIG;
    $erreur = null;
    $identifiant = '';
    if (est_post()) {
        verifier_csrf_connexion();
        $identifiant = mb_substr(entree('identifiant'), 0, 40);
        $mdp = (string)($_POST['mot_de_passe'] ?? '');
        if ($identifiant === '' || $mdp === '') {
            $erreur = 'Saisissez votre identifiant et votre mot de passe.';
        } else {
            $res = tenter_connexion($identifiant, $mdp);
            if ($res === 'ok') rediriger(url('tableau'));
            $erreur = $res === 'bloque'
                ? 'Trop de tentatives. Pour protéger le compte, la connexion est suspendue ' . $CONFIG['app']['blocage_minutes'] . ' minutes.'
                : 'Identifiant ou mot de passe incorrect. Après ' . $CONFIG['app']['max_tentatives'] . ' essais, la connexion est suspendue.';
        }
    }
    ?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Connexion · Smile</title>
<link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
<meta name="theme-color" content="#1E2A33">
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="page-connexion">
<div class="connexion">
  <div class="connexion-visuel">
    <div class="connexion-marque">
      <img src="assets/img/smile-mark.svg" alt="" width="44" height="44">
      <span>Smile</span>
    </div>
    <div class="connexion-scene" aria-hidden="true"><?php readfile(SMILE_ROOT . '/assets/img/ligne-production.svg'); ?></div>
    <div class="connexion-texte">
      <p class="connexion-titre">Ateliers, lignes, lots et maintenance, au même endroit.</p>
      <p>Suivez la production de l’usine, déclarez les incidents et tenez le stock de pièces à jour.</p>
    </div>
  </div>
  <div class="connexion-form">
    <form method="post" action="<?= e(url('connexion')) ?>" novalidate>
      <h1>Connexion</h1>
      <p class="intro">Utilisez l’identifiant fourni par l’administrateur.</p>
      <?php foreach (lire_flashs() as [$type, $msg]): ?><div class="message message-<?= e($type) ?>" role="status"><?= e($msg) ?></div><?php endforeach; ?>
      <?php if ($erreur): ?><div class="message message-erreur" role="alert"><?= e($erreur) ?></div><?php endif; ?>
      <input type="hidden" name="_csrf" value="<?= e(jeton_csrf()) ?>">
      <div class="champ">
        <label for="identifiant">Identifiant</label>
        <input id="identifiant" name="identifiant" autocomplete="username" required autofocus value="<?= e($identifiant) ?>">
      </div>
      <div class="champ">
        <label for="mot_de_passe">Mot de passe</label>
        <div class="mdp">
          <input id="mot_de_passe" name="mot_de_passe" type="password" autocomplete="current-password" required>
          <button type="button" class="bouton-discret" data-voir-mdp="mot_de_passe" aria-label="Afficher le mot de passe">Afficher</button>
        </div>
        <p class="alerte-maj" id="alerte-maj" data-alerte-maj="mot_de_passe" aria-live="polite"></p>
      </div>
      <button class="bouton bouton-plein" type="submit">Se connecter</button>
      <p class="pied-connexion"><?= e($CONFIG['app']['usine']) ?></p>
    </form>
  </div>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
<?php
}

function verifier_csrf_connexion(): void
{
    $envoye = $_POST['_csrf'] ?? '';
    if (!is_string($envoye) || !hash_equals(jeton_csrf(), $envoye)) {
        flash('erreur', 'La page de connexion a expiré. Recommencez.');
        rediriger(url('connexion'));
    }
}

function deconnexion(): void
{
    if (est_post()) {
        verifier_csrf();
        journaliser('deconnexion', 'Déconnexion');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    session_start();
    flash('succes', 'Vous êtes déconnecté.');
    rediriger(url('connexion'));
}

/* ==================================================================
 * Tableau de bord
 * ================================================================== */
function tableau_de_bord(): void
{
    global $CONFIG;
    $u = utilisateur();
    $seuil = $CONFIG['app']['seuil_stock'];
    $debutMois = date('Y-m-01');
    $il30 = date('Y-m-d', strtotime('-30 days'));

    $lotsMois = (int)q_val('SELECT COUNT(*) FROM lots WHERE date_production >= ?', [$debutMois]);
    $arret30 = (int)q_val('SELECT COALESCE(SUM(temps_arret_minutes),0) FROM incidents WHERE date_incident >= ?', [$il30]);
    $inc30 = (int)q_val('SELECT COUNT(*) FROM incidents WHERE date_incident >= ?', [$il30]);
    $piecesBas = (int)q_val('SELECT COUNT(*) FROM pieces WHERE quantite_stock < ?', [$seuil]);
    $maint30 = (int)q_val('SELECT COUNT(*) FROM maintenances WHERE debut >= ?', [$il30 . ' 00:00:00']);

    $heure = (int)date('G');
    $salut = $heure < 18 ? 'Bonjour' : 'Bonsoir';
    page_debut($u['nom_affiche'] === $u['identifiant'] ? $salut : $salut . ' ' . explode(' ', $u['nom_affiche'])[0], 'tableau', 'Situation de l’usine au ' . date_fr(date('Y-m-d')) . '.');
    ?>
    <section class="chiffres" aria-label="Indicateurs">
      <div><span class="chiffre"><?= nombre($lotsMois) ?></span><span class="chiffre-lib">lots produits ce mois-ci</span></div>
      <div><span class="chiffre"><?= duree($arret30) ?></span><span class="chiffre-lib">d’arrêt sur <?= $inc30 ?> incident<?= $inc30 > 1 ? 's' : '' ?> (30 jours)</span></div>
      <div><span class="chiffre"><?= nombre($maint30) ?></span><span class="chiffre-lib">maintenances sur 30 jours</span></div>
      <div class="<?= $piecesBas ? 'chiffre-alerte' : '' ?>"><span class="chiffre"><?= nombre($piecesBas) ?></span><span class="chiffre-lib">pièce<?= $piecesBas > 1 ? 's' : '' ?> sous le seuil de <?= $seuil ?></span></div>
    </section>

    <div class="colonnes">
      <section class="panneau panneau-large">
        <div class="panneau-tete"><h2>Temps d’arrêt par ligne, 30 derniers jours</h2></div>
        <?php
        $parLigne = q_all('SELECT l.numero, COALESCE(SUM(i.temps_arret_minutes),0) total FROM lignes l
            LEFT JOIN lots lo ON lo.ligne_numero = l.numero
            LEFT JOIN incidents i ON i.lot_id = lo.id AND i.date_incident >= ?
            GROUP BY l.numero ORDER BY l.numero', [$il30]);
        $max = max(1, ...array_map(fn($r) => (int)$r['total'], $parLigne ?: [['total' => 1]]));
        if ($parLigne): ?>
          <ul class="barres">
          <?php foreach ($parLigne as $r): $pct = round(100 * (int)$r['total'] / $max); ?>
            <li><span class="barre-lib">Ligne <?= e($r['numero']) ?></span>
                <span class="barre-piste"><span class="barre-valeur" data-largeur="<?= $pct ?>"></span></span>
                <span class="barre-nb"><?= duree((int)$r['total']) ?></span></li>
          <?php endforeach; ?>
          </ul>
        <?php else: ?><p class="vide-court">Aucune ligne enregistrée.</p><?php endif; ?>

        <h3>Derniers incidents</h3>
        <?php
        $inc = q_all('SELECT i.numero, i.date_incident, i.temps_arret_minutes, t.libelle, lo.ligne_numero FROM incidents i JOIN types_incident t ON t.code = i.type_code JOIN lots lo ON lo.id = i.lot_id ORDER BY i.date_incident DESC, i.numero DESC LIMIT 5');
        echo mini_tableau(['Date', 'Type', 'Ligne', ['Arrêt', 'num']], array_map(fn($i) => [
            date_fr($i['date_incident']), peut('incidents') ? '<a href="' . e(url('incidents', ['a' => 'voir', 'id' => $i['numero']])) . '">' . e($i['libelle']) . '</a>' : e($i['libelle']),
            'Ligne ' . e($i['ligne_numero']), '<span class="nombre">' . duree((int)$i['temps_arret_minutes']) . '</span>',
        ], $inc), 'Aucun incident enregistré.');
        ?>
      </section>

      <section class="panneau">
        <h2>Pièces à réapprovisionner</h2>
        <?php
        $bas = q_all('SELECT reference, libelle, quantite_stock FROM pieces WHERE quantite_stock < ? ORDER BY quantite_stock, libelle LIMIT 8', [$seuil]);
        if ($bas): ?>
          <ul class="liste-stock">
          <?php foreach ($bas as $p): ?>
            <li><span><?= peut('pieces') ? '<a href="' . e(url('pieces', ['a' => 'voir', 'id' => $p['reference']])) . '">' . e($p['libelle']) . '</a>' : e($p['libelle']) ?></span><?= stock_html((int)$p['quantite_stock']) ?></li>
          <?php endforeach; ?>
          </ul>
        <?php else: ?><p class="vide-court">Toutes les pièces sont au-dessus du seuil.</p><?php endif; ?>

        <h3>Accès rapides</h3>
        <div class="raccourcis">
          <?php
          $r = [
              ['lots', 'nouveau', 'Enregistrer un lot'], ['incidents', 'nouveau', 'Déclarer un incident'],
              ['maintenances', 'nouveau', 'Déclarer une maintenance'], ['operateurs', 'nouveau', 'Ajouter un opérateur'],
              ['achats', 'nouveau', 'Noter un achat'], ['produits', 'nouveau', 'Enregistrer un produit'],
          ];
          foreach ($r as [$m, $a, $lib]) if (peut($m, 'ecrire')) echo bouton_lien(url($m, ['a' => $a]), $lib, 'raccourci');
          ?>
        </div>
      </section>
    </div>
    <?php
    page_fin();
}

/* ==================================================================
 * Comptes utilisateurs (administrateur)
 * ================================================================== */
function comptes(string $action, string $id): void
{
    exiger('comptes', $action === 'liste' ? 'lire' : 'ecrire');
    $moi = utilisateur()['id'];

    if ($action === 'basculer' && est_post()) {
        verifier_csrf();
        if ((int)$id === $moi) {
            flash('erreur', 'Vous ne pouvez pas désactiver votre propre compte.');
        } elseif ($c = q_one('SELECT id, identifiant, actif FROM comptes WHERE id = ?', [$id])) {
            q('UPDATE comptes SET actif = ? WHERE id = ?', [(int)$c['actif'] ? 0 : 1, $id]);
            journaliser('compte', ((int)$c['actif'] ? 'Désactivation' : 'Activation') . ' du compte ' . $c['identifiant']);
            flash('succes', 'Le compte « ' . $c['identifiant'] . ' » a été ' . ((int)$c['actif'] ? 'désactivé.' : 'réactivé.'));
        }
        rediriger(url('comptes'));
    }

    if ($action === 'supprimer' && est_post()) {
        verifier_csrf();
        if ((int)$id === $moi) {
            flash('erreur', 'Vous ne pouvez pas supprimer votre propre compte.');
        } elseif ($c = q_one('SELECT identifiant FROM comptes WHERE id = ?', [$id])) {
            q('DELETE FROM comptes WHERE id = ?', [$id]);
            journaliser('compte', 'Suppression du compte ' . $c['identifiant']);
            flash('succes', 'Le compte « ' . $c['identifiant'] . ' » a été supprimé.');
        }
        rediriger(url('comptes'));
    }

    if (in_array($action, ['nouveau', 'modifier', 'enregistrer'], true)) {
        $edition = $id !== '';
        $c = $edition ? q_one('SELECT * FROM comptes WHERE id = ?', [$id]) : null;
        if ($edition && !$c) introuvable('comptes', 'Ce compte');
        $v = $c ?? ['identifiant' => '', 'role' => '', 'operateur_matricule' => '', 'collaborateur_id' => '', 'actif' => 1];
        $err = [];
        if ($action === 'enregistrer' && est_post()) {
            verifier_csrf();
            $v['identifiant'] = $edition ? $c['identifiant'] : mb_strtolower(entree('identifiant'));
            $v['role'] = entree('role');
            $v['operateur_matricule'] = entree('operateur_matricule');
            $v['collaborateur_id'] = entree('collaborateur_id');
            $mdp = (string)($_POST['mot_de_passe'] ?? '');
            if (!$edition) {
                if (!preg_match('/^[a-z0-9._-]{3,40}$/', $v['identifiant'])) $err['identifiant'] = 'L’identifiant doit faire 3 à 40 caractères : lettres minuscules, chiffres, point, tiret.';
                elseif (q_val('SELECT COUNT(*) FROM comptes WHERE identifiant = ?', [$v['identifiant']])) $err['identifiant'] = 'Cet identifiant est déjà pris.';
            }
            if (!isset(ROLES[$v['role']])) $err['role'] = 'Choisissez un rôle.';
            if ($v['operateur_matricule'] !== '' && !isset(options('operateurs')[$v['operateur_matricule']])) $err['operateur_matricule'] = 'Opérateur inconnu.';
            if ($v['collaborateur_id'] !== '' && !isset(options('collaborateurs')[$v['collaborateur_id']])) $err['collaborateur_id'] = 'Responsable inconnu.';
            if ($v['role'] === 'OPERATEUR' && $v['operateur_matricule'] === '') $err['operateur_matricule'] = 'Un compte opérateur doit être relié à une fiche opérateur.';
            if (in_array($v['role'], ['RESPONSABLE_ATELIER', 'COLLABORATEUR_RESPONSABLE'], true) && $v['collaborateur_id'] === '') $err['collaborateur_id'] = 'Ce rôle doit être relié à un responsable.';
            if (!$edition || $mdp !== '') {
                if ($m = mot_de_passe_faible($mdp)) $err['mot_de_passe'] = $m;
            }
            if ($edition && (int)$id === $moi && $v['role'] !== 'ADMINISTRATEUR') $err['role'] = 'Vous ne pouvez pas retirer votre propre rôle d’administrateur.';
            if (!$err) {
                $op = $v['operateur_matricule'] ?: null;
                $co = $v['collaborateur_id'] !== '' ? (int)$v['collaborateur_id'] : null;
                if ($edition) {
                    q('UPDATE comptes SET role = ?, operateur_matricule = ?, collaborateur_id = ? WHERE id = ?', [$v['role'], $op, $co, $id]);
                    if ($mdp !== '') q('UPDATE comptes SET mot_de_passe_hache = ? WHERE id = ?', [password_hash($mdp, PASSWORD_DEFAULT), $id]);
                    journaliser('compte', 'Modification du compte ' . $v['identifiant'] . ($mdp !== '' ? ' (mot de passe réinitialisé)' : ''));
                    flash('succes', 'Le compte « ' . $v['identifiant'] . ' » a été modifié.');
                } else {
                    q('INSERT INTO comptes (identifiant, mot_de_passe_hache, role, actif, operateur_matricule, collaborateur_id) VALUES (?,?,?,1,?,?)', [$v['identifiant'], password_hash($mdp, PASSWORD_DEFAULT), $v['role'], $op, $co]);
                    journaliser('compte', 'Création du compte ' . $v['identifiant']);
                    flash('succes', 'Le compte « ' . $v['identifiant'] . ' » a été créé.');
                }
                rediriger(url('comptes'));
            }
        }
        page_debut($edition ? 'Modifier le compte ' . $v['identifiant'] : 'Créer un compte', 'comptes', 'Le mot de passe doit contenir au moins 10 caractères, une majuscule, une minuscule et un chiffre.');
        if ($err) echo '<div class="message message-erreur" role="alert">Corrigez les champs signalés avant d’enregistrer.</div>';
        echo '<form class="formulaire" method="post" action="' . e(url('comptes', ['a' => 'enregistrer'] + ($edition ? ['id' => $id] : []))) . '" novalidate>' . champ_csrf() . '<div class="grille-champs">';
        echo $edition
            ? champ_html('identifiant', ['label' => 'Identifiant', 'type' => 'texte'], $v['identifiant'], null, true)
            : champ_html('identifiant', ['label' => 'Identifiant', 'type' => 'texte', 'max' => 40, 'aide' => 'Ex. k.kouassi'], $v['identifiant'], $err['identifiant'] ?? null);
        echo champ_html('role', ['label' => 'Rôle', 'type' => 'select', 'source' => 'roles'], $v['role'], $err['role'] ?? null);
        echo champ_html('operateur_matricule', ['label' => 'Fiche opérateur liée', 'type' => 'select', 'source' => 'operateurs', 'requis' => false], $v['operateur_matricule'], $err['operateur_matricule'] ?? null);
        echo champ_html('collaborateur_id', ['label' => 'Responsable lié', 'type' => 'select', 'source' => 'collaborateurs', 'requis' => false], $v['collaborateur_id'], $err['collaborateur_id'] ?? null);
        echo '<div class="champ' . (isset($err['mot_de_passe']) ? ' en-erreur' : '') . '"><label for="f_mdp">' . ($edition ? 'Nouveau mot de passe <span class="facultatif">laisser vide pour ne pas changer</span>' : 'Mot de passe') . '</label>'
            . '<div class="mdp"><input type="password" id="f_mdp" name="mot_de_passe" autocomplete="new-password"' . ($edition ? '' : ' required') . '><button type="button" class="bouton-discret" data-voir-mdp="f_mdp">Afficher</button></div>'
            . (isset($err['mot_de_passe']) ? '<p class="erreur-champ">' . e($err['mot_de_passe']) . '</p>' : '') . '</div>';
        echo '</div><div class="actions-form"><button class="bouton" type="submit">' . ($edition ? 'Enregistrer les modifications' : 'Créer le compte') . '</button><a class="bouton-discret" href="' . e(url('comptes')) . '">Annuler</a></div></form>';
        page_fin();
        return;
    }

    // Liste
    $comptes = q_all('SELECT c.*, o.prenom op_prenom, o.nom op_nom, co.prenom co_prenom, co.nom co_nom FROM comptes c LEFT JOIN operateurs o ON o.matricule = c.operateur_matricule LEFT JOIN collaborateurs co ON co.id = c.collaborateur_id ORDER BY c.role, c.identifiant');
    page_debut('Comptes utilisateurs', 'comptes', 'Chaque compte reçoit un rôle qui détermine les modules accessibles.', bouton_lien(url('comptes', ['a' => 'nouveau']), 'Créer un compte', 'bouton', 'plus'));
    echo '<div class="tableau-cadre"><table class="tableau"><thead><tr><th scope="col">Identifiant</th><th scope="col">Rôle</th><th scope="col">Personne liée</th><th scope="col">État</th><th scope="col">Dernière connexion</th><th class="actions-col" scope="col"><span class="lecteur">Actions</span></th></tr></thead><tbody>';
    foreach ($comptes as $c) {
        $personne = $c['op_nom'] ? $c['op_prenom'] . ' ' . $c['op_nom'] : ($c['co_nom'] ? $c['co_prenom'] . ' ' . $c['co_nom'] : '');
        echo '<tr><td><span class="code">' . e($c['identifiant']) . '</span></td><td>' . e(ROLES[$c['role']] ?? $c['role']) . '</td><td>' . ($personne ? e($personne) : '<span class="attenue">Aucune</span>') . '</td>'
            . '<td>' . ((int)$c['actif'] ? etiquette('Actif', 'ok') : etiquette('Désactivé', 'neutre')) . '</td>'
            . '<td>' . ($c['derniere_connexion'] ? date_fr($c['derniere_connexion'], true) : '<span class="attenue">Jamais</span>') . '</td><td class="actions-col">'
            . '<a class="bouton-lien" href="' . e(url('comptes', ['a' => 'modifier', 'id' => $c['id']])) . '">Modifier</a>';
        if ((int)$c['id'] !== $moi) {
            echo '<form method="post" class="inline" action="' . e(url('comptes', ['a' => 'basculer', 'id' => $c['id']])) . '">' . champ_csrf() . '<button class="bouton-lien" type="submit">' . ((int)$c['actif'] ? 'Désactiver' : 'Réactiver') . '</button></form>';
            echo form_supprimer(url('comptes', ['a' => 'supprimer', 'id' => $c['id']]), 'Supprimer le compte « ' . $c['identifiant'] . ' » ?');
        }
        echo '</td></tr>';
    }
    echo '</tbody></table></div>';
    page_fin();
}

/* ==================================================================
 * Journal d'activité (traçabilité)
 * ================================================================== */
function journal(): void
{
    exiger('journal');
    $libs = [
        'connexion' => ['Connexion', 'ok'], 'connexion_echec' => ['Échec de connexion', 'alerte'], 'connexion_bloquee' => ['Connexion bloquée', 'danger'],
        'acces_refuse' => ['Accès refusé', 'danger'], 'deconnexion' => ['Déconnexion', 'neutre'], 'creation' => ['Création', 'neutre'],
        'modification' => ['Modification', 'neutre'], 'suppression' => ['Suppression', 'neutre'],
    ];
    $filtre = entree('type');
    $params = [];
    $where = '';
    if ($filtre === 'securite') {
        $where = " WHERE action IN ('connexion_echec','connexion_bloquee','acces_refuse')";
    }
    $lignes = q_all('SELECT * FROM journal' . $where . ' ORDER BY id DESC LIMIT 200', $params);
    page_debut('Journal d’activité', 'journal', 'Les 200 dernières actions enregistrées, avec l’adresse IP d’origine.');
    echo '<div class="onglets"><a href="' . e(url('journal')) . '"' . ($filtre !== 'securite' ? ' aria-current="page"' : '') . '>Toutes les actions</a><a href="' . e(url('journal', ['type' => 'securite'])) . '"' . ($filtre === 'securite' ? ' aria-current="page"' : '') . '>Alertes de sécurité</a></div>';
    echo mini_tableau(['Date', 'Utilisateur', 'Action', 'Détails', 'Adresse IP'], array_map(fn($j) => [
        '<span class="nombre">' . date_fr($j['date_action'], true) . '</span>', '<span class="code">' . e($j['identifiant']) . '</span>',
        etiquette(...($libs[$j['action']] ?? [ucfirst(str_replace('_', ' ', $j['action'])), 'neutre'])), e($j['details']), '<span class="nombre">' . e($j['ip']) . '</span>',
    ], $lignes), 'Aucune action enregistrée.');
    page_fin();
}

/* ==================================================================
 * Profil : changer son mot de passe
 * ================================================================== */
function profil(): void
{
    $u = utilisateur();
    $err = [];
    if (est_post()) {
        verifier_csrf();
        $actuel = (string)($_POST['actuel'] ?? '');
        $nouveau = (string)($_POST['nouveau'] ?? '');
        $confirm = (string)($_POST['confirmation'] ?? '');
        $c = q_one('SELECT mot_de_passe_hache FROM comptes WHERE id = ?', [$u['id']]);
        if (!$c || !password_verify($actuel, $c['mot_de_passe_hache'])) $err['actuel'] = 'Le mot de passe actuel est incorrect.';
        if ($m = mot_de_passe_faible($nouveau)) $err['nouveau'] = $m;
        elseif ($nouveau !== $confirm) $err['confirmation'] = 'Les deux saisies ne correspondent pas.';
        elseif ($nouveau === $actuel) $err['nouveau'] = 'Choisissez un mot de passe différent de l’actuel.';
        if (!$err) {
            q('UPDATE comptes SET mot_de_passe_hache = ? WHERE id = ?', [password_hash($nouveau, PASSWORD_DEFAULT), $u['id']]);
            session_regenerate_id(true);
            journaliser('mot_de_passe', 'Changement de mot de passe');
            flash('succes', 'Votre mot de passe a été changé.');
            rediriger(url('profil'));
        }
    }
    page_debut('Mon profil', 'profil');
    echo '<div class="colonnes"><section class="panneau"><h2>Mon compte</h2>' . fiche([
        'Nom' => e($u['nom_affiche']), 'Identifiant' => '<span class="code">' . e($u['identifiant']) . '</span>', 'Rôle' => e(ROLES[$u['role']]),
    ]) . '</section><section class="panneau panneau-large"><h2>Changer mon mot de passe</h2>';
    if ($err) echo '<div class="message message-erreur" role="alert">Le mot de passe n’a pas été changé. Corrigez les champs signalés.</div>';
    echo '<form class="formulaire sans-cadre" method="post" action="' . e(url('profil')) . '" novalidate>' . champ_csrf() . '<div class="grille-champs une-colonne">';
    foreach (['actuel' => 'Mot de passe actuel', 'nouveau' => 'Nouveau mot de passe', 'confirmation' => 'Confirmer le nouveau mot de passe'] as $k => $lib) {
        echo '<div class="champ' . (isset($err[$k]) ? ' en-erreur' : '') . '"><label for="p_' . $k . '">' . $lib . '</label><input type="password" id="p_' . $k . '" name="' . $k . '" autocomplete="' . ($k === 'actuel' ? 'current-password' : 'new-password') . '" required>'
            . (isset($err[$k]) ? '<p class="erreur-champ">' . e($err[$k]) . '</p>' : '') . '</div>';
    }
    echo '</div><p class="aide">Au moins 10 caractères, avec une majuscule, une minuscule et un chiffre.</p><div class="actions-form"><button class="bouton" type="submit">Changer le mot de passe</button></div></form></section></div>';
    page_fin();
}
