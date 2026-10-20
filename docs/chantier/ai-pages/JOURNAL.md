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

### D-008 [phase 2] Un composant anonyme d'un module n'est pas trouvable par Flexy

**Symptôme.** Le plan prévoyait `components/Cms/*` dans le module, surchargeables
par le thème.
**Cause.** `twig_component.anonymous_template_directory` vaut `@Flexy`, la chaîne
des `components/` des thèmes (`FlexyBundle.php:177`) : un module n'y dépose rien
sans toucher la config de Flexy, et un composant à classe enregistré gagne
toujours sur un fichier du thème.
**Résolution.** Sections rendues par `ThemeTemplateRenderer` (template du thème
`cms/sections/<slug>` sinon fallback du module), comme les partials ; listées
au toolkit par `StoryProviderInterface`.
**Généralisable : oui.** Pour un module Thelia 3, un « composant » surchargeable
par le thème est un template résolu par la chaîne du thème, pas un composant UX.

### D-009 [phase 2] Le cache Twig survit à une restauration de template dans la même seconde

**Symptôme.** Après sabotage puis restauration d'un template de section, les
tests d'intégration restaient rouges (agents A et B).
**Cause.** Le compilé Twig de `var/cache/test` est invalidé sur le mtime du
fichier ; une restauration dans la même seconde garde le compilé saboté.
**Résolution.** `touch` du template puis `rm -r var/cache/test`.
**Généralisable : oui.** Tout sabotage de template pour voir un test rouge se
termine par `touch` + suppression de `var/cache/test`.

### D-010 [phase 2] Le texte libre s'affichait contre le bord de la page

**Symptôme.** Sur la page de preuve, la section `cms:richtext` (« Accès »)
n'avait pas la largeur des autres sections.
**Cause.** `blocks.css` centre les blocs par leur classe `cms-*` ; un fragment
sans classe n'en a aucune.
**Résolution.** `RichTextSection::render` enveloppe le markup dans
`<div class="cms-richtext">`, ajouté à la liste de largeur de `blocks.css`.
**Généralisable : non.**

### D-011 [phase 3] Le SDK Anthropic PHP n'embarque aucune fabrique PSR-17

**Symptôme.** Première exécution du générateur : `No PSR-17 url factory found`
(`php-http/discovery`), alors que `composer require anthropic-ai/sdk` s'était
installé sans se plaindre.
**Cause.** Le SDK exige les paquets virtuels `psr/http-client-implementation`
et `psr/http-factory-implementation` ; l'hôte les satisfaisait par
`symfony/http-client` sans fournir de fabrique PSR-17 que la découverte sache
trouver.
**Résolution.** `nyholm/psr7 ^1.8` requis par le module et installé dans
l'hôte.
**Généralisable : oui.** Un module qui embarque le SDK Anthropic PHP déclare
`nyholm/psr7` lui-même.

### D-012 [phase 3] Une fonction fléchée capture les variables à sa définition

**Symptôme.** Le registre enregistrait 0 jeton alors que le résultat en
comptait 2 500.
**Cause.** La closure d'écriture du registre, définie en début d'appel en
`fn () =>`, capturait `$usage` par valeur à ce moment-là.
**Résolution.** Closure classique avec `use (&$usage, &$turns, &$requestId)`.
**Généralisable : oui.** Une closure qui lit un état accumulé après coup
prend des références, jamais `fn`.

### D-013 [phase 3] Les sorties structurées refusent les contraintes de longueur et de plage

**Symptôme.** La doc de l'API (`tool-use-concepts.md:541-553`) exclut
`minimum`, `maximum`, `minLength`, `maxLength`, `minItems`, `maxItems` ; le SDK
PHP ne les retire pas lui-même, contrairement aux SDK Python et TypeScript.
**Cause.** Limite du compilateur de grammaire des structured outputs.
**Résolution.** D'abord un schéma complet épuré ; puis, en ligne (D-014), le
seul schéma que l'API compile : l'enveloppe. `DocumentValidator` applique le
schéma complet côté serveur.
**Généralisable : oui.** Vérifié en ligne le 2026-10-20 : format `json_schema`
+ outils sur `claude-opus-5-5` passe, dès lors que le schéma est petit.

### D-014 [phase 3] Les limites de la grammaire des structured outputs, mesurées en ligne

**Symptôme.** Cinq refus 400 successifs sur le schéma de sortie, avec le
catalogue complet (20 sections) : `Enum value 1 does not match declared type
'string'` (un `const` sans `type`, puis une clé de choix `'1'` devenue entier
PHP) ; `too many optional parameters (28) … limit 24` ; `Enum value '1' does
not match declared type '['string','null']'` (un enum n'admet pas l'union
avec null) ; `too many parameters with union types (28) … limit 16` ;
`The compiled grammar is too large … reduce the number of strict tools`,
encore après passage des outils en non strict et des listes d'items en chaîne
JSON.
**Cause.** Le compilateur de grammaire plafonne les propriétés optionnelles
(24), les types union (16) et la taille totale ; un catalogue de vingt
composants à props typées dépasse chacune de ces bornes.
**Résolution.** `DocumentSchema::strict()` n'envoie que l'enveloppe :
`sections[{id, component: enum, props: string JSON}]`. `PageGenerator`
décode `props`, `DocumentValidator` applique le schéma complet, deux tours de
correction. Outils non stricts (entrée : une chaîne). `Prop::toJsonSchema`
convertit les clés de choix en chaînes. Première génération réelle réussie :
brief 01, 3 tours, 1 696 jetons en entrée + 17 390 lus en cache, 2 021 en
sortie, 0,094 USD, 24 s.
**Généralisable : oui.** Avec un schéma de sortie variable (catalogue
extensible par le thème), ne mettre dans la grammaire que l'enveloppe et
valider côté serveur ; garder les outils non stricts tant qu'un format de
sortie est demandé.

