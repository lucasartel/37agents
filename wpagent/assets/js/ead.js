/**
 * WPAgents - EAD Layout (Ambiente de Aprendizagem & Tutor IA com Persistência)
 */
(function () {
	'use strict';

	function initEAD(container) {
		var agentSlug = container.getAttribute('data-agent');
		var courseTitle = container.getAttribute('data-course-title') || 'Curso WPAgents';
		var rawSyllabus = container.getAttribute('data-syllabus') || '';
		var restUrl = container.getAttribute('data-rest-url');
		var progressUrl = container.getAttribute('data-progress-url');
		var lessonUrl = container.getAttribute('data-lesson-url');
		var resetUrl = container.getAttribute('data-reset-url');
		var nonce = container.getAttribute('data-nonce');
		var isLoggedIn = container.getAttribute('data-logged-in') === '1';
		var sessionId = getSessionId(agentSlug);

		var modulesEl = container.querySelector('[data-ead-modules]');
		var progressPctEl = container.querySelector('[data-ead-progress-pct]');
		var progressBarEl = container.querySelector('[data-ead-progress-bar]');
		var breadcrumbEl = container.querySelector('[data-ead-breadcrumb]');
		var lessonTitleEl = container.querySelector('[data-ead-lesson-title]');
		var completeBtn = container.querySelector('[data-ead-complete-btn]');
		var regenerateBtn = container.querySelector('[data-ead-regenerate-btn]');
		var contentEl = container.querySelector('[data-ead-lesson-content]');
		var prevBtn = container.querySelector('[data-ead-prev-btn]');
		var nextBtn = container.querySelector('[data-ead-next-btn]');
		var tutorMessagesEl = container.querySelector('[data-ead-tutor-messages]');
		var tutorForm = container.querySelector('[data-ead-tutor-form]');
		var tutorInput = container.querySelector('[data-ead-tutor-input]');
		var promptChips = container.querySelectorAll('[data-ead-prompt]');
		var sidebarEl = container.querySelector('[data-ead-sidebar]') || container.querySelector('.wpagent-ead__sidebar');
		var drawerToggleBtn = container.querySelector('[data-ead-drawer-toggle]');
		var themeToggleBtn = container.querySelector('[data-ead-theme-toggle]');
		var themeStorageKey = 'wpagent-theme-' + agentSlug;

		var syllabus = parseSyllabus(rawSyllabus);
		var allLessons = getAllLessons(syllabus);
		var currentLessonIndex = 0;
		var completedLessons = {}; // { lesson_id: true }
		var lessonCache = getStoredLessonCache(); // persistent local lesson cache
		var tutorHistoryMap = getStoredTutorHistory(); // { lesson_id: [{ role, text }] }

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

		if (drawerToggleBtn && sidebarEl) {
			drawerToggleBtn.addEventListener('click', function () {
				var isOpen = sidebarEl.classList.toggle('is-drawer-open');
				drawerToggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
			});
		}

		function getStoredTutorHistory() {
			try {
				var raw = localStorage.getItem('wpagent_ead_tutor_' + agentSlug);
				var parsed = raw ? JSON.parse(raw) : {};
				return (parsed && typeof parsed === 'object') ? parsed : {};
			} catch (e) {
				return {};
			}
		}

		function saveTutorHistory() {
			try {
				localStorage.setItem('wpagent_ead_tutor_' + agentSlug, JSON.stringify(tutorHistoryMap));
			} catch (e) {}
		}

		function confirmModal(titleText, messageText, onConfirm) {
			var backdrop = document.createElement('div');
			backdrop.className = 'wpagent-ead__modal-backdrop';
			backdrop.innerHTML =
				'<div class="wpagent-ead__modal" role="dialog" aria-modal="true">' +
				'<h4>' + escapeHtml(titleText) + '</h4>' +
				'<p>' + escapeHtml(messageText) + '</p>' +
				'<div class="wpagent-ead__modal-actions">' +
				'<button type="button" class="wpagent-ead__modal-btn" data-modal-cancel>Cancelar</button>' +
				'<button type="button" class="wpagent-ead__modal-btn wpagent-ead__modal-btn--confirm" data-modal-confirm>Confirmar</button>' +
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

		function getSessionId(slug) {
			var key = 'wpagent_ead_session_' + slug;
			var id = localStorage.getItem(key);
			if (!id) {
				id = 'sess_' + Math.random().toString(36).substring(2, 15) + Date.now().toString(36);
				localStorage.setItem(key, id);
			}
			return id;
		}

		function getStoredLessonCache() {
			try {
				var stored = localStorage.getItem('wpagent_ead_lessons_' + agentSlug);
				return stored ? JSON.parse(stored) : {};
			} catch (e) {
				return {};
			}
		}

		function storeLessonCache(cache) {
			try {
				localStorage.setItem('wpagent_ead_lessons_' + agentSlug, JSON.stringify(cache));
			} catch (e) {}
		}

		function parseSyllabus(raw) {
			if (!raw || !raw.trim()) {
				return [
					{
						title: 'Módulo 1: Introdução e Fundamentos',
						lessons: [
							{ id: 'm1_l1', title: 'Aula 1: Apresentação e Objetivos', module: 'Módulo 1: Introdução e Fundamentos' },
							{ id: 'm1_l2', title: 'Aula 2: Conceitos Básicos e Visão Geral', module: 'Módulo 1: Introdução e Fundamentos' }
						]
					},
					{
						title: 'Módulo 2: Aprofundamento e Prática',
						lessons: [
							{ id: 'm2_l1', title: 'Aula 3: Metodologia e Aplicação', module: 'Módulo 2: Aprofundamento e Prática' },
							{ id: 'm2_l2', title: 'Aula 4: Exemplos e Casos Práticos', module: 'Módulo 2: Aprofundamento e Prática' }
						]
					},
					{
						title: 'Módulo 3: Conclusão',
						lessons: [
							{ id: 'm3_l1', title: 'Aula 5: Melhores Práticas e Encerramento', module: 'Módulo 3: Conclusão' }
						]
					}
				];
			}

			var lines = raw.split('\n').map(function (l) { return l.trim(); }).filter(Boolean);
			var modules = [];
			var currentMod = null;
			var lessonCount = 1;

			lines.forEach(function (line) {
				var cleanLine = line.replace(/^#+\s*/, '').trim();

				// Check if this line is explicitly a Lesson / Aula / Lição / Bullet
				var isExplicitLesson = /^(aula|li[çc][aã]o|lesson|t[oó]pico)\b/i.test(cleanLine) ||
				                       /^[-*•]\s+/i.test(cleanLine) ||
				                       /^\d+(\.\d+)?[:\-\)]\s+/i.test(cleanLine);

				// Check if this line is a Module / Seção / Unidade header
				var isModule = !isExplicitLesson && (
					/^(m[oó]dulo|se[çc][aã]o|unidade|cap[ií]tulo|parte)\b/i.test(cleanLine) ||
					(!cleanLine.startsWith('-') && !cleanLine.startsWith('*') && cleanLine.match(/^[0-9]+\.\s+[A-Z]/))
				);

				if (isModule) {
					currentMod = {
						title: cleanLine,
						lessons: []
					};
					modules.push(currentMod);
				} else {
					if (!currentMod) {
						currentMod = { title: 'Módulo 1: Conteúdo do Curso', lessons: [] };
						modules.push(currentMod);
					}
					var cleanTitle = cleanLine.replace(/^[-*•]\s*/, '').trim();
					currentMod.lessons.push({
						id: 'lesson_' + (lessonCount++),
						title: cleanTitle,
						module: currentMod.title
					});
				}
			});

			return modules.length ? modules : [
				{
					title: 'Módulo 1: Conteúdo Principal',
					lessons: [{ id: 'lesson_1', title: raw.trim(), module: 'Módulo 1: Conteúdo Principal' }]
				}
			];
		}

		function getAllLessons(mods) {
			var list = [];
			mods.forEach(function (m) {
				m.lessons.forEach(function (l) {
					list.push(l);
				});
			});
			return list;
		}

		function fetchProgress() {
			var url = progressUrl + '?agent_slug=' + encodeURIComponent(agentSlug) + '&layout_type=ead&session_id=' + encodeURIComponent(sessionId);
			fetch(url, {
				headers: { 'X-WP-Nonce': nonce }
			})
			.then(function (res) { return res.json(); })
			.then(function (data) {
				if (data && data.progress) {
					var p = data.progress;
					if (p.completed_lessons) {
						if (Array.isArray(p.completed_lessons)) {
							p.completed_lessons.forEach(function (id) { completedLessons[id] = true; });
						} else if (typeof p.completed_lessons === 'object') {
							completedLessons = p.completed_lessons;
						}
					}
					if (p.current_lesson) {
						var idx = allLessons.findIndex(function (l) { return l.id === p.current_lesson; });
						if (idx !== -1) {
							currentLessonIndex = idx;
						}
					}
				}
				renderSidebar();
				loadLesson(currentLessonIndex);
			})
			.catch(function () {
				renderSidebar();
				loadLesson(0);
			});
		}

		function saveProgress() {
			var currentLesson = allLessons[currentLessonIndex];
			var payload = {
				agent_slug: agentSlug,
				layout_type: 'ead',
				session_id: sessionId,
				current_lesson: currentLesson ? currentLesson.id : '',
				completed_lessons: completedLessons,
				status: Object.keys(completedLessons).length === allLessons.length ? 'completed' : 'in_progress'
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

		function updateProgressUI() {
			var total = allLessons.length;
			var completedCount = Object.keys(completedLessons).filter(function (k) { return completedLessons[k]; }).length;
			var pct = total > 0 ? Math.round((completedCount / total) * 100) : 0;

			progressPctEl.textContent = pct + '%';
			progressBarEl.style.width = pct + '%';
		}

		function renderSidebar() {
			modulesEl.innerHTML = '';
			updateProgressUI();

			syllabus.forEach(function (mod, modIdx) {
				var modContainer = document.createElement('div');
				modContainer.className = 'wpagent-ead__module';

				var modHeader = document.createElement('div');
				modHeader.className = 'wpagent-ead__module-header';
				modHeader.innerHTML = '<strong>' + escapeHtml(mod.title) + '</strong>';

				var lessonsList = document.createElement('div');
				lessonsList.className = 'wpagent-ead__lessons-list';

				mod.lessons.forEach(function (lesson) {
					var item = document.createElement('button');
					item.type = 'button';
					item.className = 'wpagent-ead__lesson-item';
					item.setAttribute('data-lesson-id', lesson.id);

					var isCurrent = (allLessons[currentLessonIndex] && allLessons[currentLessonIndex].id === lesson.id);
					var isDone = !!completedLessons[lesson.id];

					if (isCurrent) item.classList.add('wpagent-ead__lesson-item--active');
					if (isDone) item.classList.add('wpagent-ead__lesson-item--completed');

					var statusIcon = isDone ? '✅' : (isCurrent ? '▶️' : '⚪');
					item.innerHTML = '<span class="wpagent-ead__lesson-icon">' + statusIcon + '</span> <span class="wpagent-ead__lesson-name">' + escapeHtml(lesson.title) + '</span>';

					item.addEventListener('click', function () {
						var idx = allLessons.findIndex(function (l) { return l.id === lesson.id; });
						if (idx !== -1) {
							currentLessonIndex = idx;
							if (sidebarEl) {
								sidebarEl.classList.remove('is-drawer-open');
							}
							if (drawerToggleBtn) {
								drawerToggleBtn.setAttribute('aria-expanded', 'false');
							}
							renderSidebar();
							loadLesson(currentLessonIndex);
							saveProgress();
						}
					});

					lessonsList.appendChild(item);
				});

				modContainer.appendChild(modHeader);
				modContainer.appendChild(lessonsList);
				modulesEl.appendChild(modContainer);
			});
		}

		function loadLesson(index, forceRegenerate) {
			var lesson = allLessons[index];
			if (!lesson) return;

			breadcrumbEl.textContent = lesson.module || courseTitle;
			lessonTitleEl.textContent = lesson.title;

			// Update Complete Button
			var isDone = !!completedLessons[lesson.id];
			if (isDone) {
				completeBtn.textContent = '✓ Aula Concluída';
				completeBtn.classList.add('wpagent-ead__complete-btn--done');
			} else {
				completeBtn.textContent = 'Marcar como Concluída';
				completeBtn.classList.remove('wpagent-ead__complete-btn--done');
			}

			// Update Prev/Next Buttons
			prevBtn.disabled = (index === 0);
			nextBtn.disabled = (index === allLessons.length - 1);

			var cacheKey = lesson.module + '|' + lesson.title;

			// If already cached locally and not forced, render immediately
			if (!forceRegenerate && lessonCache[cacheKey]) {
				contentEl.innerHTML = formatMarkdown(lessonCache[cacheKey]);
				return;
			}

			contentEl.innerHTML = '<div class="wpagent-ead__loading-state"><div class="wpagent-ead__spinner"></div><p>' +
				(forceRegenerate ? 'Regerando conteúdo didático da aula com IA...' : 'Carregando conteúdo da aula...') +
				'</p></div>';

			if (regenerateBtn) regenerateBtn.disabled = true;

			fetch(lessonUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': nonce
				},
				body: JSON.stringify({
					agent_slug: agentSlug,
					module_title: lesson.module,
					lesson_title: lesson.title,
					session_id: sessionId,
					force_regenerate: !!forceRegenerate
				})
			})
			.then(function (res) { return res.json(); })
			.then(function (data) {
				if (regenerateBtn) regenerateBtn.disabled = false;
				if (data && data.content) {
					lessonCache[cacheKey] = data.content;
					storeLessonCache(lessonCache);
					contentEl.innerHTML = formatMarkdown(data.content);
				} else {
					var errMsg = (data && data.message) ? data.message : 'Erro ao carregar conteúdo da aula.';
					contentEl.innerHTML = '<div class="wpagent-ead__error-card" style="text-align:center;padding:32px 16px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;margin:20px 0;">' +
						'<p style="color:#b91c1c;font-size:15px;margin:0 0 16px;font-weight:600;">⚠️ ' + escapeHtml(errMsg) + '</p>' +
						'<button type="button" class="wpagent-ead__retry-btn" style="background:#b91c1c;color:#fff;border:none;padding:10px 20px;font-size:14px;font-weight:600;border-radius:6px;cursor:pointer;">🔄 Tentar Gerar Novamente</button>' +
						'</div>';
					var retryBtn = contentEl.querySelector('.wpagent-ead__retry-btn');
					if (retryBtn) {
						retryBtn.addEventListener('click', function() {
							loadLesson(index, true);
						});
					}
				}
			})
			.catch(function () {
				if (regenerateBtn) regenerateBtn.disabled = false;
				contentEl.innerHTML = '<div class="wpagent-ead__error-card" style="text-align:center;padding:32px 16px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;margin:20px 0;">' +
					'<p style="color:#b91c1c;font-size:15px;margin:0 0 16px;font-weight:600;">⚠️ Erro de conexão com o servidor.</p>' +
					'<button type="button" class="wpagent-ead__retry-btn" style="background:#b91c1c;color:#fff;border:none;padding:10px 20px;font-size:14px;font-weight:600;border-radius:6px;cursor:pointer;">🔄 Tentar Novamente</button>' +
					'</div>';
				var retryBtn = contentEl.querySelector('.wpagent-ead__retry-btn');
				if (retryBtn) {
					retryBtn.addEventListener('click', function() {
						loadLesson(index, true);
					});
				}
			});

			// Restore or initialize tutor history for this lesson
			tutorMessagesEl.innerHTML = '';
			var savedMessages = tutorHistoryMap[lesson.id];
			if (Array.isArray(savedMessages) && savedMessages.length > 0) {
				savedMessages.forEach(function (entry) {
					appendTutorMessage(entry.role, entry.text, true);
				});
			} else {
				appendTutorMessage('assistant', 'Olá! Sou o seu Tutor IA nesta aula sobre **' + lesson.title + '**. Em que posso te ajudar?', true);
			}
		}

		function toggleLessonCompletion() {
			var lesson = allLessons[currentLessonIndex];
			if (!lesson) return;

			if (completedLessons[lesson.id]) {
				delete completedLessons[lesson.id];
			} else {
				completedLessons[lesson.id] = true;
			}

			saveProgress();
			renderSidebar();
			loadLesson(currentLessonIndex);
		}

		function appendTutorMessage(role, text, skipSave) {
			var msg = document.createElement('div');
			msg.className = 'wpagent-ead__tutor-msg wpagent-ead__tutor-msg--' + role;
			msg.innerHTML = '<div class="wpagent-ead__tutor-bubble">' + formatMarkdown(text) + '</div>';
			tutorMessagesEl.appendChild(msg);
			tutorMessagesEl.scrollTop = tutorMessagesEl.scrollHeight;

			if (!skipSave) {
				var lesson = allLessons[currentLessonIndex];
				if (lesson && lesson.id) {
					if (!Array.isArray(tutorHistoryMap[lesson.id])) {
						tutorHistoryMap[lesson.id] = [
							{ role: 'assistant', text: 'Olá! Sou o seu Tutor IA nesta aula sobre **' + lesson.title + '**. Em que posso te ajudar?' }
						];
					}
					tutorHistoryMap[lesson.id].push({ role: role, text: text });
					if (tutorHistoryMap[lesson.id].length > 40) {
						tutorHistoryMap[lesson.id] = tutorHistoryMap[lesson.id].slice(-40);
					}
					saveTutorHistory();
				}
			}
		}

		function sendTutorMessage(text) {
			if (!text || !text.trim()) return;
			var lesson = allLessons[currentLessonIndex];
			var cacheKey = lesson ? (lesson.module + '|' + lesson.title) : '';
			var currentLessonText = lessonCache[cacheKey] || '';

			appendTutorMessage('user', text);
			tutorInput.value = '';

			var typingEl = document.createElement('div');
			typingEl.className = 'wpagent-ead__tutor-msg wpagent-ead__tutor-msg--assistant';
			typingEl.innerHTML = '<div class="wpagent-ead__tutor-bubble"><span class="wpagent-ead__typing-dot"></span><span class="wpagent-ead__typing-dot"></span><span class="wpagent-ead__typing-dot"></span></div>';
			tutorMessagesEl.appendChild(typingEl);
			tutorMessagesEl.scrollTop = tutorMessagesEl.scrollHeight;

			// Extract a generous snippet of the actual lesson content on the screen
			var lessonContentSnippet = currentLessonText;
			if (lessonContentSnippet.length > 3500) {
				lessonContentSnippet = lessonContentSnippet.substring(0, 3500) + '...';
			}

			var promptWithContext =
				'Você é o Tutor Pedagógico da aula "' + (lesson ? lesson.title : '') + '" ' +
				'(Módulo: "' + (lesson ? lesson.module : '') + '") do curso "' + courseTitle + '".\n\n' +
				(lessonContentSnippet ? 'CONTEÚDO DA AULA QUE O ALUNO ESTÁ ESTUDANDO:\n---\n' + lessonContentSnippet + '\n---\n\n' : '') +
				'DÚVIDA DO ALUNO SOBRE ESTA AULA:\n' +
				text + '\n\n' +
				'DIRETRIZES PARA A RESPOSTA DO TUTOR:\n' +
				'- Responda com linguagem acolhedora, clara e altamente didática.\n' +
				'- Baseie sua explicação nos conceitos, fundamentação e material didático desta aula e do curso.\n' +
				'- Utilize exemplos práticos e analogias claras para tirar a dúvida com profundidade.';

			fetch(restUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': nonce
				},
				body: JSON.stringify({
					message: promptWithContext,
					agent_slug: agentSlug,
					session_id: sessionId,
					conversation_id: 'ead-tutor-' + (lesson ? lesson.id : 'gen') + '-' + sessionId
				})
			})
			.then(function (res) { return res.json(); })
			.then(function (data) {
				if (typingEl.parentNode) typingEl.parentNode.removeChild(typingEl);
				if (data && data.reply) {
					appendTutorMessage('assistant', data.reply);
				} else {
					appendTutorMessage('assistant', 'Desculpe, ocorreu um erro ao gerar a resposta. Tente novamente.');
				}
			})
			.catch(function () {
				if (typingEl.parentNode) typingEl.parentNode.removeChild(typingEl);
				appendTutorMessage('assistant', 'Erro de conexão com o servidor.');
			});
		}

		function escapeHtml(str) {
			if (!str) return '';
			return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
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
				var s = escapeHtml(str);
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
						html += '<pre class="wpagent-code-block"><code' + (codeLang ? ' class="language-' + codeLang + '"' : '') + '>' + escapeHtml(codeBuffer.join('\n')) + '</code></pre>\n';
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

				// Section numbers with emojis (e.g. 1. 🎯 Objetivos da Aula)
				var sectionMatch = trimmed.match(/^(\d+)\.\s+([🎯📖💡📝].*)$/);
				if (sectionMatch) {
					flushPara();
					flushList();
					html += '<h3 class="wpagent-lesson-h3"><span class="wpagent-section-num">' + sectionMatch[1] + '. </span>' + inlineFormat(sectionMatch[2]) + '</h3>\n';
					return;
				}

				// Standard Markdown Headers
				var headerMatch = trimmed.match(/^(#{1,4})\s+(.*)$/);
				if (headerMatch) {
					flushPara();
					flushList();
					var lvl = headerMatch[1].length;
					html += '<h' + lvl + ' class="wpagent-lesson-h' + lvl + '">' + inlineFormat(headerMatch[2]) + '</h' + lvl + '>\n';
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

		// Event Handlers
		if (completeBtn) {
			completeBtn.addEventListener('click', toggleLessonCompletion);
		}

		if (regenerateBtn) {
			regenerateBtn.addEventListener('click', function () {
				confirmModal(
					'Regerar Conteúdo da Aula',
					'Deseja regerar o conteúdo desta aula utilizando a IA? O texto atual será substituído para todos os alunos.',
					function () {
						loadLesson(currentLessonIndex, true);
					}
				);
			});
		}

		if (prevBtn) {
			prevBtn.addEventListener('click', function () {
				if (currentLessonIndex > 0) {
					currentLessonIndex--;
					renderSidebar();
					loadLesson(currentLessonIndex);
					saveProgress();
				}
			});
		}

		if (nextBtn) {
			nextBtn.addEventListener('click', function () {
				if (currentLessonIndex < allLessons.length - 1) {
					currentLessonIndex++;
					renderSidebar();
					loadLesson(currentLessonIndex);
					saveProgress();
				}
			});
		}

		if (tutorForm) {
			tutorForm.addEventListener('submit', function (e) {
				e.preventDefault();
				sendTutorMessage(tutorInput.value);
			});
		}

		promptChips.forEach(function (chip) {
			chip.addEventListener('click', function () {
				var prompt = chip.getAttribute('data-ead-prompt');
				sendTutorMessage(prompt);
			});
		});

		// Initial start
		fetchProgress();
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.wpagent-ead').forEach(initEAD);
	});
})();
