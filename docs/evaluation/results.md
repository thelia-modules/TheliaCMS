# Relevé d'évaluation

Brief 01 joué le 2026-10-20 en `--dry-run` (clé posée en cours de phase 3) ;
briefs 02 à 10 joués le 2026-10-20 sur neuf pages importées (`eval-02` à
`eval-10`, pages 7 à 15 du banc), générées puis publiées par la commande, lues
sur le front. `claude-opus-5-5`, effort `high`.

| Brief | 1 h1 | Sections | Faits | Liens | Images | Longueur | Langue | A11y | Total /8 | Coût USD | Durée s | Défauts |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 01 | 1 | 1 | 1 | 0 | 1 | 1 | 1 | 1 | 7 | 0,094 | 24,4 | `cta_url` et `button_url` = `/contact`, page absente du site de dev (le brief l'imposait sans la fournir) ; `Cms:Figures` rempli avec des chiffres du brief (3 plateaux, 6 m, 12 places) : acceptable mais faible ; 3 tours, 17 390 jetons lus en cache |
| 02 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 8 | 0,056 | 7,2 | Aucun : une seule section `cms:richtext`, h1 puis h2, pas d'appel à l'action, `mailto:` ; 1 tour, rien en cache (premier appel de l'heure) |
| 03 | 1 | 1 | 1 | 0 | 1 | 1 | 1 | 1 | 7 | 0,058 | 23,2 | Bouton « Contacter la boulangerie » vers `/contact`, page absente ; le hero garde un `cta_label` vide (correct) ; 4 tours |
| 04 | 1 | 0 | 1 | 1 | 1 | 1 | 1 | 1 | 7 | 0,054 | 18,8 | Aucun produit du catalogue de dev ne répond à « Fruits et légumes » ni « panier » : le modèle n'invente rien (bien) mais livre une page « best-sellers » sans produit, qui aurait dû le dire ; FAQ livraison exacte ; 5 tours de recherche |
| 05 | 1 | 1 | 1 | 0 | 1 | 1 | 1 | 1 | 7 | 0,029 | 11,9 | Six questions exactes ; CTA vers `/contact` absent ; 2 tours |
| 06 | 1 | 0 | 1 | 0 | 1 | 1 | 1 | 1 | 6 | 0,029 | 12,9 | Brief volontairement vide (« Page sur notre atelier. ») : le modèle n'invente aucun fait mais remplit trois sections de texte creux (« Votre question sur l'atelier a sa réponse ») et pointe `/contact` ; il devrait plutôt rendre une page minimale ou signaler le manque |
| 07 | 1 | 1 | 1 | 0 | 1 | 1 | 1 | 1 | 7 | 0,043 | 18,4 | Ton institutionnel tenu, zéro point d'exclamation, chiffres et témoignage exacts ; CTA `/contact` absent ; 4 tours |
| 08 | 1 | 0 | 1 | 1 | 1 | 1 | 1 | 1 | 7 | 0,033 | 13,4 | Le formulaire de contact demandé n'est pas inséré : aucun outil ne liste les formulaires du site (`search_forms` manque, voir ci-dessous) ; `tel:` exact, pas de carte (respecté) |
| 09 | 1 | 1 | 1 | 0 | 1 | 1 | 1 | 1 | 7 | 0,047 | 18,8 | Le piège « torréfaction… non » est évité, pas de section logos (médiathèque vide, comme demandé), chiffres 2015 / 32 / 2021 exacts ; CTA `/contact` absent ; 4 tours |
| 10 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 8 | 0,032 | 15,7 | Aucun : h1 en texte libre puis quatre sections, pas d'appel à l'action, aucun lien ; 1 tour |

Moyenne : **7,1 / 8** (cible ≥ 7), minimum 6 (cible ≥ 5). Coût total des
dix pages : 0,475 USD ; durée moyenne 16,5 s.

## Ce que l'évaluation apprend

- **Lien `/contact` deviné** (01, 03, 05, 06, 07, 09) : le skill `catalogue`
  donnait `/contact` en exemple d'`url` ; le modèle le reprend même quand
  `search_pages` ne trouve aucune page de contact. Corrigé le 2026-10-20 : le
  skill n'autorise plus qu'une `url` renvoyée par un outil, une adresse
  `tel:`/`mailto:` ou une adresse absolue donnée par le brief, et dit de ne
  pas mettre de bouton quand aucune page ne convient.
- **Formulaires introuvables** (08) : aucun outil ne listait les formulaires ;
  outil `search_forms` ajouté le 2026-10-20.
- **Brief vide** (06) : le modèle remplit plutôt que de s'abstenir ; le skill
  `page-structure` dit désormais qu'un brief sans matière donne une page
  courte, sans section de remplissage.
- **Catalogue sans produit** (04) : comportement sûr (rien d'inventé) mais la
  page ne dit pas que les produits manquent ; même consigne que ci-dessus.
