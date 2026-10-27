# Vaud Ride — app vélo virtuel sur les routes vaudoises

Ce fichier donne à Claude Code le contexte du projet. Lis-le en entier avant de coder.

## Concept

App de home trainer type Zwift, mais sur les **vraies routes du canton de Vaud** : la pente, le relief et le paysage viennent des données swisstopo, et le home trainer connecté reproduit la résistance de chaque montée.

- Porteur : Vaud Promotion (promotion touristique et terroir du canton de Vaud)
- Différenciateur : authenticité du territoire et lien avec l'offre touristique (POI, « rouler ce parcours pour de vrai »)
- Pas un jeu : pas de monde imaginaire, pas d'e-sport, pas de multijoueur massif

## Périmètre MVP

Légende : ✅ fait · 🟡 partiel / à valider · ⬜ à faire (état au 2026-10-27).

1. ✅ Connexion home trainer Bluetooth **FTMS** + ceinture cardio + capteur de puissance en fallback (`lib/ble`, simulateur `?sim=1`) — 🟡 à valider sur plusieurs trainers réels
2. ✅ Mode simulation de pente : résistance pilotée par la pente réelle du parcours
3. ✅ Catalogue de parcours avec profil altimétrique (une soixantaine de sources dans `courses/`, fiche parcours : GPX, accès au départ, ascensions, meilleurs mois et météo) — 🟡 vérifier la licence SuisseMobile avant d'intégrer ces itinéraires
4. ✅ Vue 3D du relief (swisstopo + photogrammétrie Google via Cesium ion) avec cycliste glTF animé et kit personnalisable
5. ✅ Tableau de bord live : puissance, cadence, FC, vitesse, pente, distance, dénivelé
6. ✅ Comptes (pseudo unique), historique, export FIT/TCX, sync Strava
7. ✅ POI le long du parcours (OpenStreetMap + catalogue Local Hub, relecture dans l'admin, favoris, lecture vocale, carte du parcours)

Fait en plus du périmètre initial : segments chronométrés et classements/KOM, riders en direct sur le même parcours (Pusher), pause, commandes vocales, image de fin de sortie à partager, tableau de bord d'activité anonyme dans l'admin, page « Rapports » de l'admin (période au choix, parcours les plus roulés, saisonnalité, export CSV, sans aucune donnée d'origine des joueurs), admin Filament avec 2FA, PWA configurée, mode découverte sans matériel (`/ride/<slug>?demo=1`, survol à vitesse libre pour salons et vaud.ch).

Hors MVP (V2) :
- ✅ segments chronométrés et classements · ✅ rides de groupe (riders en direct)
- ✅ mode ERG / séances structurées (modèles livrés + éditeur simple, FTP gardé dans le navigateur)
- ✅ ghosts (meilleure sortie, dernière sortie, record du segment)
- ⬜ création libre de parcours
- ⬜ app iOS native (Capacitor) — nécessaire pour Safari/iPhone
- ⬜ ANT+ FE-C

Hors scope : monde ouvert, gamification poussée, vidéo réelle filmée.

## Stack

| Couche | Choix |
| --- | --- |
| Front | Nuxt 4 (Vue 3, TypeScript strict), PWA, bun |
| Rendu 3D | CesiumJS (terrain + imagery) ; Three.js seulement si Cesium est trop lourd |
| Bluetooth | Web Bluetooth API (Chrome/Edge desktop, Android) |
| Physique | TypeScript pur, côté client, testé unitairement |
| Backend | Laravel 13 + Filament (admin parcours/POI), MariaDB (géométrie dans les paquets `profile.json`, pas en base) |
| Auth | Laravel Sanctum ; OAuth Strava |
| Pipeline données | Python 3.12, GDAL/rasterio, shapely, pyproj ; exécuté en CI |
| Hébergement | CDN pour paquets parcours ; API hébergée en Suisse (nLPD) |

Contrainte connue : **Safari (macOS et iOS) ne supporte pas Web Bluetooth**. Afficher un message clair sur ces navigateurs. Isoler toute la couche BLE derrière une interface pour pouvoir brancher un plugin BLE natif (Capacitor) en V2.

## Architecture

