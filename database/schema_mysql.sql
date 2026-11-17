-- Smile : gestion d'usine
-- Schéma MySQL / MariaDB, traduit du diagramme de classes "Gestion de l'usine"
-- Encodage : utf8mb4

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS journal, tentatives_connexion, comptes, compositions, achats,
  piece_fournisseur, consommations, interventions, maintenances, machines,
  incidents, types_incident, lot_operateurs, lots, produits, operateurs,
  equipes, lignes, ateliers, collaborateurs, pieces, fournisseurs;

SET FOREIGN_KEY_CHECKS = 1;

-- CollaborateurResponsable : responsables d'atelier et d'équipe
CREATE TABLE collaborateurs (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nom           VARCHAR(60)  NOT NULL,
  prenom        VARCHAR(80)  NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Atelier : un seul responsable
CREATE TABLE ateliers (
  numero          INT UNSIGNED NOT NULL,
  libelle         VARCHAR(100) NOT NULL,
  responsable_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (numero),
  CONSTRAINT fk_atelier_resp FOREIGN KEY (responsable_id) REFERENCES collaborateurs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- LigneProduction : appartient à un atelier
CREATE TABLE lignes (
  numero                    INT UNSIGNED NOT NULL,
  atelier_numero            INT UNSIGNED NOT NULL,
  nb_operateurs_necessaires INT UNSIGNED NOT NULL,
  PRIMARY KEY (numero),
  CONSTRAINT fk_ligne_atelier FOREIGN KEY (atelier_numero) REFERENCES ateliers(numero),
  CONSTRAINT ck_ligne_nb CHECK (nb_operateurs_necessaires > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Equipe : un seul collaborateur responsable
CREATE TABLE equipes (
  numero          INT UNSIGNED NOT NULL,
  libelle         VARCHAR(100) NOT NULL,
  responsable_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (numero),
  CONSTRAINT fk_equipe_resp FOREIGN KEY (responsable_id) REFERENCES collaborateurs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Operateur : appartient à exactement une équipe
CREATE TABLE operateurs (
  matricule     VARCHAR(20)  NOT NULL,
  nom           VARCHAR(60)  NOT NULL,
  prenom        VARCHAR(80)  NOT NULL,
  date_entree   DATE         NOT NULL,
  equipe_numero INT UNSIGNED NOT NULL,
  PRIMARY KEY (matricule),
  CONSTRAINT fk_operateur_equipe FOREIGN KEY (equipe_numero) REFERENCES equipes(numero)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Produit
CREATE TABLE produits (
  reference       VARCHAR(30)   NOT NULL,
  designation     VARCHAR(120)  NOT NULL,
  prix_vente      DECIMAL(12,2) NOT NULL DEFAULT 0,
  quantite_stock  INT UNSIGNED  NOT NULL DEFAULT 0,
  PRIMARY KEY (reference),
  CONSTRAINT ck_produit_prix CHECK (prix_vente >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- LotProduction : une ligne, un produit, au moins un opérateur
CREATE TABLE lots (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  date_production   DATE         NOT NULL,
  ligne_numero      INT UNSIGNED NOT NULL,
  produit_reference VARCHAR(30)  NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_lot_ligne   FOREIGN KEY (ligne_numero) REFERENCES lignes(numero),
  CONSTRAINT fk_lot_produit FOREIGN KEY (produit_reference) REFERENCES produits(reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Association "Oeuvre sur" : opérateurs ayant oeuvré sur un lot
CREATE TABLE lot_operateurs (
  lot_id     INT UNSIGNED NOT NULL,
  matricule  VARCHAR(20)  NOT NULL,
  PRIMARY KEY (lot_id, matricule),
  CONSTRAINT fk_lo_lot FOREIGN KEY (lot_id) REFERENCES lots(id) ON DELETE CASCADE,
  CONSTRAINT fk_lo_op  FOREIGN KEY (matricule) REFERENCES operateurs(matricule)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- TypeIncident
CREATE TABLE types_incident (
  code     VARCHAR(10)  NOT NULL,
  libelle  VARCHAR(100) NOT NULL,
  PRIMARY KEY (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- IncidentProduction : un lot, un type, un temps d'arrêt
CREATE TABLE incidents (
  numero              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  lot_id              INT UNSIGNED NOT NULL,
  type_code           VARCHAR(10)  NOT NULL,
  temps_arret_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  date_incident       DATE         NOT NULL,
  PRIMARY KEY (numero),
  CONSTRAINT fk_incident_lot  FOREIGN KEY (lot_id) REFERENCES lots(id),
  CONSTRAINT fk_incident_type FOREIGN KEY (type_code) REFERENCES types_incident(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Machine : appartient à une seule ligne
CREATE TABLE machines (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  libelle       VARCHAR(100) NOT NULL,
  ligne_numero  INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_machine_ligne FOREIGN KEY (ligne_numero) REFERENCES lignes(numero)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Maintenance : une machine, date/heure de début, durée
CREATE TABLE maintenances (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  machine_id     INT UNSIGNED NOT NULL,
  debut          DATETIME     NOT NULL,
  duree_minutes  INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  CONSTRAINT fk_maint_machine FOREIGN KEY (machine_id) REFERENCES machines(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Intervention : une par couple (maintenance, opérateur)
CREATE TABLE interventions (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  maintenance_id  INT UNSIGNED NOT NULL,
  matricule       VARCHAR(20)  NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_intervention (maintenance_id, matricule),
  CONSTRAINT fk_interv_maint FOREIGN KEY (maintenance_id) REFERENCES maintenances(id) ON DELETE CASCADE,
  CONSTRAINT fk_interv_op    FOREIGN KEY (matricule) REFERENCES operateurs(matricule)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Piece
CREATE TABLE pieces (
  reference         VARCHAR(30)   NOT NULL,
  libelle           VARCHAR(120)  NOT NULL,
  prix_unitaire_ht  DECIMAL(12,2) NOT NULL DEFAULT 0,
  quantite_stock    INT UNSIGNED  NOT NULL DEFAULT 0,
  PRIMARY KEY (reference),
  CONSTRAINT ck_piece_prix CHECK (prix_unitaire_ht >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ConsommationPiece : pièces notées par l'opérateur sur son intervention
CREATE TABLE consommations (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  intervention_id   INT UNSIGNED NOT NULL,
  piece_reference   VARCHAR(30)  NOT NULL,
  quantite          INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_conso_interv FOREIGN KEY (intervention_id) REFERENCES interventions(id) ON DELETE CASCADE,
  CONSTRAINT fk_conso_piece  FOREIGN KEY (piece_reference) REFERENCES pieces(reference),
  CONSTRAINT ck_conso_qte CHECK (quantite > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fournisseur
CREATE TABLE fournisseurs (
  code           VARCHAR(20)  NOT NULL,
  raison_sociale VARCHAR(120) NOT NULL,
  adresse        VARCHAR(200) NOT NULL,
  telephone      VARCHAR(30)  NOT NULL,
  PRIMARY KEY (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Association "Est proposée par"
CREATE TABLE piece_fournisseur (
  piece_reference  VARCHAR(30) NOT NULL,
  fournisseur_code VARCHAR(20) NOT NULL,
  PRIMARY KEY (piece_reference, fournisseur_code),
  CONSTRAINT fk_pf_piece FOREIGN KEY (piece_reference) REFERENCES pieces(reference),
  CONSTRAINT fk_pf_four  FOREIGN KEY (fournisseur_code) REFERENCES fournisseurs(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AchatPiece : historique daté des prix d'achat
CREATE TABLE achats (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  piece_reference      VARCHAR(30)   NOT NULL,
  fournisseur_code     VARCHAR(20)   NOT NULL,
  date_achat           DATE          NOT NULL,
  prix_achat_unitaire  DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_achat_piece FOREIGN KEY (piece_reference) REFERENCES pieces(reference),
  CONSTRAINT fk_achat_four  FOREIGN KEY (fournisseur_code) REFERENCES fournisseurs(code),
  CONSTRAINT ck_achat_prix CHECK (prix_achat_unitaire >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CompositionPiece : nomenclature (pièce composée / pièce composant)
CREATE TABLE compositions (
  compose_reference    VARCHAR(30)  NOT NULL,
  composant_reference  VARCHAR(30)  NOT NULL,
  quantite             INT UNSIGNED NOT NULL,
  PRIMARY KEY (compose_reference, composant_reference),
  CONSTRAINT fk_comp_compose   FOREIGN KEY (compose_reference) REFERENCES pieces(reference),
  CONSTRAINT fk_comp_composant FOREIGN KEY (composant_reference) REFERENCES pieces(reference),
  CONSTRAINT ck_comp_qte  CHECK (quantite > 0),
  CONSTRAINT ck_comp_self CHECK (compose_reference <> composant_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CompteUtilisateur : authentification et rôle
CREATE TABLE comptes (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  identifiant         VARCHAR(40)  NOT NULL,
  mot_de_passe_hache  VARCHAR(255) NOT NULL,
  role                VARCHAR(30)  NOT NULL,
  actif               TINYINT(1)   NOT NULL DEFAULT 1,
  operateur_matricule VARCHAR(20)  NULL,
  collaborateur_id    INT UNSIGNED NULL,
  derniere_connexion  DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_compte_identifiant (identifiant),
  CONSTRAINT fk_compte_op   FOREIGN KEY (operateur_matricule) REFERENCES operateurs(matricule) ON DELETE SET NULL,
  CONSTRAINT fk_compte_coll FOREIGN KEY (collaborateur_id) REFERENCES collaborateurs(id) ON DELETE SET NULL,
  CONSTRAINT ck_compte_role CHECK (role IN ('ADMINISTRATEUR','RESPONSABLE_ATELIER','COLLABORATEUR_RESPONSABLE','OPERATEUR'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sécurité : tentatives de connexion (anti force brute)
CREATE TABLE tentatives_connexion (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  identifiant  VARCHAR(40)  NOT NULL,
  ip           VARCHAR(45)  NOT NULL,
  date_tentative DATETIME   NOT NULL,
  PRIMARY KEY (id),
  KEY ix_tentative (identifiant, ip, date_tentative)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sécurité : journal des actions
CREATE TABLE journal (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  compte_id   INT UNSIGNED NULL,
  identifiant VARCHAR(40)  NOT NULL,
  action      VARCHAR(40)  NOT NULL,
  details     VARCHAR(255) NOT NULL,
  ip          VARCHAR(45)  NOT NULL,
  date_action DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY ix_journal_date (date_action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
