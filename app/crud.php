<?php
/**
 * Smile — moteur de gestion des entités (créer, consulter, modifier, supprimer)
 * Couvre les scénarios nominaux et alternatifs A2 (saisie invalide),
 * A3 (élément introuvable), A4 (suppression impossible) et A5 (annulation).
 */

/** Accord au féminin des messages */
function accord(array $m): string
{
    return !empty($m['feminin']) ? 'e' : '';
}

/** Listes de choix pour les champs liés (clés étrangères) */
function options(string $source): array
{
    static $cache = [];
    if (isset($cache[$source])) {
        return $cache[$source];
    }
    $o = [];
    switch ($source) {
        case 'collaborateurs':
            foreach (q_all('SELECT id, prenom, nom FROM collaborateurs ORDER BY nom, prenom') as $r) $o[$r['id']] = $r['nom'] . ' ' . $r['prenom'];
            break;
        case 'ateliers':
            foreach (q_all('SELECT numero, libelle FROM ateliers ORDER BY numero') as $r) $o[$r['numero']] = 'Atelier ' . $r['numero'] . ' : ' . $r['libelle'];
            break;
        case 'lignes':
            foreach (q_all('SELECT l.numero, a.libelle FROM lignes l JOIN ateliers a ON a.numero = l.atelier_numero ORDER BY l.numero') as $r) $o[$r['numero']] = 'Ligne ' . $r['numero'] . ' (' . $r['libelle'] . ')';
            break;
        case 'equipes':
            foreach (q_all('SELECT numero, libelle FROM equipes ORDER BY numero') as $r) $o[$r['numero']] = 'Équipe ' . $r['numero'] . ' : ' . $r['libelle'];
            break;
        case 'produits':
            foreach (q_all('SELECT reference, designation FROM produits ORDER BY designation') as $r) $o[$r['reference']] = $r['designation'] . ' (' . $r['reference'] . ')';
            break;
        case 'operateurs':
            foreach (q_all('SELECT matricule, nom, prenom FROM operateurs ORDER BY nom, prenom') as $r) $o[$r['matricule']] = $r['nom'] . ' ' . $r['prenom'] . ' (' . $r['matricule'] . ')';
            break;
        case 'types_incident':
            foreach (q_all('SELECT code, libelle FROM types_incident ORDER BY libelle') as $r) $o[$r['code']] = $r['libelle'] . ' (' . $r['code'] . ')';
            break;
        case 'lots':
            foreach (q_all('SELECT lo.id, lo.date_production, p.designation FROM lots lo JOIN produits p ON p.reference = lo.produit_reference ORDER BY lo.id DESC') as $r) $o[$r['id']] = 'Lot ' . $r['id'] . ' du ' . date_fr($r['date_production']) . ' : ' . $r['designation'];
            break;
        case 'machines':
            foreach (q_all('SELECT id, libelle, ligne_numero FROM machines ORDER BY ligne_numero, libelle') as $r) $o[$r['id']] = $r['libelle'] . ' (ligne ' . $r['ligne_numero'] . ')';
            break;
        case 'pieces':
            foreach (q_all('SELECT reference, libelle FROM pieces ORDER BY libelle') as $r) $o[$r['reference']] = $r['libelle'] . ' (' . $r['reference'] . ')';
            break;
        case 'fournisseurs':
            foreach (q_all('SELECT code, raison_sociale FROM fournisseurs ORDER BY raison_sociale') as $r) $o[$r['code']] = $r['raison_sociale'];
            break;
        case 'roles':
            $o = ROLES;
            break;
    }
    return $cache[$source] = $o;
}

