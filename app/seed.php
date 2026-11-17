<?php
/**
 * Smile — données de démonstration
 * Usine agroalimentaire fictive (huiles et boissons), zone industrielle de Yopougon.
 */

function comptes_demo(): array
{
    return [
        ['admin', 'Admin#Smile26', 'ADMINISTRATEUR', null, null],
        ['a.kone', 'Atelier#2026', 'RESPONSABLE_ATELIER', null, 1],
        ['p.nguessan', 'Equipes#2026', 'COLLABORATEUR_RESPONSABLE', null, 4],
        ['op.kouame', 'Machine#2026', 'OPERATEUR', 'OP-0104', null],
    ];
}

function inserer_donnees_demo(): void
{
    mt_srand(2026);
    $j = fn(int $n) => date('Y-m-d', strtotime("-$n days"));

    $coll = [
        [1, 'KONÉ', 'Aminata'], [2, 'YAO', 'Kouadio Serge'], [3, 'TRAORÉ', 'Ibrahim'],
        [4, 'N’GUESSAN', 'Akissi Prisca'], [5, 'BAMBA', 'Moussa'], [6, 'OUATTARA', 'Mariam'],
    ];
    foreach ($coll as $c) q('INSERT INTO collaborateurs (id, nom, prenom) VALUES (?,?,?)', $c);

    foreach ([[1, 'Préparation des matières', 1], [2, 'Embouteillage', 2], [3, 'Conditionnement et expédition', 3]] as $a) {
        q('INSERT INTO ateliers (numero, libelle, responsable_id) VALUES (?,?,?)', $a);
    }
    $lignes = [[101, 1, 4], [102, 1, 3], [201, 2, 5], [202, 2, 5], [203, 2, 4], [301, 3, 3], [302, 3, 2]];
    foreach ($lignes as $l) q('INSERT INTO lignes (numero, atelier_numero, nb_operateurs_necessaires) VALUES (?,?,?)', $l);

    foreach ([[1, 'Équipe du matin', 4], [2, 'Équipe de l’après-midi', 5], [3, 'Équipe de nuit', 6], [4, 'Équipe de maintenance', 3]] as $e) {
        q('INSERT INTO equipes (numero, libelle, responsable_id) VALUES (?,?,?)', $e);
    }

    $ops = [
        ['OP-0101', 'KOUASSI', 'Jean-Marc', 1], ['OP-0102', 'DIABATÉ', 'Fatou', 1], ['OP-0103', 'AKA', 'Emmanuel', 1],
        ['OP-0104', 'KOUAMÉ', 'Hervé', 4], ['OP-0105', 'SORO', 'Adama', 1], ['OP-0106', 'GBAGBO', 'Ruth', 2],
        ['OP-0107', 'KOFFI', 'Arnaud', 2], ['OP-0108', 'DOSSO', 'Salimata', 2], ['OP-0109', 'ASSI', 'Christelle', 2],
        ['OP-0110', 'COULIBALY', 'Seydou', 3], ['OP-0111', 'N’DRI', 'Rodrigue', 3], ['OP-0112', 'TOURÉ', 'Aïcha', 3],
        ['OP-0113', 'ADOU', 'Fabrice', 3], ['OP-0114', 'BROU', 'Inès', 2], ['OP-0115', 'KONAN', 'Didier', 4],
        ['OP-0116', 'SANGARÉ', 'Oumar', 4], ['OP-0117', 'ZADI', 'Josiane', 1], ['OP-0118', 'TANOH', 'Patrick', 3],
    ];
    $i = 0;
    foreach ($ops as [$m, $n, $p, $eq]) {
        q('INSERT INTO operateurs (matricule, nom, prenom, date_entree, equipe_numero) VALUES (?,?,?,?,?)', [$m, $n, $p, $j(200 + 173 * $i++), $eq]);
    }

    $produits = [
        ['PRD-HPR-1L', 'Huile de palme raffinée, bouteille 1 L', 1450, 4820],
        ['PRD-HPR-5L', 'Huile de palme raffinée, bidon 5 L', 6900, 1260],
        ['PRD-EAU-15', 'Eau minérale, bouteille 1,5 L', 300, 15400],
        ['PRD-BIS-1L', 'Jus de bissap, bouteille 1 L', 900, 2380],
        ['PRD-GIN-33', 'Jus de gingembre, bouteille 33 cl', 400, 6150],
        ['PRD-PAL-20', 'Huile de palmiste, fût 20 L', 26500, 14],
    ];
    foreach ($produits as $p) q('INSERT INTO produits (reference, designation, prix_vente, quantite_stock) VALUES (?,?,?,?)', $p);
    $prodParLigne = [101 => ['PRD-HPR-1L', 'PRD-PAL-20'], 102 => ['PRD-HPR-5L', 'PRD-PAL-20'], 201 => ['PRD-EAU-15'], 202 => ['PRD-BIS-1L', 'PRD-GIN-33'], 203 => ['PRD-GIN-33', 'PRD-BIS-1L'], 301 => ['PRD-HPR-1L', 'PRD-EAU-15'], 302 => ['PRD-HPR-5L', 'PRD-BIS-1L']];
    $opsEquipe = [1 => ['OP-0101', 'OP-0102', 'OP-0103', 'OP-0105', 'OP-0117'], 2 => ['OP-0106', 'OP-0107', 'OP-0108', 'OP-0109', 'OP-0114'], 3 => ['OP-0110', 'OP-0111', 'OP-0112', 'OP-0113', 'OP-0118']];

    // Lots sur les 60 derniers jours
    $lots = [];
    for ($k = 0; $k < 46; $k++) {
        $ligne = array_keys($prodParLigne)[mt_rand(0, 6)];
        $prod = $prodParLigne[$ligne][mt_rand(0, count($prodParLigne[$ligne]) - 1)];
        $jour = (int)round(60 - $k * 1.3);
        q('INSERT INTO lots (date_production, ligne_numero, produit_reference) VALUES (?,?,?)', [$j(max(0, $jour)), $ligne, $prod]);
        $id = (int)db()->lastInsertId();
        $lots[] = [$id, $ligne, max(0, $jour)];
        $equipe = $opsEquipe[mt_rand(1, 3)];
        shuffle($equipe);
        foreach (array_slice($equipe, 0, mt_rand(2, 4)) as $m) q('INSERT INTO lot_operateurs (lot_id, matricule) VALUES (?,?)', [$id, $m]);
    }

    foreach ([['PAN', 'Panne machine'], ['ELE', 'Coupure d’électricité'], ['MAT', 'Rupture de matière première'], ['QUA', 'Non-conformité qualité'], ['EMB', 'Défaut d’emballage']] as $t) {
        q('INSERT INTO types_incident (code, libelle) VALUES (?,?)', $t);
    }
    $types = ['PAN', 'PAN', 'PAN', 'ELE', 'ELE', 'MAT', 'QUA', 'EMB'];
    foreach ([2, 5, 7, 11, 14, 17, 20, 23, 27, 30, 33, 36, 39, 41, 43, 44] as $n) {
        [$lotId, , $jour] = $lots[$n];
        q('INSERT INTO incidents (lot_id, type_code, temps_arret_minutes, date_incident) VALUES (?,?,?,?)', [$lotId, $types[mt_rand(0, 7)], mt_rand(2, 30) * 5, $j($jour)]);
    }

    $machines = [
        [101, 'Broyeur à marteaux'], [101, 'Presse à vis'], [101, 'Filtre-presse'],
        [102, 'Décanteur centrifuge'], [102, 'Pompe de transfert'],
        [201, 'Souffleuse de préformes'], [201, 'Remplisseuse rotative'], [201, 'Boucheuse'],
        [202, 'Pasteurisateur à plaques'], [202, 'Remplisseuse à chaud'],
        [203, 'Remplisseuse volumétrique'], [203, 'Étiqueteuse'],
        [301, 'Fardeleuse'], [301, 'Convoyeur à rouleaux'],
        [302, 'Palettiseur'], [302, 'Filmeuse de palettes'],
    ];
    foreach ($machines as [$l, $lib]) q('INSERT INTO machines (libelle, ligne_numero) VALUES (?,?)', [$lib, $l]);

    $pieces = [
        ['PCE-ROUL-6204', 'Roulement à billes 6204', 4500, 36],
        ['PCE-COUR-A42', 'Courroie trapézoïdale A42', 7800, 12],
        ['PCE-JOINT-40', 'Joint torique 40 mm', 650, 140],
        ['PCE-BUSE-RMP', 'Buse de remplissage inox', 18500, 9],
        ['PCE-EV-24V', 'Électrovanne 24 V', 32000, 6],
        ['PCE-CAPT-NIV', 'Capteur de niveau capacitif', 54000, 4],
        ['PCE-MOT-1K5', 'Moteur électrique 1,5 kW', 185000, 3],
        ['PCE-FUS-10A', 'Fusible 10 A', 300, 220],
        ['PCE-TETE-RMP', 'Tête de remplissage complète', 96000, 2],
        ['PCE-LAME-BRY', 'Lame de broyeur', 22000, 0],
    ];
    foreach ($pieces as $p) q('INSERT INTO pieces (reference, libelle, prix_unitaire_ht, quantite_stock) VALUES (?,?,?,?)', $p);
    foreach ([['PCE-TETE-RMP', 'PCE-BUSE-RMP', 1], ['PCE-TETE-RMP', 'PCE-JOINT-40', 2], ['PCE-TETE-RMP', 'PCE-EV-24V', 1], ['PCE-MOT-1K5', 'PCE-ROUL-6204', 2]] as $c) {
        q('INSERT INTO compositions (compose_reference, composant_reference, quantite) VALUES (?,?,?)', $c);
    }

    $fours = [
        ['FRN-001', 'Ivoire Roulements et Transmissions', 'Zone industrielle de Vridi, rue du Canal, Abidjan', '+225 27 21 75 40 12'],
        ['FRN-002', 'Comptoir Technique du Plateau', 'Boulevard de la République, Plateau, Abidjan', '+225 27 20 33 18 90'],
        ['FRN-003', 'Lagune Électro Services', 'Rue des Carrossiers, Treichville, Abidjan', '+225 27 21 24 66 05'],
        ['FRN-004', 'Inox Process Afrique', 'Zone industrielle de Yopougon, voie 12, Abidjan', '+225 27 23 46 91 70'],
    ];
    foreach ($fours as $f) q('INSERT INTO fournisseurs (code, raison_sociale, adresse, telephone) VALUES (?,?,?,?)', $f);
    $offre = [
        'PCE-ROUL-6204' => ['FRN-001', 'FRN-002'], 'PCE-COUR-A42' => ['FRN-001'], 'PCE-JOINT-40' => ['FRN-002', 'FRN-004'],
        'PCE-BUSE-RMP' => ['FRN-004'], 'PCE-EV-24V' => ['FRN-003', 'FRN-004'], 'PCE-CAPT-NIV' => ['FRN-003'],
        'PCE-MOT-1K5' => ['FRN-002', 'FRN-003'], 'PCE-FUS-10A' => ['FRN-003'], 'PCE-TETE-RMP' => ['FRN-004'], 'PCE-LAME-BRY' => ['FRN-001'],
    ];
    $prixBase = array_column($pieces, 2, 0);
    foreach ($offre as $ref => $fs) {
        foreach ($fs as $f) {
            q('INSERT INTO piece_fournisseur (piece_reference, fournisseur_code) VALUES (?,?)', [$ref, $f]);
            foreach ([300, 170, 45] as $n) {
                $prix = round($prixBase[$ref] * (0.82 + mt_rand(0, 14) / 100) / 50) * 50;
                q('INSERT INTO achats (piece_reference, fournisseur_code, date_achat, prix_achat_unitaire) VALUES (?,?,?,?)', [$ref, $f, $j($n + mt_rand(0, 20)), $prix]);
            }
        }
    }

    // Maintenances avec interventions et pièces consommées
    $maint = [
        [2, 52, 95, ['OP-0104', 'OP-0115'], [['PCE-ROUL-6204', 2], ['PCE-COUR-A42', 1]]],
        [7, 44, 60, ['OP-0116'], [['PCE-BUSE-RMP', 1], ['PCE-JOINT-40', 4]]],
        [1, 38, 140, ['OP-0104', 'OP-0116'], [['PCE-LAME-BRY', 2]]],
        [12, 31, 45, ['OP-0115'], [['PCE-FUS-10A', 3]]],
        [10, 24, 120, ['OP-0104'], [['PCE-EV-24V', 1], ['PCE-JOINT-40', 2]]],
        [14, 19, 35, ['OP-0116'], [['PCE-ROUL-6204', 4]]],
        [9, 13, 75, ['OP-0115', 'OP-0104'], [['PCE-CAPT-NIV', 1]]],
        [6, 8, 50, ['OP-0104'], [['PCE-FUS-10A', 2], ['PCE-JOINT-40', 6]]],
        [15, 4, 90, ['OP-0116', 'OP-0115'], [['PCE-COUR-A42', 2]]],
        [3, 1, 40, ['OP-0104'], []],
    ];
    foreach ($maint as [$machine, $jour, $duree, $intervenants, $consos]) {
        q('INSERT INTO maintenances (machine_id, debut, duree_minutes) VALUES (?,?,?)', [$machine, $j($jour) . ' ' . sprintf('%02d:%02d:00', mt_rand(6, 17), [0, 15, 30, 45][mt_rand(0, 3)]), $duree]);
        $mid = (int)db()->lastInsertId();
        $ivIds = [];
        foreach ($intervenants as $m) {
            q('INSERT INTO interventions (maintenance_id, matricule) VALUES (?,?)', [$mid, $m]);
            $ivIds[] = (int)db()->lastInsertId();
        }
        foreach ($consos as $k => [$ref, $qte]) {
            q('INSERT INTO consommations (intervention_id, piece_reference, quantite) VALUES (?,?,?)', [$ivIds[$k % count($ivIds)], $ref, $qte]);
        }
    }

    foreach (comptes_demo() as [$id, $mdp, $role, $op, $coll]) {
        q('INSERT INTO comptes (identifiant, mot_de_passe_hache, role, actif, operateur_matricule, collaborateur_id) VALUES (?,?,?,1,?,?)', [$id, password_hash($mdp, PASSWORD_DEFAULT), $role, $op, $coll]);
    }
}
