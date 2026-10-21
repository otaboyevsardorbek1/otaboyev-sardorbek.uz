document.addEventListener('DOMContentLoaded', function () {
  const toggleBtn = document.querySelector('.mobile-toggle');
  const navLinks = document.querySelector('.nav-links');

  if (toggleBtn && navLinks) {
    const syncMenuState = function () {
      const isOpen = navLinks.classList.contains('open');
      toggleBtn.setAttribute('aria-expanded', String(isOpen));
    };

    toggleBtn.addEventListener('click', function () {
      navLinks.classList.toggle('open');
      syncMenuState();
    });

    navLinks.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () {
        navLinks.classList.remove('open');
        syncMenuState();
      });
    });
  }

  const yearEl = document.getElementById('year');
  if (yearEl) {
    yearEl.textContent = new Date().getFullYear();
  }

  const form = document.getElementById('contact-form');
  if (form) {
    const status = document.getElementById('form-status');
    const defaultSubmitText = form.querySelector('button[type="submit"]')?.textContent || 'Send';

    form.addEventListener('submit', function (event) {
      event.preventDefault();

      const submitButton = form.querySelector('button[type="submit"]');
      const formData = new FormData(form);
      const formId = form.dataset.formId || 'contact';
      formData.set('id', formId);

      if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = 'Sending...';
      }

      if (status) {
        status.classList.remove('error');
        status.textContent = 'Sending message...';
      }

      const targetUrl = (form.action && form.action.trim()) || '/api.php/form_container/submit';

      fetch(targetUrl, {
        method: 'POST',
        body: formData,
        headers: {
          Accept: 'text/html,application/json',
          'X-Api-Token': 'otaboyev-dev-token'
        },
        credentials: 'same-origin'
      })
        .then(function (response) {
          return response.text().then(function (text) {
            if (!response.ok) {
              throw new Error(text || 'Request failed');
            }
            return text;
          });
        })
        .then(function (responseText) {
          if (status) {
            status.classList.remove('error');
            status.classList.add('success');
            status.textContent = responseText || 'Message sent successfully.';
            status.classList.add('success-flash');
            setTimeout(function () {
              status.classList.remove('success-flash');
            }, 500);
          }
          form.classList.add('is-success');
          setTimeout(function () {
            form.classList.remove('is-success');
          }, 600);
          form.reset();
        })
        .catch(function () {
          const fullName = document.getElementById('name')?.value?.trim() || 'There';
          const email = document.getElementById('email')?.value?.trim() || 'contact@otaboyev-sardorbek.uz';
          const subject = document.getElementById('subject')?.value?.trim() || 'Project inquiry';
          const message = document.getElementById('message')?.value?.trim() || 'Hello';

          const mailtoLink = `mailto:contact@otaboyev-sardorbek.uz?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(`Name: ${fullName}\nEmail: ${email}\n\n${message}`)}`;
          if (status) {
            status.classList.remove('success');
            status.classList.add('error');
            status.textContent = 'Connection to the backend is unavailable; your email app is opening with a prepared message.';
          }
          window.location.href = mailtoLink;
          form.reset();
        })
        .finally(function () {
          if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = defaultSubmitText;
          }
        });
    });
  }

  const loginNode = document.getElementById('github-login');
  const reposNode = document.getElementById('github-repos');
  const followersNode = document.getElementById('github-followers');
  const followingNode = document.getElementById('github-following');
  const bioNode = document.getElementById('github-bio');
  const repoListNode = document.getElementById('github-repos-list');
  const avatarImg = document.getElementById('github-avatar');
  const avatarShell = document.getElementById('profile-avatar-shell');

  const profileCacheKey = 'portfolio-profile-cache';
  const profileCacheTTL = 1000 * 60 * 60 * 6;
  const defaultProfile = {
    name: 'Otaboyev Sardorbek',
    role: 'Backend Developer | AI & Automation Specialist',
    email: 'otaboyevsardorbek1@gmail.com',
    phone: '+998918610470',
    github: 'https://github.com/otaboyevsardorbek1',
    telegram: 'https://t.me/prodevuzoff',
    telegramHandle: '@prodevuzoff',
    location: 'Uzbekistan',
    status: 'Open to Work',
    summary: 'A self-driven Backend Developer & Tool Maker from Uzbekistan — building bots, APIs, and developer tools.'
  };

  const safeStorage = {
    getItem: function (key) {
      try {
        return window.localStorage ? window.localStorage.getItem(key) : null;
      } catch (error) {
        return null;
      }
    },
    setItem: function (key, value) {
      try {
        if (window.localStorage) {
          window.localStorage.setItem(key, value);
        }
      } catch (error) {
        // Ignore storage errors in restricted browser contexts.
      }
    }
  };

  const parseProfileFromReadme = function (content) {
    const text = String(content || '').replace(/\r/g, '');

    const cleanValue = function (value) {
      return decodeURIComponent(String(value || '')
        .replace(/%20/g, ' ')
        .replace(/https?:\/\/[^\s]+/g, '')
        .replace(/\[[^\]]*\]/g, '')
        .replace(/<[^>]+>/g, ' ')
        .replace(/\?style=.*$/gi, '')
        .replace(/-green.*$/gi, '')
        .replace(/-orange.*$/gi, '')
        .replace(/-blue.*$/gi, '')
        .replace(/\s+/g, ' ')
        .trim());
    };

    const getMatch = function (pattern, fallback) {
      const match = text.match(pattern);
      if (!match || !match[1]) return fallback;
      return cleanValue(match[1]);
    };

    const name = getMatch(/#\s+Hi\s+.*?I\'m\s+([^\n]+)/i, defaultProfile.name)
      || getMatch(/^([A-Z][A-Za-z]+(?:\s+[A-Z][A-Za-z]+)+)\s*$/m, defaultProfile.name);

    const role = getMatch(/Backend\s+Developer[^\n]*/i, defaultProfile.role)
      || defaultProfile.role;

    const summary = getMatch(/A self-driven[^\n]*/i, defaultProfile.summary)
      || defaultProfile.summary;

    const email = getMatch(/(?:Email|E-mail)\s*[:\-]\s*([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i, defaultProfile.email);
    const phone = cleanValue(getMatch(/(?:Phone|Contact|Mobile|Tel)\s*[:\-]\s*(\+?[0-9\s()+-]{7,})/i, defaultProfile.phone)).replace(/\s*[-–—]+\s*$/, '');
    const github = getMatch(/https?:\/\/github\.com\/([A-Za-z0-9_.-]+)/i, 'https://github.com/otaboyevsardorbek1');
    const telegramLink = getMatch(/https?:\/\/t\.me\/([A-Za-z0-9_]+)/i, 'https://t.me/prodevuzoff');
    const telegramHandle = telegramLink.includes('://') ? telegramLink.replace('https://t.me/', '@') : `@${telegramLink}`;

    const location = cleanValue(getMatch(/Location\s*[-:]\s*([^\n]+)/i, defaultProfile.location))
      || cleanValue(getMatch(/Location-([A-Za-z\s]+?)(?:-green|\?|$)/i, defaultProfile.location));

    const status = cleanValue(getMatch(/Status\s*[-:]\s*([^\n]+)/i, defaultProfile.status))
      || cleanValue(getMatch(/Status-([A-Za-z%20\s]+?)(?:-orange|\?|$)/i, defaultProfile.status));

    return {
      name: name || defaultProfile.name,
      role: role || defaultProfile.role,
      email: email || defaultProfile.email,
      phone: phone || defaultProfile.phone,
      github: github.startsWith('http') ? github : `https://github.com/${github}`,
      telegram: telegramLink.startsWith('http') ? telegramLink : `https://t.me/${telegramLink}`,
      telegramHandle: telegramHandle || defaultProfile.telegramHandle,
      location: location || defaultProfile.location,
      status: status || defaultProfile.status,
      summary: summary || defaultProfile.summary
    };
  };

  const isValidProfile = function (profile) {
    if (!profile || !profile.email || !String(profile.email).includes('@')) {
      return false;
    }

    return !/style=|green\?|orange\?|blue\?/i.test(String(profile.location || ''))
      && !/style=|green\?|orange\?|blue\?/i.test(String(profile.status || ''));
  };

  const applyProfileData = function (profile) {
    const resolvedProfile = Object.assign({}, defaultProfile, profile || {});
    window.__portfolioProfile = resolvedProfile;

    const emailLink = document.getElementById('profile-email-link');
    const githubLink = document.getElementById('profile-github-link');
    const telegramLink = document.getElementById('profile-telegram-link');
    const quickProfile = document.getElementById('profile-quick-text');
    const profileName = document.getElementById('profile-name');
    const profileRole = document.getElementById('profile-role');

    const setLabeledText = function (element, label, value) {
      if (!element) return;
      element.textContent = '';
      const strong = document.createElement('strong');
      strong.textContent = `${label}:`;
      element.appendChild(strong);
      element.appendChild(document.createTextNode(` ${value}`));
    };

    if (emailLink) {
      emailLink.href = `mailto:${resolvedProfile.email}`;
      setLabeledText(emailLink, 'Email', resolvedProfile.email);
    }

    if (githubLink) {
      githubLink.href = resolvedProfile.github;
      setLabeledText(githubLink, 'GitHub', resolvedProfile.github.replace('https://', ''));
    }

    if (telegramLink) {
      telegramLink.href = resolvedProfile.telegram;
      setLabeledText(telegramLink, 'Telegram', resolvedProfile.telegramHandle);
    }

    if (quickProfile) {
      quickProfile.textContent = `${resolvedProfile.location} · Remote-friendly · ${resolvedProfile.status} · Backend-first engineer with product focus.`;
    }

    if (profileName) {
      profileName.textContent = resolvedProfile.name;
    }

    if (profileRole) {
      profileRole.textContent = resolvedProfile.role;
    }

    const formFallbackEmail = resolvedProfile.email || defaultProfile.email;
    const mailtoTargets = document.querySelectorAll('[data-profile-email]');
    mailtoTargets.forEach(function (node) {
      node.href = `mailto:${formFallbackEmail}`;
    });
  };

  const loadProfileData = function () {
    const cachedRaw = safeStorage.getItem(profileCacheKey);
    let cachedProfile = null;

    if (cachedRaw) {
      try {
        const parsed = JSON.parse(cachedRaw);
        if (parsed && parsed.timestamp && (Date.now() - parsed.timestamp) < profileCacheTTL && isValidProfile(parsed.profile)) {
          cachedProfile = parsed.profile || null;
        } else {
          safeStorage.setItem(profileCacheKey, JSON.stringify({
            timestamp: 0,
            profile: defaultProfile
          }));
        }
      } catch (error) {
        cachedProfile = null;
      }
    }

    if (cachedProfile) {
      applyProfileData(cachedProfile);
    }

    fetch('https://raw.githubusercontent.com/otaboyevsardorbek1/otaboyevsardorbek1/main/README.md', {
      headers: {
        Accept: 'text/plain'
      }
    })
      .then(function (response) {
        if (!response.ok) throw new Error('README fetch failed');
        return response.text();
      })
      .then(function (content) {
        const profile = parseProfileFromReadme(content);
        safeStorage.setItem(profileCacheKey, JSON.stringify({
          timestamp: Date.now(),
          profile: profile
        }));
        applyProfileData(profile);
      })
      .catch(function () {
        if (!cachedProfile) {
          applyProfileData(defaultProfile);
        }
      });
  };

  loadProfileData();

  if (loginNode && reposNode && followersNode && followingNode) {
    const githubHeaders = {
      Accept: 'application/vnd.github+json',
      'X-GitHub-Api-Version': '2022-11-28'
    };

    const sanitizeRepoName = function (value) {
      return String(value || '')
        .replace(/[_-]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
    };

    const formatRepoLabel = function (value) {
      const cleaned = sanitizeRepoName(value);
      if (!cleaned) return 'Project';
      return cleaned.replace(/\b\w/g, function (char) {
        return char.toUpperCase();
      });
    };

    const isSafeRepo = function (repo) {
      if (!repo) return false;
      const name = String(repo.name || '').toLowerCase();
      const description = String(repo.description || '').toLowerCase();
      const fullText = `${name} ${description}`;

      const blocked = ['porn', 'xxx', 'adult', 'template', 'demo', 'example', 'archive', 'trash', 'test', 'copy'];
      return !blocked.some(function (token) {
        return fullText.includes(token);
      });
    };

    const fallbackRepos = [
      { name: 'Backend Systems', language: 'Python', stargazers_count: 12 },
      { name: 'AI Workflows', language: 'Go', stargazers_count: 8 },
      { name: 'Automation Tools', language: 'TypeScript', stargazers_count: 6 },
      { name: 'DevOps Setup', language: 'Docker', stargazers_count: 4 }
    ];

    const renderRepoList = function (repos) {
      if (!repoListNode || !Array.isArray(repos)) return;

      const cleanedRepos = repos
        .filter(isSafeRepo)
        .sort(function (a, b) {
          return (b.stargazers_count || 0) - (a.stargazers_count || 0);
        })
        .slice(0, 6);

      const items = cleanedRepos.length ? cleanedRepos : fallbackRepos;

      repoListNode.textContent = '';
      items.forEach(function (repo) {
        const row = document.createElement('div');
        row.className = 'repo-item';

        const strong = document.createElement('strong');
        strong.textContent = formatRepoLabel(repo.name || 'Project');

        const meta = document.createElement('div');
        meta.className = 'repo-meta';
        meta.textContent = `⭐ ${repo.stargazers_count || 0} • ${repo.language || 'Code'}`;

        row.appendChild(strong);
        row.appendChild(meta);
        repoListNode.appendChild(row);
      });
    };

    if (repoListNode) {
      renderRepoList(fallbackRepos);
    }

    if (loginNode) {
      loginNode.textContent = 'otaboyevsardorbek1';
    }

    if (bioNode) {
      bioNode.textContent = 'Backend-first engineer building reliable software.';
    }

    fetch('https://api.github.com/users/otaboyevsardorbek1', { headers: githubHeaders })
      .then(function (response) {
        if (!response.ok) throw new Error('GitHub request failed');
        return response.json();
      })
      .then(function (data) {
        loginNode.textContent = data.login || 'otaboyevsardorbek1';
        reposNode.textContent = data.public_repos || '0';
        followersNode.textContent = data.followers || '0';
        followingNode.textContent = data.following || '0';
        if (bioNode && data.bio) {
          bioNode.textContent = data.bio;
        }

        if (avatarImg && data.avatar_url) {
          avatarImg.src = data.avatar_url;
          avatarImg.alt = data.login || 'GitHub avatar';
          if (avatarShell) {
            avatarShell.classList.add('has-image');
          }
        }
      })
      .catch(function () {
        loginNode.textContent = 'otaboyevsardorbek1';
        reposNode.textContent = '20+';
        followersNode.textContent = '0';
        followingNode.textContent = '0';
      });

    fetch('https://api.github.com/users/otaboyevsardorbek1/repos?sort=updated&per_page=100', { headers: githubHeaders })
      .then(function (response) {
        if (!response.ok) throw new Error('Repo request failed');
        return response.json();
      })
      .then(function (repos) {
        renderRepoList(Array.isArray(repos) ? repos : []);
      })
      .catch(function () {
        renderRepoList(fallbackRepos);
      });
  }

  const applyTheme = function (theme) {
    const resolvedTheme = theme === 'light' ? 'light' : 'dark';
    document.body.classList.toggle('light-theme', resolvedTheme === 'light');
    document.body.setAttribute('data-theme', resolvedTheme);

    try {
      if (window.localStorage) {
        window.localStorage.setItem('portfolio-theme', resolvedTheme);
      }
    } catch (error) {
      // Ignore storage exceptions in private or restricted browser modes.
    }

    const toggle = document.querySelector('.theme-toggle');
    const icon = toggle && toggle.querySelector('.theme-toggle-icon');
    if (icon) {
      icon.textContent = resolvedTheme === 'light' ? '🌙' : '☀️';
    }
  };

  let preferredTheme = 'dark';

  try {
    const savedTheme = window.localStorage && window.localStorage.getItem('portfolio-theme');
    preferredTheme = savedTheme || preferredTheme;
  } catch (error) {
    preferredTheme = 'dark';
  }

  if (!preferredTheme || (preferredTheme !== 'light' && preferredTheme !== 'dark')) {
    preferredTheme = window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
  }

  applyTheme(preferredTheme);

  const themeToggle = document.querySelector('.theme-toggle');
  if (themeToggle) {
    themeToggle.addEventListener('click', function () {
      applyTheme(document.body.classList.contains('light-theme') ? 'dark' : 'light');
    });
  }

  const animateCounter = function (node) {
    const target = Number(node.dataset.target || 0);
    const suffix = node.dataset.suffix || '';
    const duration = 900;
    const start = performance.now();

    const tick = function (now) {
      const progress = Math.min((now - start) / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      const value = Math.round(target * eased);
      node.textContent = value + suffix;
      if (progress < 1) {
        requestAnimationFrame(tick);
      } else {
        node.textContent = target + suffix;
      }
    };

    requestAnimationFrame(tick);
  };

  document.querySelectorAll('.stat-box strong').forEach(function (node) {
    const target = Number(node.dataset.target || 0);
    if (target) {
      node.textContent = '0' + (node.dataset.suffix || '');
      animateCounter(node);
    }
  });

  const filterButtons = document.querySelectorAll('.filter-btn');
  const projectCards = document.querySelectorAll('.project-card');

  filterButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      const filter = button.dataset.filter || 'all';
      filterButtons.forEach(function (btn) {
        btn.classList.toggle('active', btn === button);
      });

      projectCards.forEach(function (card) {
        const match = filter === 'all' || card.dataset.category === filter;
        card.style.display = match ? '' : 'none';
      });
    });
  });

  const revealNodes = document.querySelectorAll('.section-head, .feature-card, .project-card, .feature-item, .contact-card, .github-card, .text-card, .hero-card, .hero-meta, .cta-row, .stat-box');

  if ('IntersectionObserver' in window && revealNodes.length) {
    revealNodes.forEach(function (node) {
      node.classList.add('reveal');
    });

    const revealObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          revealObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -30px 0px' });

    revealNodes.forEach(function (node) {
      revealObserver.observe(node);
    });
  } else {
    revealNodes.forEach(function (node) {
      node.classList.add('is-visible');
    });
  }

  const header = document.querySelector('.site-header');
  const handleHeaderState = function () {
    if (!header) return;
    header.classList.toggle('scrolled', window.scrollY > 16);
  };

  handleHeaderState();
  window.addEventListener('scroll', handleHeaderState, { passive: true });

  const tiltTargets = document.querySelectorAll('.hero-card, .project-card, .feature-card, .github-card');

  tiltTargets.forEach(function (target) {
    target.addEventListener('pointermove', function (event) {
      const rect = target.getBoundingClientRect();
      const x = (event.clientX - rect.left) / rect.width;
      const y = (event.clientY - rect.top) / rect.height;
      const rotateY = (x - 0.5) * 8;
      const rotateX = (0.5 - y) * 8;
      target.style.transform = 'perspective(1000px) rotateX(' + rotateX + 'deg) rotateY(' + rotateY + 'deg) translateY(-4px)';
    });

    target.addEventListener('pointerleave', function () {
      target.style.transform = '';
    });
  });
});
