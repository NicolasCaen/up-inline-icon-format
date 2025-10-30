# Changelog

## 1.1.0 — 2025-10-30
- Import de polices enrichi dans Réglages → Icônes inline:
  - Case à cocher « Police d'icônes » (avec CSS requise).
  - Case à cocher « Police variable » (plage de poids 100–900 et style normal/italic si présents).
  - Champ « Slug de la police » pour le nom technique.
- Détection automatique des poids à partir des noms de fichiers (100..900 ou mots-clés: thin, light, regular, medium, semibold, bold, extrabold, black...).
- Détection de l'italic dans les noms de fichiers (italic/oblique/ita) avec génération des entrées `fontFace` séparées (`fontStyle: "normal"` et `"italic"`).
- Renommage normalisé des fichiers copiés dans le thème:
  - Icônes: `<slug>/<slug>.<ext>` (+ CSS réécrite si nécessaire).
  - Classiques: `<slug>/<slug>-<poids>[-italic].<ext>`.
  - Variables: `<slug>/<slug>-variable[ -italic ].<ext>`.
- Mise à jour automatique de `theme.json` pour déclarer la famille et toutes les variantes détectées.

## 1.0.0 — 2025-10-24
- Première version stable.
- Popup réorganisée (police en haut, grille sous-jacente, tailles/couleurs preset et personnalisées).
- Mise à jour live des icônes existantes (glyphe, style, data-font, data-code).
- Passage à une approche style-only (font-family, font-size, color en inline; presets via var()).
- Support import ZIP de polices + ajout automatique dans theme.json.
- Icône de toolbar avec SVG intégré.

## 0.1.0 — 2025-10-24
- Prototype initial du format RichText et popup d’insertion.
