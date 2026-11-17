<?php
/**
 * Smile — règles métier et fiches détaillées
 */

/* ==================================================================
 * Lots : au moins un opérateur (association « Oeuvre sur »)
 * ================================================================== */
function lots_champ_operateurs(array $valeurs, array $erreurs, bool $edition): void
{
    $choisis = $_POST['operateurs'] ?? null;
    if ($choisis === null) {
        $choisis = $edition && isset($valeurs['id'])
            ? array_column(q_all('SELECT matricule FROM lot_operateurs WHERE lot_id = ?', [$valeurs['id']]), 'matricule')
            : [];
    }
    $choisis = is_array($choisis) ? $choisis : [];
    $parEquipe = [];
    foreach (q_all('SELECT o.matricule, o.nom, o.prenom, eq.libelle FROM operateurs o JOIN equipes eq ON eq.numero = o.equipe_numero ORDER BY eq.numero, o.nom') as $o) {
        $parEquipe[$o['libelle']][] = $o;
    }
    ?>
    <fieldset class="groupe-cases<?= isset($erreurs['operateurs']) ? ' en-erreur' : '' ?>">
      <legend>Opérateurs ayant œuvré sur ce lot</legend>
      <?php if (isset($erreurs['operateurs'])): ?><p class="erreur-champ"><?= e($erreurs['operateurs']) ?></p><?php endif; ?>
      <?php foreach ($parEquipe as $equipe => $ops): ?>
        <p class="sous-legende"><?= e($equipe) ?></p>
        <div class="cases">
        <?php foreach ($ops as $o): ?>
          <label class="case"><input type="checkbox" name="operateurs[]" value="<?= e($o['matricule']) ?>"<?= in_array($o['matricule'], $choisis, true) ? ' checked' : '' ?>>
            <span><?= e($o['prenom'] . ' ' . $o['nom']) ?> <small><?= e($o['matricule']) ?></small></span></label>
        <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </fieldset>
    <?php
}

function lots_valider(array $d, ?string $id): array
{
    $ops = entree_tableau('operateurs');
    if (!$ops) return ['operateurs' => 'Cochez au moins un opérateur : un lot est toujours produit par au moins un ouvrier.'];
    $existants = options('operateurs');
    foreach ($ops as $m) {
        if (!isset($existants[$m])) return ['operateurs' => 'Un des opérateurs cochés n’existe plus. Rechargez la page.'];
    }
    return [];
}

function lots_enregistrer_operateurs(string $lotId, bool $edition): void
{
    q('DELETE FROM lot_operateurs WHERE lot_id = ?', [$lotId]);
    foreach (array_unique(entree_tableau('operateurs')) as $m) {
        q('INSERT INTO lot_operateurs (lot_id, matricule) VALUES (?, ?)', [$lotId, $m]);
    }
}

/* ==================================================================
 * Maintenance : l'opérateur connecté est associé automatiquement
 * ================================================================== */
function maintenance_valider(array $d, ?string $id): array
{
    if (isset($d['debut']) && strtotime((string)$d['debut']) > time() + 3600) {
        return ['debut' => 'Une maintenance déclarée ne peut pas commencer plus d’une heure dans le futur.'];
    }
    return [];
}

function maintenance_auto_intervention(string $id, bool $edition): void
{
    $mat = utilisateur()['operateur_matricule'] ?? null;
    if (!$edition && $mat) {
        q('INSERT INTO interventions (maintenance_id, matricule) VALUES (?, ?)', [$id, $mat]);
    }
}

function achat_valider(array $d, ?string $id): array
{
    if (!empty($d['piece_reference']) && !empty($d['fournisseur_code'])) {
        $lie = q_val('SELECT COUNT(*) FROM piece_fournisseur WHERE piece_reference = ? AND fournisseur_code = ?', [$d['piece_reference'], $d['fournisseur_code']]);
        if (!$lie) return ['fournisseur_code' => 'Ce fournisseur ne propose pas encore cette pièce. Associez-les d’abord depuis la fiche de la pièce.'];
    }
    return [];
}

/* ==================================================================
 * Nomenclature : interdit les cycles (une pièce ne peut pas se contenir)
 * ================================================================== */
function cree_un_cycle(string $compose, string $composant): bool
{
    if ($compose === $composant) return true;
    // Le composant contient-il déjà, directement ou non, la pièce composée ?
    $aVisiter = [$composant];
    $vus = [];
    while ($aVisiter) {
        $courant = array_pop($aVisiter);
        if (isset($vus[$courant])) continue;
        $vus[$courant] = true;
        foreach (q_all('SELECT composant_reference FROM compositions WHERE compose_reference = ?', [$courant]) as $r) {
            if ($r['composant_reference'] === $compose) return true;
            $aVisiter[] = $r['composant_reference'];
        }
    }
    return false;
}

/* ==================================================================
 * Actions spécifiques (POST uniquement, CSRF vérifié par le routeur)
 * ================================================================== */