/** Valide une valeur selon la définition du champ. Retourne [valeur normalisée, erreur] */
function valider_champ(array $f, string $v): array
{
    $label = $f['label'];
    $requis = $f['requis'] ?? true;
    if ($v === '') {
        return [null, $requis ? "Renseignez le champ « $label »." : null];
    }
    switch ($f['type']) {
        case 'int':
            if (!preg_match('/^\d{1,9}$/', $v)) return [$v, "« $label » doit être un nombre entier positif."];
            $n = (int)$v;
            if (isset($f['min']) && $n < $f['min']) return [$v, "« $label » doit être au moins égal à {$f['min']}."];
            if (isset($f['max']) && $n > $f['max']) return [$v, "« $label » ne peut pas dépasser {$f['max']}."];
            return [$n, null];
        case 'decimal':
            $v2 = str_replace([' ', ','], ['', '.'], $v);
            if (!is_numeric($v2) || (float)$v2 < 0) return [$v, "« $label » doit être un montant positif."];
            if ((float)$v2 > 999999999) return [$v, "« $label » est trop élevé."];
            return [round((float)$v2, 2), null];
        case 'date':
            $d = DateTime::createFromFormat('!Y-m-d', $v);
            if (!$d || $d->format('Y-m-d') !== $v) return [$v, "« $label » n’est pas une date valide."];
            if (!empty($f['pas_futur']) && $v > date('Y-m-d')) return [$v, "« $label » ne peut pas être dans le futur."];
            return [$v, null];
        case 'datetime':
            $d = DateTime::createFromFormat('!Y-m-d\TH:i', $v) ?: DateTime::createFromFormat('!Y-m-d H:i:s', $v);
            if (!$d) return [$v, "« $label » n’est pas une date et une heure valides."];
            return [$d->format('Y-m-d H:i:s'), null];
        case 'select':
            if (!array_key_exists($v, options($f['source']))) return [$v, "Choisissez une valeur dans la liste « $label »."];
            return [$v, null];
        case 'code':
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,' . (($f['max'] ?? 30) - 1) . '}$/', $v)) return [$v, "« $label » accepte seulement lettres, chiffres, tiret et point (sans espace)."];
            return [strtoupper($v), null];
        case 'tel':
            if (!preg_match('/^[0-9 +().-]{8,20}$/', $v)) return [$v, "« $label » doit être un numéro de téléphone valide."];
            return [$v, null];
        default: // texte
            $max = $f['max'] ?? 120;
            if (mb_strlen($v) > $max) return [$v, "« $label » ne peut pas dépasser $max caractères."];
            return [$v, null];
    }
}

function champ_html(string $nom, array $f, $valeur, ?string $erreur, bool $verrouille = false): string
{
    $id = 'f_' . $nom;
    $req = ($f['requis'] ?? true) ? ' required' : '';
    $attr = ' id="' . $id . '" name="' . e($nom) . '"' . $req . ($erreur ? ' aria-invalid="true" aria-describedby="' . $id . '_err"' : '');
    $h = '<div class="champ' . ($erreur ? ' en-erreur' : '') . (isset($f['large']) ? ' champ-large' : '') . '">';
    $h .= '<label for="' . $id . '">' . e($f['label']) . (($f['requis'] ?? true) ? '' : ' <span class="facultatif">facultatif</span>') . '</label>';
    if ($verrouille) {
        $h .= '<input type="text" id="' . $id . '" value="' . e($valeur) . '" disabled>';
    } elseif ($f['type'] === 'select') {
        $h .= '<select' . $attr . '><option value="">Choisir…</option>';
        foreach (options($f['source']) as $k => $lib) {
            $h .= '<option value="' . e($k) . '"' . ((string)$k === (string)$valeur ? ' selected' : '') . '>' . e($lib) . '</option>';
        }
        $h .= '</select>';
    } else {
        $type = ['int' => 'number', 'decimal' => 'text', 'date' => 'date', 'datetime' => 'datetime-local', 'tel' => 'tel'][$f['type']] ?? 'text';
        $extra = '';
        if ($f['type'] === 'int') $extra .= ' min="' . ($f['min'] ?? 0) . '" step="1"';
        if ($f['type'] === 'decimal') $extra .= ' inputmode="decimal"';
        if (isset($f['max']) && in_array($f['type'], ['text', 'code'], true)) $extra .= ' maxlength="' . (int)$f['max'] . '"';
        if ($f['type'] === 'datetime' && $valeur) $valeur = date('Y-m-d\TH:i', strtotime((string)$valeur));
        if (!empty($f['aide'])) $extra .= ' placeholder="' . e($f['aide']) . '"';
        $h .= '<input type="' . $type . '"' . $attr . $extra . ' value="' . e($valeur) . '">';
    }
    if ($erreur) $h .= '<p class="erreur-champ" id="' . $id . '_err">' . e($erreur) . '</p>';
    $h .= '</div>';
    return $h;
}

/* ------------------------------------------------------------------
 * Actions génériques
 * ------------------------------------------------------------------ */
