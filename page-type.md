# Page Type

## Mission

Crééer un système de "page type" dans le module Thelia CMS, afin de discriminer l'affichage front des différentes pages.
Ex :  je tiens un blog de cuisine. Je vais avoir des articles "recette" et des articles "nouveautés". ces deux type de contenues n'auront pas le même design, et ne rendront pas le même template twig.
Je veux faire évoluer le module pour ajouter une notion de type de page. Une page ne peut avoir q'un seul type.
Si je reprends mon exemple je vais créer 2 nouveaux types de page :
- news
- recipe
Lorsque j'editerais ma page "gratin de pates" je pourrais selectionner dans le select "page-type" la valeur recipe.

Cette nouvelle fonctionnalité a pour but de remplacer la class enum `PageLayout` qui est trop restrictive en état

## Front

Il faut que le module rende  le contenu de "gratin de pates" sur un fichier twig qui aura la nomenclature cmspage-{pageType}, donc dans notre cas "cmspage-recipe". Ce fichier peut exister dans le module mais également dans le theme frontOffice courant.
Si le fichier cmspage-{pageType} n'existe pas, fallback sur le fichier cmspage.html.twig qui existe déjà

(Nomenclature `cmspage-{pageType}` retenue à la place de `cms-{pageType}` : voir décision D3.)
## Inspiration

Cette demande est à peu près la même chose que ce qu'il y a acuellement sur le module Thelia Page => https://github.com/thelia-modules/Page



---

# Spécification

## User story

En tant qu'administrateur du site, je déclare des types de page (`news`, `recipe`…) et j'attribue un type à chaque page, pour que les pages d'un même type s'affichent avec leur propre gabarit en front, sans toucher au module.

## Décisions gelées

| # | Décision | Pourquoi |
|---|---|---|
| D1 | Un type de page est un **code technique seul** (minuscules, chiffres, tirets), sans libellé ni traduction. La liste des types est gérée en back-office. | Le type ne sert qu'à choisir un gabarit ; le select de la page affiche le code. |
| D2 | La page référence son type **par le code**, dans une colonne `VARCHAR` de `cms_page` (la colonne `layout` actuelle, renommée `page_type`). Pas de clé étrangère, pas d'id. | Export / import / modèles de page portables d'un site à l'autre ; migration directe des valeurs actuelles. |
| D3 | Gabarit d'un type : **`cmspage-{code}.html.twig`**. | `cms-{code}` entre en collision avec `cms-search.html.twig` déjà livré par le module : un type `search` rendrait la page de résultats de recherche. |
| D4 | Ordre de résolution : `cmspage-{code}` du thème → `cmspage-{code}` du module → `cmspage` du thème → `cmspage` du module. | Le thème prime toujours sur le module, comme aujourd'hui. |
| D5 | `PublishedPage` gagne `pageType` (string) ; `layout` (enum `PageLayout`) **reste, déprécié**, calculé depuis le type. Suppression à la prochaine version majeure. | `PublishedPage` est le contrat passé aux hooks `cmspage.*` ; le module est publié en 1.0.0 stable. |
| D6 | Développé sur `feat/cms-page-type`, empilée sur `feat/general-page-infos` (chapo, description, image). | Mêmes fichiers touchés (schema, `PublishedPage`, formulaire de page, import / export). |

### Précisions sur D4

- « Thème » = ce que le parseur Twig du front trouve pour ce nom : le thème actif, ses parents, **et les dossiers `templates/frontOffice/<thème|default>/` des autres modules** (`TwigEngine/Template/TwigParser.php:178-203`). Un module tiers qui livre `cmspage-recipe.html.twig` est donc trouvé à cette étape ; c'est le canal prévu pour qu'un module fournisse le gabarit d'un type.
- « Module » = `templates/front/` de TheliaCMS uniquement.
- `ParserInterface` n'a pas de méthode d'existence pour un gabarit namespacé (`@TheliaCMSModule/…`) : le test d'existence du module passe par le système de fichiers ou le chargeur Twig, au choix du dev.

## Hypothèses