function action_metier(string $module, string $action, string $id): void
{
    switch ("$module/$action") {

        // ---- Équipes : affecter un opérateur (A6 : pas de doublon)
        case 'equipes/affecter':
            exiger('equipes', 'ecrire');
            $mat = entree('matricule');
            $op = q_one('SELECT matricule, prenom, nom, equipe_numero FROM operateurs WHERE matricule = ?', [$mat]);
            if (!q_one('SELECT numero FROM equipes WHERE numero = ?', [$id]) || !$op) {
                flash('erreur', 'Choisissez un opérateur dans la liste.');
            } elseif ((string)$op['equipe_numero'] === $id) {
                flash('info', $op['prenom'] . ' ' . $op['nom'] . ' fait déjà partie de cette équipe. Aucun changement.');
            } else {
                q('UPDATE operateurs SET equipe_numero = ? WHERE matricule = ?', [$id, $mat]);
                journaliser('affectation', "Opérateur $mat affecté à l’équipe $id");
                flash('succes', $op['prenom'] . ' ' . $op['nom'] . ' a été affecté à cette équipe.');
            }
            rediriger(url('equipes', ['a' => 'voir', 'id' => $id]));

        // ---- Maintenance : associer un opérateur (une intervention par opérateur)
        case 'maintenances/intervenant':
            exiger('maintenances', 'ecrire');
            $mat = entree('matricule');
            if (!isset(options('operateurs')[$mat]) || !q_one('SELECT id FROM maintenances WHERE id = ?', [$id])) {
                flash('erreur', 'Choisissez un opérateur dans la liste.');
            } elseif (q_val('SELECT COUNT(*) FROM interventions WHERE maintenance_id = ? AND matricule = ?', [$id, $mat])) {
                flash('info', 'Cet opérateur intervient déjà sur cette maintenance.');
            } else {
                q('INSERT INTO interventions (maintenance_id, matricule) VALUES (?, ?)', [$id, $mat]);
                journaliser('intervention', "Opérateur $mat associé à la maintenance $id");
                flash('succes', 'Opérateur associé à la maintenance.');
            }
            rediriger(url('maintenances', ['a' => 'voir', 'id' => $id]));

        // ---- Maintenance : noter une consommation de pièces (stock décrémenté)
        case 'maintenances/consommer':
            exiger('maintenances', 'ecrire');
            $interv = (int)entree('intervention_id');
            $ref = entree('piece_reference');
            $qte = (int)entree('quantite');
            $iv = q_one('SELECT id FROM interventions WHERE id = ? AND maintenance_id = ?', [$interv, $id]);
            $pc = q_one('SELECT reference, libelle, quantite_stock FROM pieces WHERE reference = ?', [$ref]);
            if (!$iv || !$pc) {
                flash('erreur', 'Choisissez l’intervention et la pièce consommée.');
            } elseif ($qte < 1 || $qte > 10000) {
                flash('erreur', 'La quantité consommée doit être un entier positif.');
            } elseif ($qte > (int)$pc['quantite_stock']) {
                flash('erreur', 'Stock insuffisant : il reste ' . (int)$pc['quantite_stock'] . ' « ' . $pc['libelle'] . ' » en magasin.');
            } else {
                $pdo = db();
                $pdo->beginTransaction();
                q('INSERT INTO consommations (intervention_id, piece_reference, quantite) VALUES (?, ?, ?)', [$interv, $ref, $qte]);
                q('UPDATE pieces SET quantite_stock = quantite_stock - ? WHERE reference = ? AND quantite_stock >= ?', [$qte, $ref, $qte]);
                $pdo->commit();
                journaliser('consommation', "$qte × $ref sur la maintenance $id");
                flash('succes', "$qte « {$pc['libelle']} » noté(s). Le stock a été mis à jour.");
            }
            rediriger(url('maintenances', ['a' => 'voir', 'id' => $id]));

        case 'maintenances/annuler_conso':
            exiger('maintenances', 'ecrire');
            $c = q_one('SELECT c.id, c.piece_reference, c.quantite FROM consommations c JOIN interventions iv ON iv.id = c.intervention_id WHERE c.id = ? AND iv.maintenance_id = ?', [(int)entree('conso'), $id]);
            if ($c) {
                $pdo = db();
                $pdo->beginTransaction();
                q('DELETE FROM consommations WHERE id = ?', [$c['id']]);
                q('UPDATE pieces SET quantite_stock = quantite_stock + ? WHERE reference = ?', [$c['quantite'], $c['piece_reference']]);
                $pdo->commit();
                journaliser('consommation_annulee', "Consommation {$c['id']} annulée, stock rétabli");
                flash('succes', 'Consommation annulée. Les pièces ont été remises en stock.');
            }
            rediriger(url('maintenances', ['a' => 'voir', 'id' => $id]));

        // ---- Pièces : mise à jour du stock (entrée ou inventaire)
        case 'pieces/stock':
            exiger('pieces', 'ecrire');
            $mode = entree('mode');
            $qte = entree('quantite');
            if (!preg_match('/^\d{1,7}$/', $qte) || !in_array($mode, ['entree', 'inventaire'], true)) {
                flash('erreur', 'Indiquez une quantité entière positive.');
            } elseif (q_one('SELECT reference FROM pieces WHERE reference = ?', [$id])) {
                if ($mode === 'entree') {
                    q('UPDATE pieces SET quantite_stock = quantite_stock + ? WHERE reference = ?', [(int)$qte, $id]);
                    flash('succes', "$qte pièce(s) ajoutée(s) au stock.");
                } else {
                    q('UPDATE pieces SET quantite_stock = ? WHERE reference = ?', [(int)$qte, $id]);
                    flash('succes', "Stock corrigé après inventaire : $qte pièce(s).");
                }
                journaliser('stock', "Pièce $id : $mode $qte");
            }
            rediriger(url('pieces', ['a' => 'voir', 'id' => $id]));

        // ---- Pièces : nomenclature
        case 'pieces/composant':
            exiger('pieces', 'ecrire');
            $comp = entree('composant');
            $qte = entree('quantite');
            if (!isset(options('pieces')[$comp]) || !preg_match('/^\d{1,5}$/', $qte) || (int)$qte < 1) {
                flash('erreur', 'Choisissez un composant et une quantité supérieure à zéro.');
            } elseif (cree_un_cycle($id, $comp)) {
                flash('erreur', 'Impossible : cette pièce entre déjà dans la composition du composant choisi. La nomenclature tournerait en boucle.');
            } elseif (q_val('SELECT COUNT(*) FROM compositions WHERE compose_reference = ? AND composant_reference = ?', [$id, $comp])) {
                q('UPDATE compositions SET quantite = ? WHERE compose_reference = ? AND composant_reference = ?', [(int)$qte, $id, $comp]);
                flash('succes', 'Quantité du composant mise à jour.');
            } else {
                q('INSERT INTO compositions (compose_reference, composant_reference, quantite) VALUES (?, ?, ?)', [$id, $comp, (int)$qte]);
                journaliser('nomenclature', "$comp ajouté à $id");
                flash('succes', 'Composant ajouté à la nomenclature.');
            }
            rediriger(url('pieces', ['a' => 'voir', 'id' => $id]));

        case 'pieces/retirer_composant':
            exiger('pieces', 'ecrire');
            q('DELETE FROM compositions WHERE compose_reference = ? AND composant_reference = ?', [$id, entree('composant')]);
            journaliser('nomenclature', entree('composant') . " retiré de $id");
            flash('succes', 'Composant retiré de la nomenclature.');
            rediriger(url('pieces', ['a' => 'voir', 'id' => $id]));

        // ---- Pièces / fournisseurs : association « Est proposée par »
        case 'pieces/associer':
        case 'fournisseurs/associer':
            $piece = $module === 'pieces' ? $id : entree('piece');
            $four = $module === 'fournisseurs' ? $id : entree('fournisseur');
            exiger($module === 'pieces' ? 'pieces' : 'fournisseurs', 'ecrire');
            if (!isset(options('pieces')[$piece]) || !isset(options('fournisseurs')[$four])) {
                flash('erreur', 'Choisissez un élément dans la liste.');
            } elseif (q_val('SELECT COUNT(*) FROM piece_fournisseur WHERE piece_reference = ? AND fournisseur_code = ?', [$piece, $four])) {
                flash('info', 'Ce fournisseur propose déjà cette pièce.');
            } else {
                q('INSERT INTO piece_fournisseur (piece_reference, fournisseur_code) VALUES (?, ?)', [$piece, $four]);
                journaliser('association', "Pièce $piece proposée par $four");
                flash('succes', 'Association enregistrée.');
            }
            rediriger(url($module, ['a' => 'voir', 'id' => $id]));

        case 'pieces/dissocier':
        case 'fournisseurs/dissocier':
            $piece = $module === 'pieces' ? $id : entree('piece');
            $four = $module === 'fournisseurs' ? $id : entree('fournisseur');
            exiger($module === 'pieces' ? 'pieces' : 'fournisseurs', 'ecrire');
            if (q_val('SELECT COUNT(*) FROM achats WHERE piece_reference = ? AND fournisseur_code = ?', [$piece, $four])) {
                flash('erreur', 'Impossible de retirer ce lien : des achats de cette pièce chez ce fournisseur sont dans l’historique.');
            } else {
                q('DELETE FROM piece_fournisseur WHERE piece_reference = ? AND fournisseur_code = ?', [$piece, $four]);
                journaliser('association', "Lien $piece / $four retiré");
                flash('succes', 'Association retirée.');
            }
            rediriger(url($module, ['a' => 'voir', 'id' => $id]));
    }
    http_response_code(404);
    flash('erreur', 'Action inconnue.');
    rediriger(url('tableau'));
}

