# Charte de mission — pages générées par l'API Claude

Ouverte le 2026-10-20. Référentiel de la revue finale : le code livré se relit
contre cette charte.

## Intake

| Question | Réponse (2026-10-20) |
|---|---|
| Périmètre | La partie « pages » du module TheliaCMS est refaite de zéro : GrapesJS et `openstudio/page-builder-bundle` retirés, génération à partir d'un brief envoyé à l'API Claude, cadrage par skills, composition sur les éléments du toolkit du thème. Détail dans `PLAN.md` §1. |
| Ticket | Aucun : chantier déclaré **hors-ticket** par le dev. |
| Dépôt de travail | Clone `git@github.com:thelia-modules/TheliaCMS.git` dans `~/workzone/thelias/thelia-cms-ai/local/modules/TheliaCMS`, branche `feat/ai-page-generation` depuis `7dd050a` (1.5.0). |
| Banc d'essai | `thelia/thelia` `main` dans `~/workzone/thelias/thelia-cms-ai` (DDEV, PHP 8.3.30, Flexy, default-twig). Les modifications non commitées de `composer.json` et `config/bundles.php` de l'hôte sont **hors chantier** et ne sont ni commitées ni écartées par ce chantier. |
| Référence fonctionnelle | Le module 1.5.0 tel qu'il tourne dans l'hôte (pages, blocs, publication), README et `docs/` du module. |
| Référence visuelle | Rendu actuel des dix blocs `cms-*` sur Flexy (captures en baseline, phase 0). Aucune maquette du nouvel écran back-office : il sera construit sur les composants du thème `default-twig`, constat d'écart contre le thème, pas contre une maquette. |
| Données et connecteurs | API Claude (clé `ANTHROPIC_API_KEY` de l'hôte, jamais lue ni affichée) ; base DDEV locale (8 pages non publiées, 0 bloc). Aucun écrit chez un tiers hors appels de génération. |
| Conventions | Celles du module (PSR-12, `final readonly`, `declare(strict_types=1)`, tests unit + integration dans l'hôte, CI GitHub sur PHP 8.3/8.4/8.5) et le socle `~/.claude/CLAUDE.md`. L'hôte n'a pas de `.claude/` : aucune convention projet supposée au-delà. |

## Parcours retenus et critères de validation

À figer en phase 0 après les décisions. Proposition :

1. **Créer et générer une page** : nouvelle page, brief, génération, aperçu,
   publication, page visible sur le front avec les sections demandées, une h1,
   images de la médiathèque uniquement, Cache-Tag présent.
2. **Itérer** : demander une modification, voir la section changée et les autres
   intactes, historique visible.
3. **Montée de version** : un site 1.5.0 avec pages publiées passe en 2.0.0 ;
   les pages restent dans l'arbre avec titre, adresse et SEO, sans contenu,
   dépubliées (plus de 500, un 404 propre ou la page vide : à fixer en phase 1) ;
   le message de migration l'annonce.
4. **Bloc d'item** : générer un bloc pour un produit, le voir sur la fiche
   produit via `cms_item_block()`.
5. **Export / import** : round-trip d'un site contenant une page v2 et une page
   héritée.
6. **Refus et erreurs** : clé absente, refus du modèle, dépassement de
   `max_tokens`, coupure réseau : message clair, rien de corrompu en base.

## Décisions gelées

| # | Décision | Choix | Date | Pourquoi |
|---|---|---|---|---|
| D1 | Forme du contenu | **A** : document JSON de sections (composant + props), rendu HTML par Twig à la sauvegarde et à la publication | 2026-10-20 | Contraignable par schéma, validable serveur, régénérable par section ; le markup vient de composants déjà scannés par Tailwind (voir JOURNAL D-002). |
| D2 | Palette | **A** : composants Flexy en allowlist du thème + dix sections CMS converties en composants Twig du module + partials dynamiques | 2026-10-20 | Flexy n'a aucun composant de page éditoriale ; les sections `cms-*` existantes deviennent des composants du toolkit, surchargeables. |
| D3 | Édition après génération | **A + C** : brief puis itération conversationnelle et régénération par section, **et** édition manuelle des textes riches avec Tiptap (module `thelia/tiptap-module`), dans le chantier, phase 4 bis | 2026-10-20 | Un éditeur doit pouvoir corriger une virgule sans payer une génération. |
| D4 | Blocs réutilisables et d'item | **A** : même générateur, même document, écran « bloc » | 2026-10-20 | Même builder aujourd'hui ; abandonner casserait `cms_item_block()` et les cartes produit. |
| D5 | Contenus existants | **B** : purge. La migration 2.0.0 supprime le contenu GrapesJS (`draft_project_data`, `draft_*`, `published_*`) ; les pages existantes repartent vides, leurs réglages (titre, slug, SEO, arbre, tags, type) restent | 2026-10-20 | Choix du dev. Conséquence à écrire en tête du CHANGELOG et dans `UPDATE` du module : une 1.x avec des pages en ligne perd leur contenu à la montée en 2.0.0, l'export 1.x reste le seul moyen de garder l'HTML. |
| D6 | Exécution | **A** : requête SSE synchrone depuis le back-office, `max_tokens` borné, état persisté en fin de flux | 2026-10-20 | Aucune file de jobs dans l'hôte ; zéro infrastructure. Évolution B (jobs + worker) documentée dans `PLAN.md` si des hébergements coupent les requêtes longues. |
| D7 | Skills | **B** : éditables en back-office. Le module livre les skills par défaut en fichiers (`skills/*/SKILL.md`), le thème peut en livrer ; l'activation les sème dans une table `cms_skill` ; l'écran **CMS > Skills** les édite (corps, actif, ordre) avec « restaurer la version livrée » | 2026-10-20 | Choix du dev : le cadrage rédactionnel appartient au site, pas au code. |
| D8 | Montage hôte | **A** : clone placé directement dans `local/modules/TheliaCMS` de l'hôte (dossier ignoré par git ; un lien symbolique vers l'extérieur ne se résout pas dans le conteneur DDEV), `thelia/cms-module` retiré de l'hôte, ses dépendances requises directement (procédure du CI du module) | 2026-10-20 | Même disposition que le CI ; autorisation explicite du dev pour toucher `composer.json` et `composer.lock` de l'hôte. |
| D9 | Docs de chantier | **A** : `docs/chantier/ai-pages/` dans le clone, réduites en ADR avant la PR publique | 2026-10-20 | Un plan survit aux sessions seulement s'il est versionné. |
| D10 | Modèle et clé | `claude-opus-5-5` par défaut (`generator_model` surchargeable), `effort` `high`, clé lue dans `ANTHROPIC_API_KEY` seulement | 2026-10-20 | Recommandation du skill `claude-api` ; une clé en `module_config` finirait dans un export. |

## Hors périmètre explicite

Voir `PLAN.md` §1. Ajouts au fil de l'eau :

- (vide)
