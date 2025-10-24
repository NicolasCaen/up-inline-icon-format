(function(wp){
	const { registerFormatType, toggleFormat, applyFormat, removeFormat, insert } = wp.richText;
	const { RichTextToolbarButton, RichTextShortcut, __experimentalRichText: RichText } = wp.blockEditor || wp.editor;
	const { Fragment, useState, useEffect, useRef } = wp.element;
	const { Popover, Button, SelectControl, TextControl, Spinner, PanelBody } = wp.components;
	const apiFetch = wp.apiFetch;
	const { __ } = wp.i18n;

	const TYPE = 'up/inline-icon';

	function InlineIconUI( { isActive, value, onChange, contentRef } ) {
		const [open, setOpen] = useState(false);
		const [fonts, setFonts] = useState( (typeof UP_IIF !== 'undefined' && Array.isArray(UP_IIF.fonts)) ? UP_IIF.fonts : [] );
		const [font, setFont] = useState( fonts[0]?.file || '' );
		const [glyphs, setGlyphs] = useState([]);
		const [glyph, setGlyph] = useState('');
		const [label, setLabel] = useState('');
		const [loading, setLoading] = useState(false);

		useEffect(()=>{
			async function fetchFonts(){
				try {
					const res = await apiFetch({ path: '/up-iif/v1/fonts' });
					if (res && Array.isArray(res.fonts)) {
						setFonts(res.fonts);
						if (!font && res.fonts[0]) setFont(res.fonts[0].file);
					}
				} catch(e) {}
			}
			if (!fonts.length) fetchFonts();
		}, []);

		useEffect(()=>{
			async function fetchGlyphs(){
				if (!font) { setGlyphs([]); setGlyph(''); return; }
				setLoading(true);
				try {
					const res = await apiFetch({ path: `/up-iif/v1/glyphs?font=${encodeURIComponent(font)}` });
					const list = res && Array.isArray(res.glyphs) ? res.glyphs : [];
					setGlyphs(list);
					setGlyph(list[0]?.unicode || '');
				} catch(e) {
					setGlyphs([]); setGlyph('');
				} finally { setLoading(false); }
			}
			fetchGlyphs();
		}, [font]);

		const insertIcon = () => {
			if (!font || !glyph) { setOpen(false); return; }
			const fontName = font.replace(/\.svg$/,'');
			const attrs = {
				class: 'up-inline-icon',
				'data-font': fontName ,
				'data-code': glyph,
				'aria-label': label || undefined,
				'aria-hidden': label ? undefined : 'true',
				style: `font-family: "${fontName}";`
			};
			let start = value.start, end = value.end;
			let working = value;
			// If no selection, insert the glyph char first
			if (start === end) {
				working = insert( working, glyph, start, end );
				start = start;
				end = start + glyph.length;
			}
			const newVal = applyFormat( working, { type: TYPE, attributes: attrs }, start, end );
			onChange( newVal );
			setOpen(false);
		};

		return (
			Fragment(
				null,
				wp.element.createElement(RichTextToolbarButton, {
					icon: 'text',
					title: __('Icône inline', 'up-iif'),
					onClick: ()=> setOpen( v => !v ),
					isActive
				}),
				open && wp.element.createElement(Popover, { onClose: ()=> setOpen(false), anchor: contentRef?.current },
					wp.element.createElement('div', { style: { padding: 12, width: 320 } },
						wp.element.createElement(SelectControl, {
							label: __('Police (SVG)', 'up-iif'),
							value: font,
							options: [{ label: '—', value: '' }].concat(fonts.map(f=>({ label: f.name, value: f.file }))),
							onChange: setFont
						}),
						loading ? wp.element.createElement(Spinner, {}) : wp.element.createElement(SelectControl, {
							label: __('Glyphe', 'up-iif'),
							value: glyph,
							options: [{ label: '—', value: '' }].concat(glyphs.map(g=>({ label: `${g.name} (${g.code})`, value: g.unicode }))),
							onChange: setGlyph
						}),
						wp.element.createElement(TextControl, { label: __('Libellé (aria-label) optionnel', 'up-iif'), value: label, onChange: setLabel }),
						wp.element.createElement('div', { style:{ display:'flex', gap:8, justifyContent:'flex-end', marginTop:8 } },
							wp.element.createElement(Button, { variant:'secondary', onClick: ()=> setOpen(false) }, __('Annuler','up-iif')),
							wp.element.createElement(Button, { variant:'primary', onClick: insertIcon, disabled: !font || !glyph }, __('Insérer','up-iif'))
						)
					)
				)
			)
		);
	}

	registerFormatType(TYPE, {
		title: __('Icône inline','up-iif'),
		tagName: 'span',
		className: 'up-inline-icon',
		attributes: {
			'data-font': 'data-font',
			'data-code': 'data-code',
			'aria-label': 'aria-label'
		},
		edit: (props) => {
			const { isActive, value, onChange, contentRef } = props;
			return wp.element.createElement(InlineIconUI, { isActive, value, onChange, contentRef });
		},
	});

})(window.wp);
