# Relevé de déterminisme

Rondes 0 et 1 du plan `docs/plans/deterministic-skills.md`, jouées le
2026-10-27 avec `run-round.sh` : seize briefs, cinq runs chacun, en
`--dry-run`, `claude-opus-5-5`, skills de la version 2.3.0. Les runs restent
hors dépôt ; ce fichier en garde le résumé de `thelia_cms:compare-runs`.

## Résultat par effort

| Ronde | Effort | Briefs stables | Stables, ids ignorés | Coût du tour (80 runs) | Coût moyen d'un run | Durée moyenne d'un run |
|---|---|---|---|---|---|---|
| `round-0` | `high` (défaut) | 6 / 16 | 11 / 16 | 2,47 USD | 0,031 USD | 12,4 s |
| `round-1-medium` | `medium` | 4 / 16 | 13 / 16 | 1,94 USD | 0,024 USD | 9,3 s |
| `round-1-xhigh` | `xhigh` | 4 / 16 | 10 / 16 | 4,71 USD | 0,059 USD | 24,4 s |

« Stable » est le critère de `compare-runs` : même structure et même forme
dans 4 runs sur 5, longueurs à 30 % près, mêmes chiffres partout. « Ids
ignorés » refait le compte de la structure sur les seuls composants, dans
l'ordre, sans l'id des sections (calcul fait à part, sur la sortie
`--details`).

**L'effort n'est pas le levier.** Les écarts entre efforts (4 à 6 briefs
stables, 10 à 13 sans les ids) sont à la mesure du bruit de cinq runs, et ne
vont pas dans le même sens selon le critère. `xhigh` coûte 1,9 fois `high` et
double la durée sans gagner en stabilité ; `medium` coûte 20 % de moins pour
une stabilité égale, mais sa qualité n'a pas été notée (la rubrique ne porte
que sur la ronde 0). Le défaut `high` reste.

### Ronde 0 par brief

| Brief | Structure | Forme | Écart de longueur max | Chiffres instables | Coût moyen USD | Tours | Stable |
|---|---|---|---|---|---|---|---|
| 01 | 2/5 | 2/5 | 12 % | — | 0,047 | 3,0 | non |
| 02 | 5/5 | 2/5 | 11 % | — | 0,017 | 1,0 | non |
| 03 | 4/5 | 4/5 | 27 % | — | 0,041 | 3,0 | oui |
| 04 | 5/5 | 5/5 | 6 % | — | 0,046 | 4,4 | oui |
| 05 | 1/5 | 2/5 | 0 % | — | 0,034 | 1,2 | non |
| 06 | 2/5 | 5/5 | 0 % | — | 0,020 | 2,2 | non |
| 07 | 2/5 | 3/5 | 9 % | — | 0,048 | 3,0 | non |
| 08 | 3/5 | 2/5 | 20 % | — | 0,021 | 2,0 | non |
| 09 | 2/5 | 3/5 | 35 % | — | 0,044 | 3,0 | non |
| 10 | 2/5 | 3/5 | 2 % | 7 | 0,025 | 1,4 | non |
| 11 | 2/5 | 2/5 | 0 % | — | 0,031 | 2,0 | non |
| 12 | 3/5 | 4/5 | 46 % | — | 0,026 | 2,0 | non |
| 13 | 5/5 | 4/5 | 15 % | — | 0,029 | 1,0 | oui |
| 14 | 4/5 | 4/5 | 24 % | — | 0,023 | 1,2 | oui |
| 15 | 5/5 | 5/5 | 27 % | — | 0,018 | 1,0 | oui |
| 16 | 4/5 | 4/5 | 26 % | — | 0,024 | 2,0 | oui |

## Sources de variance (ronde 0, confirmées par la ronde 1)

Par ordre de poids :

1. **Les ids de section.** Le même formulaire s'appelle `contact`,
   `contact-form` ou `formulaire-contact`, le même texte d'appel
   `contact`, `contact-intro` ou `ecrire` ; les colonnes de 03 sont
   `notre-pain` ou `savoir-faire`. Sans les ids, la ronde 0 passe de 6 à 11
   briefs stables en structure. C'est la première règle à essayer : un
   vocabulaire fixe d'ids.
2. **Les sections facultatives.** Le modèle ajoute ou non un formulaire de
   contact que le brief ne demande pas (05, 09, 11), une section d'accès ou
   de parking (01), une introduction (12, 16). Règle visée : un ordre
   canonique par genre de page, qui dit quelles sections un brief allume.
3. **Deux composants pour un même contenu.** Le témoignage de 07 va dans
   `Organisms:ReviewCard:Base` ou `Layouts:Review:Base`, en 3 contre 2 dans
   chacune des trois rondes ; les moyens d'accès de 10 en colonnes ou en
   texte ; les atouts du produit de 14 en colonnes ou en texte. Règle visée :
   le départage écrit dans le catalogue.
