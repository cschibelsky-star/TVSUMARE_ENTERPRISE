/* Single video loops. Two or more campaigns play in shuffled rounds without immediate repetition. */
(() => {
  const data = document.getElementById('tvs-premium-ad-data');
  const slot = document.getElementById('tvs-premium-ad-media');
  const title = document.getElementById('tvs-premium-ad-title');
  const link = document.getElementById('tvs-premium-ad-link');
  const toggle = document.getElementById('tvs-ad-toggle');
  if (!data || !slot || !title || !link || !toggle) return;
  let ads = [];
  try { ads = JSON.parse(data.textContent || '[]'); } catch (_) { return; }
  if (!Array.isArray(ads)) return;
  const eligible = ads.filter(ad => ad && (ad.kind === 'video' || ad.kind === 'image')
    && typeof ad.src === 'string'
    && /^uploads\/anuncios\/[A-Za-z0-9_-]+\.(jpg|jpeg|png|webp|mp4)$/i.test(ad.src)
    && ((ad.kind === 'video') === /\.mp4$/i.test(ad.src)));
  if (!eligible.length) return;

  const failed = new Set();
  let bag = [];
  let previous = '';
  let timer = null;
  let paused = false;
  let generation = 0;
  const remaining = () => eligible.filter(ad => !failed.has(ad.id));
  const shuffle = array => {
    for (let i = array.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [array[i], array[j]] = [array[j], array[i]];
    }
    return array;
  };
  const pick = () => {
    if (!bag.length) {
      bag = shuffle(remaining());
      if (bag.length > 1 && bag[0].id === previous) {
        const first = bag[0]; bag[0] = bag[1]; bag[1] = first;
      }
    }
    return bag.shift();
  };
  const stop = () => {
    if (timer !== null) clearTimeout(timer);
    timer = null;
    const video = slot.querySelector('video');
    if (video) video.pause();
    generation++;
  };
  const noMedia = () => {
    slot.replaceChildren();
    const notice = document.createElement('div');
    notice.className = 'tvs-premium-ad-empty';
    const strong = document.createElement('strong');
    strong.textContent = 'Sua marca em destaque';
    const subtitle = document.createElement('span');
    subtitle.textContent = 'Espaço disponível para banners e vídeos comerciais';
    notice.append(strong, subtitle);
    slot.append(notice);
    title.textContent = 'Anuncie na TV Sumaré';
    link.href = 'anuncie.php';
    link.textContent = 'Conheça os planos';
    link.removeAttribute('target');
    link.removeAttribute('rel');
  };
  const refreshToggle = () => {
    toggle.hidden = remaining().length < 2;
    toggle.textContent = paused ? 'Retomar alternância' : 'Pausar alternância';
  };
  const showNext = () => {
    stop();
    if (paused || document.hidden) return;
    const ad = pick();
    if (!ad) { noMedia(); refreshToggle(); return; }
    previous = ad.id;
    const thisGeneration = generation;
    const media = document.createElement(ad.kind === 'video' ? 'video' : 'img');
    const onFailure = () => {
      if (generation !== thisGeneration) return;
      failed.add(ad.id);
      bag = bag.filter(item => item.id !== ad.id);
      stop();
      refreshToggle();
      if (remaining().length) timer = setTimeout(showNext, 250);
      else noMedia();
    };
    media.addEventListener('error', onFailure, {once: true});
    if (ad.kind === 'video') {
      media.muted = true;
      media.playsInline = true;
      media.autoplay = true;
      media.controls = true;
      media.loop = remaining().length === 1;
      media.preload = 'metadata';
      media.addEventListener('loadedmetadata', () => {
        if (generation !== thisGeneration) return;
        if (Number.isFinite(media.duration) && (media.duration <= 0 || media.duration > 10.05)) onFailure();
      }, {once: true});
      media.addEventListener('ended', () => {
        if (generation === thisGeneration && remaining().length > 1) showNext();
      });
    } else {
      media.alt = 'Publicidade: ' + String(ad.title || 'anunciante');
      media.loading = 'eager';
    }
    media.src = ad.src;
    slot.replaceChildren(media);
    title.textContent = String(ad.title || 'Publicidade regional');
    const href = typeof ad.href === 'string'
      && (/^https?:\/\//i.test(ad.href) || /^\/(?!\/)/.test(ad.href) || ad.href === 'anuncie.php')
      ? ad.href : 'anuncie.php';
    link.href = href;
    link.textContent = 'Conheça o anunciante';
    if (/^https?:\/\//i.test(href)) { link.target = '_blank'; link.rel = 'noopener noreferrer'; }
    else { link.removeAttribute('target'); link.removeAttribute('rel'); }
    refreshToggle();
    if (remaining().length > 1) timer = setTimeout(showNext, 10000);
    if (ad.kind === 'video') media.play().catch(() => { /* Native controls remain available. */ });
  };
  toggle.addEventListener('click', () => {
    paused = !paused;
    refreshToggle();
    if (paused) stop();
    else showNext();
  });
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) stop();
    else if (!paused) showNext();
  });
  showNext();
})();
