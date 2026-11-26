# odac.tune.audio

Site public d'**ODAC**, le registre ouvert des convertisseurs numérique-analogique.
C'est une petite application Laravel 12 + Inertia (React), sur la même pile que
mozaiklabs.fr. Le code est sous licence Apache-2.0 (voir `LICENSE`).

Le site **consomme** l'artefact que construit la CI du dépôt public ODAC. Il n'écrit
jamais dans ce dépôt, ne porte aucun compte et ne pose aucun cookie. Son seul état
est l'empreinte qu'il détient, plus la projection lisible de cet instantané.

## Ce qui est livré

**Livraison 1 : le miroir et le pointeur d'empreinte**

| route | contenu | cache |
|---|---|---|
| `GET /latest.json` | `{"version", "fingerprint", "url": "artifacts/<nom>", "entries"}` | `max-age=300, must-revalidate, public` ; ETag = empreinte stockée |
| `GET /artifacts/odac-<version>-<empreinte>.jsonl` | l'artefact, **pré-compressé en gzip** si le client l'accepte | `immutable, max-age=31536000, public` ; ETag = empreinte (`-gz` pour la version compressée) ; `Vary: Accept-Encoding` |

- **ETag** : on reprend le motif de `CirclePlaylistController` (`setEtag()` sur une
  valeur stockée), et jamais un `withEtag()` calculé sur le corps rendu. Un
  `If-None-Match` se tranche après **une seule requête indexée**, avant toute
  construction et sans lire le disque. Un test le vérifie : il compte une requête
  SQL pour un 304, et un autre test supprime le fichier avant de demander le 304.
