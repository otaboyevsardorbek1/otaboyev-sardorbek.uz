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

      fetch(form.action || '/api.php/form_container/submit', {
        method: 'POST',
        body: formData,
        headers: {
          Accept: 'text/html,application/json'
        }
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

      repoListNode.innerHTML = items
        .map(function (repo) {
          const label = formatRepoLabel(repo.name || 'Project');
          const stars = repo.stargazers_count || 0;
          const lang = repo.language || 'Code';
          return `
            <div class="repo-item">
              <strong>${label}</strong>
              <div class="repo-meta">⭐ ${stars} • ${lang}</div>
            </div>
          `;
        })
        .join('');
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