- H1. La liste des types est une table dédiée (`cms_page_type`, un code unique par ligne). Le modèle Propel généré ne doit pas s'appeler `CmsPageType` : c'est déjà le nom du formulaire `Page\Admin\CmsPageType`, que `CmsPageAdminController` importe. Nom du modèle (`phpName`) au choix du dev.
- H2. Le type `default` est un **type système** : présent dès l'installation, non supprimable, valeur par défaut d'une page. Sans gabarit `cmspage-default`, il rend `cmspage` : comportement actuel inchangé.
- H3. Les valeurs actuelles `full-width` et `landing` deviennent des types ordinaires, créés par la migration. Une installation neuve joue `TheliaMain.sql` puis toutes les migrations (`TheliaCMS.php:144-155`) : elle les reçoit aussi.
- H4. Le code d'un type est **immuable** une fois créé (il est lié à un nom de fichier). Pour « renommer », on crée le nouveau type, on bascule les pages, on supprime l'ancien.
- H5. La migration part dans un **`Config/update/1.2.0.sql`** (module en 1.2.0), pas dans `1.1.0.sql` : `module.xml` est déjà en 1.1.0 sur la branche sans tag publié, et un site qui a installé la branche ne rejouerait pas un ajout à `1.1.0.sql` (`TheliaCMS::migrationsBetween()`, comparaison stricte). Si la 1.1.0 n'est jamais publiée seule, les deux versions sortent ensemble.

## Task back

### Données

- Table `cms_page_type` : code unique (`VARCHAR(50)`), horodatage. Déclarée dans `schema.xml` et `TheliaMain.sql`.
- `cms_page.layout` renommée `page_type`, élargie à 50, `NOT NULL DEFAULT 'default'`, par `CHANGE COLUMN` (`RENAME COLUMN` exige MySQL 8 / MariaDB 10.5). Pas d'index à reprendre sur cette colonne (`TheliaMain.sql:28-30`).
- Script `1.2.0.sql` **rejouable**, même idiome que `1.1.0.sql` (test dans `information_schema` + `PREPARE`, pas de `IF NOT EXISTS`) : création de la table, renommage de la colonne, insertion (`INSERT IGNORE`) de `default`, `full-width`, `landing` et des autres valeurs présentes dans `cms_page.page_type` **dont le format est valide** ; les pages portant une valeur invalide repassent à `default`.

### Règles

- Format du code : minuscules ASCII, chiffres et tirets simples, ni en tête ni en fin, 50 caractères max (`PageTypeCode`, motif ancré avec le modificateur `D` : sans lui `$` accepte un saut de ligne final ; même garde dans la migration SQL). Le code entre dans un nom de gabarit : aucune autre forme (`/`, `.`, majuscule, espace, vide).
- Une page ne peut porter qu'un type existant ; un type inconnu à l'enregistrement est refusé par le formulaire.
- Suppression d'un type refusée tant qu'une page le porte, **corbeille comprise** (une page restaurée tomberait sur un type inexistant). Le message donne le nombre de pages concernées. Les modèles de page enregistrés ne bloquent pas la suppression (voir « Modèles de page »).
- **Le résolveur de gabarit revalide le format du code** avant de construire `cmspage-{code}` ; un code invalide (base modifiée à la main) se traite comme `default`. Pas de requête sur `cms_page_type` au rendu : un code valide mais sans ligne dans la table retombe simplement sur `cmspage` par D4.
- Le type est une propriété de la page, pas de son contenu : il s'applique **dès l'enregistrement**, sans republication, comme `layout` aujourd'hui.

### Rendu front

- `PublishedPageRepository::find()` et `draft()` remplissent `pageType` ; `layout` déprécié vaut `PageLayout::fromStorage(pageType)` (un type hors enum donne `Default`). `withHtml()` transporte `pageType`.
- Un **résolveur unique** de gabarit (thème, puis module, puis replis de D4), utilisé par `CmsPageRenderer` **et** par l'écran BO des types, pour que l'indicateur ne puisse pas diverger du rendu. Les autres appelants de `ThemeTemplateRenderer::render()` (`PartialFragmentRenderer`, `CmsSearchController`) ne changent pas de comportement.
- La prévisualisation (`draft()`), la page 404 (`Vitrine/NotFoundPageListener.php:93`) et la page de maintenance (`Vitrine/MaintenanceListener.php:128`) passent par `CmsPageRenderer` : elles suivent le type de la page qu'elles affichent.
- La classe `cms-page--{code}` reste posée sur le `<body>` par le gabarit de repli, désormais depuis `pageType`, pour les thèmes qui la ciblent (aucun style du module ni des thèmes `work` / `flexy` ne la cible aujourd'hui).

### Cache HTTP

- L'enregistrement de la page (`saveDraft`) ne purge pas le cache aujourd'hui (`CmsPageWriter.php:118-120`, à la différence de `publish`, `:181`) : un changement de `layout` ou de SEO reste invisible tant que la page est en cache. Défaut existant, aggravé par le type, qui change tout le gabarit. **L'enregistrement des réglages de la page purge `CacheTags::forPage()`.**
- Poser un fichier de gabarit ne purge rien : avec `http_cache_ttl > 0`, la page change d'aspect à l'expiration du cache. À documenter (README et `docs/shared-cache.md`).

### Modèles de page

- `cms_page_template.payload` stocke le document d'export d'une page (`Config/schema.xml:461-484`) ; des payloads déjà en base portent la clé `layout`.
- `SiteImporter::importPageFrom()` (« créer depuis un modèle ») lit `page_type`, à défaut `layout`. **Type absent du site : `default`, jamais créé** (décision du dev, 2026-10-08) : partir d'un modèle ne demande que le droit d'écrire des pages, et ne doit pas recréer un type supprimé dans les réglages. Seul l'import de site (geste d'exploitant) crée les types manquants.

