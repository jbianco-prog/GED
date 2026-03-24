# GED Documentaire — Guide d'installation

## Prérequis

- PHP 8.1+ avec extensions : `pdo_mysql`, `zip`, `fileinfo`, `exif`, `curl`
- MySQL 8.0+ ou MariaDB 10.6+
- Serveur Apache avec `mod_rewrite` activé (ou Nginx, voir config ci-dessous)
- Optionnel : `poppler-utils` (`pdftotext`, `pdfinfo`) pour l'extraction PDF

---

## 1. Structure des fichiers

```
ged/
├── bootstrap.php          ← Chargement global (inclure en premier)
├── config/
│   └── config.php         ← Configuration (DB, IA, chemins…)
├── src/
│   ├── Auth/Auth.php
│   ├── Models/            ← Database, FileModel, Folder, AuditLog
│   ├── Services/          ← MetadataExtractor, TextExtractor, SensitivityDetector,
│   │                         ClaudeAiService, ClassificationEngine
│   ├── Controllers/       ← UploadController
│   └── Helpers/Helpers.php
├── public/                ← Racine web (DocumentRoot Apache)
│   ├── index.php
│   ├── login.php / logout.php
│   ├── file.php
│   ├── upload.php
│   ├── download.php
│   ├── actions.php
│   ├── admin.php
│   ├── logs.php
│   ├── css/app.css
│   ├── js/app.js
│   └── .htaccess
├── storage/               ← Fichiers uploadés (HORS racine web)
│   └── tmp/
├── database/
│   └── schema.sql
└── .htaccess              ← Bloque l'accès direct à la racine
```

---

## 2. Base de données

```bash
mysql -u root -p < database/schema.sql
```

Cela crée la base `ged_db`, toutes les tables, et un compte admin par défaut :
- **Email** : `admin@ged.local`
- **Mot de passe** : `password` ← **À changer immédiatement**

---

## 3. Configuration

Éditer `config/config.php` ou définir des variables d'environnement :

| Variable        | Description                          | Défaut          |
|----------------|---------------------------------------|-----------------|
| `DB_HOST`       | Hôte MySQL                           | `localhost`     |
| `DB_NAME`       | Nom de la base                       | `ged_db`        |
| `DB_USER`       | Utilisateur MySQL                    | `root`          |
| `DB_PASS`       | Mot de passe MySQL                   | *(vide)*        |
| `APP_URL`       | URL publique de l'application        | `http://localhost` |
| `APP_ENV`       | `production` ou `development`        | `production`    |
| `CLAUDE_API_KEY`| Clé API Anthropic pour l'analyse IA  | *(vide)*        |

**Via variables d'environnement (recommandé en production) :**

```bash
export DB_HOST=localhost
export DB_NAME=ged_db
export DB_USER=ged_user
export DB_PASS=motdepasse_fort
export APP_URL=https://ged.monentreprise.fr
export CLAUDE_API_KEY=sk-ant-api03-...
```

---

## 4. Configuration Apache

Le `DocumentRoot` doit pointer sur le dossier `public/` :

```apache
<VirtualHost *:80>
    ServerName ged.local
    DocumentRoot /var/www/ged/public

    <Directory /var/www/ged/public>
        AllowOverride All
        Require all granted
    </Directory>

    # Bloquer l'accès au reste
    <Directory /var/www/ged>
        Require all denied
    </Directory>
    <Directory /var/www/ged/public>
        Require all granted
    </Directory>
</VirtualHost>
```

Activer mod_rewrite :
```bash
a2enmod rewrite headers
systemctl restart apache2
```

---

## 5. Configuration Nginx (alternative)

```nginx
server {
    listen 80;
    server_name ged.local;
    root /var/www/ged/public;
    index index.php;

    # Bloquer l'accès direct au storage
    location /storage { deny all; }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

---

## 6. Permissions

```bash
# Propriétaire = www-data (ou votre user Apache/Nginx)
chown -R www-data:www-data /var/www/ged
chmod -R 755 /var/www/ged
chmod -R 750 /var/www/ged/storage
chmod 640 /var/www/ged/config/config.php
```

---

## 7. Extraction PDF (optionnel mais recommandé)

```bash
# Ubuntu/Debian
apt-get install poppler-utils

# CentOS/RHEL
yum install poppler-utils
```

Sans `pdftotext`, l'extraction de texte PDF fonctionnera en mode dégradé (lecture binaire basique).

---

## 8. Clé API Claude

1. Créez un compte sur [console.anthropic.com](https://console.anthropic.com)
2. Générez une clé API
3. Définissez `CLAUDE_API_KEY` dans votre environnement

Sans clé API, l'analyse IA est désactivée mais la détection locale (mots-clés + Luhn) fonctionne toujours.

---

## 9. Sécurité post-installation

- [ ] Changer le mot de passe admin par défaut
- [ ] Configurer HTTPS (Let's Encrypt recommandé)
- [ ] Définir `APP_ENV=production`
- [ ] Restreindre `DB_USER` aux seules permissions nécessaires
- [ ] Vérifier que le dossier `storage/` est inaccessible depuis le web
- [ ] Mettre en place des sauvegardes régulières (BDD + storage)
- [ ] Activer `mod_security` ou équivalent en production

---

## 10. Comptes de démonstration

| Email               | Mot de passe | Rôle          |
|--------------------|--------------|---------------|
| admin@ged.local    | password     | Administrateur |

**Remplacez ce mot de passe via l'interface d'administration.**

---

## Structure de l'arborescence par défaut

```
/Racine
  /Clients
  /RH
  /Projets
```

Vous pouvez créer, renommer et supprimer des dossiers depuis l'interface.