function crud_liste(string $cle, array $m): void
{
    exiger($cle);
    $recherche = mb_substr(entree('q'), 0, 60);
    $pageNum = max(1, (int)entree('page', '1'));
    $parPage = 15;
    $where = [];
    $params = [];
    if ($recherche !== '' && !empty($m['recherche'])) {
        $or = [];
        foreach ($m['recherche'] as $col) {
            $or[] = "$col LIKE ?";
            $params[] = '%' . $recherche . '%';
        }
        $where[] = '(' . implode(' OR ', $or) . ')';
    }
    foreach ($m['filtres'] ?? [] as $nomFiltre => [$col, $src]) {
        $v = entree($nomFiltre);
        if ($v !== '' && array_key_exists($v, options($src))) {
            $where[] = "$col = ?";
            $params[] = $v;
        }
    }
    $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $total = (int)q_val('SELECT COUNT(*) FROM (' . $m['select'] . $sqlWhere . ') t', $params);
    $pages = max(1, (int)ceil($total / $parPage));
    $pageNum = min($pageNum, $pages);
    $lignes = q_all($m['select'] . $sqlWhere . ' ORDER BY ' . $m['ordre'] . ' LIMIT ' . $parPage . ' OFFSET ' . (($pageNum - 1) * $parPage), $params);

    $ecrire = peut($cle, 'ecrire');
    $actions = $ecrire && empty($m['pas_de_creation']) ? bouton_lien(url($cle, ['a' => 'nouveau']), $m['bouton_ajout'], 'bouton', 'plus') : '';
    page_debut($m['titre'], $cle, $m['intro'] ?? '', $actions);
    ?>
    <form class="outils" method="get" role="search">
      <input type="hidden" name="p" value="<?= e($cle) ?>">
      <?php if (!empty($m['recherche'])): ?>
      <label class="recherche"><?= icone('search') ?><span class="lecteur">Rechercher</span>
        <input type="search" name="q" value="<?= e($recherche) ?>" placeholder="<?= e($m['placeholder'] ?? 'Rechercher') ?>">
      </label>
      <?php endif; ?>
      <?php foreach ($m['filtres'] ?? [] as $nomFiltre => [$col, $src, $libFiltre]): ?>
        <label class="filtre"><span class="lecteur"><?= e($libFiltre) ?></span>
          <select name="<?= e($nomFiltre) ?>" data-auto-envoi>
            <option value=""><?= e($libFiltre) ?> : tous</option>
            <?php foreach (options($src) as $k => $lib): ?>
              <option value="<?= e($k) ?>"<?= (string)$k === entree($nomFiltre) ? ' selected' : '' ?>><?= e($lib) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      <?php endforeach; ?>
      <button class="bouton-second" type="submit">Filtrer</button>
      <span class="compte"><?= nombre($total) ?> <?= $total > 1 ? e($m['pluriel']) : e($m['singulier']) ?></span>
    </form>

    <?php if (!$lignes): ?>
      <div class="vide">
        <p><?= $recherche !== '' ? 'Aucun résultat pour « ' . e($recherche) . ' ».' : e($m['vide'] ?? 'Rien n’est encore enregistré ici.') ?></p>
        <?php if ($ecrire && $recherche === '' && empty($m['pas_de_creation'])) echo bouton_lien(url($cle, ['a' => 'nouveau']), $m['bouton_ajout'], 'bouton-second'); ?>
      </div>
    <?php else: ?>
    <div class="tableau-cadre">
      <table class="tableau">
        <thead><tr>
          <?php foreach ($m['colonnes'] as $c): ?><th class="<?= e($c[3] ?? '') ?>" scope="col"><?= e($c[1]) ?></th><?php endforeach; ?>
          <th class="actions-col" scope="col"><span class="lecteur">Actions</span></th>
        </tr></thead>
        <tbody>
        <?php foreach ($lignes as $r):
            $id = $r[$m['pk']]; ?>
          <tr>
            <?php foreach ($m['colonnes'] as $i => $c):
                $val = isset($c[2]) && $c[2] ? $c[2]($r) : e($r[$c[0]] ?? '');
                if ($i === 0 && !empty($m['detail'])) $val = '<a class="lien-fort" href="' . e(url($cle, ['a' => 'voir', 'id' => $id])) . '">' . $val . '</a>'; ?>
              <td class="<?= e($c[3] ?? '') ?>"><?= $val ?></td>
            <?php endforeach; ?>
            <td class="actions-col">
              <?php if (!empty($m['detail'])): ?><a class="bouton-lien" href="<?= e(url($cle, ['a' => 'voir', 'id' => $id])) ?>">Consulter</a><?php endif; ?>
              <?php if ($ecrire && empty($m['pas_de_modification'])): ?><a class="bouton-lien" href="<?= e(url($cle, ['a' => 'modifier', 'id' => $id])) ?>">Modifier</a><?php endif; ?>
              <?php if ($ecrire && empty($m['pas_de_suppression'])) echo form_supprimer(url($cle, ['a' => 'supprimer', 'id' => $id]), 'Supprimer ' . $m['article'] . ' « ' . ($m['nom_ligne'] ?? fn($r) => $r[$m['pk']])($r) . ' » ? Cette action est définitive.'); ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($pages > 1): ?>
      <nav class="pagination" aria-label="Pages">
        <?php for ($i = 1; $i <= $pages; $i++):
            $qs = array_merge($_GET, ['p' => $cle, 'page' => $i]); ?>
          <a href="index.php?<?= e(http_build_query($qs)) ?>"<?= $i === $pageNum ? ' aria-current="page"' : '' ?>><?= $i ?></a>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
    <?php endif;
    page_fin();
}

