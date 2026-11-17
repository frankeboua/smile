-- Smile : gestion d'usine
-- Schéma SQLite (tests et démonstration hors ligne), traduit du diagramme de classes "Gestion de l'usine"
-- Encodage : utf8mb4




-- CollaborateurResponsable : responsables d'atelier et d'équipe
CREATE TABLE collaborateurs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  nom           VARCHAR(60)  NOT NULL,
  prenom        VARCHAR(80)  NOT NULL
);

-- Atelier : un seul responsable
CREATE TABLE ateliers (
  numero          INT  NOT NULL,
  libelle         VARCHAR(100) NOT NULL,
  responsable_id  INT  NOT NULL,
  PRIMARY KEY (numero),
  CONSTRAINT fk_atelier_resp FOREIGN KEY (responsable_id) REFERENCES collaborateurs(id)
);

-- LigneProduction : appartient à un atelier
CREATE TABLE lignes (
  numero                    INT  NOT NULL,
  atelier_numero            INT  NOT NULL,
  nb_operateurs_necessaires INT  NOT NULL,
  PRIMARY KEY (numero),
  CONSTRAINT fk_ligne_atelier FOREIGN KEY (atelier_numero) REFERENCES ateliers(numero),
  CONSTRAINT ck_ligne_nb CHECK (nb_operateurs_necessaires > 0)
);

-- Equipe : un seul collaborateur responsable
CREATE TABLE equipes (
  numero          INT  NOT NULL,
  libelle         VARCHAR(100) NOT NULL,
  responsable_id  INT  NOT NULL,
  PRIMARY KEY (numero),
  CONSTRAINT fk_equipe_resp FOREIGN KEY (responsable_id) REFERENCES collaborateurs(id)
);

-- Operateur : appartient à exactement une équipe
CREATE TABLE operateurs (
  matricule     VARCHAR(20)  NOT NULL,
  nom           VARCHAR(60)  NOT NULL,
  prenom        VARCHAR(80)  NOT NULL,
  date_entree   TEXT         NOT NULL,
  equipe_numero INT  NOT NULL,
  PRIMARY KEY (matricule),
  CONSTRAINT fk_operateur_equipe FOREIGN KEY (equipe_numero) REFERENCES equipes(numero)
);

-- Produit
CREATE TABLE produits (
  reference       VARCHAR(30)   NOT NULL,
  designation     VARCHAR(120)  NOT NULL,
  prix_vente      NUMERIC NOT NULL DEFAULT 0,
  quantite_stock  INT   NOT NULL DEFAULT 0,
  PRIMARY KEY (reference),
  CONSTRAINT ck_produit_prix CHECK (prix_vente >= 0)
);

-- LotProduction : une ligne, un produit, au moins un opérateur
CREATE TABLE lots (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  date_production   TEXT         NOT NULL,
  ligne_numero      INT  NOT NULL,
  produit_reference VARCHAR(30)  NOT NULL,
  CONSTRAINT fk_lot_ligne   FOREIGN KEY (ligne_numero) REFERENCES lignes(numero),
  CONSTRAINT fk_lot_produit FOREIGN KEY (produit_reference) REFERENCES produits(reference)
);

-- Association "Oeuvre sur" : opérateurs ayant oeuvré sur un lot
CREATE TABLE lot_operateurs (
  lot_id     INT  NOT NULL,
  matricule  VARCHAR(20)  NOT NULL,
  PRIMARY KEY (lot_id, matricule),
  CONSTRAINT fk_lo_lot FOREIGN KEY (lot_id) REFERENCES lots(id) ON DELETE CASCADE,
  CONSTRAINT fk_lo_op  FOREIGN KEY (matricule) REFERENCES operateurs(matricule)
);

-- TypeIncident
CREATE TABLE types_incident (
  code     VARCHAR(10)  NOT NULL,
  libelle  VARCHAR(100) NOT NULL,
  PRIMARY KEY (code)
);

-- IncidentProduction : un lot, un type, un temps d'arrêt
CREATE TABLE incidents (
  numero INTEGER PRIMARY KEY AUTOINCREMENT,
  lot_id              INT  NOT NULL,
  type_code           VARCHAR(10)  NOT NULL,
  temps_arret_minutes INT  NOT NULL DEFAULT 0,
  date_incident       TEXT         NOT NULL,
  CONSTRAINT fk_incident_lot  FOREIGN KEY (lot_id) REFERENCES lots(id),
  CONSTRAINT fk_incident_type FOREIGN KEY (type_code) REFERENCES types_incident(code)
);

-- Machine : appartient à une seule ligne
CREATE TABLE machines (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  libelle       VARCHAR(100) NOT NULL,
  ligne_numero  INT  NOT NULL,
  CONSTRAINT fk_machine_ligne FOREIGN KEY (ligne_numero) REFERENCES lignes(numero)
);

-- Maintenance : une machine, date/heure de début, durée
CREATE TABLE maintenances (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  machine_id     INT  NOT NULL,
  debut          TEXT     NOT NULL,
  duree_minutes  INT  NOT NULL DEFAULT 0,
  CONSTRAINT fk_maint_machine FOREIGN KEY (machine_id) REFERENCES machines(id)
);

