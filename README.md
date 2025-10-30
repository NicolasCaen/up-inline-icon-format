# UP Inline Icon Format

Un format Gutenberg pour insérer des icônes inline basées sur des polices d’icônes.

- Insertion/édition d’un `span.up-inline-icon` avec:
  - Classe du glyphe (ex: `oudon-pingpong`).
  - Attributs `data-font` (famille) et `data-code` (codepoint HEX, ex: `0047`).
  - Styles inline: `font-family`, `font-size`, `color`.
    - Presets → `var(--wp--preset--...)`.
    - Personnalisé → valeur brute (ex: `18px`, `#333`).
- Popup: sélection de la police, grille des glyphes, taille/couleur (preset ou personnalisé).

## Installation
1. Copier le dossier du plugin dans `wp-content/plugins/up-inline-icon-format`.
2. Activer le plugin dans l’admin WordPress.
3. Ouvrir l’éditeur (Gutenberg). Un bouton “Icône inline” apparaît dans la barre d’outils du RichText.

## Ajouter une nouvelle police d’icônes
Deux méthodes sont possibles.

### Méthode A — Import via Réglages (ZIP)
1. Préparez un ZIP contenant des fontes (`.woff2`, `.woff`, `.ttf`, `.otf`, `.eot`, `.svg`) et, si c'est une police d'icônes, une CSS de mapping des glyphes.
2. Admin → Réglages → Icônes inline → Importer le ZIP. Options:
   - Nom de la police (affiché) et Slug (facultatif).
   - Police d'icônes (coché par défaut): nécessite une CSS. Le plugin recopie les fichiers en `<slug>/<slug>.<ext>` et réécrit la CSS si besoin, puis met à jour `theme.json` avec une face unique.
   - Police variable: si cochée, le plugin détecte les variantes normal/italic et génère des faces avec `fontWeight: "100 900"` en:
     - `<slug>-variable.<ext>` et `<slug>-variable-italic.<ext>` (si italic présent).
   - Sinon (police classique): détection automatique des poids et styles via les noms de fichiers:
     - Poids: chiffres `100..900` ou mots-clés (`thin`, `light`, `regular/normal/book`, `medium`, `semibold/demibold`, `bold`, `extrabold/ultrabold`, `black/heavy`).
     - Italic: `italic`, `oblique`, ou `ita`.
     - Fichiers copiés/renommés en `<slug>-<poids>[-italic].<ext>` et `theme.json` reçoit une face par combinaison détectée.
3. La police apparaît dans le sélecteur de la popup.

### Méthode B — Ajout manuel dans le thème
1. Placez les fichiers dans le thème, ex:
   - `assets/fonts/brands/brands.woff2`
   - `assets/fonts/brands/brands.css`
2. Ajoutez la police dans `theme.json`:
```json
{
  "fontFamily": "\"brands\", sans-serif",
  "name": "brands",
  "slug": "brands",
  "fontFace": [
    {
      "fontFamily": "brands",
      "src": ["file:./assets/fonts/brands/brands.woff2"],
      "fontDisplay": "swap"
    }
  ]
}
```
3. Assurez la CSS de mapping glyphes. Exemples:
```css
@font-face { font-family: 'brands'; src: url('./brands.woff2'); font-display: swap; }
.fa-x-twitter { content: "\e61b"; }
```
```css
@font-face { font-family: 'myfont2'; ... }
[class*='oudon-']:before { font-family: 'myfont2'; ... }
.oudon-pingpong:before { content: '\0047'; }
```
4. Veillez à charger la CSS côté front + éditeur.

## Utilisation dans l’éditeur
- Placez le curseur là où insérer l’icône, cliquez “Icône inline”.
- Sélectionnez la police puis un glyphe.
- Choisissez la taille et la couleur:
  - Preset → style via `var(--wp--preset--)`.
  - Personnalisé → saisie libre (px/rem… ou hex/rgb/hsl…).
- “Insérer” crée ou met à jour le `span.up-inline-icon`.

## Structure HTML générée (exemple)
```html
<span class="up-inline-icon oudon-pingpong"
      data-font="myfont2"
      data-code="0047"
      aria-hidden="true"
      style="font-family:myfont2; font-size:var(--wp--preset--font-size--medium); color:#ff3366;">G</span>
```

## Dépannage
- La police n’apparaît pas: vérifier le chargement de la CSS et la présence d’un `@font-face` valide.
- Le glyphe ne change pas: confirmer que le `content` CSS utilise bien un code hex (ex: `\004A`, `\e07b`).
- Réédition: placez le curseur dans l’icône avant d’ouvrir la popup.

## Développement
- Build: `npm install` puis `npm run build`.
- Le plugin enfile `build/index.js` et `build/style-index.css` si présents, sinon `assets/format.js`.
 
### Format des entrées theme.json générées
- Icônes:
```json
{
  "fontFamily": "\"brands\", sans-serif",
  "name": "brands",
  "slug": "brands",
  "fontFace": [
    { "fontFamily": "brands", "src": ["file:./assets/fonts/brands/brands.woff2"], "fontDisplay": "swap" }
  ]
}
```
- Classiques (extraits):
```json
{
  "fontFamily": "\"lemon\", sans-serif",
  "name": "lemon",
  "slug": "lemon",
  "fontFace": [
    { "fontFamily": "lemon", "fontWeight": 400, "fontStyle": "normal", "src": ["file:./assets/fonts/lemon/lemon-400.woff2"], "fontDisplay": "swap" },
    { "fontFamily": "lemon", "fontWeight": 400, "fontStyle": "italic", "src": ["file:./assets/fonts/lemon/lemon-400-italic.woff2"], "fontDisplay": "swap" }
  ]
}
```
- Variables (extraits):
```json
{
  "fontFamily": "\"family var\", sans-serif",
  "name": "family var",
  "slug": "family-var",
  "fontFace": [
    { "fontFamily": "family var", "fontStyle": "normal", "fontWeight": "100 900", "src": ["file:./assets/fonts/family-var/family-var-variable.woff2"] },
    { "fontFamily": "family var", "fontStyle": "italic", "fontWeight": "100 900", "src": ["file:./assets/fonts/family-var/family-var-variable-italic.woff2"] }
  ]
}
```

## Licence
MIT
