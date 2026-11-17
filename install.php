<?php
/**
 * Smile — installation de la base de données
 * À lancer une seule fois : http://localhost/smile/install.php
 * Le script se verrouille ensuite (fichier storage/install.lock).
 */
declare(strict_types=1);

require __DIR__ . '/app/core.php';
require __DIR__ . '/app/seed.php';

envoyer_entetes_securite();
demarrer_session();

$verrou = SMILE_ROOT . '/storage/install.lock';
$c = $CONFIG['db'];
$message = null;
$fait = false;

if (is_file($verrou)) {
    $message = ['info', 'Smile est déjà installé. Pour réinstaller, supprimez le fichier storage/install.lock.'];
} elseif (est_post()) {
    verifier_csrf_install();
    try {
        if ($c['driver'] === 'mysql') {
            $srv = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $c['host'], $c['port']), $c['user'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $srv->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $c['database']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $schema = file_get_contents(SMILE_ROOT . '/database/schema_mysql.sql');
        } else {
            if (is_file($c['sqlite'])) unlink($c['sqlite']);
            $schema = file_get_contents(SMILE_ROOT . '/database/schema_sqlite.sql');
        }
        $schema = preg_replace('/^--.*$/m', '', $schema);
        foreach (array_filter(array_map('trim', explode(';', $schema))) as $instr) {
            db()->exec($instr);
        }
        if (($_POST['demo'] ?? '') === '1') {
            db()->beginTransaction();
            inserer_donnees_demo();
            db()->commit();
        } else {
            $id = mb_strtolower(entree('identifiant'));
            $mdp = (string)($_POST['mot_de_passe'] ?? '');
            if (!preg_match('/^[a-z0-9._-]{3,40}$/', $id)) throw new RuntimeException('Identifiant administrateur invalide.');
            if ($err = mot_de_passe_faible($mdp)) throw new RuntimeException($err);
            q('INSERT INTO comptes (identifiant, mot_de_passe_hache, role, actif) VALUES (?,?,?,1)', [$id, password_hash($mdp, PASSWORD_DEFAULT), 'ADMINISTRATEUR']);
        }
        file_put_contents($verrou, 'Installé le ' . date('d/m/Y H:i'));
        $fait = true;
    } catch (Throwable $t) {
        if (db()->inTransaction()) db()->rollBack();
        $message = ['erreur', $t instanceof RuntimeException ? $t->getMessage() : 'Installation impossible : ' . $t->getMessage() . '. Vérifiez que MySQL est démarré dans XAMPP et les paramètres de config/config.php.'];
    }
}

function verifier_csrf_install(): void
{
    if (!hash_equals(jeton_csrf(), (string)($_POST['_csrf'] ?? ''))) {
        http_response_code(400);
        exit('Formulaire expiré. Rechargez la page.');
    }
}
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installation · Smile</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="page-install">
<main class="install">
  <div class="connexion-marque sombre"><img src="assets/img/smile-mark.svg" alt="" width="40" height="40"><span>Smile</span></div>
  <h1>Installation</h1>
  <?php if ($message): ?><div class="message message-<?= e($message[0]) ?>" role="alert"><?= e($message[1]) ?></div><?php endif; ?>

  <?php if ($fait): ?>
    <div class="message message-succes">La base de données est prête.</div>
    <?php if (($_POST['demo'] ?? '') === '1'): ?>
      <p>Comptes de démonstration, un par rôle :</p>
      <table class="tableau compact">
        <thead><tr><th>Identifiant</th><th>Mot de passe</th><th>Rôle</th></tr></thead>
        <tbody><?php foreach (comptes_demo() as [$id, $mdp, $role]): ?>
          <tr><td><span class="code"><?= e($id) ?></span></td><td><span class="code"><?= e($mdp) ?></span></td><td><?= e(ROLES[$role]) ?></td></tr>
        <?php endforeach; ?></tbody>
      </table>
      <p class="aide">Changez ces mots de passe avant toute utilisation réelle (menu Mon profil).</p>
    <?php endif; ?>
    <p><a class="bouton" href="index.php?p=connexion">Aller à la connexion</a></p>
  <?php elseif (!is_file($verrou)): ?>
    <p class="intro">Base cible : <strong><?= e($c['driver'] === 'mysql' ? 'MySQL, base « ' . $c['database'] . ' » sur ' . $c['host'] : 'SQLite (fichier local)') ?></strong>. Les tables existantes de Smile seront recréées.</p>
    <form method="post" class="formulaire">
      <input type="hidden" name="_csrf" value="<?= e(jeton_csrf()) ?>">
      <fieldset class="groupe-cases">
        <legend>Contenu de départ</legend>
        <label class="case"><input type="radio" name="demo" value="1" checked data-bascule-admin> <span>Données de démonstration <small>usine fictive avec ateliers, lots, incidents et stock, plus un compte par rôle</small></span></label>
        <label class="case"><input type="radio" name="demo" value="0" data-bascule-admin> <span>Base vide <small>seulement un compte administrateur</small></span></label>
      </fieldset>
      <div class="grille-champs" id="admin-vide">
        <div class="champ"><label for="i_id">Identifiant administrateur</label><input id="i_id" name="identifiant" value="admin"></div>
        <div class="champ"><label for="i_mdp">Mot de passe administrateur</label><input id="i_mdp" name="mot_de_passe" type="password" autocomplete="new-password"></div>
      </div>
      <div class="actions-form"><button class="bouton" type="submit">Installer Smile</button></div>
    </form>
  <?php else: ?>
    <p><a class="bouton" href="index.php?p=connexion">Aller à la connexion</a></p>
  <?php endif; ?>
</main>
<script src="assets/js/app.js"></script>
</body>
</html>
