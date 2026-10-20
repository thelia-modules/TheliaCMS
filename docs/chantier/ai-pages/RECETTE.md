# Cahier de recette — pages générées par l'API Claude

Recette de livraison du 2026-10-20, sur le banc d'essai DDEV (module 2.0.0,
branche `feat/ai-page-generation`, `acc98e1`), contre les parcours et critères
figés dans `CHARTE.md`. Une ligne par cas, une preuve par ligne.

## Volet 1. Non-régression

| Cas | Attendu | Résultat | Preuve | Suite |
|---|---|---|---|---|
| Suites de tests | Vertes, écart expliqué avec la baseline | Conforme : unitaires 558 → 543, intégration 263 → 359 (1 skip préexistant). Les 15 unitaires en moins sont ceux de l'éditeur retiré ; les 96 d'intégration en plus couvrent le document, les sections, le générateur, les écrans | `baselines/tests-*-avant.txt`, sorties des phases 1 à 5 dans `PLAN.md` | |
| Export du site | Même structure, format passé en v2, pages et réglages conservés | Conforme : `thelia_cms:export` répond `version: 2`, `module_version: 2.0.0`, mêmes 4 langues, les 4 pages d'origine présentes sans contenu (purge 2.0.0 annoncée), 2 pages de preuve avec `draft_document` | `baselines/site-avant.json`, export du 2026-10-20 14:33 | |
| Volumétrie | Aucune perte hors purge décidée (D5) | Conforme : pages 4 → 6 (deux pages de preuve), contenus 8 → 11 (les trois des pages de preuve), publiées 0 → 3, blocs 0, menus 2, formulaires 1, types 3, étiquettes 0 ; nouvelles tables `cms_skill` (5) et `cms_page_generation` (17 appels) | `baselines/volumetrie-avant.txt`, requête du 2026-10-20 | |
| Pages héritées | Les pages 1.5.0 restent dans l'arbre, sans contenu, non publiées, 404 propre | Conforme : `/mentions-legales` répond 404 avec la page 404 du thème (la page n'était pas publiée avant non plus) | `curl`, `ContentMigrationTest` | |
| Cache partagé | En-tête `Cache-Tag` sur une page publiée | Conforme : `cms,cms-menu,cms-page-6` | `curl -I` de la page de preuve | |
| Sitemap | La page publiée y figure | Conforme : deux entrées dans `sitemap-content.xml` (fr, index) | `curl` | |
| Recherche | `/recherche` réservée au mode vitrine | Conforme : 404 en mode boutique, comme avant (le thème garde `/search`) | `curl`, `CmsSearchController` | |
| Module sans le bundle | Le back-office et le front répondent sans `openstudio/page-builder-bundle` | Conforme : contenu, médias, réglages, blocs, pages rendus au navigateur, front 200 | `preuves/phase5/` | |

## Volet 2. Parcours utilisateur (navigateur, 1440 px)

| Parcours (charte) | Attendu | Résultat | Preuve | Suite |
|---|---|---|---|---|
| 1. Créer et générer une page | Page créée, brief, génération suivie, aperçu, publication, page en ligne avec les sections demandées, une seule h1, images de la médiathèque seulement, `Cache-Tag` | Conforme : six sections, 3 tours, 21,5 s, 0,089 USD ; une h1 ; aucune image (médiathèque vide) ; `Cache-Tag` présent | `preuves/phase4/01-05*.png`, registre lignes 13 et 14, `curl -I` | Le lien `/contact` du brief n'existe pas sur le site de dev (défaut du brief, noté en phase 3) |
| 2. Itérer | Modification d'une section, les autres intactes, historique visible | Conforme : section réécrite en 1 tour, 10,5 s, 0,033 USD ; historique de trois lignes | `preuves/phase4/`, registre ligne 14 | Capture 04 prise avant la fin (JOURNAL D-018) ; le registre fait foi |
| 2 bis. Corriger à la main | Un mot corrigé sans génération, publié, visible sur le front | Conforme : titre du hero « Lyon » → « Lyon 3e », ligne « À la main » à 0 USD, h1 du front à jour | `preuves/phase4bis/06-08*.png` | |
| 3. Montée de version | Une 1.5.0 avec pages publiées passe en 2.0.0 : arbre, adresses et SEO gardés, contenu purgé, pages dépubliées, message dans le CHANGELOG | Conforme sur le test de migration (jouée sur une base 1.5.0 avec contenus) ; sur le banc, les 4 pages d'origine n'avaient pas de contenu publié | `Tests/Integration/Content/ContentMigrationTest`, CHANGELOG « Changed (2.0.0) » | Pas rejoué sur un site réel avec pages en ligne : à faire sur le premier site migré |
| 4. Bloc d'item | Générer un bloc pour un produit, le voir sur sa fiche | Partiel : l'écran du bloc d'item est atteint depuis la fiche produit du back-office et génère comme une page (test) ; le thème Flexy de l'hôte n'appelle pas `cms_item_block()`, la fiche produit du front ne peut donc pas le montrer | `ItemBlockScreenTest`, `BlockContentScreenTest`, `preuves/phase4/`, `grep cms_item_block templates/frontOffice` vide | Arbitrage A1 |
| 5. Export / import | Aller-retour d'un site avec une page v2 et une page héritée | Conforme sur le test d'aller-retour (page v2 restituée avec son document, page héritée sans contenu) ; export réel vérifié ci-dessus | `SiteRoundTripTest`, export du banc | Import réel sur un second site non joué |
| 6. Refus et erreurs | Clé absente, refus du modèle, dépassement de `max_tokens`, panne de l'API : message clair, rien de corrompu | Conforme : clé absente = avertissement et bouton inactif ; refus = ligne « Décliné » et message ; coupure = échec enregistré, brouillon intact ; panne API = ligne « Échec », brouillon intact | `ContentScreenTest` (4 cas), `PageGeneratorTest` (refus, coupure, panne API vue rouge sous sabotage) | Coupure réseau réelle (prise débranchée) non jouée |