-- Intervention : une par couple (maintenance, opérateur)
CREATE TABLE interventions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  maintenance_id  INT  NOT NULL,
  matricule       VARCHAR(20)  NOT NULL,
  CONSTRAINT uq_intervention UNIQUE (maintenance_id, matricule),
  CONSTRAINT fk_interv_maint FOREIGN KEY (maintenance_id) REFERENCES maintenances(id) ON DELETE CASCADE,
  CONSTRAINT fk_interv_op    FOREIGN KEY (matricule) REFERENCES operateurs(matricule)
);

-- Piece
CREATE TABLE pieces (
  reference         VARCHAR(30)   NOT NULL,
  libelle           VARCHAR(120)  NOT NULL,
  prix_unitaire_ht  NUMERIC NOT NULL DEFAULT 0,
  quantite_stock    INT   NOT NULL DEFAULT 0,
  PRIMARY KEY (reference),
  CONSTRAINT ck_piece_prix CHECK (prix_unitaire_ht >= 0)
);

-- ConsommationPiece : pièces notées par l'opérateur sur son intervention
CREATE TABLE consommations (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  intervention_id   INT  NOT NULL,
  piece_reference   VARCHAR(30)  NOT NULL,
  quantite          INT  NOT NULL,
  CONSTRAINT fk_conso_interv FOREIGN KEY (intervention_id) REFERENCES interventions(id) ON DELETE CASCADE,
  CONSTRAINT fk_conso_piece  FOREIGN KEY (piece_reference) REFERENCES pieces(reference),
  CONSTRAINT ck_conso_qte CHECK (quantite > 0)
);

-- Fournisseur
CREATE TABLE fournisseurs (
  code           VARCHAR(20)  NOT NULL,
  raison_sociale VARCHAR(120) NOT NULL,
  adresse        VARCHAR(200) NOT NULL,
  telephone      VARCHAR(30)  NOT NULL,
  PRIMARY KEY (code)
);

-- Association "Est proposée par"
CREATE TABLE piece_fournisseur (
  piece_reference  VARCHAR(30) NOT NULL,
  fournisseur_code VARCHAR(20) NOT NULL,
  PRIMARY KEY (piece_reference, fournisseur_code),
  CONSTRAINT fk_pf_piece FOREIGN KEY (piece_reference) REFERENCES pieces(reference),
  CONSTRAINT fk_pf_four  FOREIGN KEY (fournisseur_code) REFERENCES fournisseurs(code)
);

-- AchatPiece : historique daté des prix d'achat
CREATE TABLE achats (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  piece_reference      VARCHAR(30)   NOT NULL,
  fournisseur_code     VARCHAR(20)   NOT NULL,
  date_achat           TEXT          NOT NULL,
  prix_achat_unitaire  NUMERIC NOT NULL,
  CONSTRAINT fk_achat_piece FOREIGN KEY (piece_reference) REFERENCES pieces(reference),
  CONSTRAINT fk_achat_four  FOREIGN KEY (fournisseur_code) REFERENCES fournisseurs(code),
  CONSTRAINT ck_achat_prix CHECK (prix_achat_unitaire >= 0)
);

-- CompositionPiece : nomenclature (pièce composée / pièce composant)
CREATE TABLE compositions (
  compose_reference    VARCHAR(30)  NOT NULL,
  composant_reference  VARCHAR(30)  NOT NULL,
  quantite             INT  NOT NULL,
  PRIMARY KEY (compose_reference, composant_reference),
  CONSTRAINT fk_comp_compose   FOREIGN KEY (compose_reference) REFERENCES pieces(reference),
  CONSTRAINT fk_comp_composant FOREIGN KEY (composant_reference) REFERENCES pieces(reference),
  CONSTRAINT ck_comp_qte  CHECK (quantite > 0),
  CONSTRAINT ck_comp_self CHECK (compose_reference <> composant_reference)
);

-- CompteUtilisateur : authentification et rôle
CREATE TABLE comptes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  identifiant         VARCHAR(40)  NOT NULL,
  mot_de_passe_hache  VARCHAR(255) NOT NULL,
  role                VARCHAR(30)  NOT NULL,
  actif               INTEGER   NOT NULL DEFAULT 1,
  operateur_matricule VARCHAR(20)  NULL,
  collaborateur_id    INT  NULL,
  derniere_connexion  TEXT     NULL,
  CONSTRAINT uq_compte_identifiant UNIQUE (identifiant),
  CONSTRAINT fk_compte_op   FOREIGN KEY (operateur_matricule) REFERENCES operateurs(matricule) ON DELETE SET NULL,
  CONSTRAINT fk_compte_coll FOREIGN KEY (collaborateur_id) REFERENCES collaborateurs(id) ON DELETE SET NULL,
  CONSTRAINT ck_compte_role CHECK (role IN ('ADMINISTRATEUR','RESPONSABLE_ATELIER','COLLABORATEUR_RESPONSABLE','OPERATEUR'))
);

-- Sécurité : tentatives de connexion (anti force brute)
CREATE TABLE tentatives_connexion (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  identifiant  VARCHAR(40)  NOT NULL,
  ip           VARCHAR(45)  NOT NULL,
  date_tentative TEXT   NOT NULL
);

-- Sécurité : journal des actions
CREATE TABLE journal (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  compte_id   INT  NULL,
  identifiant VARCHAR(40)  NOT NULL,
  action      VARCHAR(40)  NOT NULL,
  details     VARCHAR(255) NOT NULL,
  ip          VARCHAR(45)  NOT NULL,
  date_action TEXT     NOT NULL
);
