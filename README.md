# GED Documentaire — Gestion Électronique de Documents

[![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4.svg?logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1.svg?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Claude AI](https://img.shields.io/badge/Claude_AI-claude--sonnet--4-D4A017.svg?logo=anthropic&logoColor=white)](https://www.anthropic.com/)
[![Apache](https://img.shields.io/badge/Apache-2.4+-D22128.svg?logo=apache&logoColor=white)](https://httpd.apache.org/)
[![License](https://img.shields.io/badge/License-GPL-green.svg)](LICENSE)
[![Status](https://img.shields.io/badge/Status-Production-success.svg)]()
[![DLP](https://img.shields.io/badge/DLP-Actif-red.svg)]()
[![RGPD](https://img.shields.io/badge/RGPD-Conforme-blue.svg)]()

> Plateforme de gestion documentaire d'entreprise avec classification automatique par IA, détection DLP et contrôle d'accès avancé.
> LA PUBLICATION N'EST PAS COMPLETE - S'appuie sur une interface ressemblante à SharePoint

---

## Sommaire

1. [Vue d'ensemble](#1-vue-densemble)
2. [Stack technique](#2-stack-technique)
3. [Architecture du projet](#3-architecture-du-projet)
4. [Modèle de données](#4-modèle-de-données)
5. [Installation](#5-installation)
6. [Configuration](#6-configuration)
7. [Pipeline de classification IA](#7-pipeline-de-classification-ia)
8. [Système DLP](#8-système-dlp)
9. [Gestion des rôles](#9-gestion-des-rôles)
10. [Statuts de classification](#10-statuts-de-classification)
11. [Sécurité](#11-sécurité)
12. [API AJAX](#12-api-ajax-actionsphp)
13. [Structure des fichiers](#13-structure-des-fichiers)
14. [Compte par défaut](#14-compte-par-défaut)
15. [Migrations SQL](#15-migrations-sql)

---

## 1. Vue d'ensemble

La GED est une application web PHP natif (sans framework) offrant une interface type explorateur Windows / SharePoint pour la gestion de documents d'entreprise. Elle intègre un pipeline d'analyse automatique des documents basé sur l'API Claude (Anthropic) pour détecter les informations sensibles et générer des résumés thématiques.

### Fonctionnalités principales

| Fonctionnalité | Description |
|---|---|
| **Explorateur de fichiers** | Arborescence de dossiers, navigation, upload drag & drop |
| **Classification IA** | Analyse automatique du contenu et des métadonnées par Claude |
| **Détection DLP** | Mots-clés sensibles, numéros de carte bancaire (algorithme de Luhn) |
| **Résumé IA** | Génération de 3 mots-clés thématiques par document |
| **DLP global** | Interrupteur admin pour restreindre partage / téléchargement / upload |
| **DLP par société** | Protection permanente pour les documents des sociétés COMPANY-C1/C2/C3 |
| **Sélection multiple** | Suppression et réanalyse groupées dans l'arborescence |
| **Journaux d'audit** | Traçabilité complète de toutes les actions utilisateurs |
| **Prévisualisation** | Aperçu inline des PDF, images et fichiers texte |
| **Partage** | Dropbox, OneDrive, LinkedIn, Facebook, Email, Impression |

---

## 2. Stack technique

| Composant | Technologie |
|---|---|
| **Langage** | PHP 8.1+ (natif, sans framework) |
| **Base de données** | MySQL 8.0+ / MariaDB 10.6+ |
| **IA** | API Claude — `claude-sonnet-4-20250514` (Anthropic) |
| **Serveur web** | Apache 2.4+ avec `mod_rewrite` |
| **Frontend** | HTML5, CSS3, JavaScript ES6+ (vanilla) |
| **Sessions** | PHP natif avec CSRF token |
| **Stockage** | Système de fichiers local (`/storage/`) |

### Dépendances PHP

Aucune dépendance Composer. Extensions PHP natives requises :

- `pdo_mysql` — connexion base de données
- `fileinfo` — détection MIME type
- `zip` — extraction métadonnées DOCX/XLSX/PPTX
- `exif` — métadonnées images
- `curl` — appels API Claude
- `mbstring` — gestion des chaînes Unicode
- `json` — sérialisation

---

## 3. Architecture du projet

```
ged/
├── bootstrap.php                  # Autoloader + session + gestion erreurs
├── config/
│   └── config.php                 # Configuration centrale (DB, IA, upload, sécurité)
├── database/
│   ├── schema.sql                 # Schéma complet (6 tables + données initiales)
│   ├── migration_resume_ai.sql    # Colonne resume_ai dans file_analysis
│   └── migration_settings.sql    # Table settings (paramètres globaux)
├── src/
│   ├── Auth/
│   │   └── Auth.php               # Authentification, sessions, CSRF, rôles
│   ├── Controllers/
│   │   └── UploadController.php   # Pipeline upload + analyse + blocage DLP
│   ├── Helpers/
│   │   ├── Helpers.php            # Icônes SVG, badges, formatage, flash, pagination
│   │   └── Layout.php             # Layout HTML centralisé (topbar, sidebar, footer)
│   ├── Models/
│   │   ├── Database.php           # Singleton PDO + méthodes query
│   │   ├── FileModel.php          # CRUD fichiers, métadonnées, analyses
│   │   ├── Folder.php             # Arborescence récursive, breadcrumb
│   │   ├── UserModel.php          # Gestion utilisateurs, stats, mots de passe
│   │   ├── Settings.php           # Paramètres globaux, logique DLP
│   │   ├── AuditLog.php           # Journal d'activité
│   │   └── User.php               # Modèle utilisateur basique
│   └── Services/
│       ├── ClassificationEngine.php  # Orchestrateur du pipeline d'analyse
│       ├── ClaudeAiService.php       # Appels API Claude (analyze + summarize)
│       ├── SensitivityDetector.php   # Détection locale (mots-clés + Luhn)
│       ├── MetadataExtractor.php     # Extraction métadonnées (PDF/DOCX/XLSX/EXIF)
│       └── TextExtractor.php         # Extraction texte (PDF/DOCX/XLSX/PPTX/ODF/TXT)
├── public/
│   ├── index.php                  # Explorateur de fichiers (arborescence)
│   ├── file.php                   # Fiche détail + partage + réanalyse
│   ├── admin.php                  # Dashboard administration
│   ├── logs.php                   # Journal d'audit paginé
│   ├── profile.php                # Profil utilisateur + changement MDP
│   ├── search.php                 # Recherche full-text
│   ├── preview.php                # Prévisualisation inline
│   ├── preview-serve.php          # Serveur de fichiers inline (sécurisé)
│   ├── upload.php                 # Endpoint upload multipart
│   ├── download.php               # Téléchargement sécurisé + contrôle DLP
│   ├── actions.php                # API AJAX (rename, delete, move, reanalyze)
│   ├── move.php                   # Déplacement fichiers/dossiers
│   ├── login.php / logout.php     # Authentification
│   ├── css/app.css                # Feuille de styles principale
│   └── js/app.js                  # Interactions dynamiques (upload, menus, modales)
├── views/errors/
│   ├── 403.php                    # Page accès refusé
│   ├── 403_dlp.php                # Page blocage DLP
│   └── 404.php                    # Page non trouvée
├── storage/
│   ├── .htaccess                  # Blocage accès direct aux fichiers stockés
│   └── tmp/                       # Fichiers temporaires upload
└── bin/
    ├── reanalyze.php              # CLI réanalyse en masse
    └── manage-users.php           # CLI gestion utilisateurs
```

---

## 4. Modèle de données

### Table `users`

| Colonne | Type | Description |
|---|---|---|
| `id` | INT UNSIGNED PK | Identifiant unique |
| `nom` | VARCHAR(100) | Nom complet |
| `email` | VARCHAR(200) UNIQUE | Adresse e-mail (identifiant de connexion) |
| `password` | VARCHAR(255) | Hash bcrypt (cost=12) |
| `role` | ENUM('admin','user') | Rôle applicatif |
| `actif` | TINYINT(1) | Compte actif / désactivé |
| `created_at` | DATETIME | Date de création |

### Table `folders`

| Colonne | Type | Description |
|---|---|---|
| `id` | INT UNSIGNED PK | |
| `nom` | VARCHAR(200) | Nom du dossier |
| `parent_id` | INT UNSIGNED FK | Dossier parent (NULL = racine) |
| `chemin` | VARCHAR(1000) | Chemin complet calculé |
| `created_by` | INT UNSIGNED FK | Utilisateur créateur |

### Table `files`

| Colonne | Type | Description |
|---|---|---|
| `id` | INT UNSIGNED PK | |
| `nom_original` | VARCHAR(500) | Nom du fichier à l'upload |
| `nom_courant` | VARCHAR(500) | Nom actuel (après renommage éventuel) |
| `nom_stockage` | VARCHAR(500) | Nom unique sur disque (UUID) |
| `extension` | VARCHAR(20) | Extension en minuscules |
| `mime_type` | VARCHAR(200) | Type MIME détecté |
| `taille` | BIGINT | Taille en octets |
| `chemin_stockage` | VARCHAR(1000) | Chemin absolu sur disque |
| `folder_id` | INT UNSIGNED FK | Dossier contenant |
| `uploaded_by` | INT UNSIGNED FK | Utilisateur ayant déposé |
| `created_at` | DATETIME | Date de dépôt |

### Table `file_metadata`

Métadonnées extraites automatiquement (Auteur, Titre, Société, Logiciel créateur, Nb pages, etc.) depuis les fichiers DOCX, XLSX, PPTX, PDF et images (EXIF).

| Colonne | Type | Description |
|---|---|---|
| `file_id` | INT UNSIGNED FK | |
| `auteur` | VARCHAR(500) | Auteur du document |
| `titre` | VARCHAR(500) | Titre du document |
| `sujet` | VARCHAR(500) | Sujet |
| `societe` | VARCHAR(500) | Société créatrice — **utilisée pour le DLP forcé** |
| `nb_pages` | INT | Nombre de pages |
| `langue` | VARCHAR(50) | Langue détectée |
| `json_complet` | LONGTEXT | Toutes les métadonnées en JSON brut |

### Table `file_analysis`

| Colonne | Type | Description |
|---|---|---|
| `file_id` | INT UNSIGNED FK | |
| `texte_extrait` | LONGTEXT | Texte brut extrait |
| `mots_cles_detectes` | TEXT | Mots-clés sensibles trouvés (JSON) |
| `cb_detectee` | TINYINT(1) | Carte bancaire Luhn détectée |
| `nombre_cb` | INT | Nombre de CB trouvées |
| `score_ia` | DECIMAL(5,2) | Score de sensibilité IA (0–100) |
| `verdict_ia` | VARCHAR(50) | Verdict brut de l'IA |
| `raisons_ia` | TEXT | Raisons de classification (JSON) |
| `resume_ai` | VARCHAR(100) | 3 mots-clés thématiques séparés par virgule |
| `niveau_sensibilite` | ENUM | Statut final (voir section 10) |
| `metadata_analysee` | TINYINT(1) | Les métadonnées ont été analysées |
| `contenu_analyse` | TINYINT(1) | Le contenu textuel a été analysé |
| `analysed_at` | DATETIME | Date de la dernière analyse |

### Table `audit_logs`

| Colonne | Type | Description |
|---|---|---|
| `id` | BIGINT PK | |
| `user_id` | INT UNSIGNED FK | Utilisateur auteur de l'action |
| `action` | VARCHAR(100) | Code de l'action (ex: `file_upload`, `dlp_enabled`) |
| `cible_type` | VARCHAR(50) | Type de cible (`file`, `folder`, `user`, `settings`) |
| `cible_id` | INT UNSIGNED | ID de la cible |
| `detail` | TEXT | Détail libre |
| `ip` | VARCHAR(45) | Adresse IP |
| `date_action` | DATETIME | Horodatage |

### Table `settings`

| Colonne | Type | Description |
|---|---|---|
| `cle` | VARCHAR(100) PK | Clé du paramètre |
| `valeur` | TEXT | Valeur |
| `updated_by` | INT UNSIGNED FK | Admin ayant modifié |
| `updated_at` | DATETIME | Dernière modification |

**Paramètre géré actuellement :**

| Clé | Valeur | Description |
|---|---|---|
| `dlp_enabled` | `0` ou `1` | Interrupteur DLP global |

---

## 5. Installation

### Prérequis

- PHP 8.1+ avec extensions : `pdo_mysql`, `fileinfo`, `zip`, `exif`, `curl`, `mbstring`
- MySQL 8.0+ ou MariaDB 10.6+
- Apache 2.4+ avec `mod_rewrite` activé
- HTTPS recommandé en production

### Étapes

**1. Déposer les fichiers**

```bash
cp -r ged/ /var/www/html/ged
# ou pointer le DocumentRoot directement vers /var/www/html/ged/public
```

**2. Créer la base de données**

```sql
CREATE DATABASE ged_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ged_user'@'localhost' IDENTIFIED BY 'mot_de_passe_fort';
GRANT ALL PRIVILEGES ON ged_db.* TO 'ged_user'@'localhost';
FLUSH PRIVILEGES;
```

**3. Importer le schéma et les migrations**

```bash
mysql -u ged_user -p ged_db < database/schema.sql
mysql -u ged_user -p ged_db < database/migration_resume_ai.sql
mysql -u ged_user -p ged_db < database/migration_settings.sql
```

**4. Configurer l'application**

Éditer `config/config.php` :

```php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'ged_db');
define('DB_USER', 'ged_user');
define('DB_PASS', 'mot_de_passe_fort');
define('APP_URL', 'https://mondomaine.fr/ged/public');  // sans slash final
define('CLAUDE_API_KEY', 'sk-ant-...');  // obtenir sur console.anthropic.com
```

**5. Permissions**

```bash
chmod 750 storage/ storage/tmp/
chown -R www-data:www-data storage/
```

**6. Configuration Apache**

```apache
<VirtualHost *:443>
    DocumentRoot /var/www/html/ged/public
    ServerName mondomaine.fr
    <Directory /var/www/html/ged/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**7. Protection HTTP Basic Auth (optionnel)**

Déposer `.htpasswd` hors de la racine web et le référencer dans `public/.htaccess` :

```apache
AuthType Basic
AuthName "GED — Accès restreint"
AuthUserFile /var/www/ged/.htpasswd
Require valid-user
```

---

## 6. Configuration

Tous les paramètres sont dans `config/config.php`.

| Constante | Défaut | Description |
|---|---|---|
| `DB_HOST` | `localhost` | Hôte MySQL (utiliser `127.0.0.1` si socket) |
| `DB_NAME` | `ged_db` | Nom de la base de données |
| `DB_USER` | `root` | Utilisateur MySQL |
| `DB_PASS` | _(vide)_ | Mot de passe MySQL |
| `APP_NAME` | `Document Management System` | Nom affiché dans l'interface |
| `APP_URL` | `http://localhost` | URL de base **sans slash final** |
| `APP_ENV` | `production` | `production` ou `development` |
| `APP_DEBUG` | `false` | Affichage des erreurs PHP |
| `MAX_FILE_SIZE` | `52428800` (50 Mo) | Taille maximale par fichier |
| `CLAUDE_API_KEY` | _(vide)_ | Clé API Anthropic — **obligatoire pour l'IA** |
| `CLAUDE_MODEL` | `claude-sonnet-4-20250514` | Modèle Claude utilisé |
| `AI_TEXT_LIMIT` | `4000` | Nb de caractères max envoyés à Claude |
| `SESSION_LIFETIME` | `7200` | Durée de session en secondes (2h) |
| `SENSITIVE_KEYWORDS` | _(liste)_ | Mots-clés déclenchant la détection sensible |

### Variables d'environnement

Toutes les constantes de connexion peuvent être surchargées :

```bash
export DB_HOST=127.0.0.1
export DB_NAME=ged_db
export CLAUDE_API_KEY=sk-ant-...
export APP_URL=https://mondomaine.fr
```

---

## 7. Pipeline de classification IA

Déclenché automatiquement à chaque upload et à la demande via le bouton "Réanalyser".

```
Fichier uploadé
      │
      ▼
1. MetadataExtractor::extract()
   └─ DOCX/XLSX/PPTX : lecture docProps/core.xml via ZIP
   └─ PDF             : lecture xmp/info via fopen
   └─ Images          : EXIF via exif_read_data()
      │
      ▼
2. TextExtractor::extract()
   └─ PDF   : pdftotext (Poppler) ou lecture brute
   └─ DOCX  : extraction XML word/document.xml
   └─ XLSX  : extraction XML xl/sharedStrings.xml
   └─ PPTX  : extraction XML ppt/slides/*.xml
   └─ ODF   : extraction content.xml
   └─ TXT/CSV/JSON : lecture directe
      │
      ▼
3. SensitivityDetector::detect()
   ├─ Mots-clés : recherche SENSITIVE_KEYWORDS dans texte + métadonnées
   └─ Numéros CB : regex 13-19 chiffres → validation algorithme de Luhn
      │
      ▼
4. ClaudeAiService::analyze()
   └─ Envoi texte tronqué (AI_TEXT_LIMIT) + résultats détection locale
   └─ Réponse JSON : { is_sensitive, confidence_score, categories, reasons }
      │
      ▼
5. ClaudeAiService::summarize()
   └─ Génération de 3 mots-clés thématiques séparés par virgule
      │
      ▼
6. FileModel::saveAnalysis()
   └─ Calcul niveau_sensibilite final
   └─ Stockage resume_ai, score_ia, raisons, cb_detectee, etc.
```

### Algorithme de Luhn

La détection de numéros de carte bancaire utilise l'algorithme de Luhn standard (ISO/IEC 7812) :

```
1. Inverser les chiffres du numéro
2. Doubler un chiffre sur deux (positions impaires)
3. Si doublement > 9, soustraire 9
4. Sommer tous les chiffres
5. Total modulo 10 == 0 → numéro valide
```

---

## 8. Système DLP

La **Data Loss Prevention** contrôle l'accès aux documents sensibles pour les utilisateurs standard. L'administrateur n'est **jamais** soumis aux restrictions DLP.

### Trois niveaux de protection

**Niveau 1 — Interrupteur global**

Dans l'interface d'administration, le bouton DLP active ou désactive les restrictions pour tous les documents sensibles. Stocké dans `settings` (`dlp_enabled = 0|1`).

**Niveau 2 — Protection permanente par société**

Les documents dont la métadonnée `societe` correspond à l'une des sociétés protégées sont toujours soumis aux restrictions DLP, indépendamment de l'interrupteur global :

```php
// src/Models/Settings.php
public static function dlpCompanies(): array {
    return ['COMPANY - C1', 'COMPANY - C2', 'COMPANY - C3'];
}
```

**Niveau 3 — Classification du contenu**

Les restrictions ne s'appliquent que si le document est classifié `sensible` ou `sensible_eleve`.

### Matrice des restrictions

| Action | Admin | User — DLP inactif | User — DLP actif (non sensible) | User — DLP actif (sensible) |
|---|---|---|---|---|
| Télécharger | ✅ | ✅ | ✅ | 🔒 Bloqué |
| Partager | ✅ | ✅ | ✅ | 🔒 Grisé |
| Uploader | ✅ | ✅ | ✅ | 🔒 Refusé + supprimé |
| Prévisualiser | ✅ | ✅ | ✅ | ✅ |
| Consulter la fiche | ✅ | ✅ | ✅ | ✅ |

### Indicateurs visuels

- **Bandeau jaune** en haut de toutes les pages quand le DLP global est actif (non-admins uniquement)
- **Colonne DLP** dans l'arborescence : 🛡️🔴 (société protégée) / 🛡️ (DLP global) / 🔓 (inactif)
- **Champ "Protection DLP"** dans la fiche détail du fichier avec trois états colorés
- **Page d'erreur dédiée** (`403_dlp.php`) pour les tentatives de téléchargement bloquées
- **Journalisation** : `download_blocked_dlp`, `dlp_enabled`, `dlp_disabled`

---

## 9. Gestion des rôles

| Action | Rôle `user` | Rôle `admin` |
|---|---|---|
| Consulter, prévisualiser | ✅ | ✅ |
| Uploader | ✅ (hors docs sensibles si DLP actif) | ✅ (toujours) |
| Télécharger | ✅ (hors docs sensibles si DLP actif) | ✅ (toujours) |
| Renommer / supprimer ses fichiers | ✅ | ✅ |
| Réanalyser ses propres fichiers | ✅ | ✅ |
| Réanalyser / supprimer n'importe quel fichier | ✗ | ✅ |
| Gérer les dossiers | ✅ | ✅ |
| Activer / désactiver le DLP | ✗ | ✅ |
| Gérer les utilisateurs | ✗ | ✅ |
| Consulter les journaux d'audit | ✗ | ✅ |
| Voir les résultats de classification | ✗ | ✅ |

---

## 10. Statuts de classification

| Valeur BDD | Libellé affiché | Badge | Déclencheur |
|---|---|---|---|
| `non_analyse` | Non analysé | Gris | Fichier jamais passé par le pipeline |
| `en_cours` | En cours… | Bleu | Pipeline en cours d'exécution |
| `non_sensible` | Non sensible | Vert | Aucun critère sensible détecté |
| `sensible` | Sensible | Orange | Mot-clé sensible OU score IA ≥ 80 % |
| `sensible_eleve` | ⚠ Sensible élevé | Rouge | CB Luhn OU "top secret" OU score IA ≥ 90 % |
| `erreur` | Erreur analyse | Gris | Exception lors de l'analyse |

### Règles de promotion vers `sensible_eleve`

- Mot-clé `top secret`, `très secret` ou `strictly confidential` détecté
- Au moins 1 numéro de carte bancaire valide (Luhn 16 chiffres) dans le document
- Score de confiance IA ≥ 90 %
- Combinaison de plusieurs critères sensibles

### Mots-clés sensibles configurés

```
confidentiel, confidential, secret, top secret, très secret,
strictly confidential, ne pas diffuser, usage interne,
internal only, restricted, restreint
```

---

## 11. Sécurité

### Authentification

- Hash bcrypt avec `cost = 12`
- Session régénérée à la connexion (`session_regenerate_id`)
- Durée de session configurable (défaut : 2h)
- Déconnexion avec destruction complète de session

### Protection CSRF

Chaque formulaire et requête AJAX inclut un token CSRF aléatoire vérifié côté serveur :

```html
<?= Auth::csrfField() ?>
<!-- génère : <input type="hidden" name="_csrf_token" value="..."> -->
```

### Upload sécurisé

- Liste blanche d'extensions autorisées (`ALLOWED_EXTENSIONS`)
- Liste noire d'extensions dangereuses (`BLOCKED_EXTENSIONS` : php, exe, sh, js…)
- Vérification du type MIME réel via `fileinfo`
- Renommage UUID des fichiers stockés (nom original non exposé sur disque)
- Stockage hors de la racine web (`/storage/`) avec `.htaccess` de blocage

### Téléchargement sécurisé

Les fichiers ne sont jamais servis directement par Apache. `download.php` vérifie les droits, contrôle le DLP, consigne l'action et envoie le fichier via `readfile()` avec les bons headers.

---

## 12. API AJAX (actions.php)

Toutes les actions dynamiques passent par `POST /public/actions.php` avec le CSRF token.

| Action | Rôle requis | Description |
|---|---|---|
| `create_folder` | user | Créer un dossier |
| `rename_folder` | user | Renommer un dossier |
| `delete_folder` | user | Supprimer un dossier vide |
| `rename_file` | user (propriétaire) | Renommer un fichier |
| `delete_file` | user (propriétaire) ou admin | Supprimer un fichier |
| `move_file` | user | Déplacer un fichier vers un autre dossier |
| `reanalyze_file` | user (ses fichiers) ou admin | Relancer le pipeline d'analyse |

Réponse JSON systématique :

```json
{ "success": true }
// ou
{ "success": false, "error": "Message d'erreur" }
```

---

## 13. Structure des fichiers

### Autoloader (`bootstrap.php`)

Toute nouvelle classe doit être déclarée dans le tableau `$map` de `bootstrap.php` :

```php
$map = [
    'Database'             => '/src/Models/Database.php',
    'AuditLog'             => '/src/Models/AuditLog.php',
    'Folder'               => '/src/Models/Folder.php',
    'FileModel'            => '/src/Models/FileModel.php',
    'Auth'                 => '/src/Auth/Auth.php',
    'MetadataExtractor'    => '/src/Services/MetadataExtractor.php',
    'TextExtractor'        => '/src/Services/TextExtractor.php',
    'SensitivityDetector'  => '/src/Services/SensitivityDetector.php',
    'ClaudeAiService'      => '/src/Services/ClaudeAiService.php',
    'ClassificationEngine' => '/src/Services/ClassificationEngine.php',
    'UploadController'     => '/src/Controllers/UploadController.php',
    'H'                    => '/src/Helpers/Helpers.php',
    'User'                 => '/src/Models/User.php',
    'UserModel'            => '/src/Models/UserModel.php',
    'Layout'               => '/src/Helpers/Layout.php',
    'Settings'             => '/src/Models/Settings.php',
];
```

### Scripts CLI (`/bin/`)

```bash
# Réanalyser tous les fichiers non analysés
php bin/reanalyze.php

# Réanalyser tous les fichiers (forcer)
php bin/reanalyze.php --all

# Réanalyser un fichier précis (par ID)
php bin/reanalyze.php --id=42

# Simulation sans modification
php bin/reanalyze.php --dry-run

# Gestion des utilisateurs en ligne de commande
php bin/manage-users.php
```

### Icônes de fichiers

Rendues en SVG inline pour les types Office et PDF, en emojis pour les autres :

| Extension(s) | Icône | Couleur |
|---|---|---|
| docx, doc, odt | SVG Word | Bleu `#2B579A` |
| xlsx, xls, ods | SVG Excel | Vert `#217346` |
| pptx, ppt, odp | SVG PowerPoint | Orange `#C43E1C` |
| pdf | SVG Adobe Acrobat | Rouge `#E8392A` |
| csv | SVG CSV | Vert `#33915A` |
| jpg, png, gif… | 🖼️ | |
| zip, tar, gz… | 🗜️ | |
| mp4, avi… | 🎬 | |
| mp3, wav… | 🎵 | |
| txt, json… | 📃 | |
| autres | 📎 | |

---

## 14. Compte par défaut

Créé automatiquement par `database/schema.sql` :

| Champ | Valeur |
|---|---|
| Email | `admin@ged.local` |
| Mot de passe | `password` |
| Rôle | `admin` |

> ⚠️ **Changer ce mot de passe immédiatement après l'installation** via la page Profil ou le CLI.

```bash
php bin/manage-users.php
```

---

## 15. Migrations SQL

À exécuter **dans l'ordre** après l'import du schéma initial :

```bash
# 1. Schéma de base (obligatoire — tables + données initiales)
mysql -u user -p db < database/schema.sql

# 2. Colonne resume_ai (résumés IA thématiques)
mysql -u user -p db < database/migration_resume_ai.sql

# 3. Table settings (interrupteur DLP global)
mysql -u user -p db < database/migration_settings.sql
```

**`migration_resume_ai.sql`**
```sql
ALTER TABLE `file_analysis`
    ADD COLUMN `resume_ai` VARCHAR(100) NULL DEFAULT NULL
    AFTER `raisons_ia`;
```

**`migration_settings.sql`**
```sql
CREATE TABLE IF NOT EXISTS `settings` (
    `cle`        VARCHAR(100) NOT NULL PRIMARY KEY,
    `valeur`     TEXT NOT NULL DEFAULT '',
    `updated_by` INT UNSIGNED NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `settings` (`cle`, `valeur`) VALUES ('dlp_enabled', '1')
ON DUPLICATE KEY UPDATE `cle` = `cle`;
```

---

*GED Documentaire — Usage interne — Tous droits réservés.*
