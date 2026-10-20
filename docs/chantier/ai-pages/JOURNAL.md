# Journal des difficultés — pages générées par l'API Claude

Un rédacteur à la fois. Gabarit : symptôme, cause, résolution, généralisable.

## Entrées

### D-001 [cadrage] Le bundle page-builder n'est enregistré qu'en dev et test dans l'hôte

**Symptôme.** `config/bundles.php:8` de l'hôte enregistre
`OpenStudioPageBuilderBundle` en `dev` et `test` seulement, alors que
`Media/Admin/CmsMediaController` du module autowire des services du bundle.
**Cause.** Non cherchée : le bundle disparaît en phase 5.
**Résolution.** Non corrigé, consigné. Ne pas « réparer » la prod de l'hôte.
**Généralisable : non.**

### D-002 [cadrage] Tailwind de Flexy ne compile que les classes des fichiers scannés

**Symptôme.** `assets/styles/app.css:1` déclare `source(none)` puis des
`@source` explicites ; une classe utilitaire écrite par le modèle n'aurait pas
de CSS.
**Cause.** Choix du thème (build déterministe).
**Résolution.** Le modèle ne produit jamais de markup ni de classes : seulement
des props de composants (décision D1).
**Généralisable : oui.** Sur un thème Tailwind v4 à sources explicites, tout
contenu généré doit passer par des composants déjà scannés.

### D-003 [cadrage] Le module installé dans l'hôte est en retard de 26 commits

**Symptôme.** `vendor/thelia/modules/TheliaCMS` est à `3d198ee` (1.0.0),
le clone à `7dd050a` (1.5.0), 145 fichiers d'écart.
**Cause.** Verrou Composer de l'hôte.
**Résolution.** Travailler et tester sur le clone monté dans `local/modules`
(décision D8) ; ne plus lire la copie de `vendor/`.
**Généralisable : oui.** Avant de cartographier un module, comparer la ref du
lock avec le HEAD du dépôt source.

### D-004 [phase 0] Un lien symbolique vers un clone hors projet ne se résout pas dans DDEV

**Symptôme.** `local/modules/TheliaCMS -> ../../../TheliaCMS` fonctionne sur
l'hôte, `ddev exec ls local/modules/TheliaCMS/` échoue : seul le dossier du
projet est monté dans le conteneur.
**Cause.** Montage DDEV limité au projet.
**Résolution.** Clone déplacé dans `local/modules/TheliaCMS` (dossier ignoré
par le git de l'hôte, disposition du CI du module).
**Généralisable : oui.** Un module en développement sous DDEV vit dans
`local/modules/`, pas derrière un lien.

### D-005 [phase 0] Modèles Propel générés périmés après changement de version du module

**Symptôme.** `thelia_cms:export` échoue : `Unknown column 'cms_page.layout'`.
La base est en 1.5.0 (`module:refresh` a joué les migrations), mais
`var/propel/dev/model/TheliaCMS/Model/Map/CmsPageTableMap.php` porte encore le
schéma 1.0.0 (colonne `layout`, remplacée par `page_type` en 1.2.0).
**Cause.** `php Thelia cache:clear` ne régénère pas `var/propel/<env>/model` ;
les modèles sont construits une fois et conservés.
**Résolution.** Supprimer le seul dossier `model/TheliaCMS` ne suffit pas :
`PropelInitService::buildPropelModels` (`core/lib/Thelia/Core/Propel/PropelInitService.php:209`)
saute la construction tant que `var/propel/<env>/hash` égale
`var/propel/<env>/model/hash`. Suppression de `var/propel/dev` entier, puis
`cache:clear` : modèles reconstruits sur le `schema.xml` du clone. L'environnement
`test` avait été reconstruit par `bin/test-prepare` et était juste.
**Généralisable : oui.** Après tout changement de `schema.xml` (phase 1 en
fera), supprimer `var/propel/<env>` dans l'hôte, pour `dev` et pour `test`
(ou rejouer `bin/test-prepare` pour `test`).

### D-006 [phase 1] Conteneur de test périmé après un changement de constructeur

**Symptôme.** 181 erreurs `CmsPageWriter::__construct(): Argument #10 must be
of type DocumentValidator, MenuCache given` : le conteneur compilé de
`var/cache/test` injectait les anciens arguments. `php Thelia cache:clear` en
`APP_ENV=test` n'a rien changé.
**Cause.** Le bootstrap des tests d'intégration (`tests/bootstrap.php` de
l'hôte) utilise son propre conteneur compilé ; la commande ne l'a pas
reconstruit.
**Résolution.** Suppression de `var/cache/test`, le premier test recompile.
**Généralisable : oui.** Après tout changement de signature de service dans le
module : `rm -r var/cache/test var/propel/test` (et `var/propel/dev` si le
schéma a changé) dans l'hôte, avec des chemins absolus (le cwd du shell de
session saute entre l'hôte et le module).

### D-007 [phase 1] Un test de migration hors transaction laisse des lignes derrière lui

**Symptôme.** Après avoir saboté `2.0.0.sql` pour voir `ContentMigrationTest`
rouge, le run suivant échouait encore : une ligne de `cms_page_template`
insérée par le test restait dans la base de test.
**Cause.** Le test tourne sans transaction (ALTER TABLE) et compte sur la
migration elle-même pour purger ce qu'il a inséré.
**Résolution.** Le run suivant, avec le fichier restauré, a purgé la ligne.
**Généralisable : oui.** Un test hors transaction doit nettoyer ce qu'il
insère dans `tearDown`, pas s'en remettre au code testé.

## Points ouverts

- `cms_page_revision` n'a aucun lecteur en production : garder (avec le
  document v2) ou supprimer en 2.0.0 ? À trancher en phase 1.
- Guide webmaster (`docs/guide/`, PDF et captures du builder) : réécriture du
  texte dans le chantier ; captures d'écran à refaire après recette.
- Une PR par phase sur le dépôt public, ou un seul merge en fin de chantier ?