### Points de passage de `layout` à reprendre

- Formulaire de page (`Page/Admin/CmsPageType.php`) et son gabarit (`templates/backOffice/default-twig/pages/edit.html.twig`, `form_row(form.layout)`) : le select liste les types de la table, `default` en premier.
- Contrôleur et writer (`CmsPageAdminController`, `CmsPageWriter::duplicate()` : la copie garde le type).
- Seeder des pages légales : type `default`.
- Export de site : l'aller-retour conserve le type de chaque page.
- Import de site : lit `page_type`, à défaut `layout` (exports 1.0.x) ; même règle que les modèles pour un type absent, signalé dans le rapport d'import.
- I18n : libellé `'Layout'` (`I18n/fr_FR.php`, `I18n/en_US.php`) remplacé par « Type de page ».
- Tests qui posent `setLayout()` : `CmsIntegrationTestCase`, `PublishPipelineTest`, `AdminScreenTestCase`.

## Task back-office

- Écran **CMS > Réglages > Types de page**, sous `/admin/cms/settings/…` pour hériter de la garde `admin.cms.settings` (`Security/CmsAdminGuard.php:52-62`) ; écritures soumises aux droits CREATE / DELETE de cette ressource.
- Liste, ajout, suppression. Chaque ligne indique le gabarit qui servira en front : **thème** (thème actif, ses parents ou un module tiers), **module** (TheliaCMS), ou **repli `cmspage`**.
- **Ne pas** résoudre l'indicateur en appelant `ThemeTemplateRenderer` dans une requête admin : il configure le parseur Twig partagé sur le thème front, qui ajoute les dossiers du thème au chargeur des écrans BO (`Front/ThemeTemplateRenderer.php:68-74`). Le thème front actif et ses parents se lisent sans parseur (modèle : `Builder/ActiveThemeCanvas.php:36-41`).
- Ajout : un seul champ, le code ; erreur explicite si le format est refusé ou le code déjà pris.
- `default` affiché sans action de suppression.
- Traductions fr_FR / en_US de l'écran et du champ « Type de page ».

## Documentation

