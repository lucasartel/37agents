(function (blocks, element, components, blockEditor) {
	'use strict';

	var el = element.createElement;
	var Fragment = element.Fragment;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var TextControl = components.TextControl;

	var data = window.wpagentBlockData || {};
	var i18n = data.i18n || {};
	var agentOptions = Array.isArray(data.agents) && data.agents.length
		? data.agents
		: [{ value: 'default', label: 'default' }];

	var layoutLabels = {
		'': i18n.layoutDefault || 'Usar padrão do agente',
		'chat': i18n.layoutChat || 'Chat Conversacional',
		'steps': i18n.layoutSteps || 'Passo a Passo (Steps)',
		'ead': i18n.layoutEad || 'Curso EAD (Módulos & Aulas)'
	};

	var themeLabels = {
		'': i18n.themeDefault || 'Usar padrão do agente',
		'light': i18n.themeLight || 'Modo Claro',
		'dark': i18n.themeDark || 'Modo Escuro'
	};

	blocks.registerBlockType('37agents/agent-widget', {
		apiVersion: 2,
		title: i18n.blockTitle || '37agents — Assistente de IA',
		description: i18n.blockDescription || 'Incorpora um agente de IA (Chat, Steps ou Curso EAD) do 37agents.',
		icon: 'format-chat',
		category: 'widgets',
		keywords: ['ai', 'agent', 'chat', '37agents', 'chatbot', 'ead'],
		attributes: {
			agent: { type: 'string', default: 'default' },
			layout: { type: 'string', default: '' },
			theme: { type: 'string', default: '' },
			title: { type: 'string', default: '' }
		},

		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();

			var isDarkPreview = attributes.theme === 'dark';
			var selectedAgentObj = agentOptions.find(function (item) {
				return item.value === attributes.agent;
			});
			var agentDisplayName = attributes.title || (selectedAgentObj ? selectedAgentObj.label : attributes.agent || 'default');
			var layoutDisplay = layoutLabels[attributes.layout || ''] || attributes.layout;
			var themeDisplay = themeLabels[attributes.theme || ''] || attributes.theme;

			var cardStyle = {
				border: isDarkPreview ? '1px solid #334155' : '1px solid #cbd5e1',
				borderRadius: '12px',
				padding: '20px 24px',
				background: isDarkPreview ? '#0f172a' : '#f8fafc',
				color: isDarkPreview ? '#f1f5f9' : '#0f172a',
				fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
				boxShadow: '0 2px 8px rgba(15, 23, 42, 0.06)'
			};

			var badgeStyle = {
				display: 'inline-block',
				padding: '3px 10px',
				borderRadius: '999px',
				fontSize: '11px',
				fontWeight: '600',
				background: isDarkPreview ? '#1e293b' : '#e2e8f0',
				color: isDarkPreview ? '#38bdf8' : '#0369a1',
				marginRight: '8px'
			};

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: i18n.settingsHeading || 'Configurações do Agente', initialOpen: true },
						el(SelectControl, {
							label: i18n.agentLabel || 'Agente',
							value: attributes.agent || 'default',
							options: agentOptions,
							onChange: function (value) {
								setAttributes({ agent: value });
							}
						}),
						el(SelectControl, {
							label: i18n.layoutLabel || 'Layout de Interface',
							value: attributes.layout || '',
							options: [
								{ value: '', label: layoutLabels[''] },
								{ value: 'chat', label: layoutLabels.chat },
								{ value: 'steps', label: layoutLabels.steps },
								{ value: 'ead', label: layoutLabels.ead }
							],
							onChange: function (value) {
								setAttributes({ layout: value });
							}
						}),
						el(SelectControl, {
							label: i18n.themeLabel || 'Tema Visual',
							value: attributes.theme || '',
							options: [
								{ value: '', label: themeLabels[''] },
								{ value: 'light', label: themeLabels.light },
								{ value: 'dark', label: themeLabels.dark }
							],
							onChange: function (value) {
								setAttributes({ theme: value });
							}
						}),
						el(TextControl, {
							label: i18n.titleLabel || 'Título personalizado (opcional)',
							value: attributes.title || '',
							onChange: function (value) {
								setAttributes({ title: value });
							}
						})
					)
				),
				el(
					'div',
					blockProps,
					el(
						'div',
						{ style: cardStyle },
						el(
							'div',
							{ style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '10px' } },
							el('strong', { style: { fontSize: '15px' } }, '🤖 37agents — ' + agentDisplayName),
							el('code', { style: { fontSize: '11px', opacity: 0.8 } }, '[37agents_chat agent="' + (attributes.agent || 'default') + '"]')
						),
						el(
							'div',
							{ style: { marginBottom: '10px' } },
							el('span', { style: badgeStyle }, 'Layout: ' + layoutDisplay),
							el('span', { style: badgeStyle }, 'Tema: ' + themeDisplay)
						),
						el(
							'p',
							{ style: { margin: 0, fontSize: '12px', color: isDarkPreview ? '#94a3b8' : '#64748b' } },
							i18n.previewHint || 'Selecione o agente, layout e tema no painel lateral direito.'
						)
					)
				)
			);
		},

		save: function () {
			return null;
		}
	});
})(
	window.wp.blocks,
	window.wp.element,
	window.wp.components,
	window.wp.blockEditor
);