function crud_trouver(array $m, string $id): ?array
{
    return q_one('SELECT * FROM ' . $m['table'] . ' WHERE ' . $m['pk'] . ' = ?', [$id]);
}

function crud_formulaire(string $cle, array $m, ?string $id, array $valeurs = [], array $erreurs = []): void
{
    exiger($cle, 'ecrire');
    $edition = $id !== null;
    if ($edition && !$valeurs) {
        $valeurs = crud_trouver($m, $id);
        if (!$valeurs) {
            flash('erreur', ucfirst($m['article']) . ' demandé' . accord($m) . ' n’existe plus. La liste a été actualisée.');
            rediriger(url($cle));
        }
    }
    $titre = $edition ? 'Modifier ' . $m['article'] : $m['bouton_ajout'];
    page_debut($titre, $cle, $edition ? 'Les champs marqués comme facultatifs peuvent rester vides.' : 'Tous les champs sont obligatoires, sauf mention contraire.');
    ?>
    <?php if ($erreurs): ?>
      <div class="message message-erreur" role="alert">Corrigez <?= count($erreurs) > 1 ? 'les ' . count($erreurs) . ' champs signalés' : 'le champ signalé' ?> avant d’enregistrer.</div>
    <?php endif; ?>
    <form class="formulaire" method="post" action="<?= e(url($cle, ['a' => 'enregistrer'] + ($edition ? ['id' => $id] : []))) ?>" novalidate>
      <?= champ_csrf() ?>
      <div class="grille-champs">
      <?php foreach ($m['champs'] as $nom => $f):
          if ($edition && !empty($f['pk']) && empty($m['pk_auto'])) {
              echo champ_html($nom, $f, $valeurs[$nom] ?? '', null, true);
              continue;
          }
          if (!empty($f['pk']) && !empty($m['pk_auto'])) continue;
          echo champ_html($nom, $f, $valeurs[$nom] ?? ($f['defaut'] ?? ''), $erreurs[$nom] ?? null);
      endforeach; ?>
      </div>
      <?php if (isset($m['apres_champs'])) ($m['apres_champs'])($valeurs, $erreurs, $edition); ?>
      <div class="actions-form">
        <button class="bouton" type="submit"><?= $edition ? 'Enregistrer les modifications' : 'Enregistrer' ?></button>
        <a class="bouton-discret" href="<?= e(url($cle)) ?>">Annuler</a>
      </div>
    </form>
    <?php
    page_fin();
}