- **Compression** : l'artefact est compressé une fois, à l'ingestion
  (`<nom>.jsonl.gz`). Ce que l'on envoie est donc compressé, quel que soit le
  réglage de nginx. Les tests lisent les octets réellement émis (nombre magique
  gzip, décompression identique à l'original). Après un déploiement, la commande
  `odac:check-mirror` refait cette mesure de l'extérieur.
- **Artefacts anciens conservés** : une adresse qui porte une empreinte sert
  toujours les mêmes octets, même après plusieurs nouvelles versions.
- **Proxys de confiance** : la liste est nommée (`ODAC_TRUSTED_PROXIES`). La valeur
  `*`, un masque plus large que /8 ou un nom d'hôte font **refuser le démarrage**.

**Livraison 2 (début) : la lecture publique**

- `GET /` : la liste, paginée côté serveur par 48. Les filtres passent dans la
  chaîne de requête : `q` (marque, modèle, alias), `brand`, `kind`, `contested`,
  `doubt`. Les fiches remplacées n'apparaissent pas dans la liste.
- `GET /<marque>/<modèle>` : la fiche, **groupée par preuve**. Elle montre un bloc
  par revendication avec sa citation, affichée une seule fois. Vient ensuite un
  récapitulatif où chaque valeur renvoie à son bloc. Les règles d'affichage :
  - une raison (`to_be_measured`, etc.) s'affiche en clair, jamais comme une case
    vide ;
  - « cette source décrit la gamme, pas ce modèle » est signalé franchement ;
  - un champ **contesté** montre toutes les valeurs rivales avec leur preuve et
    leur poids, et nomme celle que la construction a retenue ;
  - un champ sans preuve est marqué « sans preuve » ;
  - le doute ouvert apparaît en haut de la fiche.
- **301** pour un alias de graphie et pour une fiche remplacée (`superseded_by`).
  Un alias « voisin » ne répond pas, et la recherche ne le trouve pas non plus.
- ETag de fiche calculé sur des valeurs stockées (empreinte, identifiant, version
  des assets) ; `Cache-Control: max-age=300, public` ; `Vary: X-Inertia`.

Pas encore faits : la complétude (livraison 3), la comparaison (livraison 4),
l'API `/api/*` documentée en OpenAPI et la connexion OAuth.

## Le contrat avec l'artefact

Le format suit le dépôt public `ODAC-Project/ODAC` : `tools/build.py` et
`docs/CONSUMING.md`.

- **Source** : `ODAC_SOURCE` donne l'URL de `latest.json` lui-même, par défaut
  `https://raw.githubusercontent.com/ODAC-Project/ODAC/main/dist/latest.json`. On
  peut aussi donner le dossier `dist/`. Le champ `url` du pointeur est un **nom de
  fichier nu**, résolu **à côté** de `latest.json`, donc dans le même dossier.
- **Empreinte** : `sha256(corps)[:16]`, exactement le calcul de `build.py`. Le
  champ `url` doit valoir `odac-<version>-<empreinte>.jsonl`.
- **Une ligne** : `{"type", "id", "data"}`, avec un type parmi `brand`,
  `chip_family`, `interface`, `vocabulary` et `device`. Une entrée de vocabulaire
  peut être du texte simple ; une marque et un appareil sont des objets.
- **Un appareil** :
  - ses faits résolus sont les **clés de premier niveau** de `data`. Les clés
    `id`, `brand`, `model`, `kind`, `aliases`, `usb`, `inputs`, `evidence`,
    `contested` et `open_doubt` n'en sont pas, ni les clés `_…`, qui sont le
    commentaire des curateurs et ne s'affichent jamais comme des faits ;
  - `data.brand` est l'identifiant de l'entité marque (`audio_gd`). Il peut
    différer du premier segment de l'identifiant (`audio-gd/…`) ;
  - `aliases` est une liste de `{relation, text}`. Un alias non typé est lu comme
    un voisin, par prudence ;
  - `evidence` associe à chaque champ `{claim, names_subject, source:{kind, url, quote, read_on}, submitted_by}`.
    Les clés des entrées s'écrivent `inputs.<interface>.<champ>` ;
  - `contested` associe à chaque champ une liste de valeurs `{value, weight, claims, chosen, claim, source, …}` ;
  - `open_doubt` vaut `{raised_on, text}`.
- **Le niveau atteint** n'est pas dans l'artefact. Il se calcule à partir de
  `vocabulary/completeness`, ce que fera la livraison 3. En attendant, il
  s'affiche « non établi ».

Un artefact incohérent est **refusé en entier** : empreinte fausse, nombre
d'entrées différent, ligne invalide, doublon, identifiant hors motif ou appareil
sans `kind`. Le site continue alors de servir l'instantané précédent.

**Mesure sur le vrai artefact** (`odac-2026.11.25-9237787e4341db0e.jsonl`, 735
entrées, 373 appareils), dans Docker avec PHP 8.4 :

- l'ingestion complète prend 1,8 s, avec un pic de mémoire PHP de 36 Mo (64 Mo
  pour le processus) ;
- les 373 fiches se construisent sans erreur en 1,2 s ;
- le miroir envoie **330 840 octets** compressés pour 5 548 535 octets bruts, soit
  94 % de gain ;
- un 304 sur `latest.json` répond en 40 ms environ avec `artisan serve` ;
- 9 alias de graphie redirigent en 301.

Les fixtures de test (`tests/Fixtures/odac/`) suivent ce format, mais leur contenu
est inventé : marques fictives, citations neutres, adresses `example.org`. Aucune
citation de fabricant n'est versée dans ce dépôt.

## Développement local

```bash
composer install && npm install && npm run build
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate
php artisan odac:ingest --source=tests/Fixtures/odac/dist
php artisan serve
```

Tests : `vendor/bin/pest`. Avec le PHP 8.6 de Homebrew, Laravel 12 émet des
avertissements de dépréciation, et l'un d'eux fait échouer un test 404. Les tests
passent sous PHP 8.4, la version visée en production :

```bash
docker run --rm -v "$PWD":/app -w /app php:8.4-cli php vendor/bin/pest
```

## Déploiement (non exécuté)

Cible : l'ancien VPS (Contabo). Les conteneurs sont prévus pour cohabiter avec ce
qu'il héberge déjà : ils n'écoutent que sur `127.0.0.1:8080`.

### 1. Conteneurs

