/* TV SUMARÉ: controles de navegação acessíveis, sem dependências. */
(function () {
  function init() {
    document.querySelectorAll('.side[data-nav-component]').forEach(function (side) {
      var button = side.querySelector('[data-nav-toggle]');
      var menu = side.querySelector('#tvs-admin-nav');
      if (!button || !menu) return;
      var label = button.querySelector('[data-nav-label]');
      var small = window.matchMedia('(max-width: 1100px)');
      function setOpen(open) {
        side.classList.toggle('nav-open', open);
        button.setAttribute('aria-expanded', String(open));
        if (label) label.textContent = open ? 'Ocultar áreas' : 'Explorar áreas';
      }
      setOpen(side.getAttribute('data-dashboard') === '1');
      side.setAttribute('data-nav-ready', '1');
      button.addEventListener('click', function () {
        setOpen(!side.classList.contains('nav-open'));
      });
      menu.addEventListener('click', function (event) {
        var link = event.target.closest('a');
        if (link && small.matches) setOpen(false);
      });
      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && small.matches && side.classList.contains('nav-open')) {
          setOpen(false);
          button.focus();
        }
      });
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
