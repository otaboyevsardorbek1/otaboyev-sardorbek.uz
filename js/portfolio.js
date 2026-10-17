document.addEventListener('DOMContentLoaded', function () {
  const toggleBtn = document.querySelector('.mobile-toggle');
  const navLinks = document.querySelector('.nav-links');

  if (toggleBtn && navLinks) {
    toggleBtn.addEventListener('click', function () {
      navLinks.classList.toggle('open');
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
            status.innerHTML = responseText || 'Message sent successfully.';
          }
          form.reset();
        })
        .catch(function () {
          const fullName = document.getElementById('name')?.value?.trim() || 'There';
          const email = document.getElementById('email')?.value?.trim() || 'contact@otaboyev-sardorbek.uz';
          const subject = document.getElementById('subject')?.value?.trim() || 'Project inquiry';
          const message = document.getElementById('message')?.value?.trim() || 'Hello';

          const mailtoLink = `mailto:contact@otaboyev-sardorbek.uz?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(`Name: ${fullName}\nEmail: ${email}\n\n${message}`)}`;
          if (status) {
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
    fetch('https://api.github.com/users/otaboyevsardorbek1')
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

    fetch('https://api.github.com/users/otaboyevsardorbek1/repos?sort=updated&per_page=6')
      .then(function (response) {
        if (!response.ok) throw new Error('Repo request failed');
        return response.json();
      })
      .then(function (repos) {
        if (!repoListNode || !Array.isArray(repos)) return;
        repoListNode.innerHTML = repos
          .slice(0, 6)
          .map(function (repo) {
            return `
              <div class="repo-item">
                <strong>${repo.name}</strong>
                <div class="repo-meta">⭐ ${repo.stargazers_count || 0} • ${repo.language || 'Code'}</div>
              </div>
            `;
          })
          .join('');
      })
      .catch(function () {
        if (repoListNode) {
          repoListNode.innerHTML = `
            <div class="repo-item">
              <strong>GitInsight</strong>
              <div class="repo-meta">⭐ 5 • React</div>
            </div>
            <div class="repo-item">
              <strong>API Toolkit</strong>
              <div class="repo-meta">⭐ 3 • Python</div>
            </div>
          `;
        }
      });
  }
});