### D-015 [phase 4] Le contenu d'une `StreamedResponse` n'est pas dans `getResponse()`

**Symptôme.** Le test d'écran lisait un flux SSE vide alors que le statut et
les en-têtes étaient justes.
**Cause.** `StreamedResponse::getContent()` répond `false` ; le navigateur de
test (`HttpKernelBrowser::doRequest`) capture ce que le callback écrit dans un
tampon de sortie et le range dans la réponse interne.
**Résolution.** Lire `$this->client->getInternalResponse()->getContent()`. Côté
module, `GenerationStream::flush()` ne vide les tampons (`ob_end_flush`) que
hors SAPI `cli` : sous PHPUnit, vider le tampon du navigateur de test perdrait
le flux.
**Généralisable : oui.** Un flux SSE se teste par la réponse interne du
navigateur de test, et son `flush` est conditionné au SAPI.

### D-016 [phase 4] Un service privé à consommateur unique ne se remplace pas dans le conteneur de test

**Symptôme.** Risque de voir `TestContainer::set()` sans effet sur
`ClaudeClientProviderInterface`, consommé par le seul `PageGenerator`.
**Cause.** Symfony inline un service privé référencé une fois dans la fabrique
de son consommateur ; `set()` écrit dans `privates` que personne ne lit.
**Résolution.** Alias déclaré `->public()` dans `TheliaCMS::configureServices`
(justifié : point d'extension d'un site). Deuxième piège : `set()` refuse un
service déjà initialisé ; le test installe donc un fournisseur délégant une
fois par kernel et change sa cible (`ContentScreenTest::$provider`).
**Généralisable : oui.** Pour substituer un service en test d'écran : alias
public et un seul `set()` par kernel.

### D-017 [phase 4] Les assets d'un module sont copiés une fois dans `public/assets`

**Symptôme.** La correction de contraste de `content-editor.css` n'était pas
servie ; `?v=` vide (`assets_version` absent du contexte du template).
**Cause.** `module_asset()` sert une copie publiée sous
`public/assets/backOffice/default-twig/TheliaCMS/`, faite une fois ; la
version d'asset est calculée par chaque contrôleur (`CmsMenuAdminController`
le fait pour le menu).
**Résolution.** `ContentScreen::assetsVersion()` (hash des mtime du JS et du
CSS) ; copie manuelle des deux fichiers dans `public/assets/...` sur l'hôte de
dev après modification.
**Généralisable : oui.** Après avoir touché un asset de module : recopier dans
`public/assets/backOffice/default-twig/<Module>/` (ou supprimer le dossier
publié), et fournir `assets_version` au template.

### D-018 [phase 4] `waitForURL` sur un motif déjà présent dans l'URL

**Symptôme.** Le script de preuve a « vu » la réécriture de section finie
instantanément : la capture 04 est celle de l'état précédent.
**Cause.** `page.waitForURL(/done=\d+/)` matchait l'URL courante
(`done=13`) avant que le POST en arrière-plan ne redirige vers `done=14`.
**Résolution.** Le registre prouve la réécriture (ligne 14, `kind=section`,
10,5 s) ; le script conservé dans `preuves/phase4/proof.js` attend désormais
un changement d'URL, pas un motif (capture 04 non rejouée).
**Généralisable : oui.** Attendre une URL différente de l'URL courante, jamais
un motif que l'URL courante satisfait déjà.

### D-019 [phase 4 bis] Le parser Twig de Thelia fixe le global `app` au premier rendu du processus

**Symptôme.** Les écrans de `SectionEditScreenTest` répondaient 500 :
`merge` sur `null` dans `LanguageSwitcher.html.twig` (`app.request.query`).
**Cause.** `TwigParser::assign()` (module TwigEngine) appelle
`$twig->addGlobal('app', …)` avec la requête du moment ; l'appel ne passe
qu'avant le premier rendu du processus, puis lève une `LogicException`
avalée. En test, `CmsPageWriter::saveContent` rend les sections avant toute
requête : `app.request` reste `null` pour tout le processus.
**Résolution.** Dans le test, une requête d'écran avant le premier rendu de
section (`SectionEditScreenTest::renderedOnce()`). Hors test, un processus
web rend toujours après sa requête.
**Généralisable : oui.** Un test d'écran qui rend un template du thème avant
sa première requête doit d'abord demander un écran.

## Points ouverts

- `cms_page_revision` n'a aucun lecteur en production : garder (avec le
  document v2) ou supprimer en 2.0.0 ? À trancher en phase 1.
- Guide webmaster (`docs/guide/`, PDF et captures du builder) : réécriture du
  texte dans le chantier ; captures d'écran à refaire après recette.
- Une PR par phase sur le dépôt public, ou un seul merge en fin de chantier ?
- Ajouter, supprimer ou réordonner une section à la main (phase 4 bis ne le
  fait pas : brief et révision s'en chargent) : à décider pour 2.1.
