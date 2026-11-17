# Smile — logiciel de gestion d'usine

Projet Agile des logiciels, TWIN 3, ESATIC.
Réalisé par Eboua Franck, sous la direction du Dr N'drin Hugues.

## Installer avec XAMPP (Windows)

1. Copier le dossier `smile` dans `C:\xampp\htdocs\`.
2. Ouvrir le panneau XAMPP et démarrer **Apache** et **MySQL**.
3. Dans le navigateur, ouvrir : `http://localhost/smile/install.php`
4. Choisir « Données de démonstration », puis cliquer sur **Installer Smile**.
   La base `smile_usine` est créée automatiquement.
5. Cliquer sur **Aller à la connexion**.

Si MySQL a un mot de passe, le renseigner dans `config/config.php`
(`'password' => ''`).

## Comptes de démonstration

| Identifiant  | Mot de passe   | Rôle                      |
|--------------|----------------|---------------------------|
| admin        | Admin#Smile26  | Administrateur            |
| a.kone       | Atelier#2026   | Responsable des ateliers  |
| p.nguessan   | Equipes#2026   | Collaborateur responsable |
| op.kouame    | Machine#2026   | Opérateur                 |

## Lancer sans XAMPP (démonstration hors ligne)

Avec PHP installé, depuis le dossier `smile`, dans l'invite de commandes Windows :

    set SMILE_DB_DRIVER=sqlite
    php -S localhost:8000

(sous Linux ou macOS : `SMILE_DB_DRIVER=sqlite php -S localhost:8000`)

puis ouvrir `http://localhost:8000/install.php`.

## Réinstaller

Supprimer le fichier `storage/install.lock`, puis relancer `install.php`.

## Organisation du code

- `index.php` : point d'entrée unique (routeur)
- `install.php` : création de la base et des données
- `app/core.php` : base de données, sessions, sécurité, droits par rôle
- `app/crud.php` : créer, consulter, modifier, supprimer (scénarios A2 à A5)
- `app/modules.php` : une définition par classe du diagramme de classes
- `app/metier.php` : règles métier et fiches détaillées
- `app/admin.php` : connexion, tableau de bord, comptes, journal, profil
- `database/schema_mysql.sql` : schéma MySQL (22 tables)
- `assets/` : styles, script, polices et images