- README : section « Types de page » (déclarer un type, nommer le gabarit, ordre de résolution, livraison d'un gabarit par un module tiers, effet du cache HTTP) ; `pageType` ajouté à la description de l'objet `page` des hooks.
- CHANGELOG 1.2.0 : ajout des types, dépréciation de `PublishedPage::$layout` et de `PageLayout`, **note de mise à jour** : le schéma change, vider `var/propel/<env>` et mettre à jour le module avant de servir les pages (sinon `Unknown column 'page_type'` sur toute page CMS).
- `PageLayout` et `PublishedPage::$layout` marqués `@deprecated` avec la version de suppression.
- Guide webmaster `docs/guide/guide.html` (« mise en page », capture `02-page-reglages.jpg`) : à mettre à jour.

## Critères d'acceptation

### Pour le dev (tests automatisés)

- [ ] Résolution du gabarit : les 4 cas de D4 couverts (thème seul, module seul, aucun → `cmspage` thème, rien → `cmspage` module). Test vu rouge en inversant l'ordre thème / module.
- [ ] Un code `../x`, `Recipe`, `a.b`, `-a`, vide ou de 51 caractères est refusé à la création ; `recipe` et `my-type-2` sont acceptés.
- [ ] Une page dont `page_type` vaut `../x` en base (écrit en SQL) se rend avec `cmspage`, sans erreur. Test vu rouge en retirant la revalidation.
- [ ] Suppression d'un type porté par une page en corbeille : refusée. `default` : suppression refusée.
- [ ] Migration : test **non transactionnel** qui remet le schéma en 1.1.0 (`layout` VARCHAR(20), sans `cms_page_type`), joue `1.2.0.sql` deux fois, vérifie valeurs et types, puis restaure. Un `ALTER` committe implicitement : un `IntegrationTestCase` transactionnel ne prouve rien ici.
- [ ] Aller-retour export / import (`SiteRoundTripTest`) : le type de chaque page survit ; un export 1.0.x (clé `layout`) s'importe.
- [ ] Création depuis un modèle enregistré dont le payload porte `layout` : la page reçoit ce type.
- [ ] `PublishedPage::$layout` : `full-width` → `FullWidth`, `recipe` → `Default`.
- [ ] Duplication d'une page : la copie garde le type.
- [ ] Changer le type d'une page en ligne purge ses tags de cache.
- [ ] Gate : `ddev composer ci` vert, plus le PHPStan du périmètre et les suites d'intégration du module.

### Pour le CP (recette fonctionnelle)

- [ ] Dans CMS > Réglages, je crée les types `news` et `recipe` ; ils apparaissent dans la liste avec la mention « gabarit par défaut ».
- [ ] J'édite la page « Gratin de pâtes », je choisis `recipe` dans « Type de page », j'enregistre : la valeur est conservée à la réouverture.
- [ ] Sans fichier `cmspage-recipe.html.twig`, la page s'affiche comme avant.
- [ ] Une fois le fichier `cmspage-recipe.html.twig` posé dans le thème, la page s'affiche avec ce gabarit ; les autres pages ne changent pas. L'écran des types indique « thème » pour `recipe`.
- [ ] Je change le type d'une page déjà en ligne : le nouveau gabarit s'affiche sans republier.
- [ ] Je ne peux pas supprimer `recipe` tant qu'une page l'utilise, même en corbeille ; le message me dit combien.
- [ ] Les pages existantes avant la mise à jour, la page 404 et la page de maintenance s'affichent à l'identique.

## Points à trancher

- P1. **Canevas de l'éditeur** : il reproduit le rendu front (`docs/editor-canvas.md`) mais ne connaît qu'un gabarit. Un type au gabarit très différent s'éditera dans le cadre standard. Proposé : hors périmètre, signalé dans le README.
- P2. **Données propres à un type** (ex. temps de cuisson d'une recette) : hors périmètre. Le gabarit d'un type dispose de la page publiée (titre, contenu, chapo, description, image une fois `feat/general-page-infos` fusionnée).
- P3. Le select affiche le code brut (`full-width`). Si un libellé lisible devient nécessaire, il s'ajoutera plus tard sans casser D2.

## Sources

- Code : `Page/PageLayout.php`, `Page/PublishedPage.php`, `Page/PublishedPageRepository.php`, `Page/CmsPageRenderer.php`, `Front/ThemeTemplateRenderer.php`, `templates/front/cmspage.html.twig:20`, `Config/schema.xml` (`cms_page.layout`, `cms_page_template`), `Config/update/1.1.0.sql`, `ImportExport/SiteExporter.php`, `ImportExport/SiteImporter.php`, `Page/Admin/CmsPageWriter.php`, `Vitrine/NotFoundPageListener.php`, `Vitrine/MaintenanceListener.php`, `Builder/ActiveThemeCanvas.php`, `Security/CmsAdminGuard.php`.
- Résolution Twig côté core : `vendor/thelia/modules/TwigEngine/Template/TwigParser.php` (`supportTemplateRender`, `getTemplateSearchDirectories`).
- Collision de nommage : `templates/front/cms-search.html.twig`.
- Inspiration : module thelia-modules/Page (`page_type`, `page.type_id`).

## État au 2026-10-08

- Implémenté sur `feat/cms-page-type` (non commité), corrections de la review du 2026-10-08 comprises (constantes du renderer dépréciées au lieu d'être retirées, route `{code}` contrainte, droit vérifié dès la soumission, `PageTypeRepository` en lecture et `PageTypeWriter` en écriture, connexion explicite à l'import, nettoyage SQL par classe de caractères) : D1 à D6, migration `1.2.0.sql`, écran **CMS > Réglages > Types de page**, import / export / modèles, purge du cache à l'enregistrement, documentation (README, CHANGELOG, `docs/shared-cache.md`, guide HTML).
- Écart d'implémentation : les messages de contrainte du formulaire d'ajout sont traduits dans le form type (`PageTypeCreateType`), le validateur Symfony les cherchant dans le domaine `validators` où le module n'a pas de catalogue. Les autres formulaires du module ont le même trou, non traité ici.
- Reste ouvert : capture `docs/guide/img/02-page-reglages.jpg` et PDF du guide à régénérer ; P1 à P3.
