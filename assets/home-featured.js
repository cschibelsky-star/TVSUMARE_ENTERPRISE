/* One shuffled cycle shows every eligible campaign before repeating it. */
(() => {
  const data = document.getElementById('tvs-premium-ad-data');
  const slot = document.getElementById('tvs-premium-ad-media');
  const title = document.getElementById('tvs-premium-ad-title');
  const link = document.getElementById('tvs-premium-ad-link');
  const toggle = document.getElementById('tvs-ad-toggle');
  if (!data || !slot || !title || !link || !toggle) return;
  let ads = [];
  try { ads = JSON.parse(data.textContent || '[]'); } catch (_) { return; }
  if (!Array.isArray(ads) || !ads.length) return;
  const eligible = ads.filter(ad => ad && (ad.kind === 'video' || ad.kind === 'image') &&
    typeof ad.src === 'string' && /^uploads\/anuncios\/[A-Za-z0-9_-]+\.(jpg|jpeg|png|webp|mp4)$/i.test(ad.src));
  if (!eligible.length) return;

  let bag = [];
  let previous = '';
  let timer = null;
  let paused = false;
  const shuffle = (array) => {
    for (let i = array.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [array[i], array[j]] = [array[j], array[i]];
    }
    return array;
  };
  const nextAd = () => {
    if (!bag.length) {
      bag = shuffle(eligible.slice());
      if (bag.length > 1 && bag[0].id === previous) {
        const swap = bag[0]; bag[0] = bag[1]; bag[1] = swap;
      }
    }
    return bag.shift();
  };
  const clear = () => {
    if (timer) window.clearTimeout(timer);
    timer = null;
    const video = slot.querySelector('video');
    if (video) video.pause();
  };
  const showNext = () => {
    clear();
    if (paused) return;
    const ad = nextAd();
    if (!ad) return;
    previous = ad.id;
    const media = document.createElement(ad.kind === 'video' ? 'video' : 'img');
    media.src = ad.src;
    if (ad.kind === 'video') {
      media.muted = true;
      media.playsInline = true;
      media.autoplay = true;
      media.controls = true;
      media.loop = eligible.length === 1;
      media.preload = 'metadata';
      media.addEventListener('loadedmetadata', () => {
        if (Number.isFinite(media.duration) && media.duration > 10.05) {
          clear(); media.remove(); title.textContent = 'Anúncio indisponível';
          if (eligible.length > 1) timer = window.setTimeout(showNext, 300);
        }
      });
      media.addEventListener('ended', () => { if (eligible.length > 1) showNext(); });
      media.play().catch(() => { /* Native play controls stay available. */ });
    } else {
      media.alt = 'Publicidade: ' + String(ad.title || 'anunciante');
      media.loading = 'eager';
    }
    slot.replaceChildren(media);
    title.textContent = String(ad.title || 'Publicidade regional');
    const href = typeof ad.href === 'string' && (/^https?:\/\//i.test(ad.href) || /^\/(?!\/)/.test(ad.href) || ad.href === 'anuncie.php')
      ? ad.href : 'anuncie.php';
    link.href = href;
    link.textContent = 'Conheça o anunciante';
    if (/^https?:\/\//i.test(href)) { link.target = '_blank'; link.rel = 'noopener noreferrer'; }
    else { link.removeAttribute('target'); link.removeAttribute('rel'); }
    if (eligible.length > 1) timer = window.setTimeout(showNext, 10000);
  };
  if (eligible.length > 1) {
    toggle.hidden = false;
    toggle.addEventListener('click', () => {
      paused = !paused;
      toggle.textContent = paused ? 'Retomar alternância' : 'Pausar alternância';
      if (paused) clear(); else showNext();
    });
    document.addEventListener('visibilitychange', () => {
      if (document.hidden) clear(); else if (!paused) showNext();
    });
  }
  showNext();
})();