4. **Le découpage des questions.** Les six questions de 05 tiennent en une,
   deux ou trois sections de texte selon le run (structure 1/5 dans les trois
   rondes), toujours en `cms:richtext`. Règle visée : une section par
   groupe que le brief nomme, ou une seule.
5. **La forme à l'intérieur d'un texte.** À structure égale, le nombre de
   h2 et de puces varie (02 : 3 ou 4 h2, 0 à 4 puces ; 08 : 3 à 5 h2).
6. **Les chiffres** sont tenus partout, sauf un : dans 10, des runs
   écrivent « 7e arrondissement », tiré du code postal 69007 (1 run sur 5 à
   `high` et à `xhigh`, 3 à `medium`). Exact, mais absent du brief.

Ce qui est déjà stable : une page de type avec page modèle (13) et une
traduction (15) donnent la même structure dans 5 runs sur 5 à chaque effort.
La page modèle fixe la structure mieux qu'une règle de skill. Elle passe
aussi avant les skills : voir le défaut du banc ci-dessous.

## Rubrique (premier run de la ronde 0, briefs 01 à 10)

Notée sur le document imprimé par `--dry-run` : pas de page publiée ni de
capture à 1440 px, contrairement au relevé du 2026-10-20. Depuis l'ADR 0010,
le gabarit dessine le titre de la page en h1 : le critère 1 vérifie qu'aucune
section n'écrit de h1 et que les niveaux ne sautent pas.

| Brief | 1 h1 | Sections | Faits | Liens | Images | Longueur | Langue | A11y | Total /8 | Coût USD | Durée s | Défauts |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 01 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 8 | 0,048 | 21,5 | Le site de dev n'a pas de page contact : le bouton demandé devient le formulaire, et l'avertissement le dit |
| 02 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 8 | 0,018 | 9,0 | « Directrice de la publication » là où le brief écrit « directeur » : genre déduit du prénom |
| 03 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 8 | 0,041 | 16,8 | Aucun |
| 04 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 8 | 0,041 | 14,2 | Aucun produit trouvé : la page ne garde que la FAQ, et l'avertissement dit pourquoi |
| 05 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 8 | 0,036 | 15,2 | Six questions exactes, en deux sections de texte |
| 06 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 8 | 0,024 | 10,7 | Page courte (deux sections) et avertissement sur ce qui manque, comme le veut `page-structure` ; la rubrique demande 3 sections, la règle du skill l'emporte |
| 07 | 1 | 1 | 0 | 1 | 1 | 1 | 1 | 1 | 7 | 0,053 | 21,2 | Témoignage en `ReviewCard` avec `note: 0` : la carte dessine cinq étoiles vides, une note de 0/5 que le brief ne donne pas |
| 08 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 8 | 0,023 | 10,1 | Aucun : `tel:` exact, formulaire du site inséré |
| 09 | 1 | 0 | 1 | 1 | 1 | 1 | 1 | 1 | 7 | 0,039 | 16,4 | Formulaire de contact non demandé ; 2015 et 2021 redits trois fois (intro, histoire, chiffres) |
| 10 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 8 | 0,026 | 11,0 | Aucun |

Moyenne : **7,8 / 8** (7,1 le 2026-10-20), minimum 7. La cible de qualité
(≥ 7, aucun brief sous 5) tient au point de départ des rondes de règles.

## Défauts relevés en marge

- **Note à zéro sous un témoignage.** `Organisms:ReviewCard:Base` dessine
  toujours `Molecules:Score:Base`, avec 0 par défaut : un témoignage sans
  note s'affiche avec cinq étoiles vides. Le modèle écrit `note: 0` dans tous
  les runs qui prennent ce composant. Le composant est celui du thème Flexy,
  hors du module ; côté skills, la piste est de réserver ce composant aux
  avis notés.
- **Bandeau de titre dans le banc.** La page modèle du type `eval-workshop`
  et le brouillon de `eval-15` ouvraient sur `Layouts:Subheader:Base` en h1,
  ce que l'ADR 0010 interdit ; les briefs 13 et 15 l'ont recopié dans les
  quinze runs de chaque ronde, contre la consigne des skills. `bench.json` est
  corrigé ; la copie importée sur le site de dev doit l'être aussi avant la
  ronde 2, et les briefs 13 et 15 rejoués.
- **Le contrôle des titres ne tourne pas en `--dry-run`.** `HeadingChecker`
  ne s'exécute qu'à l'enregistrement : ces h1 n'ont produit aucun
  avertissement dans la sortie de la commande.

## Suite

Ronde 2, une règle à la fois, dans l'ordre des sources de variance : le
vocabulaire fixe d'ids d'abord (le gain mesuré le plus grand, sans effet
attendu sur la qualité), puis l'ordre canonique des sections facultatives,
puis le départage des composants. Chaque règle se joue sur les briefs
qu'elle touche, puis sur le corpus entier si elle est gardée.
