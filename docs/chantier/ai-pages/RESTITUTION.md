# Restitution de fin de chantier — pages générées par l'API Claude

2026-10-20, dépôt `thelia-modules/TheliaCMS`, branche `feat/ai-page-generation`
(six commits depuis `7dd050a`, 1.5.0 ; dernier `acc98e1`), hors ticket.

## Ce qui a été livré

| Phase | Porte de sortie | Vérifié par |
|---|---|---|
| 0. Cadrage | Charte, plan, journal, baselines (tests, export, volumétrie, rendu des dix blocs) | `CHARTE.md`, `baselines/` |
| 1. Document | Contenu = document de sections, validé, rendu à la sauvegarde et à la publication ; migration 2.0.0 (purge des contenus 1.x, D5) ; export v2 | 181 tests ajoutés, `ContentMigrationTest`, `SiteRoundTripTest` |
| 2. Catalogue | Dix sections `Cms:*`, `cms:richtext`, partials, composants du thème en allowlist ; `/toolkit` | page de onze sections rendue sur Flexy |
| 3. Générateur | Brief → document par l'API Claude, skills en table, outils de recherche, registre des appels, commande | brief 01 : 3 tours, 24 s, 0,094 USD, 7/8 à la rubrique |
| 4. Back-office | Écran de contenu (brief, sections, modification, historique, publication, SSE), blocs, Skills, réglages du générateur | 11 + 2 + 6 + 3 tests d'écran, preuves navigateur |
| 4 bis. À la main | Formulaire par section depuis le schéma, Tiptap si présent, ligne `manual` au registre | 7 tests, preuve navigateur |
| 5. Suppression | GrapesJS, bundle, 61 fichiers, hôte nettoyé, README et docs réécrits | grep de contrôle à zéro, 543 + 359 tests |
| 6. Recette | Cahier de recette, quatre volets, neuf arbitrages | `RECETTE.md` |

## Difficultés rencontrées

Dix-neuf entrées au journal (`JOURNAL.md`), toutes résolues ou arbitrées.
Les marquantes :

- **D-014** : la grammaire des sorties structurées refuse un schéma de vingt
  composants (24 propriétés optionnelles, 16 unions, taille) ; seule
  l'enveloppe est envoyée, le reste est validé côté serveur et renvoyé en
  correction. C'est le choix d'architecture le plus structurant de la phase 3.
- **D-005 / D-006 / D-009 / D-017** : quatre caches de l'hôte qui survivent à
  un changement (modèles Propel, conteneur de test, Twig compilé, assets
  publiés). Documentés dans `docs/developing.md`.
- **D-015 / D-016 / D-019** : trois pièges des tests d'écran (flux SSE, service
  substitué, global `app` du parser). Idem.
- Dette créée : clés i18n orphelines de l'ancien éditeur dans `I18n/*.php` ;
  `SiteStyles::palette()` et l'écran styles caché ; guide webmaster en 1.x.

## Arbitrages

Neuf lignes dans `RECETTE.md` § Arbitrages ouverts (A1 à A9) : fiche produit
du thème sans `cms_item_block()`, structure des sections à la main, barre
d'outils Tiptap, contrastes du thème, guide webmaster, streaming par tour,
évaluation des briefs 02-10, palette résiduelle, forme du merge.

## Alertes

1. **Montée en 2.0.0 d'un site 1.x en production** : le contenu des pages et
   des blocs est purgé (D5), les pages repassent en brouillon. Risque : un site
   en ligne perd ses pages sans export préalable. À décider : imposer
   `thelia_cms:export` dans la procédure de mise à jour, et le dire dans la
   note de version (le CHANGELOG le dit déjà en tête).
2. **Coût par appel** : chaque génération est un appel payant (≈ 0,09 USD par
   page, 0,03 par section avec `claude-opus-5-5`), sans plafond dans le
   module. Risque : un profil éditeur qui itère sans compter. À décider : un
   budget mensuel lu dans `GenerationLog::spentSince()` avec alerte, en 2.1.
3. **Clé d'API dans l'environnement** : `ANTHROPIC_API_KEY` doit être posée
   sur chaque environnement qui génère ; sans elle l'écran le dit et ne génère
   pas. Rien à décider, à mettre dans la procédure de déploiement.
4. **Hôte de test** : `composer.json` et `config/bundles.php` de l'hôte ont
   été modifiés pour retirer le bundle (option A validée) ; les autres
   modifications non commitées de l'hôte sont intactes.

## Preuves

- `preuves/phase4/` : parcours brief → génération → section → publication →
  front (captures, rapports axe, scripts).
- `preuves/phase4bis/` : correction à la main, Tiptap monté, front.
- `preuves/phase5/` : écrans sans le bundle.
- `baselines/` : état de départ ; `eval/` : briefs et relevé.
- Sorties de commandes : dans `PLAN.md`, phase par phase.

## État laissé

- Branche `feat/ai-page-generation`, six commits, arbre propre après le
  commit de la phase 6.
- Banc DDEV : module 2.0.0 actif, pages de preuve #5 (« Preuve phase 2 ») et #6
  (« Studios photo à louer (preuve phase 4) », publiée, titre « Lyon 3e »),
  registre de 17 appels ; aucun admin de preuve laissé.
- Commandes : `ddev exec vendor/bin/phpunit -c local/modules/TheliaCMS/phpunit.xml.dist`,
  `… phpunit.integration.xml.dist`, `ddev exec "vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --dry-run local/modules/TheliaCMS"`,
  `rm -r var/cache/test` après un changement de service.
- Preuves navigateur : Playwright dans le scratchpad de session (hors
  projet), scripts conservés dans `preuves/*/`.

## Ce qui n'a pas été fait

- Migration 2.0.0 rejouée sur un site réel avec pages en ligne ; import réel
  sur un second site ; coupure réseau réelle.
- Briefs 02 à 10 de l'évaluation (à jouer par le dev, A7).
- Guide webmaster (A5) ; documents de chantier réduits en ADR avant la PR (D9).
- Nombre de requêtes SQL par page (pas de collecteur Propel dans le profileur).

## Capitalisation

- Équipe, neutre, dans le dépôt : `docs/developing.md` (caches de l'hôte,
  tests d'écran, API Claude), livré avec le code.
- Kit (à remonter dans le skill d'outillage Thelia 3) : les quatre caches
  (D-005, D-006, D-009, D-017), le global `app` du parser (D-019), la lecture
  d'une `StreamedResponse` en test (D-015), la substitution d'un service privé
  (D-016).
- Perso (mémoire) : disposition du banc, Playwright dans le scratchpad, admin
  de preuve créé puis supprimé.
