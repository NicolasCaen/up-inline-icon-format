import { registerFormatType, applyFormat, insert } from '@wordpress/rich-text';
import { RichTextToolbarButton, __experimentalRichText as RichText } from '@wordpress/block-editor';
import { Popover, SelectControl } from '@wordpress/components';
import { Fragment, useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
// apiFetch not used anymore after simplification
import './style.css';

const TYPE = 'up/inline-icon';

function InlineIconUI( { isActive, value, onChange, contentRef } ) {
    const [open, setOpen] = useState(false);
    const initialPacks = (typeof UP_IIF !== 'undefined' && Array.isArray(UP_IIF.iconCssPacks)) ? UP_IIF.iconCssPacks : [];
    const [iconPacks, setIconPacks] = useState(initialPacks);
    const [packIndex, setPackIndex] = useState( initialPacks.length ? 0 : -1 );

    const toChar = (hex) => {
        const code = parseInt(hex, 16);
        if (!Number.isNaN(code)) return String.fromCodePoint(code);
        return '';
    };

    const insertGlyph = (ic) => {
        if (packIndex < 0 || !ic) { setOpen(false); return; }
        const fontName = (iconPacks[packIndex]?.family || '').toString();
        const charToInsert = toChar(ic.code);
        if (!charToInsert) { setOpen(false); return; }
        const attrs = {
            class: 'up-inline-icon',
            'data-font': fontName,
            'data-code': charToInsert,
            'aria-hidden': 'true'
        };
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

    return (
        <Fragment>
            <RichTextToolbarButton
                icon="text"
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
                                onChange={ (v)=> { const i = parseInt(v,10); setPackIndex(i); } }
                            />
                        ) : (
                            <div style={{ padding:8, color:'#666' }}>
                                { __('Aucune police CSS détectée. Importez un ZIP via Réglages → Icônes inline.', 'up-iif') }
                            </div>
                        ) }
                        <div style={{ display:'grid', gridTemplateColumns:'repeat(6, 1fr)', gap:8, marginTop:8 }}>
                            { (packIndex>=0 ? (iconPacks[packIndex]?.icons || []) : []).map((ic, idx)=>{
                                const codeChar = toChar(ic.code);
                                return (
                                    <button key={idx}
                                        onClick={()=> insertGlyph(ic)}
                                        style={{
                                            fontFamily: iconPacks[packIndex]?.family || 'inherit',
                                            width:44, height:44, display:'flex', alignItems:'center', justifyContent:'center',
                                            border: '1px solid #ccc', borderRadius:4, background:'#fff', cursor:'pointer'
                                        }}
                                        title={`${ic.class} (U+${ic.code})`}
                                    >
                                        {codeChar}
                                    </button>
                                );
                            })}
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
