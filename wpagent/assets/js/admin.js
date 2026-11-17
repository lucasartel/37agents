(function ($) {
	'use strict';

	var STARTER_TEMPLATES = {
		support: {
			title: 'Assistente de Atendimento & FAQ',
			layout: 'chat',
			systemPrompt: 'Você é o assistente virtual oficial do site. Responda dúvidas dos visitantes sobre produtos, serviços, horários, prazos e políticas usando exclusivamente o conteúdo do site e os documentos da base de conhecimento. Seja cordial, objetivo e, caso não encontre a resposta, oriente o contato com a equipe humana.',
			siteContext: true,
			publicSite: true,
			guestChat: true,
			adminAssistant: false
		},
		diagnostic: {
			title: 'Diagnóstico & Orçamento Guiado',
			layout: 'steps',
			systemPrompt: 'Você é um consultor especialista que analisa respostas de diagnóstico de visitantes e entrega um plano de ação personalizado, estruturado e prático em Markdown.',
			stepsIntro: 'Olá! Responda rapidamente às 3 etapas abaixo para receber um diagnóstico personalizado e recomendações práticas.',
			stepsQuestions: "1. Contexto Atual | Qual é o seu segmento ou desafio principal hoje? | Ex.: loja virtual, consultoria, clínica, escola.\n2. Objetivo Principal | O que você deseja alcançar nos próximos 90 dias? | Ex.: aumentar vendas, reduzir chamados de suporte, automatizar processos.\n3. Recursos e Prazo | Qual é o prazo desejado e quais ferramentas já utiliza? | Ex.: WordPress, WooCommerce, CRM.",
			stepsFinalPrompt: 'Com base nas respostas do usuário, gere um relatório estruturado com: 1) Resumo do Cenário, 2) Principais Oportunidades, 3) Plano de Ação em 3 Passos e 4) Próximo Passo Recomendado.',
			stepsButtonLabel: 'Gerar Diagnóstico Completo',
			siteContext: true,
			guestChat: true,
			adminAssistant: false
		},
		course: {
			title: 'Mini-Curso Interativo EAD',
			layout: 'ead',
			systemPrompt: 'Você é o tutor e instrutor pedagógico deste curso. Explique conceitos de forma clara, didática, com exemplos práticos e sempre respeitando a ementa e o material didático indexado.',
			eadCourseTitle: 'Fundamentos Práticos de IA no WordPress',
			eadSyllabus: "Módulo 1: Primeiros Passos\nAula 1.1: Como Funcionam Agentes de IA (Conceitos iniciais; Prompts; Casos de uso)\nAula 1.2: Organizando sua Base de Conhecimento (Documentos; Perguntas frequentes; Boas práticas)\n\nMódulo 2: Aplicação Prática\nAula 2.1: Configurando Atendimento ao Visitante (Tom de voz; Regras de segurança; Testes)\nAula 2.2: Acompanhamento e Melhoria Contínua (Resumos; Feedback dos usuários; Ajustes)",
			siteContext: false,
			guestChat: true,
			adminAssistant: false
		},
		editorial: {
			title: 'Copiloto Editorial do Admin',
			layout: 'chat',
			systemPrompt: 'Você é o copiloto editorial e operacional do administrador no painel do WordPress. Ajude a criar rascunhos de artigos otimizados para SEO, revisar títulos, resumir comentários pendentes e organizar pautas de conteúdo usando o contexto do site.',
			siteContext: true,
			publicSite: false,
			guestChat: false,
			adminAssistant: true
		}
	};

	function switchAgentTab(targetTab) {
		var tabBtns = document.querySelectorAll('[data-wpagent-tab]');
		var tabPanels = document.querySelectorAll('[data-wpagent-tab-panel]');
		tabBtns.forEach(function (b) {
			var active = b.getAttribute('data-wpagent-tab') === targetTab;
			b.classList.toggle('is-active', active);
			b.setAttribute('aria-selected', active ? 'true' : 'false');
		});
		tabPanels.forEach(function (panel) {
			panel.classList.toggle('is-active', panel.getAttribute('data-wpagent-tab-panel') === targetTab);
		});
	}

	function updateLayoutVisibility(layoutVal) {
		document.querySelectorAll('.wpagent-layout-config').forEach(function (el) {
			el.style.display = 'none';
		});
		var target = document.querySelector('.wpagent-layout-config--' + layoutVal);
		if (target) {
			target.style.display = '';
		}
	}

	function applyStarterTemplate(templateKey) {
		var tpl = STARTER_TEMPLATES[templateKey];
		if (!tpl) {
			return;
		}

		var titleInput = document.getElementById('title');
		if (titleInput && !titleInput.value.trim()) {
			titleInput.value = tpl.title;
			var promptText = document.getElementById('title-prompt-text');
			if (promptText) {
				promptText.classList.add('screen-reader-text');
			}
		}

		var layoutSelect = document.getElementById('wpagent-agent-frontend-layout');
		if (layoutSelect && tpl.layout) {
			layoutSelect.value = tpl.layout;
			updateLayoutVisibility(tpl.layout);
		}

		var promptEl = document.getElementById('wpagent-agent-system-prompt');
		if (promptEl && tpl.systemPrompt) {
			promptEl.value = tpl.systemPrompt;
		}

		var stepsIntro = document.getElementById('wpagent-steps-intro');
		if (stepsIntro && typeof tpl.stepsIntro === 'string') {
			stepsIntro.value = tpl.stepsIntro;
		}
		var stepsQuestions = document.getElementById('wpagent-steps-questions');
		if (stepsQuestions && typeof tpl.stepsQuestions === 'string') {
			stepsQuestions.value = tpl.stepsQuestions;
		}
		var stepsFinal = document.getElementById('wpagent-steps-final-prompt');
		if (stepsFinal && typeof tpl.stepsFinalPrompt === 'string') {
			stepsFinal.value = tpl.stepsFinalPrompt;
		}
		var stepsBtn = document.getElementById('wpagent-steps-button-label');
		if (stepsBtn && typeof tpl.stepsButtonLabel === 'string') {
			stepsBtn.value = tpl.stepsButtonLabel;
		}

		var eadTitle = document.getElementById('wpagent-ead-course-title');
		if (eadTitle && typeof tpl.eadCourseTitle === 'string') {
			eadTitle.value = tpl.eadCourseTitle;
		}
		var eadSyllabus = document.getElementById('wpagent-ead-syllabus');
		if (eadSyllabus && typeof tpl.eadSyllabus === 'string') {
			eadSyllabus.value = tpl.eadSyllabus;
		}

		function setCheckbox(name, checked) {
			if (typeof checked !== 'boolean') {
				return;
			}
			var cb = document.querySelector('input[type="checkbox"][name="' + name + '"]');
			if (cb) {
				cb.checked = checked;
			}
		}

		setCheckbox('wpagent_agent[site_context_enabled]', tpl.siteContext);
		setCheckbox('wpagent_agent[public_site_assistant]', tpl.publicSite);
		setCheckbox('wpagent_agent[allow_guest_chat]', tpl.guestChat);
		setCheckbox('wpagent_agent[admin_assistant]', tpl.adminAssistant);

		var feedback = document.getElementById('wpagent-template-feedback');
		if (feedback) {
			feedback.textContent = '✅ Modelo "' + tpl.title + '" aplicado! Revise e clique em Publicar/Atualizar para salvar.';
			feedback.style.display = 'inline-block';
		}
	}

	function initAgentConfigScreen() {
		var tabBtns = document.querySelectorAll('[data-wpagent-tab]');
		tabBtns.forEach(function (btn) {
			btn.addEventListener('click', function () {
				switchAgentTab(btn.getAttribute('data-wpagent-tab'));
			});
		});

		var layoutSelect = document.getElementById('wpagent-agent-frontend-layout');
		if (layoutSelect) {
			layoutSelect.addEventListener('change', function () {
				updateLayoutVisibility(this.value);
			});
		}

		document.querySelectorAll('[data-wpagent-template]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				applyStarterTemplate(btn.getAttribute('data-wpagent-template'));
			});
		});

		var genBtn = document.getElementById('wpagent-btn-generate-syllabus');
		if (genBtn) {
			genBtn.addEventListener('click', function (e) {
				e.preventDefault();
				var statusSpan = document.getElementById('wpagent-syllabus-status');
				var url = genBtn.getAttribute('data-url');
				var nonce = genBtn.getAttribute('data-nonce');
				var slug = genBtn.getAttribute('data-agent-slug');
				var courseTitleInput = document.getElementById('wpagent-ead-course-title');
				var courseTitle = courseTitleInput ? courseTitleInput.value.trim() : '';
				var manualTextInput = document.querySelector('textarea[name="wpagent_manual_training_text"]');
				var manualText = manualTextInput ? manualTextInput.value.trim() : '';

				statusSpan.textContent = '⏳ Lendo documentos e extraindo ementa com IA...';
				statusSpan.style.color = '#0284c7';
				genBtn.disabled = true;

				fetch(url, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': nonce
					},
					body: JSON.stringify({
						agent_slug: slug,
						course_title: courseTitle,
						manual_text: manualText
					})
				})
					.then(function (res) { return res.json(); })
					.then(function (data) {
						genBtn.disabled = false;
						if (data && data.syllabus) {
							var syllabusTextarea = document.getElementById('wpagent-ead-syllabus');
							if (syllabusTextarea) {
								syllabusTextarea.value = data.syllabus;
								var lines = data.syllabus.split('\n').length;
								syllabusTextarea.rows = Math.min(30, Math.max(10, lines + 2));
							}
							statusSpan.textContent = '✅ Ementa estruturada e extraída com sucesso!';
							statusSpan.style.color = '#16a34a';
						} else {
							statusSpan.textContent = '⚠️ ' + ((data && data.message) ? data.message : 'Erro ao gerar ementa.');
							statusSpan.style.color = '#dc2626';
						}
					})
					.catch(function () {
						genBtn.disabled = false;
						statusSpan.textContent = '⚠️ Erro de conexão com o servidor.';
						statusSpan.style.color = '#dc2626';
					});
			});
		}
	}

	function initModerationScreen() {
		var $wrap = $('#wpagent-moderation-wrap');
		if (!$wrap.length) {
			return;
		}

		var nonce = $wrap.attr('data-nonce') || '';
		var msgDone = $wrap.attr('data-msg-done') || 'Análise concluída. Recarregando comentários processados...';
		var msgErr = $wrap.attr('data-msg-error') || 'Erro:';
		var msgUnknown = $wrap.attr('data-msg-unknown') || 'Desconhecido';

		function showNotice(type, text) {
			var $notice = $('#wpagent-moderation-notice');
			$notice.removeClass('notice-success notice-error').addClass(type === 'error' ? 'notice-error' : 'notice-success');
			$notice.find('p').text(text);
			$notice.slideDown(150);
		}

		$('#wpagent-run-moderation-now').on('click', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var $spinner = $('#wpagent-run-spinner');

			$btn.prop('disabled', true);
			$spinner.addClass('is-active');

			$.post(window.ajaxurl, {
				action: 'wpagent_run_comment_moderation_now',
				nonce: nonce
			}, function (res) {
				if (res && res.success) {
					showNotice('success', msgDone);
					setTimeout(function () { window.location.reload(); }, 900);
				} else {
					showNotice('error', msgErr + ' ' + (res && res.data && res.data.message ? res.data.message : ''));
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');
				}
			});
		});

		$('.wpagent-approve-reply-btn').on('click', function (e) {
			e.preventDefault();
			var id = $(this).data('id');
			var replyText = $('#reply-text-' + id).val();
			var $spinner = $('#spinner-' + id);
			var $row = $('#comment-row-' + id);

			$(this).prop('disabled', true);
			$spinner.addClass('is-active');

			$.post(window.ajaxurl, {
				action: 'wpagent_approve_reply_comment',
				nonce: nonce,
				comment_id: id,
				reply_text: replyText
			}, function (res) {
				if (res && res.success) {
					$row.fadeOut(300, function () { $(this).remove(); });
				} else {
					showNotice('error', msgErr + ' ' + (res && res.data && res.data.message ? res.data.message : msgUnknown));
					$row.find('button').prop('disabled', false);
					$spinner.removeClass('is-active');
				}
			});
		});

		$('.wpagent-mark-spam-btn').on('click', function (e) {
			e.preventDefault();
			var id = $(this).data('id');
			var $spinner = $('#spinner-' + id);
			var $row = $('#comment-row-' + id);

			$(this).prop('disabled', true);
			$spinner.addClass('is-active');

			$.post(window.ajaxurl, {
				action: 'wpagent_mark_comment_spam',
				nonce: nonce,
				comment_id: id
			}, function (res) {
				if (res && res.success) {
					$row.fadeOut(300, function () { $(this).remove(); });
				} else {
					showNotice('error', msgErr + ' ' + (res && res.data && res.data.message ? res.data.message : msgUnknown));
					$row.find('button').prop('disabled', false);
					$spinner.removeClass('is-active');
				}
			});
		});
	}

	window.wpagent_reset_tokens = function (userId) {
		var input = document.getElementById('reset_user_id');
		var form = document.querySelector('form[action*="admin-post.php"]');
		if (input && form) {
			input.value = userId;
			form.submit();
		}
	};

	function updateCompatibleModelDatalist(presets, selectedKey) {
		var datalist = document.getElementById('wpagent-compatible-model-presets');
		if (!datalist || !presets) {
			return;
		}
		var seen = {};
		var ordered = [];
		var addModel = function (modelId) {
			if (!modelId || seen[modelId]) {
				return;
			}
			seen[modelId] = true;
			ordered.push(modelId);
		};
		if (selectedKey && presets[selectedKey] && Array.isArray(presets[selectedKey].models)) {
			presets[selectedKey].models.forEach(addModel);
		}
		Object.keys(presets).forEach(function (key) {
			var entry = presets[key];
			if (entry && Array.isArray(entry.models)) {
				entry.models.forEach(addModel);
			}
		});
		while (datalist.firstChild) {
			datalist.removeChild(datalist.firstChild);
		}
		ordered.forEach(function (modelId) {
			var opt = document.createElement('option');
			opt.value = modelId;
			datalist.appendChild(opt);
		});
	}

	function bindCompatiblePresetSelect(selectId, baseUrlId, modelId, hintId, isAgentOverride) {
		var select = document.getElementById(selectId);
		if (!select) {
			return;
		}
		var presets = null;
		try {
			presets = JSON.parse(select.getAttribute('data-presets') || '{}');
		} catch (e) {
			presets = {};
		}
		var baseUrlInput = document.getElementById(baseUrlId);
		var modelInput = document.getElementById(modelId);
		var hintEl = document.getElementById(hintId);

		var knownUrls = {};
		var knownModels = {};
		Object.keys(presets).forEach(function (key) {
			var item = presets[key];
			if (!item) {
				return;
			}
			if (item.base_url) {
				knownUrls[item.base_url] = true;
			}
			if (item.default_model) {
				knownModels[item.default_model] = true;
			}
			if (Array.isArray(item.models)) {
				item.models.forEach(function (m) {
					knownModels[m] = true;
				});
			}
		});

		select.addEventListener('change', function () {
			var key = select.value;
			var data = presets[key] || null;
			updateCompatibleModelDatalist(presets, key);

			if (!key && isAgentOverride) {
				if (baseUrlInput && knownUrls[String(baseUrlInput.value || '').trim()]) {
					baseUrlInput.value = '';
				}
				if (modelInput && knownModels[String(modelInput.value || '').trim()]) {
					modelInput.value = '';
				}
				return;
			}

			if (!data) {
				return;
			}

			if (hintEl && data.key_hint) {
				hintEl.textContent = data.key_hint;
			}

			if (baseUrlInput) {
				var currentUrl = String(baseUrlInput.value || '').trim();
				if (data.base_url) {
					if (!isAgentOverride || !currentUrl || knownUrls[currentUrl]) {
						baseUrlInput.value = data.base_url;
					}
					baseUrlInput.setAttribute('placeholder', data.base_url);
				} else if (key === 'custom') {
					baseUrlInput.setAttribute('placeholder', 'https://seu-endpoint.exemplo.com/v1');
					if (!isAgentOverride && knownUrls[currentUrl]) {
						baseUrlInput.value = '';
					}
				}
			}

			if (modelInput) {
				var currentModel = String(modelInput.value || '').trim();
				if (data.default_model) {
					if (!currentModel || knownModels[currentModel]) {
						modelInput.value = data.default_model;
					}
					modelInput.setAttribute('placeholder', data.default_model);
				} else if (key === 'custom') {
					modelInput.setAttribute('placeholder', 'model-id');
					if (!isAgentOverride && knownModels[currentModel]) {
						modelInput.value = '';
					}
				}
			}
		});
	}

	function initCompatibleProviders() {
		bindCompatiblePresetSelect(
			'wpagent-compatible-preset',
			'wpagent-compatible-base-url',
			'wpagent-compatible-model',
			'wpagent-compatible-key-hint',
			false
		);
		bindCompatiblePresetSelect(
			'wpagent-agent-compatible-preset',
			'wpagent-agent-compatible-base-url',
			'wpagent-agent-compatible-model',
			'wpagent-agent-compatible-key-hint',
			true
		);

		var testBtn = document.getElementById('wpagent-test-compatible-api');
		if (testBtn) {
			testBtn.addEventListener('click', function () {
				var resultEl = document.getElementById('wpagent-test-compatible-result');
				var presetEl = document.getElementById('wpagent-compatible-preset');
				var baseUrlEl = document.getElementById('wpagent-compatible-base-url');
				var apiKeyEl = document.getElementById('wpagent-compatible-api-key');
				var modelEl = document.getElementById('wpagent-compatible-model');

				testBtn.disabled = true;
				if (resultEl) {
					resultEl.style.color = '#1e293b';
					resultEl.textContent = 'Testando conexão...';
				}

				$.post(window.ajaxurl, {
					action: 'wpagent_test_compatible_api',
					nonce: testBtn.getAttribute('data-nonce') || '',
					preset: presetEl ? presetEl.value : '',
					base_url: baseUrlEl ? baseUrlEl.value : '',
					api_key: apiKeyEl ? apiKeyEl.value : '',
					model: modelEl ? modelEl.value : ''
				})
					.done(function (res) {
						if (res && res.success) {
							if (baseUrlEl && res.data && res.data.base_url) {
								baseUrlEl.value = res.data.base_url;
							}
							if (modelEl && res.data && res.data.model) {
								modelEl.value = res.data.model;
							}
							if (apiKeyEl && apiKeyEl.value.trim() !== '') {
								apiKeyEl.value = '';
								apiKeyEl.setAttribute('placeholder', 'Chave salva. Preencha apenas para trocar.');
							}
							if (resultEl) {
								resultEl.style.color = '#047857';
								resultEl.textContent = (res.data && res.data.message) ? res.data.message : 'Conexão validada com sucesso!';
							}
						} else {
							var errMsg = (res && res.data && res.data.message) ? res.data.message : 'Falha ao validar a conexão.';
							if (resultEl) {
								resultEl.style.color = '#dc2626';
								resultEl.textContent = errMsg;
							}
						}
					})
					.fail(function (xhr) {
						var msg = 'Erro de comunicação ao testar a conexão.';
						if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
							msg = xhr.responseJSON.data.message;
						}
						if (resultEl) {
							resultEl.style.color = '#dc2626';
							resultEl.textContent = msg;
						}
					})
					.always(function () {
						testBtn.disabled = false;
					});
			});
		}
	}

	document.addEventListener('DOMContentLoaded', function () {
		initAgentConfigScreen();
		initModerationScreen();
		initCompatibleProviders();
	});
})(window.jQuery);
