<?php
/**
 * Smile — gabarit commun des pages connectées
 */

function menu(): array
{
    return [
        ['', [['tableau', 'Tableau de bord', 'grid']]],
        ['Production', [
            ['ateliers', 'Ateliers', 'factory'],
            ['lignes', 'Lignes de fabrication', 'line'],
            ['produits', 'Produits', 'box'],
            ['lots', 'Lots de production', 'layers'],
        ]],
        ['Personnel', [
            ['operateurs', 'Opérateurs', 'user'],
            ['equipes', 'Équipes', 'users'],
        ]],
        ['Incidents', [
            ['incidents', 'Incidents', 'alert'],
            ['types_incident', 'Types d’incident', 'tag'],
        ]],
        ['Maintenance', [
            ['machines', 'Machines', 'gear'],
            ['maintenances', 'Maintenances', 'wrench'],
        ]],
        ['Stock et achats', [
            ['pieces', 'Pièces', 'bolt'],
            ['achats', 'Achats de pièces', 'cart'],
            ['fournisseurs', 'Fournisseurs', 'truck'],
        ]],
        ['Administration', [
            ['collaborateurs', 'Responsables', 'badge'],
            ['comptes', 'Comptes utilisateurs', 'key'],
            ['journal', 'Journal d’activité', 'list'],
        ]],
    ];
}

function icone(string $nom): string
{
    $p = [
        'grid'    => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>',
        'factory' => '<path d="M3 21V10l5 3V10l5 3V10l5 3V4h3v17z"/>',
        'line'    => '<circle cx="6" cy="17" r="2"/><circle cx="18" cy="17" r="2"/><path d="M3 13h18M6 9h4v4H6zM14 9h4v4h-4z"/>',
        'box'     => '<path d="M21 8l-9-5-9 5 9 5 9-5zM3 8v8l9 5 9-5V8M12 13v8"/>',
        'layers'  => '<path d="M12 3l9 5-9 5-9-5 9-5zM3 13l9 5 9-5M3 17l9 5 9-5"/>',
        'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
        'users'   => '<circle cx="9" cy="8" r="3.5"/><path d="M2 20c0-3.5 3-5.5 7-5.5s7 2 7 5.5M16 4.5a3.5 3.5 0 010 7M18 14.5c2.5.6 4 2.5 4 5.5"/>',
        'alert'   => '<path d="M12 3l10 18H2zM12 10v5M12 18v.5"/>',
        'tag'     => '<path d="M3 12V3h9l9 9-9 9z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
        'gear'    => '<circle cx="12" cy="12" r="3.5"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1"/>',
        'wrench'  => '<path d="M14.5 6.5a4 4 0 005 5L13 18a2.1 2.1 0 01-3-3l6.5-6.5a4 4 0 01-2-2zM5 19l3-3"/>',
        'bolt'    => '<path d="M12 2l7 4v8l-7 4-7-4V6z"/><circle cx="12" cy="10" r="2.5"/>',
        'cart'    => '<path d="M3 4h2l2.5 11h11L21 7H6.5"/><circle cx="9" cy="19.5" r="1.5"/><circle cx="17" cy="19.5" r="1.5"/>',
        'truck'   => '<path d="M2 6h12v10H2zM14 10h4l3 3v3h-7z"/><circle cx="6" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        'badge'   => '<rect x="4" y="3" width="16" height="18" rx="2"/><circle cx="12" cy="10" r="3"/><path d="M8 17h8"/>',
        'key'     => '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M17 6l3 3M15 8l2 2"/>',
        'list'    => '<path d="M8 6h13M8 12h13M8 18h13M3 6h1M3 12h1M3 18h1"/>',
        'logout'  => '<path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h11"/>',
        'search'  => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
        'plus'    => '<path d="M12 5v14M5 12h14"/>',
    ][$nom] ?? '';
    return '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">' . $p . '</svg>';
}

function page_debut(string $titre, string $courant, string $intro = '', string $actions = ''): void
{
    global $CONFIG;
    $u = utilisateur();
    ?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titre) ?> · Smile</title>
<link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
<meta name="theme-color" content="#1E2A33">
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<a class="lien-evitement" href="#contenu">Aller au contenu principal</a>
<div class="app">
  <aside class="nav" id="nav">
    <a class="marque" href="<?= e(url('tableau')) ?>">
      <img src="assets/img/smile-mark.svg" alt="" width="34" height="34">
      <span><strong>Smile</strong><small>Gestion d’usine</small></span>
    </a>
    <nav aria-label="Navigation principale">
      <?php foreach (menu() as [$groupe, $items]):
          $visibles = array_filter($items, fn($i) => peut($i[0]));
          if (!$visibles) continue; ?>
        <?php if ($groupe): ?><p class="nav-groupe"><?= e($groupe) ?></p><?php endif; ?>
        <ul>
          <?php foreach ($visibles as [$cle, $libelle, $ico]): ?>
            <li><a href="<?= e(url($cle)) ?>"<?= $cle === $courant ? ' aria-current="page"' : '' ?>><?= icone($ico) ?><?= e($libelle) ?></a></li>
          <?php endforeach; ?>
        </ul>
      <?php endforeach; ?>
    </nav>
    <div class="nav-pied"><?= e($CONFIG['app']['usine']) ?></div>
  </aside>

  <div class="principal">
    <header class="barre">
      <button class="bouton-menu" type="button" data-bascule="nav" aria-controls="nav" aria-expanded="false" aria-label="Ouvrir le menu"><span></span></button>
      <div class="barre-espace"></div>
      <a class="moi" href="<?= e(url('profil')) ?>">
        <span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($u['nom_affiche'], 0, 1))) ?></span>
        <span><strong><?= e($u['nom_affiche']) ?></strong><small><?= e(ROLES[$u['role']]) ?></small></span>
      </a>
      <form method="post" action="<?= e(url('deconnexion')) ?>">
        <?= champ_csrf() ?>
        <button class="bouton-discret" type="submit" title="Se déconnecter"><?= icone('logout') ?><span class="masque-mobile">Se déconnecter</span></button>
      </form>
    </header>

    <main class="contenu" id="contenu" tabindex="-1">
      <div class="entete">
        <div>
          <h1><?= e($titre) ?></h1>
          <?php if ($intro): ?><p class="intro"><?= $intro ?></p><?php endif; ?>
        </div>
        <?php if ($actions): ?><div class="entete-actions"><?= $actions ?></div><?php endif; ?>
      </div>
      <?php foreach (lire_flashs() as [$type, $msg]): ?>
        <div class="message message-<?= e($type) ?>" role="<?= $type === 'erreur' ? 'alert' : 'status' ?>"><?= e($msg) ?></div>
      <?php endforeach; ?>
<?php
}

function page_fin(): void
{
    ?>
    </main>
  </div>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
<?php
}

function bouton_lien(string $href, string $texte, string $classe = 'bouton', string $ico = ''): string
{
    return '<a class="' . e($classe) . '" href="' . e($href) . '">' . ($ico ? icone($ico) : '') . e($texte) . '</a>';
}

/** Formulaire de suppression (POST + CSRF + confirmation) */
function form_supprimer(string $action, string $confirmation, string $texte = 'Supprimer'): string
{
    return '<form method="post" action="' . e($action) . '" class="inline" data-confirmer="' . e($confirmation) . '">'
        . champ_csrf() . '<button type="submit" class="bouton-lien danger">' . e($texte) . '</button></form>';
}

function etiquette(string $texte, string $ton = 'neutre'): string
{
    return '<span class="etiquette etiquette-' . e($ton) . '">' . e($texte) . '</span>';
}