```
[swisstopo: swissALTI3D, swissTLM3D, SWISSIMAGE]
        │  (hors ligne, une fois par parcours)
        ▼
[pipeline/ Python] ──► [paquet parcours: profile.json + tuiles terrain] ──► CDN
                                                                     │
                                                                     ▼
┌──────────────── App web (PWA, Nuxt) ────────────────┐
│ Rendu 3D (Cesium) ◄─► Moteur physique ◄─► Pont BLE   │◄── BLE ──► Home trainer FTMS + cardio
└─────────────────────────┬───────────────────────────┘
                          │ HTTPS
                          ▼
                 [API Laravel] ──► Strava
```

Principe : tout le travail géographique lourd est fait hors ligne. L'app charge un paquet prêt à l'emploi, calcule la vitesse et envoie la pente au trainer plusieurs fois par seconde.

## Arborescence cible

```
/pipeline        Python : construction des paquets parcours
/app             Nuxt 3 (PWA)
  /composables   useTrainer, useHeartRate, useRide, usePhysics
  /lib/ble       ftms.ts, heart-rate.ts, cycling-power.ts, transport.ts (interface)
  /lib/physics   speed.ts (+ tests)
  /lib/course    chargement et interpolation du profil
  /components    Dashboard, ElevationProfile, Scene3D, PoiCard
/api             Laravel + Filament
/courses         sources des parcours (GPX/GeoJSON + POI), versionnées
```

## Données topographiques

| Source | Usage | Licence |
| --- | --- | --- |
| swissALTI3D (swisstopo), 0,5 m / 2 m | Altitude et pente le long du parcours, maillage du relief | Open data, citer « © swisstopo » |
| swissTLM3D Routes et chemins | Géométrie des parcours, type de route | Open data, citer la source |
| swissTLM3D Forêt / bâtiments / hydrographie | Habillage de la scène 3D | Open data, citer la source |
| SWISSIMAGE | Texture du terrain | Open data |
| Itinéraires SuisseMobile | Parcours officiels | **Licence à vérifier — ne pas intégrer avant validation** |
| Géodonnées VD (geo.vd.ch) | Compléments cantonaux | À vérifier |

Système de coordonnées : données swisstopo en **LV95 (EPSG:2056)**, altitudes LN02 ; convertir en WGS84 (EPSG:4326) pour Cesium. Utiliser pyproj, jamais de formule approchée.

### Pipeline d'un parcours (`pipeline/build_course.py <slug>`)

1. Lire `courses/<slug>/route.gpx` (ou GeoJSON), reprojeter en LV95
2. Rééchantillonner tous les 5 m le long de la ligne
3. Échantillonner l'altitude sur swissALTI3D (interpolation bilinéaire)
4. Lisser le profil (fenêtre glissante ~50 m, configurable) pour supprimer les pics parasites ; gérer ponts et tunnels via des overrides manuels dans `courses/<slug>/overrides.yaml`
5. Calculer la pente par point, la borner à [-15 %, +20 %] (limites usuelles des trainers)
6. Générer le terrain du corridor (1–3 km autour du tracé) au format quantized-mesh ou 3D Tiles, drapé de SWISSIMAGE
7. Écrire `dist/<slug>/profile.json` :

```json
{
  "slug": "marchairuz-biere",
  "name": "Col du Marchairuz depuis Bière",
  "version": 1,
  "distance_m": 0,
  "elevation_gain_m": 0,
  "points": [{ "d": 0, "lat": 0, "lon": 0, "ele": 0, "grade": 0 }],
  "pois": [{ "d": 0, "id": "", "title": { "fr": "", "de": "", "en": "" } }],
  "attribution": "© swisstopo"
}
```

`d` = distance cumulée en mètres. Tester : distance totale et D+ à ±2 % d'une référence connue.

## Bluetooth (Web Bluetooth)

**FTMS — Fitness Machine Service `0x1826`**
- Indoor Bike Data `0x2AD2` (notify) : vitesse, cadence, puissance instantanée selon les flags
- Fitness Machine Control Point `0x2AD9` (write + indicate) :
  - `0x00` Request Control (obligatoire avant toute commande)
  - `0x07` Start or Resume
  - `0x11` Set Indoor Bike Simulation Parameters : wind speed (sint16, 0,001 m/s), **grade (sint16, 0,01 %)**, Crr (uint8, 0,0001), Cw (uint8, 0,01 kg/m)
