# Évaluation manuelle du générateur

À jouer dès que l'hôte a une clé (`ANTHROPIC_API_KEY` dans `.env.local`, puis
`ddev restart`). Dix briefs, une rubrique, un relevé par brief. Aucun test
automatisé n'appelle l'API : cette évaluation est la seule mesure de qualité
réelle, et elle coûte de l'argent (ordre de grandeur attendu : quelques
centimes par page avec `claude-opus-5-5`, le préfixe du prompt étant mis en
cache une heure).

## Procédure

1. Une page par brief, créée en back-office ou par import, dans la langue du
   brief (toutes en français).
2. Pour chaque brief :

   ```bash
   ddev exec php Thelia thelia_cms:generate --page <id> --locale fr_FR --brief docs/evaluation/briefs/<n>.md --dry-run
   ```

   Le document et la ligne de coût s'affichent ; sans `--dry-run` le brouillon
   est enregistré, `thelia_cms:publish --page <id>` le publie.
3. Noter la rubrique dans `results.md` (une ligne par brief, une colonne par
   critère, 0 ou 1), le coût et la durée affichés, et les défauts observés.
4. Capture à 1440 px de la page publiée (Playwright), gardée hors dépôt.

## Rubrique (un point par critère, 8 au maximum)

| # | Critère | Comment juger |
|---|---|---|
| 1 | Une seule h1, hiérarchie sans saut | `HeadingChecker` ne remonte rien ; lecture du HTML publié |
| 2 | Sections pertinentes | Chaque section répond à une partie du brief ; pas de section vide ni décorative |
| 3 | Aucun fait inventé | Chiffres, noms, témoignages, dates : tous présents dans le brief |
| 4 | Liens internes valides | Toute `url` vers une page du site est issue de `search_pages` et répond 200 |
| 5 | Images de la médiathèque seulement | Tout `image` est un id renvoyé par `search_media`, ou null |
| 6 | Longueur tenue | Hero ≤ 8 mots ; paragraphes ≤ 3 phrases ; 3 à 7 sections |
| 7 | Langue et registre | Français correct, ton demandé, aucun mot d'une autre langue |
| 8 | Accessibilité | Alt renseignés ou vides à bon escient, liens explicites, pas de texte en majuscules |

Un brief vaut 8 au plus ; la cible pour clore la phase 3 est une moyenne ≥ 7
sur les dix briefs, et aucun brief sous 5.

## Les briefs

`briefs/01.md` à `briefs/10.md`, choisis pour couvrir les types de page et les
pièges : une page sans chiffres (le modèle doit laisser `Cms:Figures` de côté),
une page légale (texte libre seulement), une page en anglais sur un site
français (langue de la page = fr_FR : le brief doit être traduit), un brief
qui cite des produits (outils de recherche), un brief qui demande une FAQ, un
brief trop court, un brief avec un ton imposé, une page contact avec
formulaire, une page « à propos », une page d'accès.
