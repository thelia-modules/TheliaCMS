# Chantier « pages générées par l'API Claude » — plan

Rédigé le 2026-10-20 sur le clone `thelia-modules/TheliaCMS` à `7dd050a` (1.5.0),
branche `feat/ai-page-generation`. Banc d'essai : `thelia/thelia` `main` dans
`~/workzone/thelias/thelia-cms-ai` (PHP 8.3.30 sous DDEV, thème Flexy, module
installé en 1.0.0 dans `vendor/`, à remplacer par le clone).

Ce document est le plan de référence : il se relit avant chaque phase et se met à
jour dans la même PR que le code. Les décisions gelées sont dans `CHARTE.md`, les
problèmes rencontrés dans `JOURNAL.md`.

## 1. Périmètre

**Objet.** Refaire de zéro la partie « pages » du module : GrapesJS et
`openstudio/page-builder-bundle` disparaissent ; une page est produite à partir
d'un brief envoyé à l'API Claude, cadrée par des skills (fichiers d'instructions
versionnés) et composée au maximum d'éléments du toolkit du thème (composants
Twig de Flexy et de sa chaîne d'héritage).

**Reste en place, inchangé sauf point de contact.** Arbre de pages, adresses et
301, corbeille, types de page, tags, menus, formulaires, médiathèque, SEO,
sitemap, recherche, consentement, scripts, notices, réglages, ACL, dashboard,
vitrine (404, maintenance), cache partagé, preview signée, import/export,
modèles de page, commandes console.

**Hors périmètre.** Génération d'images, génération de menus ou de formulaires,
traduction automatique entre langues (une page par langue reste une génération
par langue), SEO automatique au-delà de ce que le skill demande, édition WYSIWYG
complète (voir D3), refonte du thème Flexy.

## 2. État réel du module (lu sur pièces, 2026-10-20)

Détail complet dans le rapport de cartographie (section 9 ci-dessous reprend les
pièges). L'essentiel :

- Contenu d'une page : `cms_page_content` (une ligne par page et locale) avec
  `draft_project_data` (JSON GrapesJS), `draft_html`, `draft_css`,
  `published_html`, `published_css`, `published_at` (`Config/schema.xml:130-139`).
  Le JSON GrapesJS est aussi copié dans `cms_page_revision`, dans l'export
  (`ImportExport/SiteExporter.php:169,215`) et dans `cms_page_template.payload`.
- Écriture : `Builder/Admin/CmsPageBuilderController` → `CmsPageWriter::saveContent`
  (normalise + assainit) → `CmsPageWriter::publish` (normalise, assainit,
  `ImageRewriter`, vide, texte d'exemple, index de recherche, révision, purge
  cache). `HeadingChecker` n'est appelé que depuis le contrôleur builder
  (`CmsPageBuilderController.php:175`).
- Lecture : `CmsPageViewListener` → `PublishedPageRepository::find` →
  `CmsPageRenderer` (substitution des partials `data-cms-partial` au rendu) →
  template `cmspage[-type].html.twig` du thème ou du module, HTML en `|raw`, CSS
  de page inline en `<style>`.
- Blocs : dix blocs statiques `cms-*` (`Builder/CoreBlockCatalog`) stylés par
  `assets/blocks.css` et par `cms.css` de Flexy ; neuf partials dynamiques
  (`Partial/Definition/*`) ; blocs réutilisables (`cms_block*`) et blocs d'item
  (`cms_item_block`) édités avec **le même builder** que les pages.
- Couplage GrapesJS/bundle : 3 contrats du bundle dans `TheliaCMS.php:17-19,392-397`,
  `Builder/CmsBuilderConfig`, `Media/LibraryImageUploader`, `Media/LibraryImageCatalog`,
  `Media/Admin/CmsMediaController` (routes `openstudio_page_builder_image_*`),
  `Builder/{ActiveThemeCanvas,ThemeCanvas,EditorBaseBlocks,InitialCanvas}`,
  `assets/src/*` (esbuild + grapesjs 0.22.12), artefacts compilés commités
  `templates/backOffice/default-twig/assets/page-builder/editor.{js,css}` (1,2 Mo),
  deux templates `builder.html.twig`, 5 tests unitaires et 4 d'intégration.
