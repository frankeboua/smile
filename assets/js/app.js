/* Smile — comportements de l'interface (aucun script en ligne : compatible CSP) */
(function () {
  'use strict';

  // Confirmation avant toute suppression
  document.querySelectorAll('form[data-confirmer]').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
      if (!window.confirm(f.getAttribute('data-confirmer'))) ev.preventDefault();
    });
  });

  // Menu latéral sur petit écran
  document.querySelectorAll('[data-bascule]').forEach(function (b) {
    var cible = document.getElementById(b.getAttribute('data-bascule'));
    b.addEventListener('click', function (ev) {
      ev.stopPropagation();
      cible.classList.toggle('ouvert');
    });
    document.addEventListener('click', function (ev) {
      if (cible.classList.contains('ouvert') && !cible.contains(ev.target)) cible.classList.remove('ouvert');
    });
  });

  // Afficher / masquer un mot de passe
  document.querySelectorAll('[data-voir-mdp]').forEach(function (b) {
    var champ = document.getElementById(b.getAttribute('data-voir-mdp'));
    b.addEventListener('click', function () {
      var visible = champ.type === 'text';
      champ.type = visible ? 'password' : 'text';
      b.textContent = visible ? 'Afficher' : 'Masquer';
      b.setAttribute('aria-label', visible ? 'Afficher le mot de passe' : 'Masquer le mot de passe');
    });
  });

  // Filtres de liste : envoi au changement
  document.querySelectorAll('select[data-auto-envoi]').forEach(function (s) {
    s.addEventListener('change', function () { s.form.submit(); });
  });

  // Barres du tableau de bord (largeur posée en JS : pas de style en ligne)
  var barres = Array.prototype.slice.call(document.querySelectorAll('.barre-valeur[data-largeur]'));
  var max = barres.reduce(function (m, b) { return Math.max(m, +b.getAttribute('data-largeur')); }, 0);
  requestAnimationFrame(function () {
    barres.forEach(function (b) {
      var v = +b.getAttribute('data-largeur');
      b.style.width = v + '%';
      if (v === max && max > 0) b.classList.add('max');
    });
  });

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
