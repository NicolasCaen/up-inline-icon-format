import { registerFormatType, applyFormat, insert } from '@wordpress/rich-text';
import { RichTextToolbarButton, __experimentalRichText as RichText } from '@wordpress/block-editor';
import { Popover, SelectControl, TextControl, Button } from '@wordpress/components';
import { Fragment, useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
// apiFetch not used anymore after simplification
import './style.css';

const TYPE = 'up/inline-icon';

function InlineIconUI( { isActive, value, onChange, contentRef } ) {
    const [open, setOpen] = useState(false);
    const initialPacks = (typeof UP_IIF !== 'undefined' && Array.isArray(UP_IIF.iconCssPacks)) ? UP_IIF.iconCssPacks : [];
    const [iconPacks, setIconPacks] = useState(initialPacks);
    const [packIndex, setPackIndex] = useState( initialPacks.length ? 0 : -1 );
    const [selectedIcon, setSelectedIcon] = useState(null); // { class, code }
    const [pendingHex, setPendingHex] = useState('');
    const [currentEl, setCurrentEl] = useState(null); // existing span.up-inline-icon under caret
    // Style controls state must be declared before effects that depend on them
    const [sizeSlug, setSizeSlug] = useState('');
    const [sizeCustom, setSizeCustom] = useState(''); // e.g. 16px, 1rem
    const [colorSlug, setColorSlug] = useState('');
    const [colorCustom, setColorCustom] = useState(''); // e.g. #333333
    // Editor settings (fontSizes/colors)
    const settings = useSelect( (select)=> {
        const s = select('core/block-editor')?.getSettings?.() || {};
        return {
            fontSizes: s.fontSizes || [],
            colors: s.colors || []
        };

    // Live update when glyph changes while editing an existing element
    useEffect(()=>{
        if (!open || !currentEl || !currentEl.parentNode || !selectedIcon) return;
        const fontName = (iconPacks[packIndex]?.family || '').toString();
        const hexCode = String(selectedIcon.code).toUpperCase();
        const charToInsert = toChar(hexCode);
        if (!charToInsert) return;
        const styleParts = [];
        const fontSizeCss = sizeSlug ? `var(--wp--preset--font-size--${sizeSlug})` : (sizeCustom || '').trim();
        if (fontSizeCss) styleParts.push(`font-size:${fontSizeCss};`);
        const colorCss = colorSlug ? `var(--wp--preset--color--${colorSlug})` : (colorCustom || '').trim();
        if (colorCss) styleParts.push(`color:${colorCss};`);
        const styleAttr = styleParts.join(' ');
        const utilClasses = [];
        if (sizeSlug) utilClasses.push(`has-${sizeSlug}-font-size`);
        if (colorSlug) { utilClasses.push('has-text-color'); utilClasses.push(`has-${colorSlug}-color`); }
        if (fontName) {
            const famSlug = slugify(fontName);
            if (famSlug) utilClasses.push(`has-${famSlug}-font-family`);
        }
        const classAttr = ['up-inline-icon', selectedIcon.class].concat(utilClasses).join(' ').trim();
        const attrs = {
            class: classAttr,
            'data-font': fontName,
            'data-code': hexCode,
            'aria-hidden': 'true',
            ...(styleAttr ? { style: styleAttr } : {})
        };
        let start = value.start, end = value.end;
        if (start === end) {
            if (start > 0) { start = start - 1; } else { start = 0; end = Math.min(1, value.text.length||1); }
        }
        const working = insert( value, charToInsert, start, end );
        const applied = applyFormat( working, { type: TYPE, attributes: attrs }, start, start + charToInsert.length );
        onChange( applied );
        // keep popup open for further edits
    }, [selectedIcon]);
    }, []);

    // Live update of existing element when controls change
    useEffect(()=>{
        if (!open) return;
        try {
            const doc = (contentRef && contentRef.current && contentRef.current.ownerDocument) ? contentRef.current.ownerDocument : (typeof document !== 'undefined' ? document : null);
            if (!doc || !doc.getSelection) return;
            const sel = doc.getSelection();
            const baseNode = sel && sel.anchorNode ? sel.anchorNode : null;
            const node = baseNode ? (baseNode.nodeType === 1 ? baseNode : baseNode.parentElement) : null;
            const el = node && typeof node.closest === 'function' ? node.closest('span.up-inline-icon') : null;
            setCurrentEl(el || null);
            if (!el) return;
            // font family -> select pack
            const fam = el.getAttribute('data-font') || '';
            let idx = -1;
            if (fam) {
                idx = iconPacks.findIndex(p => (p.family||'').toString() === fam);
                if (idx >= 0) setPackIndex(idx);
            }
            // glyph by data-code
            const dataCodeAttr = el.getAttribute('data-code');
            if (dataCodeAttr) {
                const hex = isHex(dataCodeAttr) ? dataCodeAttr.toUpperCase() : (dataCodeAttr.codePointAt(0)?.toString(16).toUpperCase() || '');
                if (hex) {
                    // Try immediate resolution with local idx, else defer
                    const pIdx = idx >= 0 ? idx : packIndex;
                    const base = (pIdx >= 0) ? iconPacks[pIdx] : null;
                    if (base && Array.isArray(base.icons)) {
                        const found = base.icons.find(ic => ic.code.toUpperCase() === hex);
                        if (found) setSelectedIcon(found); else setPendingHex(hex);
                    } else {
                        setPendingHex(hex);
                    }
                }
            }
            // classes for presets
            const cls = el.className || '';
            const fsMatch = cls.match(/has-([a-z0-9-]+)-font-size/);
            setSizeSlug(fsMatch ? fsMatch[1] : '');
            const colorMatches = Array.from(cls.matchAll(/has-([a-z0-9-]+)-color/g)).map(m=>m[1]);
            const presetColor = colorMatches.find(slug => slug !== 'text') || '';
            setColorSlug(presetColor);
            // style inline overrides
            const style = el.getAttribute('style') || '';
            const fsInline = style.match(/font-size\s*:\s*([^;]+)/i);
            setSizeCustom(fsInline ? fsInline[1].trim() : '');
            const colInline = style.match(/color\s*:\s*([^;]+)/i);
            setColorCustom(colInline ? colInline[1].trim() : '');
        } catch(e) {}
    }, [open, contentRef]);

    // Resolve pending hex once packIndex is ready
    useEffect(()=>{
        if (!open || !pendingHex) return;
        const base = (packIndex >= 0) ? iconPacks[packIndex] : null;
        if (base && Array.isArray(base.icons)) {
            const found = base.icons.find(ic => ic.code.toUpperCase() === pendingHex.toUpperCase());
            if (found) { setSelectedIcon(found); setPendingHex(''); }
        }
    }, [open, pendingHex, packIndex, iconPacks]);

    useEffect(()=>{
        if (!open || !currentEl || !currentEl.isConnected) return;
        try {
            const fontName = (iconPacks[packIndex]?.family || '').toString();
            const dc = currentEl.getAttribute('data-code') || '';
            const existingHex = isHex(dc) ? dc.toUpperCase() : charToHex(currentEl.textContent || '');
            const hexCode = selectedIcon ? String(selectedIcon.code).toUpperCase() : existingHex;
            const charToInsert = hexCode ? toChar(hexCode) : '';
            // Build style
            const styleParts = [];
            const fontSizeCss = sizeSlug ? `var(--wp--preset--font-size--${sizeSlug})` : (sizeCustom || '').trim();
            if (fontSizeCss) styleParts.push(`font-size:${fontSizeCss};`);
            const colorCss = colorSlug ? `var(--wp--preset--color--${colorSlug})` : (colorCustom || '').trim();
            if (colorCss) styleParts.push(`color:${colorCss};`);
            const styleAttr = styleParts.join(' ');
            // Classes utilitaires
            const utilClasses = [];
            if (sizeSlug) utilClasses.push(`has-${sizeSlug}-font-size`);
            if (colorSlug) { utilClasses.push('has-text-color'); utilClasses.push(`has-${colorSlug}-color`); }
            const famSlug = fontName ? slugify(fontName) : '';
            if (famSlug) utilClasses.push(`has-${famSlug}-font-family`);
            const glyphClass = selectedIcon ? selectedIcon.class : (currentEl.className.match(/\b([^\s]+)\b/) && currentEl.className.split(' ').find(c=>c!=='up-inline-icon' && !c.startsWith('has-')) ) || '';
            const classAttr = ['up-inline-icon'].concat(glyphClass?[glyphClass]:[]).concat(utilClasses).join(' ').trim();
            // Apply
            currentEl.className = classAttr;
            if (fontName) currentEl.setAttribute('data-font', fontName);
            if (hexCode) currentEl.setAttribute('data-code', hexCode);
            if (styleAttr) currentEl.setAttribute('style', styleAttr); else currentEl.removeAttribute('style');
            if (charToInsert) {
                // Force update through editor API instead of direct DOM manipulation
                const range = document.createRange();
                range.selectNodeContents(currentEl);
                const selection = window.getSelection();
                selection.removeAllRanges();
                selection.addRange(range);
                
                // Use execCommand to ensure Gutenberg tracks the change
                document.execCommand('insertText', false, charToInsert);
                
                // Clear selection
                selection.removeAllRanges();
            }
        } catch(e) {}
    }, [open, currentEl, selectedIcon, sizeSlug, sizeCustom, colorSlug, colorCustom, packIndex]);

    const slugify = (str='') => String(str)
        .toLowerCase()
        .normalize('NFKD')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    const toChar = (hex) => {
        const code = parseInt(hex, 16);
        if (!Number.isNaN(code)) return String.fromCodePoint(code);
        return '';
    };
    const isHex = (s='') => /^[0-9a-fA-F]{1,6}$/.test(String(s));
    const charToHex = (ch='') => {
        if (!ch) return '';
        const cp = ch.codePointAt(0);
        return cp ? cp.toString(16).toUpperCase() : '';
    };

    const doInsert = () => {
        if (packIndex < 0 || !selectedIcon) { setOpen(false); return; }
        const fontName = (iconPacks[packIndex]?.family || '').toString();
        const hexCode = (selectedIcon && selectedIcon.code) ? String(selectedIcon.code).toUpperCase() : '';
        const charToInsert = hexCode ? toChar(hexCode) : '';
        if (!charToInsert) { setOpen(false); return; }
        // Build inline style from size/color (inherit by default)
        const styleParts = [];
        const fontSizeCss = sizeSlug ? `var(--wp--preset--font-size--${sizeSlug})` : (sizeCustom || '').trim();
        if (fontSizeCss) styleParts.push(`font-size:${fontSizeCss};`);
        const colorCss = colorSlug ? `var(--wp--preset--color--${colorSlug})` : (colorCustom || '').trim();
        if (colorCss) styleParts.push(`color:${colorCss};`);
        const styleAttr = styleParts.join(' ');
        // Build utility classes for WP presets
        const utilClasses = [];
        if (sizeSlug) utilClasses.push(`has-${sizeSlug}-font-size`);
        if (colorSlug) { utilClasses.push('has-text-color'); utilClasses.push(`has-${colorSlug}-color`); }
        if (fontName) {
            const famSlug = slugify(fontName);
            if (famSlug) utilClasses.push(`has-${famSlug}-font-family`);
        }
        const classAttr = ['up-inline-icon', selectedIcon.class].concat(utilClasses).join(' ').trim();
        const attrs = {
            class: classAttr,
            'data-font': fontName,
            'data-code': hexCode,
            'aria-hidden': 'true',
            ...(styleAttr ? { style: styleAttr } : {})
        };
        // If we are editing an existing icon span, update via RichText value replacement
        if (currentEl && currentEl.parentNode) {
            let start = value.start, end = value.end;
            if (start === end) {
                // replace the character under/just before caret
                if (start > 0) { start = start - 1; } else { start = 0; end = Math.min(1, value.text.length||1); }
            }
            let working = insert( value, charToInsert, start, end );
            const applied = applyFormat( working, { type: TYPE, attributes: attrs }, start, start + charToInsert.length );
            onChange( applied );
            setOpen(false);
            return;
        }
        // Otherwise insert a new formatted char
        let start = value.start, end = value.end;
        let working = value;
        if (start === end) {
            working = insert( working, charToInsert, start, end );
            end = start + charToInsert.length;
        }
        const newVal = applyFormat( working, { type: TYPE, attributes: attrs }, start, end );
        onChange( newVal );
        setOpen(false);
    };

    useEffect(()=>{
        if (iconPacks.length && packIndex === -1) setPackIndex(0);
    }, [iconPacks, packIndex]);

    return (
        <Fragment>
            <RichTextToolbarButton
                icon={ (
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="20" height="20" aria-hidden="true">
                      <rect width="24" height="24" fill="none"/>
                      <path d="M5.13,10.71H8.87L6.22,8.06A1.84,1.84,0,1,1,8.06,6.22l2.65,2.65V5.13a1.84,1.84,0,0,1,0-2.59,1.81,1.81,0,0,1,2.58,0,1.84,1.84,0,0,1,0,2.59V8.87L16,6.22a1.83,1.83,0,1,1,1.83,1.84l-2.65,2.65h3.74a1.84,1.84,0,0,1,2.59,0,1.81,1.81,0,0,1,0,2.58,1.84,1.84,0,0,1-2.59,0H15.13L17.78,16A1.83,1.83,0,1,1,16,17.78l-2.66-2.65v3.74a1.84,1.84,0,0,1,0,2.59,1.81,1.81,0,0,1-2.58,0,1.84,1.84,0,0,1,0-2.59V15.13L8.06,17.78A1.84,1.84,0,1,1,6.22,16l2.65-2.66H5.13a1.84,1.84,0,0,1-2.59,0,1.81,1.81,0,0,1,0-2.58A1.84,1.84,0,0,1,5.13,10.71Z"/>
                    </svg>
                ) }
                title={ __('Icône inline', 'up-iif') }
                onClick={ ()=> setOpen(v=>!v) }
                isActive={ isActive }
            />
            { open && (
                <Popover onClose={ ()=> setOpen(false) } anchor={ contentRef?.current }>
                    <div style={{ padding: 12, width: 360 }}>
                        { iconPacks.length ? (
                            <SelectControl
                                label={ __('Police d’icônes (CSS)', 'up-iif') }
                                value={ String(packIndex) }
                                options={ iconPacks.map((p, i)=>({ label: p.name + (p.family ? ` – ${p.family}` : ''), value: String(i) })) }
                                onChange={ (v)=> { const i = parseInt(v,10); setPackIndex(i); setSelectedIcon(null); } }
                            />
                        ) : (
                            <div style={{ padding:8, color:'#666' }}>
                                { __('Aucune police CSS détectée. Importez un ZIP via Réglages → Icônes inline.', 'up-iif') }
                            </div>
                        ) }
                        {/* Glyph grid right below font select */}
                        <div style={{ display:'grid', gridTemplateColumns:'repeat(6, 1fr)', gap:8, marginTop:8 }}>
                            { (packIndex>=0 ? (iconPacks[packIndex]?.icons || []) : []).map((ic, idx)=>{
                                const codeChar = toChar(ic.code);
                                const isSel = !!(selectedIcon && selectedIcon.class===ic.class && selectedIcon.code===ic.code);
                                return (
                                    <button key={idx}
                                        onClick={()=> setSelectedIcon(ic)}
                                        style={{
                                            fontFamily: iconPacks[packIndex]?.family || 'inherit',
                                            width:44, height:44, display:'flex', alignItems:'center', justifyContent:'center',
                                            border: isSel ? '2px solid #1e1e1e' : '1px solid #ccc',
                                            borderRadius:4, background:'#fff', cursor:'pointer'
                                        }}
                                        title={`${ic.class} (U+${ic.code})`}
                                    >
                                        {codeChar}
                                    </button>
                                );
                            })}
                        </div>
                        {/* Size + custom side-by-side */}
                        <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:8, marginTop:8 }}>
                            <SelectControl
                                label={ __('Taille', 'up-iif') }
                                value={ sizeSlug }
                                options={[{ label: __('Hérité', 'up-iif'), value: '' }].concat(
                                    (settings.fontSizes || []).map(fs=>({ label: fs.name || fs.slug, value: fs.slug }))
                                )}
                                onChange={ (v)=> setSizeSlug(v) }
                            />
                            <TextControl
                                label={ __('Taille personnalisée', 'up-iif') }
                                value={ sizeCustom }
                                placeholder="16px, 1rem"
                                onChange={ setSizeCustom }
                            />
                        </div>
                        {/* Color + custom side-by-side */}
                        <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:8, marginTop:8 }}>
                            <SelectControl
                                label={ __('Couleur', 'up-iif') }
                                value={ colorSlug }
                                options={[{ label: __('Hérité', 'up-iif'), value: '' }].concat(
                                    (settings.colors || []).map(c=>({ label: c.name || c.slug, value: c.slug }))
                                )}
                                onChange={ (v)=> setColorSlug(v) }
                            />
                            <TextControl
                                label={ __('Couleur personnalisée', 'up-iif') }
                                value={ colorCustom }
                                placeholder="#000000"
                                onChange={ setColorCustom }
                            />
                        </div>
                        <div style={{ display:'flex', gap:8, justifyContent:'flex-end', marginTop:12 }}>
                            <Button variant="secondary" onClick={()=> setOpen(false)}>{ __('Annuler', 'up-iif') }</Button>
                            <Button variant="primary" onClick={ doInsert } disabled={ packIndex<0 || !selectedIcon }>{ __('Insérer', 'up-iif') }</Button>
                        </div>
                    </div>
                </Popover>
            )}
        </Fragment>
    );
}

registerFormatType(TYPE, {
    title: __('Icône inline','up-iif'),
    tagName: 'span',
    className: 'up-inline-icon',
    attributes: {
        'data-font': 'data-font',
        'data-code': 'data-code',
        'aria-label': 'aria-label',
        style: 'style'
    },
    edit: (props) => {
        const { isActive, value, onChange, contentRef } = props;
        return <InlineIconUI isActive={isActive} value={value} onChange={onChange} contentRef={contentRef} />;
    },
});