- Hôte : le bundle n'est enregistré qu'en `dev` et `test`
  (`config/bundles.php:8`) alors que `CmsMediaController` autowire ses services.
- Thème Flexy : Tailwind v4 avec `source(none)` et des `@source` explicites sur
  `components/`, `*.html.twig`, `form/`, `blocks/`
  (`assets/styles/app.css:1-17`). **Toute classe utilitaire absente de ces
  fichiers n'existe pas dans le CSS compilé.** Flexy importe `cms.css` (25 l.,
  variables `--cms-*`) pour les blocs `cms-*` du module.
- Toolkit Flexy : composants Twig anonymes (`{% props %}`) ou à classe
  (`Base.php`), rangés `components/{Atoms,Molecules,Organisms,Layouts,Forms,Fields}/<Nom>/<Variante>.html.twig`,
  stories `toolkit.html.twig`, `/toolkit` en debug, `StoryProviderInterface`
  pour qu'un module y liste ses composants. La palette est orientée e-commerce
  (cart, checkout, cards) : aucun composant « hero », « CTA », « FAQ »,
  « témoignages » n'existe côté thème ; ces sections sont aujourd'hui les blocs
  `cms-*` du module.
- Données locales : 8 lignes `cms_page_content`, 0 publiée, 0 bloc. Le module
  est publié sur Packagist en 1.x et le README dit qu'un site réel l'utilise :
  une reprise des pages existantes doit exister.
- SDK : `anthropic-ai/sdk` 0.59.0 (2026-10-15, PHP ≥ 8.1), pas de 1.x.
  Modèle par défaut recommandé par le skill `claude-api` : `claude-opus-5-5`
  (thinking toujours actif, `effort` à régler, pas de `tool_choice` forcé,
  gérer `stop_reason = refusal`, fallbacks côté serveur).
- Hôte sans `symfony/messenger` : pas de file de jobs disponible.

## 3. Décisions structurantes

Chaque décision a une recommandation ; la réponse du dev est consignée dans
`CHARTE.md`. Tant qu'une décision n'est pas gelée, la phase qui en dépend ne
démarre pas.