/* ==================================================================
 * Fiches détaillées (consultation)
 * ================================================================== */
function bouton_modifier(string $cle, string $id): string
{
    return peut($cle, 'ecrire') ? bouton_lien(url($cle, ['a' => 'modifier', 'id' => $id]), 'Modifier', 'bouton-second') : '';
}

function bouton_suppr(string $cle, string $id, string $quoi): string
{
    return peut($cle, 'ecrire') ? form_supprimer(url($cle, ['a' => 'supprimer', 'id' => $id]), "Supprimer $quoi ? Cette action est définitive.", 'Supprimer') : '';
}

function retour(string $cle, string $texte): string
{
    return '<a class="retour" href="' . e(url($cle)) . '">' . e($texte) . '</a>';
}

function introuvable(string $cle, string $quoi): never
{
    flash('erreur', "$quoi n’existe plus. La liste a été actualisée.");
    rediriger(url($cle));
}

function select_html(string $nom, array $opts, string $placeholder = 'Choisir…'): string
{
    $seul = count($opts) === 1;
    $h = '<select name="' . e($nom) . '" required><option value="">' . e($placeholder) . '</option>';
    foreach ($opts as $k => $v) $h .= '<option value="' . e($k) . '"' . ($seul ? ' selected' : '') . '>' . e($v) . '</option>';
    return $h . '</select>';
}

