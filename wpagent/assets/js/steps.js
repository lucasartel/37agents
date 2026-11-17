/**
 * WPAgents - STEPS Layout (Passo a Passo Guiado & Relatório Final)
 */
(function () {
	'use strict';

	function initSteps(container) {
		var agentSlug = container.getAttribute('data-agent');
		var restUrl = container.getAttribute('data-rest-url');
		var progressUrl = container.getAttribute('data-progress-url');
		var reportUrl = container.getAttribute('data-report-url');
		var resetUrl = container.getAttribute('data-reset-url');
		var leadsUrl = container.getAttribute('data-leads-url') || '';
		var leadCaptureEnabled = container.getAttribute('data-lead-capture-enabled') === '1';
		var nonce = container.getAttribute('data-nonce');
		var isLoggedIn = container.getAttribute('data-logged-in') === '1';
		var buttonLabel = container.getAttribute('data-button-label') || 'Gerar Relatório Final';
		var sessionId = getSessionId(agentSlug);

		// Read intro and questions from JSON block (preserves newlines, unlike data-attributes)
		var introText = '';
		var rawQuestions = '';
		var configScript = container.querySelector('script.wpagent-steps-config');
		if (configScript) {
			try {
				var cfg = JSON.parse(configScript.textContent);
				introText = cfg.intro || '';
				rawQuestions = cfg.questions || '';
			} catch (e) {
				introText = '';
				rawQuestions = '';
			}
		}

		var stepCountEl = container.querySelector('[data-steps-count]');
		var progressBarEl = container.querySelector('[data-steps-progress]');
		var introCard = container.querySelector('[data-steps-intro-card]');
		var introTextEl = container.querySelector('[data-steps-intro-text]');
		var startBtn = container.querySelector('[data-steps-start]');
		var questionCard = container.querySelector('[data-steps-question-card]');
		var badgeEl = container.querySelector('[data-steps-badge]');
		var questionTitleEl = container.querySelector('[data-steps-question-title]');
		var questionTipEl = container.querySelector('[data-steps-question-tip]');
		var inputEl = container.querySelector('[data-steps-input]');
		var prevBtn = container.querySelector('[data-steps-prev]');
		var skipBtn = container.querySelector('[data-steps-skip]');
		var nextBtn = container.querySelector('[data-steps-next]');
		var reportCard = container.querySelector('[data-steps-report-card]');
		var reportContentEl = container.querySelector('[data-steps-report-content]');
		var copyBtn = container.querySelector('[data-steps-copy]');
		var printBtn = container.querySelector('[data-steps-print]');
		var restartBtn = container.querySelector('[data-steps-restart]');
		var themeToggleBtn = container.querySelector('[data-steps-theme-toggle]');
		var themeStorageKey = 'wpagent-theme-' + agentSlug;

		function applyTheme(theme) {
			var isDark = theme === 'dark';
			container.classList.toggle('is-dark-mode', isDark);
			container.setAttribute('data-default-theme', isDark ? 'dark' : 'light');
			if (themeToggleBtn) {
				themeToggleBtn.textContent = isDark ? '☀️ Modo claro' : '🌙 Modo escuro';
				themeToggleBtn.setAttribute('aria-pressed', isDark ? 'true' : 'false');
			}
		}

		(function initTheme() {
			var stored = '';
			try {
				stored = localStorage.getItem(themeStorageKey) || '';
			} catch (e) {}
			if (stored === 'dark' || stored === 'light') {
				applyTheme(stored);
			} else {
				applyTheme(container.getAttribute('data-default-theme') === 'dark' ? 'dark' : 'light');
			}
		})();

		if (themeToggleBtn) {
			themeToggleBtn.addEventListener('click', function () {
				var next = container.classList.contains('is-dark-mode') ? 'light' : 'dark';
				applyTheme(next);
				try {
					localStorage.setItem(themeStorageKey, next);
				} catch (e) {}
			});
		}

		function confirmModal(titleText, messageText, onConfirm) {
			var backdrop = document.createElement('div');
			backdrop.className = 'wpagent-steps__modal-backdrop';
			backdrop.innerHTML =
				'<div class="wpagent-steps__modal" role="dialog" aria-modal="true">' +
				'<h4>' + escapeHtml(titleText) + '</h4>' +
				'<p>' + escapeHtml(messageText) + '</p>' +
				'<div class="wpagent-steps__modal-actions">' +
				'<button type="button" class="wpagent-steps__modal-btn" data-modal-cancel>Cancelar</button>' +
				'<button type="button" class="wpagent-steps__modal-btn wpagent-steps__modal-btn--confirm" data-modal-confirm>Confirmar</button>' +
				'</div></div>';
			container.appendChild(backdrop);

			function close() {
				if (backdrop.parentNode) backdrop.parentNode.removeChild(backdrop);
			}
			backdrop.querySelector('[data-modal-cancel]').addEventListener('click', close);
			backdrop.querySelector('[data-modal-confirm]').addEventListener('click', function () {
				close();
				onConfirm();
			});
			backdrop.addEventListener('click', function (e) {
				if (e.target === backdrop) close();
			});
		}

		var steps = parseQuestions(rawQuestions);
		var currentStepIndex = 0;
		var answers = []; // [{ title, question, answer }]
		var isGeneratingReport = false;
		var hasStarted = false;

		function getSessionId(slug) {
			var key = 'wpagent_steps_session_' + slug;
			var id = localStorage.getItem(key);
			if (!id) {
				id = 'sess_' + Math.random().toString(36).substring(2, 15) + Date.now().toString(36);
				localStorage.setItem(key, id);
			}
			return id;
		}

		function escapeHtml(str) {
			if (!str) return '';
			return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
		}

		function parseQuestions(raw) {
			if (!raw || !raw.trim()) {
				return [
					{ title: 'Objetivo', question: 'Qual é o seu objetivo principal com este planejamento?', tip: 'Ex.: Aumentar vendas, estruturar processos, criar um plano de estudo.' },
					{ title: 'Público ou Contexto', question: 'Para quem ou para qual cenário este trabalho se destina?', tip: 'Descreva seu público-alvo, equipe ou área de foco.' },
					{ title: 'Desafios', question: 'Quais são as principais dificuldades ou barreiras atuais?', tip: 'Liste os pontos de atenção ou bloqueios que precisam ser superados.' },
					{ title: 'Prazos e Recursos', question: 'Qual é o prazo estimado e quais recursos estão disponíveis?', tip: 'Ex.: 30 dias, orçamento restrito, equipe de 2 pessoas.' }
				];
			}

			var normalized = raw.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
			var lines = normalized.split('\n').map(function (l) { return l.trim(); }).filter(Boolean);
			var parsed = [];
			lines.forEach(function (line, idx) {
				var parts = line.split('|').map(function (p) { return p.trim(); });
				if (parts.length >= 3) {
					parsed.push({ title: parts[0], question: parts[1], tip: parts.slice(2).join(' | ') });
				} else if (parts.length === 2) {
					parsed.push({ title: parts[0], question: parts[1], tip: '' });
				} else {
					parsed.push({ title: 'Etapa ' + (idx + 1), question: parts[0], tip: '' });
				}
			});

			return parsed.length ? parsed : [
				{ title: 'Etapa 1', question: raw.trim(), tip: '' }
			];
		}

		function fetchProgress() {
			var url = progressUrl + '?agent_slug=' + encodeURIComponent(agentSlug) + '&layout_type=steps&session_id=' + encodeURIComponent(sessionId);
			fetch(url, {
				headers: { 'X-WP-Nonce': nonce }
			})
			.then(function (res) { return res.json(); })
			.then(function (data) {
				if (data && data.progress) {
					var p = data.progress;
					if (p.status === 'completed' && p.final_report) {
						hasStarted = true;
						showReport(p.final_report);
						return;
					}
					if (Array.isArray(p.completed_steps) && p.completed_steps.length > 0) {
						answers = p.completed_steps;
						hasStarted = true;
					}
					if (typeof p.current_step === 'number' && p.current_step > 0) {
						currentStepIndex = Math.min(p.current_step, steps.length - 1);
						hasStarted = true;
					}
				}
				renderCurrentState();
			})
			.catch(function () {
				renderCurrentState();
			});
		}

		function saveProgress(finalReport, status) {
			var payload = {
				agent_slug: agentSlug,
				layout_type: 'steps',
				session_id: sessionId,
				current_step: currentStepIndex,
				completed_steps: answers,
				final_report: finalReport || '',
				status: status || 'in_progress'
			};

			fetch(progressUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': nonce
				},
				body: JSON.stringify(payload)
			}).catch(function () {});
		}

		function renderCurrentState() {
			if (!hasStarted && introText && currentStepIndex === 0 && answers.length === 0) {
				introCard.style.display = 'block';
				introTextEl.innerHTML = formatMarkdown(introText);
				questionCard.style.display = 'none';
				reportCard.style.display = 'none';
				updateProgress(0, steps.length);
				return;
			}

			introCard.style.display = 'none';
			reportCard.style.display = 'none';
			questionCard.style.display = 'block';

			var cur = steps[currentStepIndex];
			if (!cur) return;

			badgeEl.textContent = cur.title || ('Etapa ' + (currentStepIndex + 1));
			questionTitleEl.textContent = cur.question;
			if (cur.tip) {
				questionTipEl.textContent = '💡 ' + cur.tip;
				questionTipEl.style.display = 'block';
			} else {
				questionTipEl.style.display = 'none';
			}

			// Restore existing answer if present
			var existing = answers[currentStepIndex];
			inputEl.value = existing ? (existing.answer === '[Etapa pulada / sem resposta]' ? '' : existing.answer) : '';
			inputEl.focus();

			// If last step, change button text
			if (currentStepIndex === steps.length - 1) {
				nextBtn.textContent = buttonLabel + ' ➔';
				nextBtn.classList.add('wpagent-steps__next-btn--finish');
			} else {
				nextBtn.textContent = 'Próxima Etapa ➔';
				nextBtn.classList.remove('wpagent-steps__next-btn--finish');
			}

			if (prevBtn) {
				prevBtn.style.display = currentStepIndex > 0 ? 'inline-flex' : 'none';
			}

			updateProgress(currentStepIndex + 1, steps.length);
		}

		function updateProgress(current, total) {
			var pct = Math.round((current / total) * 100);
			stepCountEl.textContent = 'Etapa ' + current + ' de ' + total + ' (' + pct + '%)';
			progressBarEl.style.width = pct + '%';
		}

		function handleNext() {
			if (isGeneratingReport) return;

			var val = inputEl.value.trim();
			var cur = steps[currentStepIndex];

			answers[currentStepIndex] = {
				title: cur.title,
				question: cur.question,
				answer: val || '[Sem resposta fornecida]'
			};

			if (currentStepIndex < steps.length - 1) {
				currentStepIndex++;
				saveProgress();
				renderCurrentState();
			} else {
				// Last step! Generate final report!
				generateReport();
			}
		}

		function handleSkip() {
			if (isGeneratingReport) return;

			var cur = steps[currentStepIndex];
			answers[currentStepIndex] = {
				title: cur.title,
				question: cur.question,
				answer: '[Etapa pulada / sem resposta]'
			};

			if (currentStepIndex < steps.length - 1) {
				currentStepIndex++;
				saveProgress();
				renderCurrentState();
			} else {
				generateReport();
			}
		}

		function handlePrev() {
			if (isGeneratingReport) return;
			if (currentStepIndex > 0) {
				var val = inputEl.value.trim();
				if (val) {
					var cur = steps[currentStepIndex];
					answers[currentStepIndex] = {
						title: cur.title,
						question: cur.question,
						answer: val
					};
				}
				currentStepIndex--;
				saveProgress();
				renderCurrentState();
			}
		}

		function generateReport() {
			isGeneratingReport = true;
			questionCard.style.display = 'none';
			reportCard.style.display = 'block';
			reportContentEl.innerHTML = '<div class="wpagent-steps__loading"><div class="wpagent-steps__spinner"></div><p><strong>Aguarde um instante...</strong><br>O assistente de IA está compilando suas respostas e gerando o relatório final completo...</p></div>';
			updateProgress(steps.length, steps.length);

			fetch(reportUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': nonce
				},
				body: JSON.stringify({
					agent_slug: agentSlug,
					answers: answers,
					session_id: sessionId
				})
			})
			.then(function (res) { return res.json(); })
			.then(function (data) {
				isGeneratingReport = false;
				if (data.report) {
					showReport(data.report);
				} else {
					var errMsg = data.message || 'Erro ao gerar relatório. Tente novamente.';
					reportContentEl.innerHTML = '<div class="wpagent-steps__error-box" style="text-align:center;padding:24px 16px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;margin:20px 0;">' +
						'<p style="color:#b91c1c;font-weight:600;font-size:15px;margin:0 0 12px;">⚠️ ' + escapeHtml(errMsg) + '</p>' +
						'<button type="button" class="wpagent-steps__retry-btn" style="background:#b91c1c;color:#fff;border:none;padding:10px 20px;font-size:14px;font-weight:600;border-radius:6px;cursor:pointer;">🔄 Tentar Gerar Novamente</button>' +
						'</div>';
					var retryBtn = reportContentEl.querySelector('.wpagent-steps__retry-btn');
					if (retryBtn) {
						retryBtn.addEventListener('click', function () {
							generateReport();
						});
					}
				}
			})
			.catch(function (err) {
				isGeneratingReport = false;
				reportContentEl.innerHTML = '<div class="wpagent-steps__error-box" style="text-align:center;padding:24px 16px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;margin:20px 0;">' +
					'<p style="color:#b91c1c;font-weight:600;font-size:15px;margin:0 0 12px;">⚠️ Erro de conexão com o servidor.</p>' +
					'<button type="button" class="wpagent-steps__retry-btn" style="background:#b91c1c;color:#fff;border:none;padding:10px 20px;font-size:14px;font-weight:600;border-radius:6px;cursor:pointer;">🔄 Tentar Gerar Novamente</button>' +
					'</div>';
				var retryBtn = reportContentEl.querySelector('.wpagent-steps__retry-btn');
				if (retryBtn) {
					retryBtn.addEventListener('click', function () {
						generateReport();
					});
				}
			});
		}

		function showReport(markdown) {
			introCard.style.display = 'none';
			questionCard.style.display = 'none';
			reportCard.style.display = 'block';
			reportContentEl.innerHTML = formatMarkdown(markdown);
			stepCountEl.textContent = 'Questionário Concluído (100%)';
			progressBarEl.style.width = '100%';
		}

		function formatMarkdown(text) {
			if (!text) return '';

			var lines = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
			var html = '';
			var inCode = false;
			var codeBuffer = [];
			var codeLang = '';
			var inList = null; // 'ul' or 'ol'
			var paraBuffer = [];
			var tableBuffer = [];

			function flushPara() {
				if (paraBuffer.length > 0) {
					var pText = paraBuffer.join('<br>');
					html += '<p class="wpagent-p">' + pText + '</p>\n';
					paraBuffer = [];
				}
			}

			function flushList() {
				if (inList !== null) {
					html += '</' + inList + '>\n';
					inList = null;
				}
			}

			function flushTable() {
				if (tableBuffer.length > 0) {
					html += '<div class="wpagent-table-wrapper"><table class="wpagent-table">';
					var first = true;
					tableBuffer.forEach(function (row) {
						if (/^\|[\s\-:|]+\|$/.test(row)) return;
						var cells = row.split('|').map(function (c) { return c.trim(); });
						if (cells[0] === '') cells.shift();
						if (cells.length > 0 && cells[cells.length - 1] === '') cells.pop();

						if (first) {
							html += '<thead><tr>';
							cells.forEach(function (c) { html += '<th>' + inlineFormat(c) + '</th>'; });
							html += '</tr></thead><tbody>';
							first = false;
						} else {
							html += '<tr>';
							cells.forEach(function (c) { html += '<td>' + inlineFormat(c) + '</td>'; });
							html += '</tr>';
						}
					});
					html += '</tbody></table></div>\n';
					tableBuffer = [];
				}
			}

			function inlineFormat(str) {
				if (!str) return '';
				var s = str
					.replace(/&/g, '&amp;')
					.replace(/</g, '&lt;')
					.replace(/>/g, '&gt;');
				s = s.replace(/`([^`]+)`/g, '<code class="wpagent-inline-code">$1</code>');
				s = s.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
				s = s.replace(/\*(.*?)\*/g, '<em>$1</em>');
				s = s.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, function (match, label, rawUrl) {
					var cleanUrl = String(rawUrl || '').trim();
					if (/^(https?:\/\/|mailto:|\/(?!\/))/i.test(cleanUrl)) {
						return '<a href="' + cleanUrl.replace(/"/g, '&quot;') + '" target="_blank" rel="noopener noreferrer">' + label + '</a>';
					}
					return label;
				});
				return s;
			}

			lines.forEach(function (line) {
				var trimmed = line.trim();

				// Fenced code blocks
				var codeMatch = trimmed.match(/^```([a-z0-9_-]*)/i);
				if (codeMatch) {
					flushPara();
					flushList();
					flushTable();
					if (inCode) {
						var codeText = codeBuffer.join('\n')
							.replace(/&/g, '&amp;')
							.replace(/</g, '&lt;')
							.replace(/>/g, '&gt;');
						html += '<pre class="wpagent-code-block"><code' + (codeLang ? ' class="language-' + codeLang + '"' : '') + '>' + codeText + '</code></pre>\n';
						codeBuffer = [];
						inCode = false;
					} else {
						inCode = true;
						codeLang = codeMatch[1];
					}
					return;
				}

				if (inCode) {
					codeBuffer.push(line);
					return;
				}

				// Table rows
				if (/^\|.+\|$/.test(trimmed)) {
					flushPara();
					flushList();
					tableBuffer.push(trimmed);
					return;
				} else {
					flushTable();
				}

				// Blank line: terminates lists and paragraphs
				if (trimmed === '') {
					flushPara();
					flushList();
					return;
				}

				// Horizontal Dividers
				if (/^(?:---|\*\*\*|___)$/.test(trimmed)) {
					flushPara();
					flushList();
					html += '<hr class="wpagent-divider">\n';
					return;
				}

				// Section numbers with emojis
				var sectionMatch = trimmed.match(/^(\d+)\.\s+([🎯📖💡📝].*)$/);
				if (sectionMatch) {
					flushPara();
					flushList();
					html += '<h3 class="wpagent-report-h3"><span class="wpagent-section-num">' + sectionMatch[1] + '. </span>' + inlineFormat(sectionMatch[2]) + '</h3>\n';
					return;
				}

				// Standard Markdown Headers
				var headerMatch = trimmed.match(/^(#{1,4})\s+(.*)$/);
				if (headerMatch) {
					flushPara();
					flushList();
					var lvl = headerMatch[1].length;
					html += '<h' + lvl + ' class="wpagent-report-h' + lvl + '">' + inlineFormat(headerMatch[2]) + '</h' + lvl + '>\n';
					return;
				}

				// Callouts & Quotes (> [!TIP] or > texto)
				var quoteMatch = trimmed.match(/^>\s*(.*)$/);
				if (quoteMatch) {
					flushPara();
					flushList();
					var qText = quoteMatch[1];
					var calloutMatch = qText.match(/^\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]\s*(.*)$/i);
					if (calloutMatch) {
						var type = calloutMatch[1].toLowerCase();
						var icon = type === 'tip' ? '💡' : (type === 'important' ? '⚡' : (type === 'warning' ? '⚠️' : '📌'));
						html += '<div class="wpagent-callout wpagent-callout--' + type + '"><div class="wpagent-callout__icon">' + icon + '</div><div class="wpagent-callout__body">' + inlineFormat(calloutMatch[2]) + '</div></div>\n';
					} else {
						html += '<blockquote class="wpagent-quote">' + inlineFormat(qText) + '</blockquote>\n';
					}
					return;
				}

				// Unordered list (- or * or •)
				var ulMatch = trimmed.match(/^[-*•]\s+(.*)$/);
				if (ulMatch) {
					flushPara();
					if (inList !== 'ul') {
						flushList();
						html += '<ul class="wpagent-ul">\n';
						inList = 'ul';
					}
					html += '  <li class="wpagent-li">' + inlineFormat(ulMatch[1]) + '</li>\n';
					return;
				}

				// Ordered list (1. or 1))
				var olMatch = trimmed.match(/^\d+[\.\)]\s+(.*)$/);
				if (olMatch) {
					flushPara();
					if (inList !== 'ol') {
						flushList();
						html += '<ol class="wpagent-ol">\n';
						inList = 'ol';
					}
					html += '  <li class="wpagent-oli">' + inlineFormat(olMatch[1]) + '</li>\n';
					return;
				}

				// Normal paragraph line
				flushList();
				paraBuffer.push(inlineFormat(trimmed));
			});

			flushPara();
			flushList();
			flushTable();

			return html;
		}

		function handleReset() {
			confirmModal(
				'Reiniciar Questionário',
				'Deseja reiniciar o questionário do início? As respostas anteriores serão apagadas.',
				function () {
					fetch(resetUrl, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': nonce
						},
						body: JSON.stringify({
							agent_slug: agentSlug,
							layout_type: 'steps',
							session_id: sessionId
						})
					})
					.then(function () {
						hasStarted = false;
						currentStepIndex = 0;
						answers = [];
						renderCurrentState();
					})
					.catch(function () {
						hasStarted = false;
						currentStepIndex = 0;
						answers = [];
						renderCurrentState();
					});
				}
			);
		}

		// Event Listeners
		if (startBtn) {
			startBtn.addEventListener('click', function () {
				hasStarted = true;
				renderCurrentState();
			});
		}
		if (prevBtn) {
			prevBtn.addEventListener('click', handlePrev);
		}
		if (nextBtn) {
			nextBtn.addEventListener('click', handleNext);
		}
		if (skipBtn) {
			skipBtn.addEventListener('click', handleSkip);
		}
		if (inputEl) {
			inputEl.addEventListener('keydown', function (e) {
				if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
					e.preventDefault();
					handleNext();
				}
			});
		}
		if (copyBtn) {
			copyBtn.addEventListener('click', function () {
				var text = reportContentEl.innerText || reportContentEl.textContent;
				navigator.clipboard.writeText(text).then(function () {
					var orig = copyBtn.textContent;
					copyBtn.textContent = '✅ Copiado!';
					setTimeout(function () { copyBtn.textContent = orig; }, 2000);
				});
			});
		}
		if (printBtn) {
			printBtn.addEventListener('click', function () {
				window.print();
			});
		}
		if (restartBtn) {
			restartBtn.addEventListener('click', handleReset);
		}

		function setupLeadCapture() {
			var leadBar = container.querySelector('[data-wpagent-lead-bar]');
			if (!leadBar) {
				return;
			}
			var storageKey = 'wpagent-lead-' + agentSlug;
			var alreadyCaptured = false;
			try {
				alreadyCaptured = Boolean(window.localStorage.getItem(storageKey));
			} catch (err) {}

			if (isLoggedIn || !leadCaptureEnabled || !leadsUrl || alreadyCaptured) {
				leadBar.style.display = 'none';
				return;
			}

			var nameInput = leadBar.querySelector('[data-lead-name]');
			var emailInput = leadBar.querySelector('[data-lead-email]');
			var submitLeadBtn = leadBar.querySelector('[data-lead-submit]');
			var feedbackEl = leadBar.querySelector('[data-lead-feedback]');

			var showFeedback = function (msg, isError) {
				if (!feedbackEl) {
					return;
				}
				feedbackEl.textContent = msg;
				feedbackEl.classList.toggle('is-error', Boolean(isError));
				feedbackEl.style.display = 'block';
			};

			var submitLead = function () {
				var name = nameInput ? String(nameInput.value || '').trim() : '';
				var email = emailInput ? String(emailInput.value || '').trim() : '';
				if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
					showFeedback('Informe um e-mail válido.', true);
					if (emailInput) {
						emailInput.focus();
					}
					return;
				}
				if (submitLeadBtn) {
					submitLeadBtn.disabled = true;
				}
				fetch(leadsUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': nonce || ''
					},
					body: JSON.stringify({
						agent_slug: agentSlug,
						name: name,
						email: email,
						session_id: sessionId
					})
				})
					.then(function (response) {
						return response.json().catch(function () {
							return {};
						}).then(function (data) {
							if (!response.ok || (data && data.success === false)) {
								throw new Error((data && data.message) || 'Não foi possível salvar seus dados.');
							}
							return data || {};
						});
					})
					.then(function (data) {
						try {
							window.localStorage.setItem(storageKey, JSON.stringify({ name: name, email: email }));
						} catch (err) {}
						showFeedback((data && data.message) || 'Obrigado! Seus dados foram registrados.', false);
						window.setTimeout(function () {
							leadBar.style.display = 'none';
						}, 1400);
					})
					.catch(function (err) {
						showFeedback(err.message || 'Erro ao enviar os dados.', true);
						if (submitLeadBtn) {
							submitLeadBtn.disabled = false;
						}
					});
			};

			if (submitLeadBtn) {
				submitLeadBtn.addEventListener('click', submitLead);
			}
			[nameInput, emailInput].forEach(function (inputEl) {
				if (!inputEl) {
					return;
				}
				inputEl.addEventListener('keydown', function (event) {
					if (event.key === 'Enter') {
						event.preventDefault();
						submitLead();
					}
				});
			});
		}

		// Initial load
		setupLeadCapture();
		fetchProgress();
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.wpagent-steps').forEach(initSteps);
	});
})();