| # | Décision | Options | Reco |
|---|---|---|---|
| D1 | Forme du contenu généré et stocké | **A.** Document JSON = liste ordonnée de sections, chacune un composant du catalogue avec ses props (textes riches en HTML assaini) ; rendu en HTML par Twig à la sauvegarde et à la publication. **B.** HTML libre à classes `cms-*` (pipeline actuel conservé, toolkit hors jeu, classes Tailwind impossibles). **C.** Twig généré par le modèle (puissant mais exécution de code produit par un LLM : sandbox obligatoire, erreurs de compilation à gérer). | **Gelé : A** : contraignable par schéma (structured outputs), validable serveur, régénérable section par section, CSS garanti car le markup vient des composants déjà scannés par Tailwind. |
| D2 | Palette offerte au modèle | **A.** Composants Flexy éligibles (allowlist déclarée par le thème dans `config/theliacms.yaml`) **+** les dix sections `cms-*` converties en composants Twig fournis par le module (`components/Cms/*`, listés dans `/toolkit` via `StoryProviderInterface`, surchargeables par le thème) **+** les partials dynamiques existants. **B.** Composants Flexy seuls (pas de hero, CTA, FAQ : pages pauvres). | **Gelé : A** |
| D3 | Parcours d'édition après génération | **A.** Brief initial puis itération conversationnelle sur la page (« raccourcis le hero ») et régénération d'une section ; historique conservé par page et langue. **B.** Un seul prompt, régénération complète à chaque fois. **C.** A + édition manuelle des textes (Tiptap sur les props riches). | **Gelé : A + C**, Tiptap en phase 4 bis. |
| D4 | Blocs réutilisables et blocs d'item (même builder aujourd'hui) | **A.** Même générateur, même modèle de document, écran dédié « bloc ». **B.** Abandon des deux (perte de `cms_item_block`, carte produit, `cms_item_block()`). **C.** Différer : impossible sans garder GrapesJS. | **Gelé : A**, traité en fin de phase 4. |
| D5 | Pages et blocs existants (format GrapesJS) | **A.** `published_html` et `published_css` conservés et servis tels quels ; la page est marquée « contenu hérité ». **B.** Purge : les pages existantes repartent vides, réglages conservés. | **Gelé : B** (choix du dev). La migration 2.0.0 vide le contenu et dépublie ; le CHANGELOG et `UPDATE` l'annoncent. |
| D6 | Exécution d'une génération (30 s à plusieurs minutes, pas de Messenger) | **A.** Requête synchrone en `StreamedResponse` SSE depuis le back-office, `set_time_limit(0)`, keep-alive, état persisté à la fin ; `max_tokens` borné. **B.** Table de jobs + commande `thelia_cms:generate:work` lancée par cron ou supervisor, LiveComponent qui interroge l'état. | **Gelé : A** (zéro infrastructure), B documenté comme évolution si les hébergements coupent les requêtes longues. |
| D7 | Où vivent les skills | **A.** Fichiers versionnés seulement. **B.** Skills éditables en back-office : fichiers `skills/<nom>/SKILL.md` du module et du thème semés dans une table `cms_skill` à l'activation, écran **CMS > Skills** (corps, actif, ordre, « restaurer la version livrée »). | **Gelé : B** (choix du dev). |
| D8 | Montage du clone dans l'hôte | **A.** Comme le CI du module : clone dans `local/modules/TheliaCMS` et retrait de `thelia/cms-module` de `composer.json` de l'hôte. **B.** `repositories` de type `path` vers le clone. | **Gelé : A** (fait ; un lien symbolique vers l'extérieur ne se résout pas dans DDEV, JOURNAL D-004). |
| D9 | Emplacement des documents de chantier | **A.** `docs/chantier/ai-pages/` dans le clone, retirés ou réduits en ADR avant la PR publique. **B.** Hors dépôt. | **Gelé : A** |
| D10 | Modèle et clé | Modèle par défaut `claude-opus-5-5`, surchargeable par réglage (`generator_model`) ; clé lue dans `ANTHROPIC_API_KEY` uniquement, jamais en `module_config`. Effort par défaut `high`. | **Gelé** |

## 4. Architecture cible (sous les recos ci-dessus)

### 4.1 Modèle de contenu v2

- `cms_page_content` : `draft_document` (CLOB JSON), `draft_html` (rendu du
  brouillon), `published_document`, `published_html`, `published_at` ;
  suppression de `draft_project_data`, `draft_css` et `published_css` (D5 :
  purge, aucun contenu hérité ; la migration 2.0.0 vide `draft_html`,
  `published_html` et `published_at` des lignes existantes). Même évolution
  pour `cms_block_content`.
- Nouvelle table `cms_skill` (D7) : `code` unique, `name`, `description`,
  `body` (CLOB), `origin` (`module`, `theme`, `site`), `position`, `enabled`,
  `shipped_body` (la version livrée, pour « restaurer »), `updated_by`,
  timestamps. Semée à l'activation et à la mise à jour depuis
  `skills/*/SKILL.md` du module et du thème actif (un skill déjà modifié par le
  site n'est pas écrasé, seul `shipped_body` l'est).
- Nouvelle table `cms_page_generation` : `page_id`, `locale`, `kind`
  (`brief`, `revision`, `section`), `prompt`, `request_id`, `model`,
  `input_tokens`, `output_tokens`, `cache_read_tokens`, `stop_reason`,
  `duration_ms`, `status`, `error`, `created_by`, `created_at`. C'est
  l'historique conversationnel ET le journal de coût.
- `cms_page_revision` : `document` + `html` à la place de `project_data`
  (table toujours sans lecteur : à trancher en phase 1, garder ou supprimer).
- Document : `{"version":1,"sections":[{"id":"s1","component":"Cms:Hero","props":{...}}]}`.
  Un schéma JSON est dérivé du catalogue (D2) ; les props de type `html` passent
  par `PublishedContentSanitizer::html`, les props `image` sont des ids de la
  médiathèque, les props `reference` (produit, catégorie, page, menu, formulaire)
  sont des ids vérifiés.
- Migration `Config/update/2.0.0.sql` + régénération de `TheliaMain.sql` ;
  tests `SchemaInstallTest`, `*MigrationTest`, `ModuleLifecycleTest` à étendre.

### 4.2 Catalogue de composants

- `Toolkit/ComponentCatalog` : lit l'allowlist du thème (`config/theliacms.yaml`,
  clé `generator.components`), résout chaque entrée dans la chaîne de thèmes
  (`{% props %}` des composants anonymes, propriétés publiques des `Base.php`),
  lit la story `toolkit.html.twig` comme exemple d'usage, et produit : le schéma
  JSON d'une section, la fiche texte injectée dans le prompt, la liste pour
  l'écran de réglages (remplace `EditorBaseBlocks` + `disabled_blocks`).