function crud_enregistrer(string $cle, array $m, ?string $id): void
{
    exiger($cle, 'ecrire');
    verifier_csrf();
    $edition = $id !== null;
    if ($edition && !crud_trouver($m, $id)) {
        flash('erreur', ucfirst($m['article']) . ' n’existe plus.');
        rediriger(url($cle));
    }
    $donnees = [];
    $erreurs = [];
    $saisie = [];
    foreach ($m['champs'] as $nom => $f) {
        if (!empty($f['pk']) && ($edition || !empty($m['pk_auto']))) continue;
        $brut = entree($nom);
        $saisie[$nom] = $brut;
        [$val, $err] = valider_champ($f, $brut);
        if ($err) $erreurs[$nom] = $err;
        $donnees[$nom] = $val;
    }
    // Unicité de l'identifiant saisi
    if (!$edition && empty($m['pk_auto']) && !isset($erreurs[$m['pk']]) && crud_trouver($m, (string)$donnees[$m['pk']])) {
        $erreurs[$m['pk']] = 'Cet identifiant est déjà utilisé. Choisissez-en un autre.';
    }
    if (isset($m['valider'])) {
        $erreurs += ($m['valider'])($donnees, $edition ? $id : null);
    }
    if ($erreurs) {
        if ($edition) $saisie[$m['pk']] = $id;
        crud_formulaire($cle, $m, $id, $saisie, $erreurs);
        return;
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($edition) {
            $sets = implode(', ', array_map(fn($c) => "$c = ?", array_keys($donnees)));
            q('UPDATE ' . $m['table'] . " SET $sets WHERE " . $m['pk'] . ' = ?', [...array_values($donnees), $id]);
            $nouvelId = $id;
        } else {
            $cols = implode(', ', array_keys($donnees));
            $marques = implode(', ', array_fill(0, count($donnees), '?'));
            q('INSERT INTO ' . $m['table'] . " ($cols) VALUES ($marques)", array_values($donnees));
            $nouvelId = !empty($m['pk_auto']) ? $pdo->lastInsertId() : (string)$donnees[$m['pk']];
        }
        if (isset($m['apres_enregistrer'])) ($m['apres_enregistrer'])($nouvelId, $edition);
        $pdo->commit();
    } catch (Throwable $t) {
        $pdo->rollBack();
        if ($t instanceof RuntimeException) {
            flash('erreur', $t->getMessage());
            crud_formulaire($cle, $m, $id, $saisie, ['_' => $t->getMessage()]);
            return;
        }
        throw $t;
    }
    journaliser($edition ? 'modification' : 'creation', $m['titre'] . ' : ' . $nouvelId);
    flash('succes', ucfirst($m['article']) . ($edition ? ' a été modifié' : ' a été enregistré') . accord($m) . '.');
    rediriger(!empty($m['detail']) ? url($cle, ['a' => 'voir', 'id' => $nouvelId]) : url($cle));
}

function crud_supprimer(string $cle, array $m, string $id): void
{
    exiger($cle, 'ecrire');
    verifier_csrf();
    if (!est_post()) rediriger(url($cle));
    if (!crud_trouver($m, $id)) {
        flash('erreur', ucfirst($m['article']) . ' n’existe plus. La liste a été actualisée.');
        rediriger(url($cle));
    }
    // A4 : suppression impossible si des éléments y sont rattachés
    $raisons = [];
    foreach ($m['dependances'] ?? [] as [$table, $col, $libelle]) {
        $n = (int)q_val("SELECT COUNT(*) FROM $table WHERE $col = ?", [$id]);
        if ($n > 0) $raisons[] = $n . ' ' . $libelle;
    }
    if ($raisons) {
        flash('erreur', 'Suppression impossible : ' . $m['article'] . ' est encore lié' . accord($m) . ' à ' . implode(', ', $raisons) . '. Retirez ces liens d’abord.');
        rediriger(url($cle, !empty($m['detail']) ? ['a' => 'voir', 'id' => $id] : []));
    }
    try {
        q('DELETE FROM ' . $m['table'] . ' WHERE ' . $m['pk'] . ' = ?', [$id]);
    } catch (PDOException $ex) {
        flash('erreur', 'Suppression impossible : cet élément est utilisé ailleurs dans l’application.');
        rediriger(url($cle));
    }
    journaliser('suppression', $m['titre'] . ' : ' . $id);
    flash('succes', ucfirst($m['article']) . ' a été supprimé' . accord($m) . '.');
    rediriger(url($cle));
}

/** Petit tableau en lecture seule utilisé dans les fiches détaillées */
function mini_tableau(array $entetes, array $lignes, string $vide): string
{
    if (!$lignes) return '<p class="vide-court">' . e($vide) . '</p>';
    $h = '<div class="tableau-cadre"><table class="tableau compact"><thead><tr>';
    foreach ($entetes as $en) {
        [$lib, $cls] = is_array($en) ? $en : [$en, ''];
        $h .= '<th scope="col" class="' . e($cls) . '">' . e($lib) . '</th>';
    }
    $h .= '</tr></thead><tbody>';
    foreach ($lignes as $l) {
        $h .= '<tr>';
        foreach ($l as $i => $cell) {
            $cls = is_array($entetes[$i] ?? null) ? $entetes[$i][1] : '';
            $h .= '<td class="' . e($cls) . '">' . $cell . '</td>';
        }
        $h .= '</tr>';
    }
    return $h . '</tbody></table></div>';
}

function fiche(array $paires): string
{
    $h = '<dl class="fiche">';
    foreach ($paires as $k => $v) $h .= '<div><dt>' . e($k) . '</dt><dd>' . $v . '</dd></div>';
    return $h . '</dl>';
}
