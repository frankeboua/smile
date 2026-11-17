<?php
/**
 * Smile — définition des modules (une entrée par classe du diagramme)
 */

function stock_html(int $q): string
{
    global $CONFIG;
    $n = '<span class="nombre">' . nombre($q) . '</span>';
    if ($q === 0) return '<span class="stock">' . $n . etiquette('Rupture', 'danger') . '</span>';
    if ($q < $CONFIG['app']['seuil_stock']) return '<span class="stock">' . $n . etiquette('Stock bas', 'alerte') . '</span>';
    return $n;
}

function modules(): array
{
    return [

    /* ---------------- Production ---------------- */
    'ateliers' => [
        'titre' => 'Ateliers', 'singulier' => 'atelier', 'pluriel' => 'ateliers', 'article' => 'l’atelier',
        'bouton_ajout' => 'Créer un atelier', 'table' => 'ateliers', 'pk' => 'numero', 'detail' => true,
        'intro' => 'Chaque atelier a un responsable et regroupe plusieurs lignes de fabrication.',
        'champs' => [
            'numero' => ['label' => 'Numéro d’atelier', 'type' => 'int', 'min' => 1, 'pk' => true],
            'libelle' => ['label' => 'Libellé', 'type' => 'texte', 'max' => 100, 'aide' => 'Ex. Conditionnement'],
            'responsable_id' => ['label' => 'Responsable', 'type' => 'select', 'source' => 'collaborateurs'],
        ],
        'select' => 'SELECT a.numero, a.libelle, c.nom AS resp_nom, c.prenom AS resp_prenom,
                     (SELECT COUNT(*) FROM lignes l WHERE l.atelier_numero = a.numero) AS nb_lignes
                     FROM ateliers a JOIN collaborateurs c ON c.id = a.responsable_id',
        'ordre' => 'a.numero', 'recherche' => ['a.libelle', 'c.nom'], 'placeholder' => 'Libellé ou responsable',
        'colonnes' => [
            ['numero', 'N°', fn($r) => e($r['numero']), 'num'],
            ['libelle', 'Libellé'],
            ['resp', 'Responsable', fn($r) => e($r['resp_prenom'] . ' ' . $r['resp_nom'])],
            ['nb_lignes', 'Lignes', fn($r) => '<span class="nombre">' . (int)$r['nb_lignes'] . '</span>', 'num'],
        ],
        'nom_ligne' => fn($r) => $r['libelle'],
        'dependances' => [['lignes', 'atelier_numero', 'ligne(s) de fabrication']],
    ],

    'lignes' => [
        'titre' => 'Lignes de fabrication', 'singulier' => 'ligne', 'pluriel' => 'lignes', 'article' => 'la ligne', 'feminin' => true,
        'bouton_ajout' => 'Ajouter une ligne', 'table' => 'lignes', 'pk' => 'numero', 'detail' => true,
        'intro' => 'Une ligne appartient à un atelier, demande un effectif d’opérateurs et regroupe des machines.',
        'champs' => [
            'numero' => ['label' => 'Numéro de ligne', 'type' => 'int', 'min' => 1, 'pk' => true],
            'atelier_numero' => ['label' => 'Atelier', 'type' => 'select', 'source' => 'ateliers'],
            'nb_operateurs_necessaires' => ['label' => 'Opérateurs nécessaires', 'type' => 'int', 'min' => 1, 'max' => 500],
        ],
        'select' => 'SELECT l.numero, l.nb_operateurs_necessaires, l.atelier_numero, a.libelle AS atelier,
                     (SELECT COUNT(*) FROM machines m WHERE m.ligne_numero = l.numero) AS nb_machines,
                     (SELECT COUNT(*) FROM lots lo WHERE lo.ligne_numero = l.numero) AS nb_lots
                     FROM lignes l JOIN ateliers a ON a.numero = l.atelier_numero',
        'ordre' => 'l.numero', 'recherche' => ['a.libelle'], 'placeholder' => 'Nom de l’atelier',
        'filtres' => ['atelier' => ['l.atelier_numero', 'ateliers', 'Atelier']],
        'colonnes' => [
            ['numero', 'Ligne', fn($r) => 'Ligne ' . e($r['numero'])],
            ['atelier', 'Atelier', fn($r) => e($r['atelier_numero'] . ' : ' . $r['atelier'])],
            ['nb', 'Opérateurs nécessaires', fn($r) => '<span class="nombre">' . (int)$r['nb_operateurs_necessaires'] . '</span>', 'num'],
            ['nb_machines', 'Machines', fn($r) => '<span class="nombre">' . (int)$r['nb_machines'] . '</span>', 'num'],
            ['nb_lots', 'Lots produits', fn($r) => '<span class="nombre">' . (int)$r['nb_lots'] . '</span>', 'num'],
        ],
        'nom_ligne' => fn($r) => 'Ligne ' . $r['numero'],
        'dependances' => [['machines', 'ligne_numero', 'machine(s)'], ['lots', 'ligne_numero', 'lot(s) de production']],
    ],

    'produits' => [
        'titre' => 'Produits', 'singulier' => 'produit', 'pluriel' => 'produits', 'article' => 'le produit',
        'bouton_ajout' => 'Enregistrer un produit', 'table' => 'produits', 'pk' => 'reference', 'detail' => true,
        'intro' => 'Produits fabriqués par l’usine, avec leur prix de vente et leur stock.',
        'champs' => [
            'reference' => ['label' => 'Référence', 'type' => 'code', 'max' => 30, 'pk' => true, 'aide' => 'Ex. PRD-HUI-1L'],
            'designation' => ['label' => 'Désignation', 'type' => 'texte', 'max' => 120, 'large' => true],
            'prix_vente' => ['label' => 'Prix de vente unitaire (F CFA)', 'type' => 'decimal'],
            'quantite_stock' => ['label' => 'Quantité en stock', 'type' => 'int', 'min' => 0],
        ],
        'select' => 'SELECT p.reference, p.designation, p.prix_vente, p.quantite_stock FROM produits p',
        'ordre' => 'p.designation', 'recherche' => ['p.reference', 'p.designation'], 'placeholder' => 'Référence ou désignation',
        'colonnes' => [
            ['reference', 'Référence', fn($r) => '<span class="code">' . e($r['reference']) . '</span>'],
            ['designation', 'Désignation'],
            ['prix', 'Prix de vente', fn($r) => '<span class="nombre">' . fcfa($r['prix_vente']) . '</span>', 'num'],
            ['stock', 'Stock', fn($r) => stock_html((int)$r['quantite_stock']), 'num'],
        ],
        'nom_ligne' => fn($r) => $r['designation'],
        'dependances' => [['lots', 'produit_reference', 'lot(s) de production']],
    ],

    'lots' => [
        'titre' => 'Lots de production', 'singulier' => 'lot', 'pluriel' => 'lots', 'article' => 'le lot',
        'bouton_ajout' => 'Enregistrer un lot', 'table' => 'lots', 'pk' => 'id', 'pk_auto' => true, 'detail' => true,
        'intro' => 'Un lot est produit sur une ligne, pour un produit, par un ou plusieurs opérateurs.',
        'champs' => [
            'id' => ['label' => 'N° de lot', 'type' => 'int', 'pk' => true],
            'date_production' => ['label' => 'Date de production', 'type' => 'date', 'pas_futur' => true],
            'ligne_numero' => ['label' => 'Ligne de production', 'type' => 'select', 'source' => 'lignes'],
            'produit_reference' => ['label' => 'Produit fabriqué', 'type' => 'select', 'source' => 'produits'],
        ],
        'select' => 'SELECT lo.id, lo.date_production, lo.ligne_numero, p.designation,
                     (SELECT COUNT(*) FROM lot_operateurs x WHERE x.lot_id = lo.id) AS nb_op,
                     (SELECT COUNT(*) FROM incidents i WHERE i.lot_id = lo.id) AS nb_inc
                     FROM lots lo JOIN produits p ON p.reference = lo.produit_reference',
        'ordre' => 'lo.date_production DESC, lo.id DESC', 'recherche' => ['p.designation'], 'placeholder' => 'Produit',
        'filtres' => ['ligne' => ['lo.ligne_numero', 'lignes', 'Ligne']],
        'colonnes' => [
            ['id', 'Lot', fn($r) => 'Lot ' . e($r['id'])],
            ['date', 'Date', fn($r) => date_fr($r['date_production'])],
            ['ligne', 'Ligne', fn($r) => 'Ligne ' . e($r['ligne_numero'])],
            ['designation', 'Produit'],
            ['nb_op', 'Opérateurs', fn($r) => '<span class="nombre">' . (int)$r['nb_op'] . '</span>', 'num'],
            ['nb_inc', 'Incidents', fn($r) => (int)$r['nb_inc'] ? etiquette((string)(int)$r['nb_inc'], 'alerte') : '<span class="attenue">0</span>', 'num'],
        ],
        'nom_ligne' => fn($r) => 'Lot ' . $r['id'],
        'dependances' => [['incidents', 'lot_id', 'incident(s)']],
        'apres_champs' => 'lots_champ_operateurs',
        'valider' => 'lots_valider',
        'apres_enregistrer' => 'lots_enregistrer_operateurs',
    ],

    /* ---------------- Personnel ---------------- */
    'operateurs' => [
        'titre' => 'Opérateurs', 'singulier' => 'opérateur', 'pluriel' => 'opérateurs', 'article' => 'l’opérateur',
        'bouton_ajout' => 'Ajouter un opérateur', 'table' => 'operateurs', 'pk' => 'matricule', 'detail' => true,
        'intro' => 'Chaque opérateur appartient à une seule équipe. Changer l’équipe d’un opérateur l’y affecte.',
        'champs' => [
            'matricule' => ['label' => 'Matricule', 'type' => 'code', 'max' => 20, 'pk' => true, 'aide' => 'Ex. OP-0412'],
            'nom' => ['label' => 'Nom', 'type' => 'texte', 'max' => 60],
            'prenom' => ['label' => 'Prénom', 'type' => 'texte', 'max' => 80],
            'date_entree' => ['label' => 'Date d’entrée dans la société', 'type' => 'date', 'pas_futur' => true],
            'equipe_numero' => ['label' => 'Équipe', 'type' => 'select', 'source' => 'equipes'],
        ],
        'select' => 'SELECT o.matricule, o.nom, o.prenom, o.date_entree, o.equipe_numero, eq.libelle AS equipe
                     FROM operateurs o JOIN equipes eq ON eq.numero = o.equipe_numero',
        'ordre' => 'o.nom, o.prenom', 'recherche' => ['o.matricule', 'o.nom', 'o.prenom'], 'placeholder' => 'Matricule, nom ou prénom',
        'filtres' => ['equipe' => ['o.equipe_numero', 'equipes', 'Équipe']],
        'colonnes' => [
            ['matricule', 'Matricule', fn($r) => '<span class="code">' . e($r['matricule']) . '</span>'],
            ['nom', 'Nom et prénom', fn($r) => e($r['nom'] . ' ' . $r['prenom'])],
            ['equipe', 'Équipe', fn($r) => e($r['equipe'])],
            ['date', 'Entrée', fn($r) => date_fr($r['date_entree'])],
        ],
        'nom_ligne' => fn($r) => $r['prenom'] . ' ' . $r['nom'],
        'dependances' => [['lot_operateurs', 'matricule', 'lot(s) de production'], ['interventions', 'matricule', 'intervention(s) de maintenance']],
    ],

    'equipes' => [
        'titre' => 'Équipes', 'singulier' => 'équipe', 'pluriel' => 'équipes', 'article' => 'l’équipe', 'feminin' => true,
        'bouton_ajout' => 'Ajouter une équipe', 'table' => 'equipes', 'pk' => 'numero', 'detail' => true,
        'intro' => 'Une équipe est encadrée par un seul collaborateur responsable.',
        'champs' => [
            'numero' => ['label' => 'Numéro d’équipe', 'type' => 'int', 'min' => 1, 'pk' => true],
            'libelle' => ['label' => 'Libellé', 'type' => 'texte', 'max' => 100, 'aide' => 'Ex. Équipe du matin'],
            'responsable_id' => ['label' => 'Collaborateur responsable', 'type' => 'select', 'source' => 'collaborateurs'],
        ],
        'select' => 'SELECT eq.numero, eq.libelle, c.nom AS resp_nom, c.prenom AS resp_prenom,
                     (SELECT COUNT(*) FROM operateurs o WHERE o.equipe_numero = eq.numero) AS nb_membres
                     FROM equipes eq JOIN collaborateurs c ON c.id = eq.responsable_id',
        'ordre' => 'eq.numero', 'recherche' => ['eq.libelle', 'c.nom'], 'placeholder' => 'Libellé ou responsable',
        'colonnes' => [
            ['numero', 'N°', fn($r) => e($r['numero']), 'num'],
            ['libelle', 'Libellé'],
            ['resp', 'Responsable', fn($r) => e($r['resp_prenom'] . ' ' . $r['resp_nom'])],
            ['nb', 'Membres', fn($r) => '<span class="nombre">' . (int)$r['nb_membres'] . '</span>', 'num'],
        ],
        'nom_ligne' => fn($r) => $r['libelle'],
        'dependances' => [['operateurs', 'equipe_numero', 'opérateur(s)']],
    ],

    /* ---------------- Incidents ---------------- */
    'incidents' => [
        'titre' => 'Incidents de production', 'singulier' => 'incident', 'pluriel' => 'incidents', 'article' => 'l’incident',
        'bouton_ajout' => 'Déclarer un incident', 'table' => 'incidents', 'pk' => 'numero', 'pk_auto' => true, 'detail' => true,
        'intro' => 'Historique des incidents : chacun concerne un seul lot et note le temps d’arrêt.',
        'champs' => [
            'numero' => ['label' => 'N° d’incident', 'type' => 'int', 'pk' => true],
            'date_incident' => ['label' => 'Date de l’incident', 'type' => 'date', 'pas_futur' => true],
            'lot_id' => ['label' => 'Lot concerné', 'type' => 'select', 'source' => 'lots', 'large' => true],
            'type_code' => ['label' => 'Type d’incident', 'type' => 'select', 'source' => 'types_incident'],
            'temps_arret_minutes' => ['label' => 'Temps d’arrêt (minutes)', 'type' => 'int', 'min' => 0, 'max' => 100000],
        ],
        'select' => 'SELECT i.numero, i.date_incident, i.temps_arret_minutes, i.lot_id, i.type_code, t.libelle AS type_libelle,
                     lo.ligne_numero, p.designation
                     FROM incidents i JOIN types_incident t ON t.code = i.type_code
                     JOIN lots lo ON lo.id = i.lot_id JOIN produits p ON p.reference = lo.produit_reference',
        'ordre' => 'i.date_incident DESC, i.numero DESC', 'recherche' => ['t.libelle', 'p.designation'], 'placeholder' => 'Type ou produit',
        'filtres' => ['type' => ['i.type_code', 'types_incident', 'Type'], 'ligne' => ['lo.ligne_numero', 'lignes', 'Ligne']],
        'colonnes' => [
            ['numero', 'N°', fn($r) => e($r['numero']), 'num'],
            ['date', 'Date', fn($r) => date_fr($r['date_incident'])],
            ['type', 'Type', fn($r) => e($r['type_libelle'])],
            ['lot', 'Lot', fn($r) => 'Lot ' . e($r['lot_id']) . ', ligne ' . e($r['ligne_numero'])],
            ['produit', 'Produit', fn($r) => e($r['designation'])],
            ['arret', 'Temps d’arrêt', fn($r) => '<span class="nombre">' . duree((int)$r['temps_arret_minutes']) . '</span>', 'num'],
        ],
        'nom_ligne' => fn($r) => 'n° ' . $r['numero'],
    ],

    'types_incident' => [
        'titre' => 'Types d’incident', 'singulier' => 'type', 'pluriel' => 'types', 'article' => 'le type d’incident',
        'bouton_ajout' => 'Ajouter un type', 'table' => 'types_incident', 'pk' => 'code',
        'intro' => 'Codes utilisés pour classer les incidents de production.',
        'champs' => [
            'code' => ['label' => 'Code', 'type' => 'code', 'max' => 10, 'pk' => true, 'aide' => 'Ex. PAN'],
            'libelle' => ['label' => 'Libellé', 'type' => 'texte', 'max' => 100],
        ],
        'select' => 'SELECT t.code, t.libelle, (SELECT COUNT(*) FROM incidents i WHERE i.type_code = t.code) AS nb FROM types_incident t',
        'ordre' => 't.libelle', 'recherche' => ['t.code', 't.libelle'],
        'colonnes' => [
            ['code', 'Code', fn($r) => '<span class="code">' . e($r['code']) . '</span>'],
            ['libelle', 'Libellé'],
            ['nb', 'Incidents', fn($r) => '<span class="nombre">' . (int)$r['nb'] . '</span>', 'num'],
        ],
        'nom_ligne' => fn($r) => $r['libelle'],
        'dependances' => [['incidents', 'type_code', 'incident(s)']],
    ],

    /* ---------------- Maintenance ---------------- */
    'machines' => [
        'titre' => 'Machines', 'singulier' => 'machine', 'pluriel' => 'machines', 'article' => 'la machine', 'feminin' => true,
        'bouton_ajout' => 'Ajouter une machine', 'table' => 'machines', 'pk' => 'id', 'pk_auto' => true, 'detail' => true,
        'intro' => 'Chaque machine est affectée à une seule ligne. Consultez son historique de maintenance.',
        'champs' => [
            'id' => ['label' => 'N° machine', 'type' => 'int', 'pk' => true],
            'libelle' => ['label' => 'Désignation', 'type' => 'texte', 'max' => 100, 'aide' => 'Ex. Remplisseuse rotative'],
            'ligne_numero' => ['label' => 'Ligne d’affectation', 'type' => 'select', 'source' => 'lignes'],
        ],
        'select' => 'SELECT m.id, m.libelle, m.ligne_numero,
                     (SELECT COUNT(*) FROM maintenances x WHERE x.machine_id = m.id) AS nb_maint,
                     (SELECT MAX(x.debut) FROM maintenances x WHERE x.machine_id = m.id) AS derniere
                     FROM machines m',
        'ordre' => 'm.ligne_numero, m.libelle', 'recherche' => ['m.libelle'], 'placeholder' => 'Désignation',
        'filtres' => ['ligne' => ['m.ligne_numero', 'lignes', 'Ligne']],
        'colonnes' => [
            ['id', 'N°', fn($r) => 'M' . str_pad((string)$r['id'], 3, '0', STR_PAD_LEFT)],
            ['libelle', 'Désignation'],
            ['ligne', 'Ligne', fn($r) => 'Ligne ' . e($r['ligne_numero'])],
            ['nb', 'Maintenances', fn($r) => '<span class="nombre">' . (int)$r['nb_maint'] . '</span>', 'num'],
            ['der', 'Dernière maintenance', fn($r) => $r['derniere'] ? date_fr($r['derniere']) : '<span class="attenue">Aucune</span>'],
        ],
        'nom_ligne' => fn($r) => $r['libelle'],
        'dependances' => [['maintenances', 'machine_id', 'maintenance(s)']],
    ],

    'maintenances' => [
        'titre' => 'Maintenances', 'singulier' => 'maintenance', 'pluriel' => 'maintenances', 'article' => 'la maintenance', 'feminin' => true,
        'bouton_ajout' => 'Déclarer une maintenance', 'table' => 'maintenances', 'pk' => 'id', 'pk_auto' => true, 'detail' => true,
        'intro' => 'Chaque maintenance porte sur une machine. Les opérateurs y notent leurs interventions et les pièces consommées.',
        'champs' => [
            'id' => ['label' => 'N°', 'type' => 'int', 'pk' => true],
            'machine_id' => ['label' => 'Machine', 'type' => 'select', 'source' => 'machines', 'large' => true],
            'debut' => ['label' => 'Date et heure de début', 'type' => 'datetime'],
            'duree_minutes' => ['label' => 'Durée (minutes)', 'type' => 'int', 'min' => 0, 'max' => 100000],
        ],
        'select' => 'SELECT mt.id, mt.debut, mt.duree_minutes, m.libelle AS machine, m.ligne_numero,
                     (SELECT COUNT(*) FROM interventions iv WHERE iv.maintenance_id = mt.id) AS nb_interv
                     FROM maintenances mt JOIN machines m ON m.id = mt.machine_id',
        'ordre' => 'mt.debut DESC', 'recherche' => ['m.libelle'], 'placeholder' => 'Machine',
        'filtres' => ['machine' => ['mt.machine_id', 'machines', 'Machine']],
        'colonnes' => [
            ['id', 'N°', fn($r) => e($r['id']), 'num'],
            ['debut', 'Début', fn($r) => date_fr($r['debut'], true)],
            ['machine', 'Machine', fn($r) => e($r['machine']) . ' <span class="attenue">ligne ' . e($r['ligne_numero']) . '</span>'],
            ['duree', 'Durée', fn($r) => '<span class="nombre">' . duree((int)$r['duree_minutes']) . '</span>', 'num'],
            ['nb', 'Intervenants', fn($r) => (int)$r['nb_interv'] ? '<span class="nombre">' . (int)$r['nb_interv'] . '</span>' : etiquette('À compléter', 'alerte'), 'num'],
        ],
        'nom_ligne' => fn($r) => 'n° ' . $r['id'],
        'valider' => 'maintenance_valider',
        'apres_enregistrer' => 'maintenance_auto_intervention',
    ],

    /* ---------------- Stock et achats ---------------- */
    'pieces' => [
        'titre' => 'Pièces', 'singulier' => 'pièce', 'pluriel' => 'pièces', 'article' => 'la pièce', 'feminin' => true,
        'bouton_ajout' => 'Ajouter une pièce', 'table' => 'pieces', 'pk' => 'reference', 'detail' => true,
        'intro' => 'Stock de pièces détachées, nomenclature et fournisseurs.',
        'champs' => [
            'reference' => ['label' => 'Référence', 'type' => 'code', 'max' => 30, 'pk' => true, 'aide' => 'Ex. PCE-ROUL-6204'],
            'libelle' => ['label' => 'Libellé', 'type' => 'texte', 'max' => 120, 'large' => true],
            'prix_unitaire_ht' => ['label' => 'Prix unitaire HT (F CFA)', 'type' => 'decimal'],
            'quantite_stock' => ['label' => 'Quantité en stock', 'type' => 'int', 'min' => 0],
        ],
        'select' => 'SELECT pc.reference, pc.libelle, pc.prix_unitaire_ht, pc.quantite_stock,
                     (SELECT COUNT(*) FROM piece_fournisseur pf WHERE pf.piece_reference = pc.reference) AS nb_four
                     FROM pieces pc',
        'ordre' => 'pc.libelle', 'recherche' => ['pc.reference', 'pc.libelle'], 'placeholder' => 'Référence ou libellé',
        'colonnes' => [
            ['reference', 'Référence', fn($r) => '<span class="code">' . e($r['reference']) . '</span>'],
            ['libelle', 'Libellé'],
            ['prix', 'Prix HT', fn($r) => '<span class="nombre">' . fcfa($r['prix_unitaire_ht']) . '</span>', 'num'],
            ['stock', 'Stock', fn($r) => stock_html((int)$r['quantite_stock']), 'num'],
            ['four', 'Fournisseurs', fn($r) => '<span class="nombre">' . (int)$r['nb_four'] . '</span>', 'num'],
        ],
        'nom_ligne' => fn($r) => $r['libelle'],
        'dependances' => [
            ['consommations', 'piece_reference', 'consommation(s) en maintenance'],
            ['achats', 'piece_reference', 'achat(s)'],
            ['piece_fournisseur', 'piece_reference', 'fournisseur(s)'],
            ['compositions', 'compose_reference', 'composant(s) de nomenclature'],
            ['compositions', 'composant_reference', 'pièce(s) composée(s)'],
        ],
    ],

    'achats' => [
        'titre' => 'Achats de pièces', 'singulier' => 'achat', 'pluriel' => 'achats', 'article' => 'l’achat',
        'bouton_ajout' => 'Noter un achat', 'table' => 'achats', 'pk' => 'id', 'pk_auto' => true,
        'intro' => 'Historique des prix et des dates d’achat de chaque pièce, par fournisseur.',
        'champs' => [
            'id' => ['label' => 'N°', 'type' => 'int', 'pk' => true],
            'piece_reference' => ['label' => 'Pièce', 'type' => 'select', 'source' => 'pieces'],
            'fournisseur_code' => ['label' => 'Fournisseur', 'type' => 'select', 'source' => 'fournisseurs'],
            'date_achat' => ['label' => 'Date d’achat', 'type' => 'date', 'pas_futur' => true],
            'prix_achat_unitaire' => ['label' => 'Prix d’achat unitaire (F CFA)', 'type' => 'decimal'],
        ],
        'select' => 'SELECT ac.id, ac.date_achat, ac.prix_achat_unitaire, ac.piece_reference, pc.libelle, f.raison_sociale
                     FROM achats ac JOIN pieces pc ON pc.reference = ac.piece_reference
                     JOIN fournisseurs f ON f.code = ac.fournisseur_code',
        'ordre' => 'ac.date_achat DESC, ac.id DESC', 'recherche' => ['pc.libelle', 'f.raison_sociale'], 'placeholder' => 'Pièce ou fournisseur',
        'filtres' => ['piece' => ['ac.piece_reference', 'pieces', 'Pièce'], 'fournisseur' => ['ac.fournisseur_code', 'fournisseurs', 'Fournisseur']],
        'colonnes' => [
            ['date', 'Date', fn($r) => date_fr($r['date_achat'])],
            ['piece', 'Pièce', fn($r) => e($r['libelle']) . ' <span class="code">' . e($r['piece_reference']) . '</span>'],
            ['four', 'Fournisseur', fn($r) => e($r['raison_sociale'])],
            ['prix', 'Prix unitaire', fn($r) => '<span class="nombre">' . fcfa($r['prix_achat_unitaire']) . '</span>', 'num'],
        ],
        'nom_ligne' => fn($r) => $r['libelle'] . ' du ' . date_fr($r['date_achat']),
        'valider' => 'achat_valider',
        'pas_de_modification' => true,
    ],

    'fournisseurs' => [
        'titre' => 'Fournisseurs', 'singulier' => 'fournisseur', 'pluriel' => 'fournisseurs', 'article' => 'le fournisseur',
        'bouton_ajout' => 'Ajouter un fournisseur', 'table' => 'fournisseurs', 'pk' => 'code', 'detail' => true,
        'intro' => 'Fournisseurs de pièces détachées et historique de leurs prix.',
        'champs' => [
            'code' => ['label' => 'Code fournisseur', 'type' => 'code', 'max' => 20, 'pk' => true, 'aide' => 'Ex. FRN-014'],
            'raison_sociale' => ['label' => 'Raison sociale', 'type' => 'texte', 'max' => 120, 'large' => true],
            'adresse' => ['label' => 'Adresse', 'type' => 'texte', 'max' => 200, 'large' => true],
            'telephone' => ['label' => 'Téléphone', 'type' => 'tel', 'aide' => 'Ex. +225 27 22 41 18 00'],
        ],
        'select' => 'SELECT f.code, f.raison_sociale, f.adresse, f.telephone,
                     (SELECT COUNT(*) FROM piece_fournisseur pf WHERE pf.fournisseur_code = f.code) AS nb_pieces
                     FROM fournisseurs f',
        'ordre' => 'f.raison_sociale', 'recherche' => ['f.code', 'f.raison_sociale', 'f.adresse'], 'placeholder' => 'Code, nom ou adresse',
        'colonnes' => [
            ['code', 'Code', fn($r) => '<span class="code">' . e($r['code']) . '</span>'],
            ['raison_sociale', 'Raison sociale'],
            ['adresse', 'Adresse'],
            ['telephone', 'Téléphone', fn($r) => '<span class="nombre">' . e($r['telephone']) . '</span>'],
            ['nb', 'Pièces', fn($r) => '<span class="nombre">' . (int)$r['nb_pieces'] . '</span>', 'num'],
        ],
        'nom_ligne' => fn($r) => $r['raison_sociale'],
        'dependances' => [['achats', 'fournisseur_code', 'achat(s)'], ['piece_fournisseur', 'fournisseur_code', 'pièce(s) proposée(s)']],
    ],

    /* ---------------- Administration ---------------- */
    'collaborateurs' => [
        'titre' => 'Responsables', 'singulier' => 'responsable', 'pluriel' => 'responsables', 'article' => 'le responsable',
        'bouton_ajout' => 'Ajouter un responsable', 'table' => 'collaborateurs', 'pk' => 'id', 'pk_auto' => true,
        'intro' => 'Collaborateurs responsables d’atelier ou d’équipe.',
        'champs' => [
            'id' => ['label' => 'N°', 'type' => 'int', 'pk' => true],
            'nom' => ['label' => 'Nom', 'type' => 'texte', 'max' => 60],
            'prenom' => ['label' => 'Prénom', 'type' => 'texte', 'max' => 80],
        ],
        'select' => 'SELECT c.id, c.nom, c.prenom,
                     (SELECT COUNT(*) FROM ateliers a WHERE a.responsable_id = c.id) AS nb_at,
                     (SELECT COUNT(*) FROM equipes q WHERE q.responsable_id = c.id) AS nb_eq
                     FROM collaborateurs c',
        'ordre' => 'c.nom, c.prenom', 'recherche' => ['c.nom', 'c.prenom'],
        'colonnes' => [
            ['nom', 'Nom et prénom', fn($r) => e($r['nom'] . ' ' . $r['prenom'])],
            ['at', 'Ateliers dirigés', fn($r) => '<span class="nombre">' . (int)$r['nb_at'] . '</span>', 'num'],
            ['eq', 'Équipes encadrées', fn($r) => '<span class="nombre">' . (int)$r['nb_eq'] . '</span>', 'num'],
        ],
        'nom_ligne' => fn($r) => $r['prenom'] . ' ' . $r['nom'],
        'dependances' => [['ateliers', 'responsable_id', 'atelier(s)'], ['equipes', 'responsable_id', 'équipe(s)']],
    ],
    ];
}