- `components/Cms/*` : les dix sections (`Hero`, `MediaText`, `Cta`, `Quote`,
  `Testimonials`, `Figures`, `Logos`, `Gallery`, `Faq`, `Section`) réécrites en
  composants Twig anonymes du module, props typées, markup `cms-*` conservé pour
  que `blocks.css` et `cms.css` continuent de s'appliquer ; déclarées au
  `/toolkit` par un `StoryProviderInterface`. Surchargeables par le thème en
  livrant `components/Cms/<Nom>/Base.html.twig`.
- Partials : inchangés ; exposés au catalogue comme composants « dynamiques »
  (`Partial/PartialDefinitionInterface::props` → schéma). Le rendu continue de
  passer par `data-cms-partial` substitué à la vue.

### 4.3 Générateur

- `Generation/PageGenerator` (final readonly) sur `anthropic-ai/sdk` ^0.59 :
  - system prompt = skills (D7) + fiche catalogue + règles de sortie, bloc
    `cacheControl` ephemeral sur le préfixe stable ; brief, page (titre, chapo,
    type, tag, langue), contexte boutique (nom, langues, devises) après le
    dernier point de cache ;
  - `outputConfig.format` = schéma JSON du document (structured outputs) ;
  - outils (`tool_choice: auto`, `strict: true`) : `search_products`,
    `search_categories`, `search_pages` (liens internes), `search_media`
    (images de la médiathèque par mots-clés, ids seulement), `read_component`
    (story complète d'un composant à la demande) ; boucle `toolRunner()` du SDK ;
  - streaming (`createStream`) pour les requêtes longues, `max_tokens` borné
    (16 000 pour une page), `effort` réglable, gestion de `refusal`,
    `max_tokens`, erreurs typées (429, 5xx, réseau) avec `fallbacks: "default"`
    sur l'API Claude ;
  - toute réponse passe par `Generation/DocumentValidator` (schéma + références
    + sanitizer) ; un document invalide est renvoyé au modèle une fois avec
    l'erreur, puis refusé ;
  - `Generation/GenerationLog` écrit `cms_page_generation` (usage, coût
    estimé à partir d'une grille de prix versionnée).
- Skills (`skills/*/SKILL.md`, front-matter `name`, `description`, corps,
  semés dans `cms_skill`, D7) : `page-structure` (une h1, hiérarchie, sections
  attendues par type de page), `copywriting` (ton, longueur, langue cible, pas
  d'invention de chiffres), `accessibility` (alt, contrastes, liens
  explicites), `seo` (meta, maillage interne via `search_pages`), `catalogue`
  (comment utiliser les composants et partials), plus `skills/` du thème.
  Chargement : lecture de `cms_skill` (actifs, dans l'ordre) ; description de
  chaque skill dans le prompt, corps complet des skills marqués `always`, les
  autres via l'outil `read_skill`. Le préfixe caché du prompt est invalidé à
  chaque modification d'un skill : acceptable, l'édition est rare.
- Écran **CMS > Skills** (phase 4) : liste, édition du corps (textarea, pas de
  WYSIWYG), activation, ordre, « restaurer la version livrée », droit
  `admin.cms.settings`.
- Pas d'appel réseau dans les tests : client SDK construit sur un transport
  `Psr\Http\Client` factice qui rejoue des réponses enregistrées (fixtures JSON)
  ; un jeu d'évaluation manuel (`tests/eval/`, 10 briefs × rubrique) pour
  juger la qualité, lancé à la main.

### 4.4 Back-office

- Route `/admin/cms/pages/{id}/content` remplace `/builder` : brief (texte,
  ton, public, sections souhaitées, longueur), bouton « Générer », flux SSE
  (D6) affichant l'avancement, aperçu rendu dans un iframe (réutilise
  `PreviewLink` sur le brouillon), fil des itérations, régénération d'une
  section, « Publier » (pipeline existant). Stimulus + Turbo, pas de bundle
  JS externe ; assets du module servis par `module_asset()`.
- Même écran pour les blocs réutilisables et d'item (D4), avec un brief orienté
  « bloc » et un catalogue restreint.
- Réglages : `generator_model`, `generator_effort`, `generator_max_tokens`,
  `generator_monthly_budget` (alerte, pas de blocage), liste des composants
  actifs (remplace `disabled_blocks`), `heading_check_mode` conservé.
- ACL : nouveau droit `admin.cms.generate` (générer coûte de l'argent),
  distinct de `admin.cms.page`.

### 4.5 Rendu

- `Rendering/DocumentRenderer` : document → HTML par
  `{{ component(name, props) }}`, enveloppé dans `<div class="cms-page-content">`
  (classe conservée, `PageContentNormalizer::CONTAINER_CLASS`), exécuté à la
  sauvegarde (brouillon) et à la publication (puis `ImageRewriter`, vide, texte
  d'exemple, `HeadingChecker` **désormais dans le pipeline pour tous les chemins**).
- Front : `cmspage.html.twig` inchangé ; `<style>` inline seulement si
  `published_css` hérité non nul.
- `MediaUsageFinder` et `BlockUsageFinder` lisent le document (ids) et non plus
  une regex sur le JSON GrapesJS.

### 4.6 Suppression

Liste exhaustive dans le rapport de cartographie §4 : dépendance Composer,
contrats et alias du bundle, `Builder/{CmsBuilderConfig,ActiveThemeCanvas,ThemeCanvas,CanvasStylesheetProviderInterface,EditorBaseBlocks,InitialCanvas,PageContentNormalizer (partie body/GrapesJS)}`,
`Media/{LibraryImageUploader,LibraryImageCatalog,Admin/CmsMediaController}`
(routes renommées `admin.cms.media.*`), `assets/src/*`, `assets/package.json`,
`assets/build.mjs`, artefacts `editor.{js,css}`, `templates/backOffice/default-twig/{pages,blocks}/builder.html.twig`,
`asset_mapper.excluded_patterns` dans `TheliaCMS.php:340-349`, tests
`EditorLabelsTest`, `EditorPluginListTest`, `ThemeCanvasTest`,
`ThemeCanvasDeclarationTest`, `BuilderImageDeletionTest`, `InitialCanvasTest`,
`CanvasStylesTest`, `EditorBaseBlocksTest`, `docs/editor-canvas.md`, textes
« visual page builder » (`module.xml`, `composer.json`, README, guide PDF).

## 5. Phases

Une branche, une PR par phase empilée sur la précédente (ou un seul merge en fin
de chantier si le dépôt public ne doit pas montrer d'état intermédiaire : à
trancher avec D9). Chaque phase se termine par : tests unitaires et
d'intégration du module verts dans l'hôte, `php-cs-fixer` et PHPStan de l'hôte
verts sur le module, `JOURNAL.md` à jour, rapport de fin de phase.

### Phase 0 — Cadrage et socle (faite le 2026-10-20)

- [x] Branche `feat/ai-page-generation`, `docs/chantier/ai-pages/{PLAN,CHARTE,JOURNAL}.md`.
- [x] Décisions D1 à D10 gelées dans `CHARTE.md`.
- [x] Montage (D8) : clone dans `local/modules/TheliaCMS` de l'hôte,
      `thelia/cms-module` retiré de `require-dev` de l'hôte, ses dépendances
      requises directement (`openstudio/page-builder-bundle`,
      `symfony/html-sanitizer`, `thelia/thelia-library-module`),
      `bin/test-prepare` rejoué, base dev migrée en 1.5.0 par `module:refresh`,
      cache Propel `dev` reconstruit (JOURNAL D-005).
- [x] Baselines dans `baselines/` : `tests-unit-avant.txt` (558 OK),
      `tests-integration-avant.txt` (263 OK, 1 skipped), `site-avant.json`
      (export 1.5.0 du site local), `volumetrie-avant.txt`,
      `inventaire-avant.txt`, `blocks-catalogue-avant.html` (markup des dix
      blocs, produit par `dump-blocks.php`).
- [x] `anthropic-ai/sdk ^0.59` requis dans le module (`composer.json`) et
      installé dans l'hôte (0.59.0).
- [ ] **Clé API absente** : ni `ANTHROPIC_API_KEY` dans `.env.local` de l'hôte
      ni dans l'environnement du conteneur. Nécessaire à partir de la phase 3
      (évaluation manuelle) ; les tests n'en ont pas besoin.
- [ ] Non fait : capture d'écran des blocs sur Flexy (aucune page publiée
      localement, Playwright non installé) ; baseline de performance (aucune
      page publiée à mesurer). À prendre en phase 2 sur la page de test des
      dix sections, avant et après conversion.
- [ ] Rien n'est commité : `docs/chantier/` et `composer.json` du module sont
      en attente d'un feu vert de commit.

### Phase 1 — Modèle de contenu v2, sans IA (faite le 2026-10-20)

- [x] `Content/` : `Document`, `Section`, `SectionDefinitionInterface` (tag
      `thelia_cms.section`), `SectionCatalog`, `RichTextSection` (`cms:richtext`),
      `DocumentValidator`, `DocumentRenderer` (enveloppe `cms-page-content`),
      exceptions ; 15 tests unitaires.
- [x] Schéma : `draft_document` / `published_document` sur `cms_page_content`
      et `cms_block_content`, `document` sur `cms_page_revision` ; colonnes
      GrapesJS et CSS retirées ; `TheliaMain.sql` mis à jour à la main ;
      `Config/update/2.0.0.sql` replayable (purge seulement si une colonne 1.x
      existe) ; `module.xml` 2.0.0 ; CHANGELOG en tête.
- [x] Writers : `saveContent(Document)` valide, rend, assainit ; `publish`
      re-rend depuis le document, puis sanitizer, images, vide, texte
      d'exemple, **`HeadingChecker` en mode `block` sur tous les chemins**
      (`HeadingStructureException`) ; `unpublish` vide le snapshot ;
      révisions gardées (document + html). Même chose pour les blocs, sans
      contrôle des titres.
- [x] Lecteurs : `PublishedPage` sans `css` ni `layout` (`PageLayout`
      supprimé), `PublishedItemBlock` sans `css`, partial `cms-block` sans
      `<style>`, `cmspage.html.twig` sans bloc `stylesheets`, finders d'usage
      sur les documents.
- [x] Builder GrapesJS conservé jusqu'en phase 5 : son `save` enveloppe le HTML
      dans un document `cms:richtext`, le canvas repart toujours du HTML rendu ;
      `InitialCanvas` et `BuilderContent` supprimés ; le formulaire garde ses
      trois champs cachés (le composant du bundle les exige).
- [x] Export v2 ; un fichier v1 importe tout sauf les contenus, avec une
      alerte ; modèle de page v1 → page vide, avec une alerte.
- [x] Tests : fixtures sur le document (7 fichiers), `ContentMigrationTest`
      (1.5.0 → 2.0.0 joué deux fois, purge, fresh install intact),
      `PublishPipelineTest` (+4), `SiteRoundTripTest` (+1), `EmojiRoundTripTest`
      réécrit ; `InitialCanvasTest` supprimé. Deux tests vus rouges par
      sabotage avant d'être crus.
- [x] Gate : unit 565 OK, intégration 270 OK (1 skipped préexistant),
      php-cs-fixer de l'hôte sans écart sur les fichiers touchés.
- Écarts avec le plan initial : pas de notice back-office à la montée de
  version (le CHANGELOG et `UPDATE.md` porteront l'avertissement, à écrire en
  phase 5 avec le README) ; `HeadingChecker` reste dans `Builder/` jusqu'au
  nettoyage de la phase 5 ; les pages légales semées en 1.x perdent leur texte
  à la montée (conséquence de D5, la base de dev l'a montré : 0 page
  publiable après migration).

### Phase 2 — Catalogue et composants (faite le 2026-10-20)

- [x] Props typées : `Content/Prop`, `PropType` (text, html, integer, boolean,
      choice, image, url, items, reference, json), `PropSchema` (normalisation
      stricte, `toJsonSchema()` prêt pour la phase 3).
- [x] `SectionDefinitionInterface` étendue (`label`, `description`, `schema`,
      `sample`), `SectionProviderInterface`, `SectionCatalog` à providers.
- [x] Dix sections `Cms:*` dans `Content/Section/` sur `AbstractTemplateSection`
      (templates `templates/front/sections/<slug>.html.twig` surchargeables par
      `cms/sections/<slug>.html.twig`, images résolues par id via
      `SectionImages`, `uid` stable pour les `aria-labelledby`). Même jeu de
      classes `cms-*` que la baseline (diff vide hors conteneur). Huit écrites
      par deux agents, relues sur pièces.
- [x] `cms:richtext` enveloppé dans `.cms-richtext` (largeur des sections,
      `blocks.css`).
- [x] Partials : `Content/Partial/PartialSection` + provider, nom
      `Partial:<name>`, rendu = placeholder substitué à la vue.
- [x] Composants du thème : `Content/Theme/ThemeSections` (allowlist
      `sections.components` de `config/theliacms.yaml`, héritage de chaîne),
      `ComponentPropsReader` (`{# @prop #}` + `{% props %}`, camelCase ↔
      snake_case), `ThemeComponentSection` (rendu par `ComponentRendererInterface`),
      provider. Rien d'ouvert par défaut ; Flexy ne déclare rien.
- [x] `cms_section(name, props)` dans `CmsExtension` ; stories dans
      `templates/front/sections/toolkit/`, `SectionStories` enregistré si Flexy
      est là : `/toolkit` liste les dix sections sous « CMS » (vérifié HTTP).
- [x] Tests : unit 580 OK ; intégration 331 OK (1 skipped) dont 12 fichiers
      `Tests/Integration/Content/**` (sections, thème, partials) ; deux tests
      vus rouges par sabotage des templates (agents).
- [x] Preuve : page « Preuve phase 2 : onze sections » importée (`var/proof-phase2.json`,
      page 5 de la base de dev) et publiée par `thelia_cms:publish`, rendue sur
      Flexy avec `blocks.css` + `cms.css` : `baselines/page-preuve-phase2.{html,png}`
      (1440 px). Les images restent des emplacements vides : la médiathèque de
      dev est vide.
- [x] Docs : `docs/sections.md`, README (section « Sections »), CHANGELOG.
- Écarts avec le plan initial : sections rendues par templates du module
  (mécanisme des partials) et non par des composants UX anonymes du module,
  qui ne seraient pas trouvables sous `@Flexy` ni surchargeables par fichier ;
  écran de réglages inchangé (phase 4) ; `EditorBaseBlocks`/`BlockCatalog`
  toujours là pour le panneau GrapesJS (phase 5).

### Phase 3 — Générateur

- SDK, `PageGenerator`, skills, outils, validation, log, coût, fallbacks,
  gestion des refus et des erreurs.
- Transport factice et fixtures enregistrées ; tests unitaires du prompt
  (snapshot du system prompt, stabilité du préfixe caché) ; test d'intégration
  du pipeline brief → document → html → publish.
- Commande `thelia_cms:generate --page --locale --brief` (utile pour la recette
  et pour les scripts).
- Évaluation manuelle : 10 briefs réels (thème e-commerce), rubrique de 8
  critères (une h1, sections pertinentes, aucun chiffre inventé, liens internes
  valides, images de la médiathèque seulement, longueur, langue, accessibilité) ;
  résultat consigné dans `JOURNAL.md` avec le coût mesuré par génération.

### Phase 4 — Back-office

- Écran `/admin/cms/pages/{id}/content`, SSE, aperçu, itérations, régénération
  de section, publication ; liens mis à jour (`PageListPresenter`, dashboard,
  `_sample_text_warning`, `CmsTemplateAdminController`).
- Droit `admin.cms.generate`, `EditorProfileSeeder`, `WriteRoutesTest`,
  `WriteNeedsTheTokenTest`, `WritePermissionTest`.
- Blocs réutilisables et d'item (D4) sur le même écran.
- Écran **CMS > Skills** (D7).
- Preuve : parcours enregistré au navigateur (créer une page, générer, itérer,
  publier, voir le front), axe-core sur l'écran et sur la page publiée.

### Phase 4 bis — Édition manuelle des textes (D3, Tiptap)

- Sur l'écran de contenu, chaque prop de type `html` d'une section s'édite
  avec l'éditeur du module `thelia/tiptap-module` (déjà dans l'hôte, à
  déclarer en `suggest` du module, dégradé en textarea s'il est absent) ; les
  props `text`, `choice`, `image`, `reference` s'éditent dans un formulaire
  généré depuis le schéma du composant.
- Une édition manuelle met à jour le document, re-rend `draft_html`, et
  s'inscrit dans l'historique (`cms_page_generation`, `kind = manual`) pour que
  l'itération suivante parte du texte corrigé.
- Preuve : corriger un mot, publier, le voir sur le front sans génération.

### Phase 5 — Suppression et nettoyage

- Tout le §4.6 ; `composer.json` du module ; `README.md`, `CHANGELOG.md`,
  `module.xml` 2.0.0, `docs/` (nouveau `docs/generating-a-page.md`,
  `docs/creating-a-component.md` remplace `creating-a-block.md`), guide
  webmaster (texte ; captures à refaire, hors périmètre si pas de maquette).
- Vérification sur pièces : `grep -ri "grapes\|PageBuilderBundle\|page-builder"`
  retourne zéro hors CHANGELOG, vu tomber sur un cas connu avant d'être cru.

### Phase 6 — Recette et restitution

- Non-régression : baselines de la phase 0 rejouées (tests, export, pages
  héritées encore servies à l'identique, cache tags, sitemap, recherche).
- Parcours utilisateur, recette graphique sur Flexy, performance (rendu d'une
  page publiée avant/après, temps et coût d'une génération).
- Cahier de recette lisible par un non-développeur ; restitution avec
  arbitrages restants ; capitalisation (`.claude/rules/`, skill projet).

## 6. Pièges vérifiés à garder sous les yeux

- `HeadingChecker` n'est appliqué que depuis le builder (`CmsPageBuilderController.php:175`) :
  à mettre dans le pipeline pour la commande et l'écran réglages.
- Le HTML de page importé n'est pas assaini (`ImportExport/SiteImporter.php:348-355`) :
  l'import v2 doit valider le document comme une génération.
- `BlockReference` détecte l'usage d'un bloc par regex `"block": N`
  (`Block/BlockReference.php:25`) ; `MediaUsageFinder.php:144-165` lit
  `draft_project_data`. Les deux doivent lire le document.
- Statut dérivé en deux implémentations (`PageStatus`, `PageStatusCriteria`)
  tenues en phase par `PageStatusCriteriaTest` ; `unpublish` laisse
  `published_html` en base.
- Tailwind `source(none)` : une classe hors des fichiers scannés n'existe pas.
  Aucune classe utilitaire ne doit venir du modèle ; seules les props des
  composants sont générées.
- `cms-page-content` est ciblée par `SiteStylesCss.php:34`, `cmspage.html.twig:37`,
  `blocks.css:64-154` : la classe reste.
- Le bundle n'est enregistré qu'en dev et test dans l'hôte : ne pas chercher à
  « réparer » la prod, il disparaît en phase 5.
- Les artefacts compilés sont commités et le build cherche le bundle en remontant
  jusqu'à 8 niveaux (`assets/build.mjs:11-33`) : ne pas relancer `npm run build`
  pendant le chantier.
- Emoji : stockage en références numériques (`Storage/*`, `EmojiRoundTripTest`) :
  le document JSON passe par le même encodage.
- Modèles 5.x : `budget_tokens` et `tool_choice: any|tool` renvoient 400 ;
  thinking toujours actif ; `stop_reason = refusal` à traiter ; rétention de
  données 30 jours requise pour Fable 5.1 si jamais sélectionné.

## 7. Prompt de reprise (contexte vierge)

> Lis dans cet ordre `docs/chantier/ai-pages/CHARTE.md`, `PLAN.md`, `JOURNAL.md`,
> puis `git log --oneline main..HEAD` dans `~/workzone/thelias/thelia-cms-ai/local/modules/TheliaCMS`.
> Vérifie l'état réel : `git status`, dernier rapport de phase dans `JOURNAL.md`,
> tests du module dans l'hôte `~/workzone/thelias/thelia-cms-ai`
> (`ddev exec vendor/bin/phpunit -c local/modules/TheliaCMS` puis
> `-c local/modules/TheliaCMS/phpunit.integration.xml.dist`). Reprends à la
> première étape non cochée de la phase en cours ; ne rouvre aucune décision
> gelée de la charte sans l'écrire dans les points ouverts du journal.