```bash
git clone <dépôt> /opt/odac-site && cd /opt/odac-site
cp .env.example .env    # remplir APP_KEY, ODAC_SOURCE, éventuellement ODAC_CONTRIBUTE_URL
docker compose build
docker compose run --rm odac-app php artisan key:generate --show   # copier dans .env
docker compose up -d
docker compose exec odac-app php artisan migrate --force
docker compose exec odac-app php artisan odac:ingest
```

Trois services :

- `odac-app` : PHP-FPM 8.4 ;
- `odac-scheduler` : `schedule:work`, qui lance `odac:ingest` toutes les 10 minutes
  (`withoutOverlapping`) ;
- `odac-nginx` : nginx avec les fichiers publics de la même image.

La base SQLite et les artefacts vivent dans des volumes (`odac-db`,
`odac-storage`) et survivent à un redéploiement.

### 2. Variables

| variable | rôle |
|---|---|
| `APP_KEY` | requis par Laravel, même sans session |
| `APP_URL` | `https://odac.tune.audio` (redirections et liens de pagination en https) |
| `ODAC_SOURCE` | URL de `latest.json` (par défaut celle de GitHub), du dossier `dist/`, ou un chemin local |
| `ODAC_FINGERPRINT` | `sha256-16`, le calcul de `tools/build.py` |
| `ODAC_TRUSTED_PROXIES` | vide avec ce montage (voir plus bas) ; jamais `*` |
| `ODAC_CONTRIBUTE_URL` | page de contribution sur mozaiklabs.fr ; vide = pas de bouton |
| `ODAC_MAX_ARTIFACT_BYTES`, `ODAC_HTTP_TIMEOUT` | garde-fous de l'ingestion |

### 3. Cron d'ingestion

Rien à ajouter si le service `odac-scheduler` tourne. Sans lui, on passe par le
crontab de l'hôte :

```
* * * * * cd /opt/odac-site && docker compose exec -T odac-app php artisan schedule:run >> /dev/null 2>&1
```

Une ingestion lit `latest.json`, soit quelques centaines d'octets. Elle ne
télécharge l'artefact que si l'empreinte a changé.

### 4. nginx de l'hôte (TLS)

`docker/nginx.conf` est la configuration du nginx **dans** le conteneur. Il fait
confiance à `X-Forwarded-For` uniquement depuis la passerelle `172.30.57.1`, le
sous-réseau fixé dans `compose.yaml`. PHP voit donc le client dans `REMOTE_ADDR`,
et `ODAC_TRUSTED_PROXIES` reste vide. Sur l'hôte :

```nginx
server {
    listen 443 ssl http2;
    server_name odac.tune.audio;
    # ssl_certificate … (certbot)

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $remote_addr;   # écrase, ne concatène pas
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_http_version 1.1;
        gzip off;   # la compression est faite derrière ; ne pas recompresser
    }
}
server { listen 80; server_name odac.tune.audio; return 301 https://$host$request_uri; }
```

### 5. Vérifier après chaque déploiement

Cette commande mesure les octets réellement envoyés et vérifie le 304 et le cache :

```bash
docker compose exec odac-app php artisan odac:check-mirror https://odac.tune.audio
```

Elle donne aussi la taille compressée de l'artefact. **La question du CDN se
repose au-delà de 1 Mo compressé.**

## Points ouverts

1. **Adresse d'une fiche** : décidée, c'est `odac.tune.audio/<marque>/<modèle>`,
   sans préfixe. Les premiers segments `artifacts`, `api`, `dist`, `odac`, `build`,
   `storage` et `up` sont réservés : ils ne peuvent pas servir de nom de marque.
2. **Adresse du miroir, à confirmer** : `docs/CONSUMING.md` annonce un miroir
   MozAIk Labs « URL to be announced » sans fixer de chemin. On garde donc
   `/latest.json` et `/artifacts/<nom>`. Une fois l'adresse choisie, il faudra
   l'écrire dans `CONSUMING.md`. Le même texte promet aussi un miroir « derrière un
   CDN », alors que la décision actuelle est un VPS sans CDN.
3. **Niveau de complétude** : à calculer à partir de `vocabulary/completeness`
   (livraison 3).