function voir(string $cle, string $id): void
{
    exiger($cle);
    switch ($cle) {
        case 'ateliers':
            $a = q_one('SELECT a.*, c.prenom, c.nom FROM ateliers a JOIN collaborateurs c ON c.id = a.responsable_id WHERE a.numero = ?', [$id]) ?? introuvable($cle, 'Cet atelier');
            $lignes = q_all('SELECT l.numero, l.nb_operateurs_necessaires, (SELECT COUNT(*) FROM machines m WHERE m.ligne_numero = l.numero) nbm FROM lignes l WHERE l.atelier_numero = ? ORDER BY l.numero', [$id]);
            $actions = bouton_modifier($cle, $id) . bouton_suppr($cle, $id, 'l’atelier « ' . $a['libelle'] . ' »');
            page_debut('Atelier ' . $a['numero'] . ' : ' . $a['libelle'], $cle, retour($cle, 'Tous les ateliers'), $actions);
            echo '<div class="colonnes">';
            echo '<section class="panneau"><h2>Informations</h2>' . fiche([
                'Numéro' => e($a['numero']), 'Libellé' => e($a['libelle']), 'Responsable' => e($a['prenom'] . ' ' . $a['nom']),
                'Lignes de fabrication' => '<span class="nombre">' . count($lignes) . '</span>',
                'Effectif nécessaire' => '<span class="nombre">' . array_sum(array_column($lignes, 'nb_operateurs_necessaires')) . '</span> opérateurs',
            ]) . '</section>';
            echo '<section class="panneau panneau-large"><div class="panneau-tete"><h2>Lignes de fabrication</h2>'
                . (peut('lignes', 'ecrire') ? bouton_lien(url('lignes', ['a' => 'nouveau', 'atelier_numero' => $id]), 'Ajouter une ligne', 'bouton-second', 'plus') : '') . '</div>';
            echo mini_tableau(['Ligne', ['Opérateurs nécessaires', 'num'], ['Machines', 'num'], ''], array_map(fn($l) => [
                'Ligne ' . e($l['numero']), '<span class="nombre">' . (int)$l['nb_operateurs_necessaires'] . '</span>', '<span class="nombre">' . (int)$l['nbm'] . '</span>',
                '<a class="bouton-lien" href="' . e(url('lignes', ['a' => 'voir', 'id' => $l['numero']])) . '">Consulter</a>',
            ], $lignes), 'Cet atelier n’a pas encore de ligne. Ajoutez-en une pour commencer.');
            echo '</section></div>';
            break;

        case 'lignes':
            $l = q_one('SELECT l.*, a.libelle FROM lignes l JOIN ateliers a ON a.numero = l.atelier_numero WHERE l.numero = ?', [$id]) ?? introuvable($cle, 'Cette ligne');
            $machines = q_all('SELECT id, libelle FROM machines WHERE ligne_numero = ? ORDER BY libelle', [$id]);
            $lots = q_all('SELECT lo.id, lo.date_production, p.designation, (SELECT COUNT(*) FROM lot_operateurs x WHERE x.lot_id = lo.id) nb FROM lots lo JOIN produits p ON p.reference = lo.produit_reference WHERE lo.ligne_numero = ? ORDER BY lo.date_production DESC LIMIT 8', [$id]);
            $ouvriers = q_all('SELECT DISTINCT o.matricule, o.prenom, o.nom FROM lot_operateurs x JOIN lots lo ON lo.id = x.lot_id JOIN operateurs o ON o.matricule = x.matricule WHERE lo.ligne_numero = ? ORDER BY o.nom', [$id]);
            page_debut('Ligne ' . $l['numero'], $cle, 'Atelier ' . e($l['atelier_numero'] . ' : ' . $l['libelle']) . '. ' . retour($cle, 'Toutes les lignes'), bouton_modifier($cle, $id) . bouton_suppr($cle, $id, 'la ligne ' . $id));
            echo '<div class="colonnes"><section class="panneau"><h2>Informations</h2>' . fiche([
                'Atelier' => '<a href="' . e(url('ateliers', ['a' => 'voir', 'id' => $l['atelier_numero']])) . '">' . e($l['libelle']) . '</a>',
                'Opérateurs nécessaires' => '<span class="nombre">' . (int)$l['nb_operateurs_necessaires'] . '</span>',
                'Ouvriers ayant travaillé sur la ligne' => '<span class="nombre">' . count($ouvriers) . '</span>',
            ]) . '<h3>Machines</h3>' . ($machines ? '<ul class="liste-simple">' . implode('', array_map(fn($m) => '<li><a href="' . e(url('machines', ['a' => 'voir', 'id' => $m['id']])) . '">' . e($m['libelle']) . '</a></li>', $machines)) . '</ul>' : '<p class="vide-court">Aucune machine affectée.</p>') . '</section>';
            echo '<section class="panneau panneau-large"><h2>Derniers lots produits</h2>' . mini_tableau(['Lot', 'Date', 'Produit', ['Opérateurs', 'num']], array_map(fn($x) => [
                '<a href="' . e(url('lots', ['a' => 'voir', 'id' => $x['id']])) . '">Lot ' . e($x['id']) . '</a>', date_fr($x['date_production']), e($x['designation']), '<span class="nombre">' . (int)$x['nb'] . '</span>',
            ], $lots), 'Aucun lot produit sur cette ligne.');
            echo '<h3>Ouvriers de la ligne</h3>' . mini_tableau(['Matricule', 'Nom'], array_map(fn($o) => ['<span class="code">' . e($o['matricule']) . '</span>', '<a href="' . e(url('operateurs', ['a' => 'voir', 'id' => $o['matricule']])) . '">' . e($o['prenom'] . ' ' . $o['nom']) . '</a>'], $ouvriers), 'Aucun ouvrier n’a encore travaillé sur cette ligne.') . '</section></div>';
            break;

        case 'produits':
            $p = q_one('SELECT * FROM produits WHERE reference = ?', [$id]) ?? introuvable($cle, 'Ce produit');
            $lots = q_all('SELECT id, date_production, ligne_numero FROM lots WHERE produit_reference = ? ORDER BY date_production DESC LIMIT 10', [$id]);
            page_debut($p['designation'], $cle, retour($cle, 'Tous les produits'), bouton_modifier($cle, $id) . bouton_suppr($cle, $id, 'le produit « ' . $p['designation'] . ' »'));
            echo '<div class="colonnes"><section class="panneau"><h2>Fiche produit</h2>' . fiche([
                'Référence' => '<span class="code">' . e($p['reference']) . '</span>', 'Désignation' => e($p['designation']),
                'Prix de vente unitaire' => '<span class="nombre">' . fcfa($p['prix_vente']) . '</span>', 'Quantité en stock' => stock_html((int)$p['quantite_stock']),
                'Valeur du stock' => '<span class="nombre">' . fcfa($p['prix_vente'] * $p['quantite_stock']) . '</span>',
            ]) . '</section><section class="panneau panneau-large"><h2>Lots fabriqués</h2>' . mini_tableau(['Lot', 'Date', 'Ligne'], array_map(fn($x) => [
                '<a href="' . e(url('lots', ['a' => 'voir', 'id' => $x['id']])) . '">Lot ' . e($x['id']) . '</a>', date_fr($x['date_production']), 'Ligne ' . e($x['ligne_numero']),
            ], $lots), 'Ce produit n’a pas encore été fabriqué.') . '</section></div>';
            break;

        case 'lots':
            $lo = q_one('SELECT lo.*, p.designation, l.atelier_numero FROM lots lo JOIN produits p ON p.reference = lo.produit_reference JOIN lignes l ON l.numero = lo.ligne_numero WHERE lo.id = ?', [$id]) ?? introuvable($cle, 'Ce lot');
            $ops = q_all('SELECT o.matricule, o.prenom, o.nom, eq.libelle FROM lot_operateurs x JOIN operateurs o ON o.matricule = x.matricule JOIN equipes eq ON eq.numero = o.equipe_numero WHERE x.lot_id = ? ORDER BY o.nom', [$id]);
            $inc = q_all('SELECT i.numero, i.date_incident, i.temps_arret_minutes, t.libelle FROM incidents i JOIN types_incident t ON t.code = i.type_code WHERE i.lot_id = ? ORDER BY i.date_incident', [$id]);
            page_debut('Lot ' . $lo['id'], $cle, 'Produit le ' . date_fr($lo['date_production']) . '. ' . retour($cle, 'Tous les lots'), bouton_modifier($cle, $id) . bouton_suppr($cle, $id, 'le lot ' . $id));
            echo '<div class="colonnes"><section class="panneau"><h2>Informations</h2>' . fiche([
                'Date de production' => date_fr($lo['date_production']),
                'Ligne' => '<a href="' . e(url('lignes', ['a' => 'voir', 'id' => $lo['ligne_numero']])) . '">Ligne ' . e($lo['ligne_numero']) . '</a> <span class="attenue">atelier ' . e($lo['atelier_numero']) . '</span>',
                'Produit' => '<a href="' . e(url('produits', ['a' => 'voir', 'id' => $lo['produit_reference']])) . '">' . e($lo['designation']) . '</a>',
                'Temps d’arrêt cumulé' => '<span class="nombre">' . duree(array_sum(array_column($inc, 'temps_arret_minutes'))) . '</span>',
            ]) . '</section><section class="panneau panneau-large"><h2>Opérateurs ayant œuvré</h2>' . mini_tableau(['Matricule', 'Nom', 'Équipe'], array_map(fn($o) => [
                '<span class="code">' . e($o['matricule']) . '</span>', e($o['prenom'] . ' ' . $o['nom']), e($o['libelle']),
            ], $ops), 'Aucun opérateur.');
            echo '<div class="panneau-tete"><h3>Incidents sur ce lot</h3>' . (peut('incidents', 'ecrire') ? bouton_lien(url('incidents', ['a' => 'nouveau', 'lot_id' => $id]), 'Déclarer un incident', 'bouton-second', 'plus') : '') . '</div>';
            echo mini_tableau(['N°', 'Date', 'Type', ['Arrêt', 'num']], array_map(fn($i) => [
                '<a href="' . e(url('incidents', ['a' => 'voir', 'id' => $i['numero']])) . '">' . e($i['numero']) . '</a>', date_fr($i['date_incident']), e($i['libelle']), '<span class="nombre">' . duree((int)$i['temps_arret_minutes']) . '</span>',
            ], $inc), 'Aucun incident sur ce lot.') . '</section></div>';
            break;

        case 'operateurs':
            $o = q_one('SELECT o.*, eq.libelle FROM operateurs o JOIN equipes eq ON eq.numero = o.equipe_numero WHERE o.matricule = ?', [$id]) ?? introuvable($cle, 'Cet opérateur');
            $lots = q_all('SELECT lo.id, lo.date_production, lo.ligne_numero, p.designation FROM lot_operateurs x JOIN lots lo ON lo.id = x.lot_id JOIN produits p ON p.reference = lo.produit_reference WHERE x.matricule = ? ORDER BY lo.date_production DESC LIMIT 8', [$id]);
            $int = q_all('SELECT mt.id, mt.debut, m.libelle FROM interventions iv JOIN maintenances mt ON mt.id = iv.maintenance_id JOIN machines m ON m.id = mt.machine_id WHERE iv.matricule = ? ORDER BY mt.debut DESC LIMIT 8', [$id]);
            $anc = (new DateTime($o['date_entree']))->diff(new DateTime())->y;
            page_debut($o['prenom'] . ' ' . $o['nom'], $cle, 'Matricule ' . e($o['matricule']) . '. ' . retour($cle, 'Tous les opérateurs'), bouton_modifier($cle, $id) . bouton_suppr($cle, $id, 'l’opérateur ' . $o['prenom'] . ' ' . $o['nom']));
            echo '<div class="colonnes"><section class="panneau"><h2>Fiche opérateur</h2>' . fiche([
                'Matricule' => '<span class="code">' . e($o['matricule']) . '</span>', 'Nom' => e($o['nom']), 'Prénom' => e($o['prenom']),
                'Date d’entrée' => date_fr($o['date_entree']) . ' <span class="attenue">' . ($anc ? $anc . ' an' . ($anc > 1 ? 's' : '') . ' d’ancienneté' : 'moins d’un an') . '</span>',
                'Équipe' => '<a href="' . e(url('equipes', ['a' => 'voir', 'id' => $o['equipe_numero']])) . '">' . e($o['libelle']) . '</a>',
            ]) . '</section><section class="panneau panneau-large"><h2>Derniers lots</h2>' . mini_tableau(['Lot', 'Date', 'Ligne', 'Produit'], array_map(fn($x) => [
                'Lot ' . e($x['id']), date_fr($x['date_production']), 'Ligne ' . e($x['ligne_numero']), e($x['designation']),
            ], $lots), 'Aucun lot enregistré pour cet opérateur.') . '<h3>Interventions de maintenance</h3>' . mini_tableau(['N°', 'Début', 'Machine'], array_map(fn($x) => [
                e($x['id']), date_fr($x['debut'], true), e($x['libelle']),
            ], $int), 'Aucune intervention.') . '</section></div>';
            break;

        case 'equipes':
            $eq = q_one('SELECT eq.*, c.prenom, c.nom FROM equipes eq JOIN collaborateurs c ON c.id = eq.responsable_id WHERE eq.numero = ?', [$id]) ?? introuvable($cle, 'Cette équipe');
            $membres = q_all('SELECT matricule, prenom, nom, date_entree FROM operateurs WHERE equipe_numero = ? ORDER BY nom', [$id]);
            $autres = [];
            foreach (q_all('SELECT o.matricule, o.prenom, o.nom, eq.libelle FROM operateurs o JOIN equipes eq ON eq.numero = o.equipe_numero WHERE o.equipe_numero <> ? ORDER BY o.nom', [$id]) as $x) {
                $autres[$x['matricule']] = $x['nom'] . ' ' . $x['prenom'] . ' (actuellement ' . $x['libelle'] . ')';
            }
            page_debut('Équipe ' . $eq['numero'] . ' : ' . $eq['libelle'], $cle, retour($cle, 'Toutes les équipes'), bouton_modifier($cle, $id) . bouton_suppr($cle, $id, 'l’équipe « ' . $eq['libelle'] . ' »'));
            echo '<div class="colonnes"><section class="panneau"><h2>Informations</h2>' . fiche([
                'Numéro' => e($eq['numero']), 'Libellé' => e($eq['libelle']), 'Collaborateur responsable' => e($eq['prenom'] . ' ' . $eq['nom']),
                'Membres' => '<span class="nombre">' . count($membres) . '</span>',
            ]);
            if (peut('equipes', 'ecrire') && $autres) {
                echo '<h3>Affecter un opérateur</h3><form class="form-ligne" method="post" action="' . e(url('equipes', ['a' => 'affecter', 'id' => $id])) . '">' . champ_csrf()
                    . '<label class="lecteur" for="aff">Opérateur</label>' . str_replace('<select ', '<select id="aff" ', select_html('matricule', $autres, 'Choisir un opérateur…')) . '<button class="bouton" type="submit">Affecter</button></form>';
            }
            echo '</section><section class="panneau panneau-large"><h2>Membres de l’équipe</h2>' . mini_tableau(['Matricule', 'Nom', 'Entrée', ''], array_map(fn($m) => [
                '<span class="code">' . e($m['matricule']) . '</span>', e($m['prenom'] . ' ' . $m['nom']), date_fr($m['date_entree']),
                '<a class="bouton-lien" href="' . e(url('operateurs', ['a' => 'voir', 'id' => $m['matricule']])) . '">Consulter</a>',
            ], $membres), 'Cette équipe n’a pas encore de membre.') . '</section></div>';
            break;

        case 'incidents':
            $i = q_one('SELECT i.*, t.libelle, lo.date_production, lo.ligne_numero, p.designation FROM incidents i JOIN types_incident t ON t.code = i.type_code JOIN lots lo ON lo.id = i.lot_id JOIN produits p ON p.reference = lo.produit_reference WHERE i.numero = ?', [$id]) ?? introuvable($cle, 'Cet incident');
            page_debut('Incident n° ' . $i['numero'], $cle, retour($cle, 'Historique des incidents'), bouton_modifier($cle, $id) . bouton_suppr($cle, $id, 'l’incident n° ' . $id));
            echo '<section class="panneau etroit"><h2>Détails</h2>' . fiche([
                'Date' => date_fr($i['date_incident']), 'Type' => e($i['libelle']) . ' <span class="code">' . e($i['type_code']) . '</span>',
                'Temps d’arrêt' => '<span class="nombre">' . duree((int)$i['temps_arret_minutes']) . '</span>',
                'Lot concerné' => '<a href="' . e(url('lots', ['a' => 'voir', 'id' => $i['lot_id']])) . '">Lot ' . e($i['lot_id']) . '</a> du ' . date_fr($i['date_production']),
                'Produit' => e($i['designation']), 'Ligne' => 'Ligne ' . e($i['ligne_numero']),
            ]) . '</section>';
            break;

        case 'machines':
            $m = q_one('SELECT m.*, l.atelier_numero FROM machines m JOIN lignes l ON l.numero = m.ligne_numero WHERE m.id = ?', [$id]) ?? introuvable($cle, 'Cette machine');
            $hist = q_all('SELECT mt.id, mt.debut, mt.duree_minutes, (SELECT COUNT(*) FROM interventions iv WHERE iv.maintenance_id = mt.id) nb FROM maintenances mt WHERE mt.machine_id = ? ORDER BY mt.debut DESC', [$id]);
            page_debut($m['libelle'], $cle, 'Machine M' . str_pad((string)$m['id'], 3, '0', STR_PAD_LEFT) . ', ligne ' . e($m['ligne_numero']) . '. ' . retour($cle, 'Toutes les machines'), bouton_modifier($cle, $id) . bouton_suppr($cle, $id, 'la machine « ' . $m['libelle'] . ' »'));
            echo '<div class="colonnes"><section class="panneau"><h2>Informations</h2>' . fiche([
                'Désignation' => e($m['libelle']),
                'Ligne' => '<a href="' . e(url('lignes', ['a' => 'voir', 'id' => $m['ligne_numero']])) . '">Ligne ' . e($m['ligne_numero']) . '</a> <span class="attenue">atelier ' . e($m['atelier_numero']) . '</span>',
                'Maintenances' => '<span class="nombre">' . count($hist) . '</span>',
                'Temps total de maintenance' => '<span class="nombre">' . duree(array_sum(array_column($hist, 'duree_minutes'))) . '</span>',
            ]) . '</section><section class="panneau panneau-large"><div class="panneau-tete"><h2>Historique des maintenances</h2>'
                . (peut('maintenances', 'ecrire') ? bouton_lien(url('maintenances', ['a' => 'nouveau', 'machine_id' => $id]), 'Déclarer une maintenance', 'bouton-second', 'plus') : '') . '</div>'
                . mini_tableau(['N°', 'Début', ['Durée', 'num'], ['Intervenants', 'num'], ''], array_map(fn($h) => [
                    e($h['id']), date_fr($h['debut'], true), '<span class="nombre">' . duree((int)$h['duree_minutes']) . '</span>', '<span class="nombre">' . (int)$h['nb'] . '</span>',
                    '<a class="bouton-lien" href="' . e(url('maintenances', ['a' => 'voir', 'id' => $h['id']])) . '">Consulter</a>',
                ], $hist), 'Aucune maintenance sur cette machine.') . '</section></div>';
            break;

        case 'maintenances':
            $mt = q_one('SELECT mt.*, m.libelle, m.ligne_numero FROM maintenances mt JOIN machines m ON m.id = mt.machine_id WHERE mt.id = ?', [$id]) ?? introuvable($cle, 'Cette maintenance');
            $interv = q_all('SELECT iv.id, o.matricule, o.prenom, o.nom FROM interventions iv JOIN operateurs o ON o.matricule = iv.matricule WHERE iv.maintenance_id = ? ORDER BY o.nom', [$id]);
            $consos = q_all('SELECT c.id, c.quantite, pc.reference, pc.libelle, pc.prix_unitaire_ht, o.prenom, o.nom FROM consommations c JOIN interventions iv ON iv.id = c.intervention_id JOIN operateurs o ON o.matricule = iv.matricule JOIN pieces pc ON pc.reference = c.piece_reference WHERE iv.maintenance_id = ? ORDER BY c.id', [$id]);
            $ecrire = peut('maintenances', 'ecrire');
            $cout = array_sum(array_map(fn($c) => $c['quantite'] * $c['prix_unitaire_ht'], $consos));
            page_debut('Maintenance n° ' . $mt['id'], $cle, e($mt['libelle']) . ', ligne ' . e($mt['ligne_numero']) . '. ' . retour($cle, 'Toutes les maintenances'), bouton_modifier($cle, $id) . bouton_suppr($cle, $id, 'la maintenance n° ' . $id));
            echo '<div class="colonnes"><section class="panneau"><h2>Informations</h2>' . fiche([
                'Machine' => '<a href="' . e(url('machines', ['a' => 'voir', 'id' => $mt['machine_id']])) . '">' . e($mt['libelle']) . '</a>',
                'Début' => date_fr($mt['debut'], true), 'Durée' => '<span class="nombre">' . duree((int)$mt['duree_minutes']) . '</span>',
                'Fin prévue' => date_fr(date('Y-m-d H:i:s', strtotime($mt['debut']) + 60 * (int)$mt['duree_minutes']), true),
                'Coût des pièces (HT)' => '<span class="nombre">' . fcfa($cout) . '</span>',
            ]);
            if ($ecrire) {
                $dispo = array_diff_key(options('operateurs'), array_flip(array_column($interv, 'matricule')));
                if ($dispo) echo '<h3>Associer un opérateur</h3><form class="form-ligne" method="post" action="' . e(url('maintenances', ['a' => 'intervenant', 'id' => $id])) . '">' . champ_csrf() . select_html('matricule', $dispo, 'Choisir un opérateur…') . '<button class="bouton" type="submit">Associer</button></form>';
            }
            echo '</section><section class="panneau panneau-large"><h2>Intervenants</h2>' . mini_tableau(['Matricule', 'Opérateur'], array_map(fn($x) => ['<span class="code">' . e($x['matricule']) . '</span>', e($x['prenom'] . ' ' . $x['nom'])], $interv), 'Aucun opérateur associé. Associez au moins un intervenant.');
            echo '<h3>Pièces consommées</h3>' . mini_tableau(['Pièce', 'Noté par', ['Quantité', 'num'], ['Montant HT', 'num'], ''], array_map(fn($c) => [
                e($c['libelle']) . ' <span class="code">' . e($c['reference']) . '</span>', e($c['prenom'] . ' ' . $c['nom']), '<span class="nombre">' . (int)$c['quantite'] . '</span>',
                '<span class="nombre">' . fcfa($c['quantite'] * $c['prix_unitaire_ht']) . '</span>',
                $ecrire ? '<form method="post" class="inline" data-confirmer="Annuler cette consommation et remettre les pièces en stock ?" action="' . e(url('maintenances', ['a' => 'annuler_conso', 'id' => $id])) . '">' . champ_csrf() . '<input type="hidden" name="conso" value="' . (int)$c['id'] . '"><button class="bouton-lien danger" type="submit">Annuler</button></form>' : '',
            ], $consos), 'Aucune pièce consommée pour l’instant.');
            if ($ecrire && $interv) {
                $optsI = [];
                foreach ($interv as $x) $optsI[$x['id']] = $x['prenom'] . ' ' . $x['nom'];
                $optsP = [];
                foreach (q_all('SELECT reference, libelle, quantite_stock FROM pieces WHERE quantite_stock > 0 ORDER BY libelle') as $p) $optsP[$p['reference']] = $p['libelle'] . ' (' . $p['quantite_stock'] . ' en stock)';
                echo '<form class="form-ligne form-conso" method="post" action="' . e(url('maintenances', ['a' => 'consommer', 'id' => $id])) . '">' . champ_csrf()
                    . '<label><span>Intervenant</span>' . select_html('intervention_id', $optsI) . '</label>'
                    . '<label><span>Pièce</span>' . select_html('piece_reference', $optsP) . '</label>'
                    . '<label class="court"><span>Quantité</span><input type="number" name="quantite" min="1" value="1" required></label>'
                    . '<button class="bouton" type="submit">Noter la consommation</button></form>';
            }
            echo '</section></div>';
            break;

        case 'pieces':
            $p = q_one('SELECT * FROM pieces WHERE reference = ?', [$id]) ?? introuvable($cle, 'Cette pièce');
            $ecrire = peut('pieces', 'ecrire');
            $comps = q_all('SELECT c.composant_reference, c.quantite, pc.libelle FROM compositions c JOIN pieces pc ON pc.reference = c.composant_reference WHERE c.compose_reference = ? ORDER BY pc.libelle', [$id]);
            $dans = q_all('SELECT c.compose_reference, c.quantite, pc.libelle FROM compositions c JOIN pieces pc ON pc.reference = c.compose_reference WHERE c.composant_reference = ?', [$id]);
            $fours = q_all('SELECT f.code, f.raison_sociale, f.telephone, (SELECT ac.prix_achat_unitaire FROM achats ac WHERE ac.piece_reference = ? AND ac.fournisseur_code = f.code ORDER BY ac.date_achat DESC, ac.id DESC LIMIT 1) dernier_prix FROM piece_fournisseur pf JOIN fournisseurs f ON f.code = pf.fournisseur_code WHERE pf.piece_reference = ? ORDER BY f.raison_sociale', [$id, $id]);
            $achats = q_all('SELECT ac.date_achat, ac.prix_achat_unitaire, f.raison_sociale FROM achats ac JOIN fournisseurs f ON f.code = ac.fournisseur_code WHERE ac.piece_reference = ? ORDER BY ac.date_achat DESC LIMIT 10', [$id]);
            page_debut($p['libelle'], $cle, 'Référence ' . e($p['reference']) . '. ' . retour($cle, 'Toutes les pièces'), bouton_modifier($cle, $id) . bouton_suppr($cle, $id, 'la pièce « ' . $p['libelle'] . ' »'));
            echo '<div class="colonnes"><section class="panneau"><h2>Stock</h2>' . fiche([
                'Quantité en stock' => stock_html((int)$p['quantite_stock']), 'Prix unitaire HT' => '<span class="nombre">' . fcfa($p['prix_unitaire_ht']) . '</span>',
                'Valeur du stock' => '<span class="nombre">' . fcfa($p['prix_unitaire_ht'] * $p['quantite_stock']) . '</span>',
            ]);
            if ($ecrire) {
                echo '<h3>Mettre à jour le stock</h3><form class="form-ligne" method="post" action="' . e(url('pieces', ['a' => 'stock', 'id' => $id])) . '">' . champ_csrf()
                    . '<label><span>Opération</span><select name="mode"><option value="entree">Entrée en stock (+)</option><option value="inventaire">Correction d’inventaire (=)</option></select></label>'
                    . '<label class="court"><span>Quantité</span><input type="number" name="quantite" min="0" required></label><button class="bouton" type="submit">Valider</button></form>';
            }
            echo '<h3>Fournisseurs de la pièce</h3>' . mini_tableau(['Fournisseur', ['Dernier prix', 'num'], ''], array_map(fn($f) => [
                '<a href="' . e(url('fournisseurs', ['a' => 'voir', 'id' => $f['code']])) . '">' . e($f['raison_sociale']) . '</a>',
                $f['dernier_prix'] !== null ? '<span class="nombre">' . fcfa($f['dernier_prix']) . '</span>' : '<span class="attenue">Aucun achat</span>',
                $ecrire ? '<form method="post" class="inline" data-confirmer="Retirer ce fournisseur pour cette pièce ?" action="' . e(url('pieces', ['a' => 'dissocier', 'id' => $id])) . '">' . champ_csrf() . '<input type="hidden" name="fournisseur" value="' . e($f['code']) . '"><button class="bouton-lien danger" type="submit">Retirer</button></form>' : '',
            ], $fours), 'Aucun fournisseur associé.');
            if ($ecrire) {
                $dispoF = array_diff_key(options('fournisseurs'), array_flip(array_column($fours, 'code')));
                if ($dispoF) echo '<form class="form-ligne" method="post" action="' . e(url('pieces', ['a' => 'associer', 'id' => $id])) . '">' . champ_csrf() . select_html('fournisseur', $dispoF, 'Associer un fournisseur…') . '<button class="bouton-second" type="submit">Associer</button></form>';
            }
            echo '</section><section class="panneau panneau-large"><h2>Nomenclature</h2><p class="aide">Pièces qui entrent dans la composition de celle-ci.</p>' . mini_tableau(['Composant', ['Quantité', 'num'], ''], array_map(fn($c) => [
                '<a href="' . e(url('pieces', ['a' => 'voir', 'id' => $c['composant_reference']])) . '">' . e($c['libelle']) . '</a> <span class="code">' . e($c['composant_reference']) . '</span>', '<span class="nombre">' . (int)$c['quantite'] . '</span>',
                $ecrire ? '<form method="post" class="inline" data-confirmer="Retirer ce composant de la nomenclature ?" action="' . e(url('pieces', ['a' => 'retirer_composant', 'id' => $id])) . '">' . champ_csrf() . '<input type="hidden" name="composant" value="' . e($c['composant_reference']) . '"><button class="bouton-lien danger" type="submit">Retirer</button></form>' : '',
            ], $comps), 'Cette pièce n’a pas de composant.');
            if ($ecrire) {
                $dispoC = options('pieces');
                unset($dispoC[$id]);
                echo '<form class="form-ligne" method="post" action="' . e(url('pieces', ['a' => 'composant', 'id' => $id])) . '">' . champ_csrf()
                    . '<label><span>Composant</span>' . select_html('composant', $dispoC) . '</label><label class="court"><span>Quantité</span><input type="number" name="quantite" min="1" value="1" required></label><button class="bouton-second" type="submit">Ajouter ou mettre à jour</button></form>';
            }
            if ($dans) echo '<p class="aide">Entre dans la composition de : ' . implode(', ', array_map(fn($d) => '<a href="' . e(url('pieces', ['a' => 'voir', 'id' => $d['compose_reference']])) . '">' . e($d['libelle']) . '</a> (×' . (int)$d['quantite'] . ')', $dans)) . '.</p>';
            echo '<div class="panneau-tete"><h3>Derniers prix d’achat</h3>' . (peut('achats', 'ecrire') && $fours ? bouton_lien(url('achats', ['a' => 'nouveau', 'piece_reference' => $id]), 'Noter un achat', 'bouton-second', 'plus') : '') . '</div>'
                . mini_tableau(['Date', 'Fournisseur', ['Prix unitaire', 'num']], array_map(fn($a) => [date_fr($a['date_achat']), e($a['raison_sociale']), '<span class="nombre">' . fcfa($a['prix_achat_unitaire']) . '</span>'], $achats), 'Aucun achat enregistré.') . '</section></div>';
            break;

        case 'fournisseurs':
            $f = q_one('SELECT * FROM fournisseurs WHERE code = ?', [$id]) ?? introuvable($cle, 'Ce fournisseur');
            $ecrire = peut('fournisseurs', 'ecrire');
            $pieces = q_all('SELECT pc.reference, pc.libelle, pc.quantite_stock FROM piece_fournisseur pf JOIN pieces pc ON pc.reference = pf.piece_reference WHERE pf.fournisseur_code = ? ORDER BY pc.libelle', [$id]);
            $achats = q_all('SELECT ac.date_achat, ac.prix_achat_unitaire, pc.libelle, pc.reference FROM achats ac JOIN pieces pc ON pc.reference = ac.piece_reference WHERE ac.fournisseur_code = ? ORDER BY ac.date_achat DESC', [$id]);
            page_debut($f['raison_sociale'], $cle, 'Code ' . e($f['code']) . '. ' . retour($cle, 'Tous les fournisseurs'), bouton_modifier($cle, $id) . bouton_suppr($cle, $id, 'le fournisseur « ' . $f['raison_sociale'] . ' »'));
            echo '<div class="colonnes"><section class="panneau"><h2>Coordonnées</h2>' . fiche([
                'Raison sociale' => e($f['raison_sociale']), 'Adresse' => e($f['adresse']), 'Téléphone' => '<span class="nombre">' . e($f['telephone']) . '</span>',
                'Achats enregistrés' => '<span class="nombre">' . count($achats) . '</span>',
            ]) . '<h3>Pièces proposées</h3>' . mini_tableau(['Pièce', ''], array_map(fn($p) => [
                '<a href="' . e(url('pieces', ['a' => 'voir', 'id' => $p['reference']])) . '">' . e($p['libelle']) . '</a>',
                $ecrire ? '<form method="post" class="inline" data-confirmer="Retirer cette pièce de l’offre du fournisseur ?" action="' . e(url('fournisseurs', ['a' => 'dissocier', 'id' => $id])) . '">' . champ_csrf() . '<input type="hidden" name="piece" value="' . e($p['reference']) . '"><button class="bouton-lien danger" type="submit">Retirer</button></form>' : '',
            ], $pieces), 'Aucune pièce associée.');
            if ($ecrire) {
                $dispo = array_diff_key(options('pieces'), array_flip(array_column($pieces, 'reference')));
                if ($dispo) echo '<form class="form-ligne" method="post" action="' . e(url('fournisseurs', ['a' => 'associer', 'id' => $id])) . '">' . champ_csrf() . select_html('piece', $dispo, 'Associer une pièce…') . '<button class="bouton-second" type="submit">Associer</button></form>';
            }
            echo '</section><section class="panneau panneau-large"><h2>Historique des achats</h2>' . mini_tableau(['Date', 'Pièce', ['Prix unitaire', 'num']], array_map(fn($a) => [
                date_fr($a['date_achat']), e($a['libelle']) . ' <span class="code">' . e($a['reference']) . '</span>', '<span class="nombre">' . fcfa($a['prix_achat_unitaire']) . '</span>',
            ], $achats), 'Aucun achat chez ce fournisseur.') . '</section></div>';
            break;

        default:
            rediriger(url($cle));
    }
    page_fin();
}