- Attendre la réponse indicate (`0x80`) de chaque commande ; file d'attente, une commande à la fois
- Envoyer la pente au plus 2–4 fois par seconde, seulement si elle change

**Heart Rate `0x180D`** → Heart Rate Measurement `0x2A37`
**Cycling Power `0x1818`** → Cycling Power Measurement `0x2A63` (fallback sans résistance pilotée)

Gérer : déconnexion/reconnexion automatique, plusieurs appareils simultanés, mode simulateur sans matériel (`?sim=1`) pour développer et tester.

## Moteur physique

Résoudre la vitesse `v` à partir de la puissance `P` et de la pente :

```
P = v · m · g · (sin θ + Crr · cos θ) + ½ · ρ · CdA · v³
```

Valeurs par défaut : m = poids cycliste + 9 kg, g = 9,81, Crr = 0,004, CdA = 0,32, ρ = 1,2 (ajuster selon l'altitude). Résolution par Newton ou bisection, pas de temps 250 ms, inertie lissée. Tests unitaires obligatoires (plat, montée 8 %, descente, puissance nulle).

## Parcours de lancement (proposition)

Corniche de Lavaux · Col du Marchairuz depuis Bière · Col du Mollendruz depuis L'Isle · Tour du Lac de Joux · Col des Mosses depuis Aigle · Col de la Croix depuis Villars · Bord du Léman Nyon–Morges · Montée du Chasseron · Gros-de-Vaud et Broye · Lausanne–Ouchy–Chalet-à-Gobet.

Commencer par **Marchairuz** pour le prototype.

## Conventions

- TypeScript strict, ESLint + Prettier ; PHP : Laravel Pint ; Python : ruff + mypy
- Tests : Vitest (front), Pest (API), pytest (pipeline)
- UI en français, i18n prêt pour DE et EN dès le départ (`@nuxtjs/i18n`)
- Unités métriques partout
- Attribution « © swisstopo » visible dans la scène 3D
- Pas de données personnelles en query string ; hébergement et données utilisateurs en Suisse (nLPD)
- Commits petits et explicites ; une PR par fonctionnalité

## Déploiement

- **API** (`api/deploy.php`, Deployer → Infomaniak, `vaudride.myvaud.dev`) : `cd api && vendor/bin/dep deploy production`. Sauvegarde de la base, migrations, `CourseSeeder`, vidage d'OPcache, contrôle `/up`. **Claude ne peut actuellement pas déployer l'API lui-même** : c'est l'équipe qui lance cette commande (Claude prépare le code et indique si une migration est à lancer).
- **App** (Worker Cloudflare `vaudride`, variables dans `nitro.cloudflare.wrangler.vars` de `app/nuxt.config.ts`) : `cd app && bun run build && bunx wrangler deploy`. Les variables publiques du Worker (clé Pusher, jeton Cesium ion, adresse de contact de la page « Protection des données ») sont dans ce fichier ; les secrets restent hors du dépôt.
- Ordre : l'API d'abord (les migrations), puis l'app.

## Ordre de travail recommandé

1. ✅ **Prototype** : simulateur BLE + vrai FTMS, profil Marchairuz généré par le pipeline, moteur physique, dashboard + profil 2D. Critère : la montée « se sent » crédible sur un vrai trainer (🟡 réglage fin de la sensation à poursuivre)
2. ✅ Scène 3D Cesium avec terrain du corridor et caméra qui suit le cycliste
3. ✅ Catalogue de parcours, POI, écran de fin de sortie
4. ✅ API : comptes, historique, export FIT/TCX, Strava
5. ✅ Admin Filament pour gérer parcours et POI (+ imports Local Hub, segments, joueurs, activité)
6. 🟡 PWA (configurée), perfs (niveaux de détail, fallback 2D sur machines modestes : ⬜), beta : ⬜

Prochaines étapes proposées : « rouler pour de vrai » (suivi des parcours virtuels → réels) · fallback 2D · tests E2E en mode `?sim=1` · Capacitor iOS (écarté pour l'instant ; le mode découverte marche déjà sur Safari/iPhone).

## Questions ouvertes

- Licence de réutilisation des itinéraires SuisseMobile
- Réutilisation directe du terrain 3D de swisstopo (map.geo.admin.ch)
- Modèle : gratuit ou freemium
- Lien avec le projet Left Right Waadt pour recommander des parcours
