
document.addEventListener('DOMContentLoaded', function(){
  var toggle = document.querySelector('[data-menu-toggle]');
  var nav = document.querySelector('[data-main-nav]');
  if(toggle && nav){ toggle.addEventListener('click', function(){ nav.classList.toggle('open'); }); }
  document.querySelectorAll('[data-filter-category]').forEach(function(el){
    el.addEventListener('click', function(){ window.location.href = 'noticias.php?categoria=' + encodeURIComponent(el.getAttribute('data-filter-category')); });
  });
  var searchInput = document.querySelector('[data-search-cards]');
  if(searchInput){ searchInput.addEventListener('input', function(){
    var q = this.value.toLowerCase();
    document.querySelectorAll('[data-card]').forEach(function(card){ card.style.display = card.innerText.toLowerCase().includes(q) ? '' : 'none'; });
  }); }
  var select = document.querySelector('[data-category-select]');
  if(select){ select.addEventListener('change', function(){ if(this.value){ window.location.href = this.value; } }); }
});
