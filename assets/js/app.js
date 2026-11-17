/* Smile — comportements de l'interface (aucun script en ligne : compatible CSP) */
(function () {
  'use strict';

  var moinsDeMouvement = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Confirmation avant toute suppression
  document.querySelectorAll('form[data-confirmer]').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
      if (!window.confirm(f.getAttribute('data-confirmer'))) ev.preventDefault();
    });
  });

  // Envoi d'un formulaire POST : bouton occupé et pas de double envoi.
  // Écouteur posé après la confirmation : un envoi annulé n'est pas bloqué.
  document.querySelectorAll('form[method="post"], form[method="POST"]').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
      if (ev.defaultPrevented) return;
      if (f.classList.contains('envoi-en-cours')) { ev.preventDefault(); return; }
      f.classList.add('envoi-en-cours');
      f.setAttribute('aria-busy', 'true');
      // Si l'utilisateur revient en arrière (cache du navigateur), on débloque
      window.addEventListener('pageshow', function () {
        f.classList.remove('envoi-en-cours');
        f.removeAttribute('aria-busy');
      }, { once: true });
    });
  });

  // Menu latéral sur petit écran : ARIA, touche Échap, focus géré
  document.querySelectorAll('[data-bascule]').forEach(function (b) {
    var cible = document.getElementById(b.getAttribute('data-bascule'));
    if (!cible) return;
    var poser = function (ouvert, rendreFocus) {
      cible.classList.toggle('ouvert', ouvert);
      b.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
      b.setAttribute('aria-label', ouvert ? 'Fermer le menu' : 'Ouvrir le menu');
      if (ouvert) {
        var lien = cible.querySelector('a[aria-current="page"]') || cible.querySelector('a');
        if (lien) setTimeout(function () { lien.focus({ preventScroll: false }); }, 60);
      } else if (rendreFocus) {
        b.focus();
      }
    };
    b.addEventListener('click', function (ev) {
      ev.stopPropagation();
      poser(!cible.classList.contains('ouvert'), false);
    });
    document.addEventListener('click', function (ev) {
      if (cible.classList.contains('ouvert') && !cible.contains(ev.target)) poser(false, false);
    });
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && cible.classList.contains('ouvert')) poser(false, true);
    });
  });

  // Menu latéral : la page courante reste visible dans la liste (longue sur petit écran)
  var actif = document.querySelector('.nav a[aria-current="page"]');
  if (actif && actif.scrollIntoView) {
    var nav = actif.closest('.nav');
    if (nav && actif.offsetTop > nav.clientHeight - 80) actif.scrollIntoView({ block: 'center' });
  }

  // Afficher / masquer un mot de passe
  document.querySelectorAll('[data-voir-mdp]').forEach(function (b) {
    var champ = document.getElementById(b.getAttribute('data-voir-mdp'));
    b.setAttribute('aria-pressed', 'false');
    b.addEventListener('click', function () {
      var visible = champ.type === 'text';
      champ.type = visible ? 'password' : 'text';
      b.textContent = visible ? 'Afficher' : 'Masquer';
      b.setAttribute('aria-pressed', visible ? 'false' : 'true');
      b.setAttribute('aria-label', visible ? 'Afficher le mot de passe' : 'Masquer le mot de passe');
    });
  });

  // Alerte « majuscules activées » sur le champ mot de passe
  document.querySelectorAll('[data-alerte-maj]').forEach(function (zone) {
    var champ = document.getElementById(zone.getAttribute('data-alerte-maj'));
    if (!champ) return;
    var verifier = function (ev) {
      if (!ev.getModifierState) return;
      zone.textContent = ev.getModifierState('CapsLock') ? 'Attention : les majuscules sont activées.' : '';
    };
    champ.addEventListener('keydown', verifier);
    champ.addEventListener('keyup', verifier);
    champ.addEventListener('blur', function () { zone.textContent = ''; });
  });

  // Filtres de liste : envoi au changement
  document.querySelectorAll('select[data-auto-envoi]').forEach(function (s) {
    s.addEventListener('change', function () { s.form.submit(); });
  });

  // Barres du tableau de bord : échelle posée en JS (pas de style en ligne),
  // animée en transform (pas de reflow)
  var barres = Array.prototype.slice.call(document.querySelectorAll('.barre-valeur[data-largeur]'));
  var max = barres.reduce(function (m, b) { return Math.max(m, +b.getAttribute('data-largeur')); }, 0);
  barres.forEach(function (b) {
    var v = Math.max(0, Math.min(100, +b.getAttribute('data-largeur') || 0));
    b.style.setProperty('--part', String(v / 100));
    if (v === max && max > 0) b.classList.add('max');
  });
  requestAnimationFrame(function () {
    requestAnimationFrame(function () {
      barres.forEach(function (b) { b.classList.add('pret'); });
    });
  });

  // Indicateurs du tableau de bord : compteur qui monte jusqu'à la valeur
  // (entiers seulement ; le texte final est exactement celui du serveur)
  if (!moinsDeMouvement) {
    document.querySelectorAll('.chiffres .chiffre').forEach(function (el, i) {
      var texte = el.textContent.trim();
      if (!/^\d[\d\s  ]*$/.test(texte)) return;
      var cible = parseInt(texte.replace(/\D/g, ''), 10);
      if (!(cible > 1)) return;
      var sep = (texte.match(/[\s  ]/) || [' '])[0];
      var format = function (n) { return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, sep); };
      var duree = 700, retard = 80 + i * 40, debut = null;
      el.setAttribute('aria-label', texte);
      el.textContent = '0';
      var pas = function (t) {
        if (debut === null) debut = t + retard;
        var x = Math.min(1, Math.max(0, (t - debut) / duree));
        var e = 1 - Math.pow(1 - x, 3); // décélération à l'arrivée
        el.textContent = x < 1 ? format(Math.round(cible * e)) : texte;
        if (x < 1) requestAnimationFrame(pas); else el.removeAttribute('aria-label');
      };
      requestAnimationFrame(pas);
    });
  }

  // Installation : champs administrateur seulement pour une base vide
  var bloc = document.getElementById('admin-vide');
  if (bloc) {
    var maj = function () {
      var vide = document.querySelector('input[name="demo"]:checked').value === '0';
      bloc.hidden = !vide;
    };
    document.querySelectorAll('[data-bascule-admin]').forEach(function (r) { r.addEventListener('change', maj); });
    maj();
  }
})();