## Volet 3. Recette graphique

| Écran | Référence | Résultat | Preuve | Suite |
|---|---|---|---|---|
| Dix sections sur Flexy | Rendu des dix blocs `cms-*` de 1.5.0 | Même markup `cms-*`, mêmes feuilles de style (`blocks.css`, palette du thème) ; constat d'écart fait en phase 2 sur la page de onze sections | `baselines/blocks-catalogue-avant.html`, `baselines/page-preuve-phase2.png`, `preuves/phase4/05-front.png` | |
| Écran de contenu | Composants du thème `default-twig`, pas de maquette | Construit sur la barre, les cartes et les formulaires du thème ; axe-core 0 violation hors barre de debug | `preuves/phase4/03-written.png`, `axe-screen-written.json`, `preuves/phase5/` | |
| Volet d'édition à la main | Idem | axe-core : seule la barre d'outils du module Tiptap remonte des champs couleur sans libellé | `preuves/phase4bis/06-edit-faq.png`, `axe-screen-edit-open.json` | Arbitrage A3 |
| Skills, réglages, médias, blocs, pages | Thème `default-twig` | Rendus ; axe remonte des contrastes du thème lui-même (navigation, aides de libellés, texte atténué), présents avant le chantier | `preuves/phase4/admin-cms-*.png`, `preuves/phase5/` | Arbitrage A4 (thème, hors module) |

## Volet 4. Performance

Banc au repos, DDEV, PHP 8.3, `APP_ENV=dev` (barre de debug active, donc
chiffres majorés). Aucune baseline de performance n'avait pu être prise en
phase 0 (aucune page publiée) : comparaison à un ordre de grandeur sain.

| Page | Temps serveur | Mémoire | Mur (navigateur) |
|---|---|---|---|
| Page publiée de six sections | 140 ms | 14 MiB | 206 ms |
| Page publiée de onze sections (phase 2) | 136 ms | 14 MiB | 213 ms |
| Écran de contenu (six sections, formulaires d'édition) | 110 ms | 14 MiB | 781 ms (iframe d'aperçu comprise) |
| Liste des pages | 90 ms | 12 MiB | 154 ms |

Non mesurable : le nombre de requêtes SQL par page (le profileur de l'hôte
n'a pas de collecteur Propel). Génération : 21,5 s et 0,089 USD pour une page
de six sections, 10,5 s et 0,033 USD pour une section ; le préfixe du prompt
(≈ 17 400 jetons) est lu en cache à partir du deuxième appel de l'heure.

## Arbitrages ouverts

| # | Constat | Options | Coût estimé | Reco |
|---|---|---|---|---|
| A1 | Le thème Flexy de l'hôte n'appelle pas `cms_item_block()` : un bloc d'item généré n'apparaît pas sur la fiche produit | (a) ajouter l'appel dans le thème du site ; (b) laisser au thème | 0,25 j côté thème | (a) sur le premier site qui en a besoin |
| A2 | Ajouter, supprimer ou réordonner une section à la main n'existe pas (brief et révision s'en chargent) | (a) formulaire de structure en 2.1 ; (b) rester sur le brief | 1 j | (b) jusqu'à un retour d'éditeur |
| A3 | La barre d'outils de Tiptap a deux champs couleur sans libellé (module tiers) | (a) signaler au module Tiptap ; (b) masquer ces boutons par `tiptap.toolbar_items` | 0 (réglage) | (b) |
| A4 | Contrastes du thème `default-twig` (navigation, aides, texte atténué) sous 4,5:1 | (a) ticket thème ; (b) rien | hors module | (a) |
| A5 | Guide webmaster (PDF, captures) encore en 1.x | (a) refaire après recette client ; (b) retirer du module | 0,5 j | (a) |
| A6 | Streaming par tour : un tour long reste muet jusqu'à sa fin | (a) streaming du SDK par jeton ; (b) rester ainsi | 0,5 j | (b) tant qu'aucun tour ne dépasse 60 s |
| A7 | Évaluation manuelle des briefs 02 à 10 non relevée dans `eval/results.md` | (a) le dev joue et note ; (b) clore sur le brief 01 | 0,10 USD par brief | (a) |
| A8 | `SiteStyles::palette()` et l'écran styles caché restent dans le code | (a) retirer en 2.1 ; (b) garder | 0,25 j | (a) |
| A9 | Une PR par phase sur le dépôt public, ou un seul merge | — | — | un seul merge, docs de chantier réduites en ADR (D9) |
